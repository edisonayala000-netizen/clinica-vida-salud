<?php
/**
 * Rutas del paquete ATENCION (CU16, CU17, CU19).
 * Formato: método, URL, [clase del caso de uso, método], permiso requerido.
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Atencion\CasosUso\CU16RegistrarAtencion;
use App\Paquetes\Atencion\CasosUso\CU17ConsultarRecetas;
use App\Paquetes\Atencion\CasosUso\CU19GestionarMedicamentos;

return static function (Enrutador $r): void {
    // CU16 · Registrar atención médica (diagnóstico, signos vitales y receta)
    $r->get('/citas/{id}/atencion',  [CU16RegistrarAtencion::class, 'formulario'], 'atencion.registrar');
    $r->post('/citas/{id}/atencion', [CU16RegistrarAtencion::class, 'guardar'],    'atencion.registrar');
    $r->get('/recetas/{id}',         [CU16RegistrarAtencion::class, 'verReceta'],  ['atencion.ver', 'portal.recetas']);
    $r->post('/recetas/{id}/enviar', [CU16RegistrarAtencion::class, 'reenviar'],   ['atencion.ver', 'portal.recetas']);

    // CU17 · Consultar mis recetas (paciente)
    $r->get('/mis-recetas',                [CU17ConsultarRecetas::class, 'listar'],          'portal.recetas');
    $r->post('/mis-recetas/{id}/recordar', [CU17ConsultarRecetas::class, 'cambiarRecordar'], 'portal.recetas');

    // CU19 · Gestionar el catálogo de medicamentos
    $r->get('/medicamentos',               [CU19GestionarMedicamentos::class, 'listar'],        'medicamentos.gestionar');
    $r->post('/medicamentos',              [CU19GestionarMedicamentos::class, 'crear'],         'medicamentos.gestionar');
    $r->post('/medicamentos/{id}',         [CU19GestionarMedicamentos::class, 'actualizar'],    'medicamentos.gestionar');
    $r->post('/medicamentos/{id}/estado',  [CU19GestionarMedicamentos::class, 'cambiarEstado'], 'medicamentos.gestionar');
};
