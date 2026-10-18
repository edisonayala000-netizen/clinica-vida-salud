<?php
/**
 * Rutas del paquete PACIENTES (CU03, CU04, CU05).
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Pacientes\CasosUso\CU03GestionarPacientes;
use App\Paquetes\Pacientes\CasosUso\CU04GestionarPerfilPaciente;
use App\Paquetes\Pacientes\CasosUso\CU05ConsultarHistorialCitas;

return static function (Enrutador $r): void {
    // CU03 · Gestionar pacientes
    $r->get('/pacientes',               [CU03GestionarPacientes::class, 'listar'],        'pacientes.ver');
    $r->get('/pacientes/nuevo',         [CU03GestionarPacientes::class, 'nuevo'],         'pacientes.gestionar');
    $r->post('/pacientes',              [CU03GestionarPacientes::class, 'crear'],         'pacientes.gestionar');
    $r->get('/pacientes/{id}',          [CU03GestionarPacientes::class, 'ver'],           'pacientes.ver');
    $r->get('/pacientes/{id}/editar',   [CU03GestionarPacientes::class, 'editar'],        'pacientes.gestionar');
    $r->post('/pacientes/{id}',         [CU03GestionarPacientes::class, 'actualizar'],    'pacientes.gestionar');
    $r->post('/pacientes/{id}/estado',  [CU03GestionarPacientes::class, 'cambiarEstado'], 'pacientes.gestionar');
    $r->get('/api/pacientes',           [CU03GestionarPacientes::class, 'buscarJson'],    'citas.programar');

    // CU04 · Gestionar perfil del paciente (registro en línea + perfil)
    $r->get('/registro',                  [CU04GestionarPerfilPaciente::class, 'formularioRegistro'],  'publico');
    $r->post('/registro',                 [CU04GestionarPerfilPaciente::class, 'registrar'],           'publico');
    $r->get('/mi-perfil',                 [CU04GestionarPerfilPaciente::class, 'ver'],                 'portal.perfil');
    $r->post('/mi-perfil/personales',     [CU04GestionarPerfilPaciente::class, 'guardarDatos'],        'portal.perfil');
    $r->post('/mi-perfil/medica',         [CU04GestionarPerfilPaciente::class, 'guardarMedica'],       'portal.perfil');
    $r->post('/mi-perfil/emergencia',     [CU04GestionarPerfilPaciente::class, 'guardarEmergencia'],   'portal.perfil');
    $r->post('/mi-perfil/cuenta',         [CU04GestionarPerfilPaciente::class, 'guardarPreferencias'], 'portal.perfil');

    // CU05 · Consultar historial de citas
    $r->get('/pacientes/{id}/historial', [CU05ConsultarHistorialCitas::class, 'historialPaciente'], 'pacientes.historial');
    $r->get('/mis-citas',                [CU05ConsultarHistorialCitas::class, 'misCitas'],          'portal.mis_citas');
};
