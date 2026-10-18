<?php
namespace App\Nucleo;

/**
 * Lectura segura de los datos de la petición HTTP.
 */
final class Peticion
{
    public static function metodo(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Ruta relativa a la carpeta del sistema, p. ej. "/citas/nueva". */
    public static function ruta(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = self::base();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        return '/' . trim(rawurldecode($uri), '/');
    }

    /**
     * Carpeta base pública del sistema (vacía si está en la raíz).
     * Ej.: http://localhost/clinica-vida-salud  -> "/clinica-vida-salud"
     */
    public static function base(): string
    {
        static $base = null;
        if ($base === null) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $base = rtrim(str_replace(['/backend/public/index.php', '/public/index.php'], '', $script), '/');
            $base = rtrim(str_replace('/index.php', '', $base), '/');
        }
        return $base;
    }

    /** Valor de $_POST o $_GET recortado; null si no viene o está vacío. */
    public static function entrada(string $clave, mixed $porDefecto = null): mixed
    {
        $valor = $_POST[$clave] ?? $_GET[$clave] ?? $porDefecto;
        if (is_string($valor)) {
            $valor = trim($valor);
            return $valor === '' ? $porDefecto : $valor;
        }
        return $valor;
    }

    /** Entero o null. */
    public static function entero(string $clave): ?int
    {
        $v = self::entrada($clave);
        return ($v !== null && preg_match('/^-?\d+$/', (string) $v)) ? (int) $v : null;
    }

    /** @return array<int,int> lista de enteros (checkbox múltiples) */
    public static function enteros(string $clave): array
    {
        $v = $_POST[$clave] ?? $_GET[$clave] ?? [];
        return array_values(array_unique(array_map('intval', array_filter((array) $v, 'is_numeric'))));
    }

    public static function esAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
