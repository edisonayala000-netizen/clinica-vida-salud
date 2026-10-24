<?php
/**
 * =====================================================================
 *  TAREA PROGRAMADA · CU18 Enviar notificaciones
 *
 *  Envía los recordatorios cuya hora ya llegó (citas y tomas de
 *  medicamentos), anula los que tienen demasiado atraso y respeta el
 *  límite diario de correos. Debe ejecutarse cada 5 minutos:
 *    - en Windows, con el Programador de tareas (programar_tarea_windows.bat);
 *    - en la nube, como tarea cron del servidor.
 *  Cada ejecución sin pendientes termina en milisegundos.
 *
 *  Uso:
 *     php backend/tareas/enviar_notificaciones.php
 *     php backend/tareas/enviar_notificaciones.php --url=http://localhost:8000
 *
 *  --url es la dirección del sistema para los enlaces de los correos. Si no
 *  se indica, se usa app.url (variable APP_URL) y, si tampoco está, la de
 *  XAMPP: http://localhost/clinica-vida-salud
 * =====================================================================
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/nucleo/Aplicacion.php';

use App\Nucleo\Aplicacion;
use App\Paquetes\Notificaciones\Servicios\Notificador;

Aplicacion::iniciar();

// Sin navegador no hay dirección de la petición: se arma con --url para los enlaces.
$opciones = getopt('', ['url::']);
$url = (string) ($opciones['url'] ?? '');
if ($url === '' && (string) Aplicacion::config('app.url', '') === '') {
    $url = 'http://localhost/clinica-vida-salud';
}
if ($url !== '') {
    $partes = parse_url($url);
    $_SERVER['HTTP_HOST'] = ($partes['host'] ?? 'localhost') . (isset($partes['port']) ? ':' . $partes['port'] : '');
    $_SERVER['HTTPS'] = ($partes['scheme'] ?? 'http') === 'https' ? 'on' : '';
    $_SERVER['SCRIPT_NAME'] = rtrim($partes['path'] ?? '', '/') . '/index.php';
}

try {
    $r = (new Notificador())->procesarPendientes();
    printf("[%s] Notificaciones: %d enviada(s), %d con error, %d anulada(s)%s\n", date('Y-m-d H:i:s'),
        $r['enviadas'], $r['errores'], $r['anuladas'], $r['limite'] ? ' · límite diario alcanzado' : '');
    exit($r['errores'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    App\Nucleo\ManejadorErrores::escribirLog($e);
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Error: ' . $e->getMessage() . "\n");
    exit(2);
}
