<?php
namespace App\Nucleo;

use RuntimeException;

/**
 * Envío de correos con la API HTTP de Brevo (https://api.brevo.com/v3/smtp/email).
 *
 * - Se usa la extensión curl de PHP: no hace falta ninguna librería externa.
 * - La clave y el remitente verificado se leen de la configuración (config.local.php).
 * - Sin clave, si el destinatario es de un dominio reservado para pruebas
 *   (example.com, .test, ...) o si no está en la lista correo.solo_a (cuando se
 *   usa en desarrollo), el correo NO se envía: se escribe en
 *   storage/logs/correos.log. Así las pruebas nunca escriben a personas reales.
 */
final class Correo
{
    private const URL_API = 'https://api.brevo.com/v3/smtp/email';

    /**
     * @return array{enviado:bool, id_externo:?string} enviado = false si solo se escribió en el log
     * @throws RuntimeException si Brevo rechaza el envío o no responde
     */
    public static function enviar(string $destino, string $nombreDestino, string $asunto, string $html, string $texto): array
    {
        $c = Aplicacion::config('correo', []);
        $soloA = array_map('strtolower', (array) ($c['solo_a'] ?? []));
        if (empty($c['api_key']) || empty($c['remitente']) || self::esDominioSinEnvio($destino, $c['dominios_sin_envio'] ?? [])
            || ($soloA && !in_array(strtolower($destino), $soloA, true))) {
            self::escribirLog($destino, $asunto, $texto);
            return ['enviado' => false, 'id_externo' => null];
        }

        $cuerpo = json_encode([
            'sender'      => ['name' => $c['nombre'] ?? 'Clínica Vida Salud', 'email' => $c['remitente']],
            'to'          => [['email' => $destino, 'name' => $nombreDestino]],
            'subject'     => $asunto,
            'htmlContent' => $html,
            'textContent' => $texto,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::URL_API);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $c['api_key'],
            ],
        ]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errorRed = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException('No se pudo conectar con Brevo: ' . $errorRed);
        }
        $datos = json_decode((string) $respuesta, true) ?? [];
        if ($codigo < 200 || $codigo >= 300) {
            // Brevo responde {"code": "...", "message": "..."}; p. ej. una IP no autorizada da 401.
            throw new RuntimeException(sprintf('Brevo rechazó el envío (HTTP %d): %s', $codigo,
                $datos['message'] ?? mb_substr((string) $respuesta, 0, 200)));
        }
        return ['enviado' => true, 'id_externo' => $datos['messageId'] ?? null];
    }

    /** ¿El correo es de un dominio reservado (example.com, *.test, ...) al que nunca se envía? */
    public static function esDominioSinEnvio(string $correo, array $dominios): bool
    {
        $dominio = strtolower(substr(strrchr($correo, '@') ?: '', 1));
        foreach ($dominios as $d) {
            $d = strtolower($d);
            if ($dominio === $d || str_ends_with($dominio, '.' . $d)) {
                return true;
            }
        }
        return false;
    }

    /** Modo de desarrollo y pruebas: el correo queda en storage/logs/correos.log. */
    private static function escribirLog(string $destino, string $asunto, string $texto): void
    {
        $dir = Aplicacion::raiz('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $linea = sprintf("===== %s · Para: %s · Asunto: %s\n%s\n\n", date('Y-m-d H:i:s'), $destino, $asunto, $texto);
        @file_put_contents($dir . '/correos.log', $linea, FILE_APPEND);
    }
}
