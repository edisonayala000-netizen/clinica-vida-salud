<?php
namespace App\Nucleo;

use PDOException;
use Throwable;

/**
 * Manejo centralizado de errores (requerimiento técnico 7):
 *  - Nunca muestra credenciales, SQL ni rutas internas al usuario.
 *  - Traduce los errores de PostgreSQL a mensajes comprensibles.
 *  - Registra el detalle técnico en storage/logs/errores.log.
 */
final class ManejadorErrores
{
    /** Nombre de restricción de la BD -> mensaje para el usuario. */
    private const MENSAJES = [
        'uq_persona_ci'              => 'Ese CI ya pertenece a otra persona registrada en el sistema.',
        'uq_usuario_nombre'          => 'Ese nombre de usuario ya está en uso.',
        'uq_usuario_correo'          => 'Ese correo ya está registrado en otra cuenta.',
        'uq_usuario_persona'         => 'Esa persona ya tiene una cuenta de usuario.',
        'uq_medico_registro'         => 'Ya existe un médico con ese número de registro profesional.',
        'uq_especialidad_nombre_normalizado' => 'Ya existe una especialidad con ese nombre.',
        'uq_dia_no_laborable'        => 'Esa fecha ya está bloqueada.',
        'ex_cita_medico_sin_solape'  => 'RN07: el médico ya tiene una cita en ese horario. Elija otro horario.',
        'ex_cita_paciente_sin_solape'=> 'El paciente ya tiene otra cita en ese mismo horario.',
        'ex_horario_sin_solape'      => 'El bloque se superpone con otro horario activo del médico ese día.',
        'ck_horario_rango'           => 'La hora final debe ser posterior a la hora inicial.',
        'ck_cita_rango'              => 'La hora final de la cita debe ser posterior a la inicial.',
        'fk_cita_medico_especialidad'=> 'El médico no atiende la especialidad elegida.',
    ];

    public static function registrar(): void
    {
        $depurar = (bool) Aplicacion::config('app.depuracion', false);
        ini_set('display_errors', $depurar ? '1' : '0');
        error_reporting(E_ALL);
        set_exception_handler([self::class, 'manejar']);
        set_error_handler(static function (int $nivel, string $msg, string $archivo, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;            // error silenciado con @
            }
            throw new \ErrorException($msg, 0, $nivel, $archivo, $linea);
        });
    }

    /** Convierte un error de PostgreSQL en ErrorNegocio con mensaje claro. */
    public static function traducir(Throwable $e): ErrorNegocio
    {
        if ($e instanceof ErrorNegocio) {
            return $e;
        }
        if ($e instanceof PDOException) {
            $estado = $e->errorInfo[0] ?? (string) $e->getCode();
            $texto = $e->getMessage();
            foreach (self::MENSAJES as $restriccion => $mensaje) {
                if (str_contains($texto, $restriccion)) {
                    return new ErrorNegocio($mensaje);
                }
            }
            // Mensajes de los triggers: RAISE EXCEPTION 'RNxx: ...'
            if ($estado === 'P0001' && preg_match('/ERROR:\s+(.+?)(\n|CONTEXT|$)/s', $texto, $m)) {
                return new ErrorNegocio(trim($m[1]));
            }
            return match ($estado) {
                '23503' => new ErrorNegocio('No se puede completar: el registro está relacionado con otros datos.'),
                '23505' => new ErrorNegocio('Ya existe un registro con esos datos.'),
                '23514', '22007', '22008', '22P02' => new ErrorNegocio('Algún dato no tiene el formato correcto.'),
                default => self::registrarYGenerico($e),
            };
        }
        return self::registrarYGenerico($e);
    }

    private static function registrarYGenerico(Throwable $e): ErrorNegocio
    {
        self::escribirLog($e);
        return new ErrorNegocio('Ocurrió un problema interno. Intente nuevamente o avise al administrador.');
    }

    public static function escribirLog(Throwable $e): void
    {
        $dir = Aplicacion::raiz('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $linea = sprintf("[%s] %s: %s en %s:%d\n", date('Y-m-d H:i:s'), get_class($e),
            $e->getMessage(), $e->getFile(), $e->getLine());
        @file_put_contents($dir . '/errores.log', $linea, FILE_APPEND);
    }

    /** Último recurso para excepciones no capturadas. */
    public static function manejar(Throwable $e): void
    {
        self::escribirLog($e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (Peticion::esAjax()) {
            Respuesta::json(['ok' => false, 'mensaje' => 'Ocurrió un problema interno.'], 500);
        }
        $detalle = Aplicacion::config('app.depuracion') ? $e->getMessage() : null;
        try {
            Vista::mostrar('compartido/error', [
                'titulo'  => 'Algo salió mal',
                'codigo'  => 500,
                'mensaje' => 'No pudimos completar la operación. Intente nuevamente; si persiste, avise al administrador.',
                'detalle' => $detalle,
            ], 'simple');
        } catch (Throwable) {
            echo 'Error interno del sistema.';
        }
    }
}
