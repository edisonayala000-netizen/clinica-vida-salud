<?php
/**
 * Controlador frontal: TODA petición entra por aquí.
 * El enrutador decide qué caso de uso la atiende (ver el archivo rutas.php de cada paquete).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/nucleo/Aplicacion.php';

App\Nucleo\Aplicacion::ejecutar();
