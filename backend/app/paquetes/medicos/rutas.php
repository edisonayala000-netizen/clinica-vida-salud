<?php
/**
 * Rutas del paquete MÉDICOS (CU06, CU07, CU08).
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Medicos\CasosUso\CU06GestionarEspecialidades;
use App\Paquetes\Medicos\CasosUso\CU07GestionarMedicos;
use App\Paquetes\Medicos\CasosUso\CU08GestionarHorarios;

return static function (Enrutador $r): void {
    // CU06 · Gestionar especialidades
    $r->get('/especialidades',              [CU06GestionarEspecialidades::class, 'listar'],        'especialidades.gestionar');
    $r->post('/especialidades',             [CU06GestionarEspecialidades::class, 'crear'],         'especialidades.gestionar');
    $r->post('/especialidades/{id}',        [CU06GestionarEspecialidades::class, 'actualizar'],    'especialidades.gestionar');
    $r->post('/especialidades/{id}/estado', [CU06GestionarEspecialidades::class, 'cambiarEstado'], 'especialidades.gestionar');

    // CU07 · Gestionar médicos
    $r->get('/medicos',                [CU07GestionarMedicos::class, 'listar'],        'medicos.ver');
    $r->get('/medicos/nuevo',          [CU07GestionarMedicos::class, 'nuevo'],         'medicos.gestionar');
    $r->post('/medicos',               [CU07GestionarMedicos::class, 'crear'],         'medicos.gestionar');
    $r->get('/medicos/{id}',           [CU07GestionarMedicos::class, 'ver'],           'medicos.ver');
    $r->get('/medicos/{id}/editar',    [CU07GestionarMedicos::class, 'editar'],        'medicos.gestionar');
    $r->post('/medicos/{id}',          [CU07GestionarMedicos::class, 'actualizar'],    'medicos.gestionar');
    $r->post('/medicos/{id}/estado',   [CU07GestionarMedicos::class, 'cambiarEstado'], 'medicos.gestionar');

    // CU08 · Gestionar horarios de atención y días no laborables
    $r->get('/horarios',               [CU08GestionarHorarios::class, 'mostrar'],        'horarios.gestionar');
    $r->post('/horarios',              [CU08GestionarHorarios::class, 'crear'],          'horarios.gestionar');
    $r->post('/horarios/{id}',         [CU08GestionarHorarios::class, 'actualizar'],     'horarios.gestionar');
    $r->post('/horarios/{id}/estado',  [CU08GestionarHorarios::class, 'cambiarEstado'],  'horarios.gestionar');
    $r->post('/feriados',              [CU08GestionarHorarios::class, 'agregarFeriado'], 'horarios.gestionar');
    $r->post('/feriados/{id}/quitar',  [CU08GestionarHorarios::class, 'quitarFeriado'],  'horarios.gestionar');
};
