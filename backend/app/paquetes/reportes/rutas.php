<?php
/**
 * Rutas del paquete REPORTES (CU14).
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Reportes\CasosUso\CU14GenerarReportes;

return static function (Enrutador $r): void {
    // CU14 · Generar reportes (vista, impresión/PDF y CSV)
    $r->get('/reportes', [CU14GenerarReportes::class, 'generar'], 'reportes.ver');
};
