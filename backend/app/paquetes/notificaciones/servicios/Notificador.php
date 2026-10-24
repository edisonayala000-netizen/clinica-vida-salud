<?php
namespace App\Paquetes\Notificaciones\Servicios;

use App\Nucleo\Aplicacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Correo;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\ManejadorErrores;
use App\Nucleo\Vista;
use App\Paquetes\Notificaciones\Modelos\NotificacionModelo;
use Throwable;

/**
 * Envía los correos del sistema y deja constancia en NOTIFICACION (CU18).
 *
 * - enviar(): un correo armado por el caso de uso en el momento (CU15).
 * - avisarCita() / enviarReceta(): correos inmediatos de CU09, CU10, CU11 y CU16.
 * - procesarPendientes(): la tarea programada envía los recordatorios cuya hora llegó.
 *
 * El contenido sale de una plantilla de frontend/vistas/correos/ (HTML y texto) y el
 * envío lo hace App\Nucleo\Correo con la API de Brevo. Si falla, la notificación queda
 * en estado «error» con el motivo y la operación principal sigue.
 */
final class Notificador
{
    private NotificacionModelo $notificaciones;
    private ContenidoCorreo $contenido;

    public function __construct()
    {
        $this->notificaciones = new NotificacionModelo();
        $this->contenido = new ContenidoCorreo($this->notificaciones);
    }

    /**
     * Correo armado en el momento con una plantilla propia (CU15 recuperar contraseña).
     * @param array<string,mixed> $datos      variables para la plantilla correos/{$plantilla}
     * @param array<string,int>   $referencias id_cita, id_receta o id_detalle relacionados
     */
    public function enviar(string $tipo, int $idPersona, string $correo, string $nombre, string $asunto,
                           string $plantilla, array $datos, array $referencias = []): bool
    {
        $id = $this->notificaciones->registrar($tipo, $idPersona, $correo, $asunto, $referencias);
        return $this->enviarCorreo($id, $correo, $nombre, $asunto, $plantilla, $datos);
    }

    /**
     * Aviso inmediato de una cita registrada, reprogramada o cancelada (si el paciente tiene correo).
     * Nunca lanza errores: la cita ya se guardó y el aviso no debe deshacer esa operación.
     */
    public function avisarCita(string $tipo, int $idCita): bool
    {
        try {
            return $this->avisarCitaSinProteger($tipo, $idCita);
        } catch (Throwable $e) {
            ManejadorErrores::escribirLog($e);
            return false;
        }
    }

    private function avisarCitaSinProteger(string $tipo, int $idCita): bool
    {
        $c = $this->notificaciones->cita($idCita);
        if ($c === null || !$c['correo_paciente']) {
            return false;
        }
        $cuando = date('d/m/Y', strtotime($c['fecha'])) . ' a las ' . substr($c['hora_inicio'], 0, 5);
        $asunto = match ($tipo) {
            'cita_registrada'   => "Cita registrada: {$c['codigo']} el {$cuando}",
            'cita_reprogramada' => "Su cita {$c['codigo']} cambió al {$cuando}",
            'cita_cancelada'    => "Cita cancelada: {$c['codigo']} del " . date('d/m/Y', strtotime($c['fecha'])),
        };
        $id = $this->notificaciones->registrar($tipo, (int) $c['id_paciente'], $c['correo_paciente'], $asunto, ['id_cita' => $idCita]);
        return $this->despachar($this->notificaciones->obtener($id));
    }

