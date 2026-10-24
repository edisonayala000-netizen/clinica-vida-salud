<?php
namespace App\Paquetes\Notificaciones\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de NOTIFICACION: cola y registro de los correos del sistema.
 */
final class NotificacionModelo
{
    /**
     * Registra un correo para enviarlo ahora (estado pendiente).
     * @param array{id_cita?:int,id_receta?:int,id_detalle?:int} $referencias
     */
    public function registrar(string $tipo, int $idPersona, string $correo, string $asunto, array $referencias = []): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_cita, id_receta, id_detalle)
             VALUES (:tipo, :persona, :correo, :asunto, :cita, :receta, :detalle)
             RETURNING id_notificacion",
            [
                'tipo'    => $tipo,
                'persona' => $idPersona,
                'correo'  => $correo,
                'asunto'  => mb_substr($asunto, 0, 150),
                'cita'    => $referencias['id_cita'] ?? null,
                'receta'  => $referencias['id_receta'] ?? null,
                'detalle' => $referencias['id_detalle'] ?? null,
            ]
        );
    }

    public function marcarEnviada(int $id, ?string $idExterno): void
    {
        BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'enviada', enviada_en = now(), intentos = intentos + 1,
                    id_externo = :externo, error = NULL
              WHERE id_notificacion = :id",
            ['id' => $id, 'externo' => $idExterno !== null ? mb_substr($idExterno, 0, 100) : null]
        );
    }

    public function marcarError(int $id, string $error): void
    {
        BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'error', intentos = intentos + 1, error = :error WHERE id_notificacion = :id",
            ['id' => $id, 'error' => mb_substr($error, 0, 250)]
        );
    }
}
