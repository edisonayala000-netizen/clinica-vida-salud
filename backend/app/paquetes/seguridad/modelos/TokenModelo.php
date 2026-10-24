<?php
namespace App\Paquetes\Seguridad\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de TOKEN_RECUPERACION (CU15).
 * Solo se guarda el hash SHA-256 del token; el token se envía por correo.
 * El trigger fn_tg_token_un_uso impide usar un token dos veces o vencido.
 */
final class TokenModelo
{
    public function crear(int $idUsuario, string $hash, int $minutos, string $ip): void
    {
        BaseDatos::ejecutar(
            "INSERT INTO token_recuperacion (id_usuario, token_hash, expira_en, ip)
             VALUES (:u, :h, now() + make_interval(mins => :m), :ip)",
            ['u' => $idUsuario, 'h' => $hash, 'm' => $minutos, 'ip' => mb_substr($ip, 0, 45)]
        );
    }

    /** Enlaces pedidos por la cuenta en la última hora. */
    public function solicitudesUltimaHora(int $idUsuario): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM token_recuperacion WHERE id_usuario = :u AND creado_en > now() - INTERVAL '1 hour'",
            ['u' => $idUsuario]
        );
    }

    /** Solicitudes hechas desde una IP en la última hora (según la bitácora, incluye las de cuentas inexistentes). */
    public function solicitudesIpUltimaHora(string $ip): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM bitacora
              WHERE accion IN ('recuperacion_solicitada', 'recuperacion_rechazada') AND ip = :ip
                AND fecha_hora > now() - INTERVAL '1 hour'",
            ['ip' => $ip]
        );
    }

    /** Token vigente (no usado y no vencido) con los datos de su cuenta, o null. */
    public function vigente(string $hash): ?array
    {
        return BaseDatos::uno(
            "SELECT t.id_token, t.id_usuario, t.expira_en, u.nombre_usuario, u.nombres, u.estado, u.password_hash
               FROM token_recuperacion t JOIN v_usuario u ON u.id_usuario = t.id_usuario
              WHERE t.token_hash = :h AND t.usado_en IS NULL AND t.expira_en > now()",
            ['h' => $hash]
        );
    }

    /** Marca el token como usado; false si ya no estaba disponible (otra pestaña lo usó primero). */
    public function marcarUsado(int $idToken): bool
    {
        return BaseDatos::ejecutar(
            "UPDATE token_recuperacion SET usado_en = now()
              WHERE id_token = :id AND usado_en IS NULL AND expira_en > now()",
            ['id' => $idToken]
        )->rowCount() === 1;
    }

    /** Anula los demás enlaces vigentes de la cuenta (tras cambiar la contraseña). */
    public function anularPendientes(int $idUsuario): void
    {
        BaseDatos::ejecutar(
            "UPDATE token_recuperacion SET usado_en = now()
              WHERE id_usuario = :u AND usado_en IS NULL AND expira_en > now()",
            ['u' => $idUsuario]
        );
    }
}
