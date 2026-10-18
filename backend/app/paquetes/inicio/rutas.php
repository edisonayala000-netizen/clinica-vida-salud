<?php
/**
 * Rutas del paquete INICIO: página de entrada y panel principal.
 */
use App\Nucleo\Enrutador;
use App\Paquetes\Inicio\CasosUso\PanelPrincipal;

return static function (Enrutador $r): void {
    $r->get('/',      [PanelPrincipal::class, 'entrada'], 'publico');
    $r->get('/panel', [PanelPrincipal::class, 'mostrar'], 'autenticado');
};
