<?php
namespace App\Nucleo;

/**
 * Enlaces firmados para los correos (CU18): confirmar o cancelar una cita y
 * desactivar recordatorios sin iniciar sesión.
 *
 * El token lleva los datos (JSON en base64url) y una firma HMAC-SHA256 hecha con
 * la clave secreta del sistema: si alguien cambia un solo carácter (p. ej. el id
 * de la cita), la firma no coincide y el enlace se rechaza. También lleva su
 * fecha de vencimiento.
 */
final class EnlaceFirmado
{
    /** @param array<string,int|string> $datos */
    public static function crear(array $datos, int $venceUnix): string
    {
        $carga = self::base64url(json_encode($datos + ['v' => $venceUnix], JSON_UNESCAPED_UNICODE));
        return $carga . '.' . self::base64url(hash_hmac('sha256', $carga, self::secreto(), true));
    }

    /** @return array<string,mixed>|null los datos, o null si el token es inválido, fue alterado o venció */
    public static function leer(string $token): ?array
    {
        if (!preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/', $token, $m)) {
            return null;
        }
        $esperada = self::base64url(hash_hmac('sha256', $m[1], self::secreto(), true));
        if (!hash_equals($esperada, $m[2])) {
            return null;
        }
        $datos = json_decode((string) base64_decode(strtr($m[1], '-_', '+/')), true);
        if (!is_array($datos) || (int) ($datos['v'] ?? 0) < time()) {
            return null;
        }
        return $datos;
    }

    private static function base64url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    /** Clave de la configuración (APP_SECRETO) o, si no hay, la de storage/secreto.key (se crea la primera vez). */
    private static function secreto(): string
    {
        static $secreto = null;
        if ($secreto !== null) {
            return $secreto;
        }
        $configurado = (string) Aplicacion::config('app.secreto', '');
        if ($configurado !== '') {
            return $secreto = $configurado;
        }
        $archivo = Aplicacion::raiz('storage/secreto.key');
        if (!is_file($archivo)) {
            file_put_contents($archivo, bin2hex(random_bytes(32)), LOCK_EX);
        }
        return $secreto = trim((string) file_get_contents($archivo));
    }
}