    /** Envía la receta al correo del paciente (CU16) y anota cuándo se envió. Nunca lanza errores. */
    public function enviarReceta(int $idReceta): bool
    {
        try {
            $filas = $this->notificaciones->receta($idReceta);
            if (!$filas || !$filas[0]['correo_paciente']) {
                return false;
            }
            $r = $filas[0];
            $id = $this->notificaciones->registrar('receta', (int) $r['id_paciente'], $r['correo_paciente'],
                "Su receta {$r['codigo_receta']} · Clínica Vida Salud", ['id_receta' => $idReceta]);
            $enviada = $this->despachar($this->notificaciones->obtener($id)) === true;
            if ($enviada) {
                $this->notificaciones->marcarRecetaEnviada($idReceta);
            }
            return $enviada;
        } catch (Throwable $e) {
            ManejadorErrores::escribirLog($e);
            return false;
        }
    }

    /**
     * Tarea programada: anula lo que ya no tiene sentido enviar y envía los correos cuya hora llegó.
     * @return array{enviadas:int,errores:int,anuladas:int,limite:bool}
     */
    public function procesarPendientes(): array
    {
        $cfg = Aplicacion::config('notificaciones', []);
        $r = ['enviadas' => 0, 'errores' => 0, 'anuladas' => 0, 'limite' => false];
        $r['anuladas'] = $this->notificaciones->anularVencidas((int) ($cfg['max_atraso_horas'] ?? 6));

        $pdo = BaseDatos::conexion();
        for ($i = 0; $i < (int) ($cfg['por_ejecucion'] ?? 50); $i++) {
            if ($this->notificaciones->enviadosHoy() >= (int) ($cfg['limite_diario'] ?? 280)) {
                $r['limite'] = true;    // se completa al día siguiente
                break;
            }
            $pdo->beginTransaction();
            try {
                $n = $this->notificaciones->siguienteLista((int) ($cfg['max_intentos'] ?? 3));
                if ($n === null) {
                    $pdo->commit();
                    break;
                }
                $resultado = $this->despachar($n, true);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            $r[match ($resultado) { true => 'enviadas', false => 'errores', null => 'anuladas' }]++;
        }
        return $r;
    }

    /** CU18: el administrador vuelve a intentar un correo que falló. */
    public function reintentar(int $id): bool
    {
        $n = $this->notificaciones->obtener($id) ?? throw new ErrorNegocio('La notificación no existe.');
        if ($n['estado'] === 'enviada') {
            throw new ErrorNegocio('Ese correo ya fue enviado.');
        }
        if ($n['tipo'] === 'recuperar_clave') {
            throw new ErrorNegocio('Un enlace de recuperación no se reenvía: el usuario debe pedir uno nuevo.');
        }
        return $this->despachar($n) === true;
    }

    /**
     * Arma y envía una notificación ya registrada.
     * @return bool|null true enviada, false error, null anulada (ya no corresponde enviarla)
     */
    private function despachar(array $n, bool $puedeAnular = false): ?bool
    {
        $correo = $this->contenido->para($n);
        if ($correo === null) {
            $this->notificaciones->anular((int) $n['id_notificacion'], 'Ya no corresponde enviarla (la cita o el aviso cambió).');
            return $puedeAnular ? null : false;
        }
        return $this->enviarCorreo((int) $n['id_notificacion'], $n['correo_destino'], $correo['nombre'], $n['asunto'],
            $correo['plantilla'], $correo['datos']);
    }

    private function enviarCorreo(int $id, string $correo, string $nombre, string $asunto, string $plantilla, array $datos): bool
    {
        try {
            $datos += ['nombre' => $nombre, 'asunto' => $asunto];
            $html = Vista::parcial("correos/{$plantilla}", $datos + ['formato' => 'html']);
            $texto = Vista::parcial("correos/{$plantilla}", $datos + ['formato' => 'texto']);
            $r = Correo::enviar($correo, $nombre, $asunto, $html, trim($texto));
            $this->notificaciones->marcarEnviada($id, $r['enviado'] ? $r['id_externo'] : 'solo registrado en correos.log');
            return true;
        } catch (Throwable $e) {
            ManejadorErrores::escribirLog($e);
            $this->notificaciones->marcarError($id, $e->getMessage());
            return false;
        }
    }
}
