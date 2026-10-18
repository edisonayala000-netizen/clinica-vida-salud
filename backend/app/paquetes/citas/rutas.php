<?php
/**
 * Rutas del paquete CITAS (CU09 a CU13).
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Citas\CasosUso\CU09ProgramarCita;
use App\Paquetes\Citas\CasosUso\CU10ReprogramarCita;
use App\Paquetes\Citas\CasosUso\CU11CancelarCita;
use App\Paquetes\Citas\CasosUso\CU12RegistrarAsistencia;
use App\Paquetes\Citas\CasosUso\CU13ConsultarAgendaMedica;

return static function (Enrutador $r): void {
    // CU09 · Programar cita (recepción y paciente en línea) + consultar disponibilidad
    $r->get('/citas/nueva',  [CU09ProgramarCita::class, 'formulario'],         'citas.programar');
    $r->post('/citas',       [CU09ProgramarCita::class, 'registrar'],          'citas.programar');
    $r->get('/reservar',     [CU09ProgramarCita::class, 'formularioPaciente'], 'portal.reservar');
    $r->post('/reservar',    [CU09ProgramarCita::class, 'registrarPaciente'],  'portal.reservar');
    $r->get('/api/medicos',                [CU09ProgramarCita::class, 'medicosJson'], ['citas.programar', 'portal.reservar']);
    $r->get('/api/disponibilidad/dias',    [CU09ProgramarCita::class, 'diasJson'],    ['citas.programar', 'citas.reprogramar', 'portal.reservar']);
    $r->get('/api/disponibilidad',         [CU09ProgramarCita::class, 'horariosJson'],['citas.programar', 'citas.reprogramar', 'portal.reservar']);

    // CU10 · Reprogramar cita
    $r->get('/citas/{id}/reprogramar',  [CU10ReprogramarCita::class, 'formulario'], 'citas.reprogramar');
    $r->post('/citas/{id}/reprogramar', [CU10ReprogramarCita::class, 'guardar'],    'citas.reprogramar');

    // CU11 · Cancelar cita
    $r->post('/citas/{id}/cancelar',     [CU11CancelarCita::class, 'cancelar'],         'citas.cancelar');
    $r->post('/mis-citas/{id}/cancelar', [CU11CancelarCita::class, 'cancelarPaciente'], 'portal.mis_citas');

    // CU12 · Registrar asistencia / estado de la cita
    $r->post('/citas/{id}/estado', [CU12RegistrarAsistencia::class, 'cambiar'], ['citas.confirmar', 'citas.atender']);

    // CU13 · Consultar agenda médica y citas
    $r->get('/agenda',     [CU13ConsultarAgendaMedica::class, 'agenda'],  ['agenda.ver', 'agenda.propia']);
    $r->get('/agenda/semana', [CU13ConsultarAgendaMedica::class, 'agendaSemanal'], ['agenda.ver', 'agenda.propia']);
    $r->get('/citas',      [CU13ConsultarAgendaMedica::class, 'listado'], 'citas.ver');
    $r->get('/citas/{id}', [CU13ConsultarAgendaMedica::class, 'ver'],     ['citas.ver', 'agenda.propia', 'portal.mis_citas']);
};
