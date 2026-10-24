<?php
namespace App\Paquetes\Notificaciones\Servicios;

use App\Nucleo\Correo;
use App\Nucleo\ManejadorErrores;
use App\Nucleo\Vista;
use App\Paquetes\Notificaciones\Modelos\NotificacionModelo;
use Throwable;

/**
 * Envía un correo del sistema y deja constancia en NOTIFICACION.
 *
 * El contenido sale de una plantilla de frontend/vistas/correos/ (HTML y texto);
 * el envío lo hace App\Nucleo\Correo con la API de Brevo. Si el envío falla, la
 * notificación queda en estado «error» con el motivo y la operación principal sigue.
 */
final class Notificador
{
    private NotificacionModelo $notificaciones;

    public function __construct()
    {
        $this->notificaciones = new NotificacionModelo();
    }

    /**
     * @param array<string,mixed> $datos      variables para la plantilla correos/{$plantilla}
     * @param array<string,int>   $referencias id_cita, id_receta o id_detalle relacionados
     * @return bool true si Brevo aceptó el correo (o se escribió en el log de desarrollo)
     */
    public function enviar(string $tipo, int $idPersona, string $correo, string $nombre, string $asunto,
                           string $plantilla, array $datos, array $referencias = []): bool
    {
        $id = $this->notificaciones->registrar($tipo, $idPersona, $correo, $asunto, $referencias);
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
