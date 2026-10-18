<?php
namespace App\Nucleo;

/**
 * Manejo de la sesión PHP (RF01: sesión segura).
 *  - Cookie HttpOnly y SameSite=Lax (no accesible desde JavaScript).
 *  - Se regenera el identificador al iniciar sesión (evita fijación).
 *  - Expira tras N minutos de inactividad.
 *  - Mensajes "flash" que se muestran una sola vez.
 */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(Aplicacion::config('sesion.nombre', 'VIDASALUD_SESION'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Peticion::base() ?: '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        // Cierre automático por inactividad
        $limite = (int) Aplicacion::config('sesion.minutos_inactividad', 30) * 60;
        if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > $limite) {
            $tenia = isset($_SESSION['usuario']);
            self::destruir();
            session_start();
            if ($tenia) {
                self::flash('aviso', 'Su sesión se cerró por inactividad. Ingrese nuevamente.');
            }
        }
        $_SESSION['ultima_actividad'] = time();
    }

    /** Nuevo identificador de sesión (al iniciar sesión). */
    public static function regenerar(): void
    {
        session_regenerate_id(true);
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        return $_SESSION[$clave] ?? $porDefecto;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function quitar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Guarda un mensaje para la siguiente pantalla: exito | error | aviso */
    public static function flash(string $tipo, string $mensaje): void
    {
        $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    /** @return array<int,array{tipo:string,mensaje:string}> */
    public static function tomarFlash(): array
    {
        $m = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $m;
    }

    /** Conserva los datos del formulario y sus errores para volver a mostrarlos. */
    public static function guardarFormulario(array $datos, array $errores): void
    {
        unset($datos['_csrf'], $datos['clave'], $datos['clave_confirmacion'], $datos['clave_actual']);
        $_SESSION['form_anterior'] = $datos;
        $_SESSION['form_errores'] = $errores;
    }

    /** @return array{0:array,1:array} [datos anteriores, errores] */
    public static function tomarFormulario(): array
    {
        $r = [$_SESSION['form_anterior'] ?? [], $_SESSION['form_errores'] ?? []];
        unset($_SESSION['form_anterior'], $_SESSION['form_errores']);
        return $r;
    }
}
