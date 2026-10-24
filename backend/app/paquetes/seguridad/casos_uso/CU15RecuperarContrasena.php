<?php
namespace App\Paquetes\Seguridad\CasosUso;

use App\Nucleo\Aplicacion;
use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Sesion;
use App\Nucleo\Validador;
use App\Nucleo\Vista;
use App\Paquetes\Notificaciones\Servicios\Notificador;
use App\Paquetes\Seguridad\Modelos\TokenModelo;
use App\Paquetes\Seguridad\Modelos\UsuarioModelo;
use Throwable;

/**
 * CU15 · Recuperar contraseña (por correo electrónico)
 * Requisito: RF01 · Actores: Administrador, Recepcionista, Médico, Paciente (sin sesión)
 *
 * Flujo principal:
 *  1. En el inicio de sesión, el usuario elige «¿Olvidó su contraseña?».
 *  2. Escribe su nombre de usuario o su correo.
 *  3. El sistema busca la cuenta; si está activa, genera un token aleatorio, guarda solo
 *     su hash SHA-256 con una vigencia de 30 minutos y envía el enlace por correo (Brevo).
 *  4. El sistema muestra siempre el mismo mensaje, exista o no la cuenta.
 *  5. El usuario abre el enlace, escribe y confirma su nueva contraseña.
 *  6. El sistema marca el token como usado, guarda la nueva contraseña (hash bcrypt),
 *     desbloquea la cuenta y anula los demás enlaces pendientes.
 * Flujos alternativos:
 *  3a. La cuenta no existe o está inactiva: no se envía nada (mismo mensaje del paso 4).
 *  3b. La cuenta ya pidió 3 enlaces en la última hora: no se envía otro (mismo mensaje).
 *  2a. Demasiadas solicitudes desde la misma IP en una hora: se rechaza la solicitud.
 *  5a. El enlace no existe, venció o ya se usó: se informa y se ofrece pedir uno nuevo.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: seguridad (usa el paquete notificaciones para el correo)
 * Actores.........: Usuario sin sesión
 * Requisitos......: RF01 · RN12
 * Base de datos...: usuario (vista v_usuario), token_recuperacion (trigger fn_tg_token_un_uso: un solo uso y
 *                   vigente), notificacion (registro del correo), bitacora
 * Rutas...........: GET/POST /recuperar (público) · GET/POST /restablecer (público, con el token del enlace)
 * Vistas..........: frontend/vistas/seguridad/olvide_clave.php, frontend/vistas/seguridad/restablecer_clave.php,
 *                   frontend/vistas/correos/recuperar_clave.php
 * Usa.............: UsuarioModelo, TokenModelo, Notificador (App\Nucleo\Correo → API de Brevo)
 * Precondición....: El usuario tiene una cuenta activa con un correo al que tiene acceso.
 * Postcondición...: La cuenta tiene la nueva contraseña; el enlace usado y los demás pendientes ya no sirven.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL y Brevo -> vista en
 *                   frontend/vistas/seguridad/
 * Diagramas.......: doc/Grupo13_SI1_Clinica_Vida_Salud.docx (por agregar: caso de uso, secuencia y clases)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU15RecuperarContrasena extends Controlador
{
    /** Mensaje único del paso 4: no revela si la cuenta existe. */
    private const MENSAJE_ENVIADO = 'Si los datos corresponden a una cuenta activa, le enviamos un enlace a su correo. '
        . 'Revise también la carpeta de spam. El enlace vence en %d minutos.';

    private UsuarioModelo $usuarios;
    private TokenModelo $tokens;

    public function __construct()
    {
        $this->usuarios = new UsuarioModelo();
        $this->tokens = new TokenModelo();
    }

    /** GET /recuperar */
    public function formulario(): void
    {
        if (Autenticacion::hayUsuario()) {
            $this->redirigir('/panel');
        }
        Vista::mostrar('seguridad/olvide_clave', ['titulo' => 'Recuperar contraseña'], 'simple');
    }

    /** POST /recuperar — pasos 2 a 4 */
    public function solicitar(): void
    {
        try {
            $d = Validador::validar($_POST, ['usuario' => ['Usuario o correo', 'requerido|texto:3:120']]);
            $cfg = Aplicacion::config('recuperacion', []);
            $minutos = (int) ($cfg['minutos_vigencia'] ?? 30);
            $ip = Peticion::ip();

            // 2a. Límite por IP (cuenta también las solicitudes de cuentas inexistentes).
            if ($this->tokens->solicitudesIpUltimaHora($ip) >= (int) ($cfg['max_por_hora_ip'] ?? 20)) {
                Bitacora::registrar('recuperacion_rechazada', 'seguridad', 'Demasiadas solicitudes desde esta IP.',
                    ['id_usuario' => null, 'nombre_usuario' => $d['usuario']]);
                throw new ErrorNegocio('Se hicieron demasiadas solicitudes desde este equipo. Intente nuevamente en una hora.');
            }

            $cuenta = $this->usuarios->buscarParaRecuperar($d['usuario']);
            $quien = ['id_usuario' => $cuenta['id_usuario'] ?? null, 'nombre_usuario' => $cuenta['nombre_usuario'] ?? $d['usuario']];
            $activa = $cuenta && $cuenta['estado'] === 'activo'
                && ($cuenta['estado_medico'] ?? 'activo') === 'activo'
                && ($cuenta['estado_paciente'] ?? 'activo') === 'activo';

            if (!$activa) {
                // 3a. Cuenta inexistente o inactiva: no se envía nada.
                Bitacora::registrar('recuperacion_rechazada', 'seguridad',
                    $cuenta ? 'Cuenta inactiva: no se envió el enlace.' : 'La cuenta escrita no existe.', $quien);
            } elseif ($this->tokens->solicitudesUltimaHora((int) $cuenta['id_usuario']) >= (int) ($cfg['max_por_hora_cuenta'] ?? 3)) {
                // 3b. Límite por cuenta: no se envía otro correo.
                Bitacora::registrar('recuperacion_rechazada', 'seguridad', 'Límite de enlaces por hora alcanzado.', $quien);
            } else {
                $this->enviarEnlace($cuenta, $minutos, $ip, $quien);
            }

            Sesion::flash('info', sprintf(self::MENSAJE_ENVIADO, $minutos));
            $this->redirigir('/login');
        } catch (Throwable $e) {
            $this->volverConError($e, '/recuperar');
        }
    }

    /** Paso 3: genera el token, guarda su hash y envía el correo. */
    private function enviarEnlace(array $cuenta, int $minutos, string $ip, array $quien): void
    {
        $token = bin2hex(random_bytes(32));                 // 64 caracteres hexadecimales
        BaseDatos::transaccion(fn () => $this->tokens->crear((int) $cuenta['id_usuario'], hash('sha256', $token), $minutos, $ip));

        $enviado = (new Notificador())->enviar(
            'recuperar_clave', (int) $cuenta['id_persona'], $cuenta['correo'], $cuenta['nombres'] . ' ' . $cuenta['apellidos'],
            'Recupere su contraseña · Clínica Vida Salud', 'recuperar_clave',
            [
                'usuario' => $cuenta['nombre_usuario'],
                'enlace'  => Peticion::origen() . url('/restablecer', ['token' => $token]),
                'minutos' => $minutos,
            ]
        );
        Bitacora::registrar('recuperacion_solicitada', 'seguridad', $enviado
            ? 'Enlace de recuperación enviado a ' . self::ocultarCorreo($cuenta['correo']) . '.'
            : 'No se pudo enviar el correo de recuperación (ver la tabla notificacion).', $quien);
    }

    /** GET /restablecer?token=… — paso 5 */
    public function formularioRestablecer(): void
    {
        if (Autenticacion::hayUsuario()) {
            $this->redirigir('/panel');
        }
        $token = (string) Peticion::entrada('token', '');
        $cuenta = $this->buscarToken($token);
        Vista::mostrar('seguridad/restablecer_clave', [
            'titulo' => 'Crear nueva contraseña',
            'token'  => $cuenta ? $token : null,
            'cuenta' => $cuenta,
        ], 'simple');
    }

    /** POST /restablecer — paso 6 */
    public function restablecer(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        try {
            $cuenta = $this->buscarToken($token)
                ?? throw new ErrorNegocio('El enlace no es válido, ya se usó o venció. Solicite uno nuevo.');
            $d = Validador::validar($_POST, [
                'clave'              => ['Nueva contraseña', 'requerido|clave'],
                'clave_confirmacion' => ['Confirmación', 'requerido'],
            ]);
            if ($d['clave'] !== $d['clave_confirmacion']) {
                throw new ErrorNegocio('Revise los datos.', ['clave_confirmacion' => 'La confirmación no coincide.']);
            }
            if (password_verify($d['clave'], $cuenta['password_hash'])) {
                throw new ErrorNegocio('Revise los datos.', ['clave' => 'La nueva contraseña debe ser distinta de la actual.']);
            }

            $id = (int) $cuenta['id_usuario'];
            BaseDatos::transaccion(function () use ($cuenta, $id, $d) {
                if (!$this->tokens->marcarUsado((int) $cuenta['id_token'])) {
                    throw new ErrorNegocio('El enlace no es válido, ya se usó o venció. Solicite uno nuevo.');
                }
                $this->usuarios->cambiarClave($id, password_hash($d['clave'], PASSWORD_DEFAULT));   // también desbloquea
                $this->tokens->anularPendientes($id);
            });
            Bitacora::registrar('recuperacion_completada', 'seguridad', 'Restableció su contraseña con el enlace del correo.',
                ['id_usuario' => $id, 'nombre_usuario' => $cuenta['nombre_usuario']]);
            $this->exito('Su contraseña fue cambiada. Ya puede iniciar sesión con la nueva contraseña.', '/login');
        } catch (Throwable $e) {
            $this->volverConError($e, '/restablecer?token=' . rawurlencode($token));
        }
    }

    /** Token del enlace → cuenta vigente y activa, o null. */
    private function buscarToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $cuenta = $this->tokens->vigente(hash('sha256', $token));
        return ($cuenta && $cuenta['estado'] === 'activo') ? $cuenta : null;
    }

    /** "maria.lopez@example.com" -> "ma***@example.com" (para la bitácora). */
    private static function ocultarCorreo(string $correo): string
    {
        [$local, $dominio] = array_pad(explode('@', $correo, 2), 2, '');
        return mb_substr($local, 0, 2) . '***@' . $dominio;
    }
}
