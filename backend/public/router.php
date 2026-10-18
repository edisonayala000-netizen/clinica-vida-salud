<?php
/**
 * Solo para el servidor integrado de PHP (alternativa a Apache), desde la carpeta raíz del proyecto:
 *   php -S localhost:8000 backend/public/router.php
 * Sirve los archivos de frontend/assets en /assets y envía todo lo demás al controlador frontal.
 */
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($ruta, '/assets/')) {
    $archivo = dirname(__DIR__, 2) . '/frontend' . $ruta;
    if (is_file($archivo)) {
        $tipos = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png',
                  'otf' => 'font/otf', 'ttf' => 'font/ttf', 'ico' => 'image/x-icon'];
        header('Content-Type: ' . ($tipos[pathinfo($archivo, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($archivo);
        return true;
    }
    http_response_code(404);
    return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
