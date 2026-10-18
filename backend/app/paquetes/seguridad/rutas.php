<?php
/**
 * Rutas del paquete SEGURIDAD (CU01, CU02).
 * Formato: método, URL, [clase del caso de uso, método], permiso requerido.
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Seguridad\CasosUso\CU01IniciarSesion;
use App\Paquetes\Seguridad\CasosUso\CU02GestionarUsuariosPermisos;

return static function (Enrutador $r): void {
    // CU01 · Iniciar sesión
    $r->get('/login',          [CU01IniciarSesion::class, 'formulario'],      'publico');
    $r->post('/login',         [CU01IniciarSesion::class, 'iniciar'],         'publico');
    $r->post('/salir',         [CU01IniciarSesion::class, 'cerrar'],          'autenticado');
    $r->get('/cuenta/clave',   [CU01IniciarSesion::class, 'formularioClave'], 'autenticado');
    $r->post('/cuenta/clave',  [CU01IniciarSesion::class, 'cambiarClave'],    'autenticado');

    // CU02 · Gestionar usuarios y permisos
    $r->get('/usuarios',               [CU02GestionarUsuariosPermisos::class, 'listar'],          'usuarios.gestionar');
    $r->get('/usuarios/nuevo',         [CU02GestionarUsuariosPermisos::class, 'nuevo'],           'usuarios.gestionar');
    $r->post('/usuarios',              [CU02GestionarUsuariosPermisos::class, 'crear'],           'usuarios.gestionar');
    $r->get('/usuarios/{id}/editar',   [CU02GestionarUsuariosPermisos::class, 'editar'],          'usuarios.gestionar');
    $r->post('/usuarios/{id}',         [CU02GestionarUsuariosPermisos::class, 'actualizar'],      'usuarios.gestionar');
    $r->post('/usuarios/{id}/estado',  [CU02GestionarUsuariosPermisos::class, 'cambiarEstado'],   'usuarios.gestionar');
    $r->get('/permisos',               [CU02GestionarUsuariosPermisos::class, 'permisos'],        'usuarios.gestionar');
    $r->post('/permisos',              [CU02GestionarUsuariosPermisos::class, 'guardarPermisos'], 'usuarios.gestionar');
    $r->get('/bitacora',               [CU02GestionarUsuariosPermisos::class, 'bitacora'],        'bitacora.ver');
};
