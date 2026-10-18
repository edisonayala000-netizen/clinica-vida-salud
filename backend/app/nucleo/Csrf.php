<?php
namespace App\Nucleo;

/**
 * Protección contra CSRF: cada formulario lleva un token aleatorio que
 * se compara con el guardado en la sesión.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function validar(): void
    {
        $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($enviado) || !hash_equals(self::token(), $enviado)) {
            http_response_code(419);
            if (Peticion::esAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'mensaje' => 'El formulario expiró. Recargue la página.']);
                exit;
            }
            Sesion::flash('error', 'El formulario expiró. Vuelva a intentarlo.');
            $volver = $_SERVER['HTTP_REFERER'] ?? url('/');
            header('Location: ' . $volver);
            exit;
        }
    }
}
