<?php
/**
 * Rutas del paquete NOTIFICACIONES (CU18).
 * Formato: método, URL, [clase del caso de uso, método], permiso requerido.
 * Las rutas /correo/... son públicas: se abren desde el correo con un enlace firmado.
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Notificaciones\CasosUso\CU18EnviarNotificaciones;

return static function (Enrutador $r): void {
    // CU18 · Administrador: correos enviados y programados
    $r->get('/notificaciones',                  [CU18EnviarNotificaciones::class, 'listar'],           'notificaciones.ver');
    $r->post('/notificaciones/enviar',          [CU18EnviarNotificaciones::class, 'enviarPendientes'], 'notificaciones.ver');
    $r->post('/notificaciones/{id}/reintentar', [CU18EnviarNotificaciones::class, 'reintentar'],       'notificaciones.ver');

    // CU18 · Paciente desde el correo (sin iniciar sesión)
    $r->get('/correo/cita',  [CU18EnviarNotificaciones::class, 'verCita'],       'publico');
    $r->post('/correo/cita', [CU18EnviarNotificaciones::class, 'responderCita'], 'publico');
    $r->get('/correo/baja',  [CU18EnviarNotificaciones::class, 'verBaja'],       'publico');
    $r->post('/correo/baja', [CU18EnviarNotificaciones::class, 'confirmarBaja'], 'publico');
};
