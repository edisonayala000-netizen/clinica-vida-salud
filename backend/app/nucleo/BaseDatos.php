<?php
namespace App\Nucleo;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Acceso a PostgreSQL mediante PDO (controlador pdo_pgsql).
 *
 * - Una sola conexión por petición (patrón Singleton).
 * - TODAS las consultas son parametrizadas (prepare + execute) para
 *   evitar inyección SQL (requerimiento técnico 7).
 * - transaccion() agrupa varias operaciones: si una falla, se revierte todo.
 */
final class BaseDatos
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $c = Aplicacion::config('bd');
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $c['host'], $c['puerto'], $c['nombre']);
            self::$pdo = new PDO($dsn, $c['usuario'], $c['clave'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Codificación y zona horaria fijas: no dependen de la configuración de Windows.
            self::$pdo->exec("SET client_encoding TO 'UTF8'");
            self::$pdo->exec("SET TIME ZONE 'America/La_Paz'");
        }
        return self::$pdo;
    }

    /** Ejecuta una sentencia preparada y devuelve el statement. */
    public static function ejecutar(string $sql, array $parametros = []): PDOStatement
    {
        $sentencia = self::conexion()->prepare($sql);
        foreach ($parametros as $clave => $valor) {
            $nombre = is_int($clave) ? $clave + 1 : ':' . ltrim($clave, ':');
            $tipo = match (true) {
                is_int($valor)  => PDO::PARAM_INT,
                is_bool($valor) => PDO::PARAM_BOOL,
                $valor === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $sentencia->bindValue($nombre, $valor, $tipo);
        }
        $sentencia->execute();
        return $sentencia;
    }

    /** @return array<int,array<string,mixed>> todas las filas */
    public static function todos(string $sql, array $parametros = []): array
    {
        return self::ejecutar($sql, $parametros)->fetchAll();
    }

    /** @return array<string,mixed>|null una fila o null */
    public static function uno(string $sql, array $parametros = []): ?array
    {
        $fila = self::ejecutar($sql, $parametros)->fetch();
        return $fila === false ? null : $fila;
    }

    /** Devuelve el valor de la primera columna de la primera fila. */
    public static function valor(string $sql, array $parametros = []): mixed
    {
        $v = self::ejecutar($sql, $parametros)->fetchColumn();
        return $v === false ? null : $v;
    }

    /**
     * Ejecuta $operacion dentro de una transacción.
     * Informa a PostgreSQL qué usuario hace el cambio y desde qué IP (los
     * usan los triggers del historial de citas y de la auditoría) y un
     * detalle opcional.
     */
    public static function transaccion(callable $operacion, ?string $detalle = null): mixed
    {
        $pdo = self::conexion();
        $pdo->beginTransaction();
        try {
            $usuario = Autenticacion::id();
            self::ejecutar("SELECT set_config('app.id_usuario', :u, true)", ['u' => $usuario ? (string) $usuario : '']);
            self::ejecutar("SELECT set_config('app.ip', :ip, true)", ['ip' => mb_substr(Peticion::ip(), 0, 45)]);
            self::ejecutar("SELECT set_config('app.detalle', :d, true)", ['d' => $detalle ?? '']);
            $resultado = $operacion();
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
