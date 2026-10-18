<?php
namespace App\Paquetes\Medicos\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de la tabla ESPECIALIDAD.
 */
final class EspecialidadModelo
{
    public function listar(?string $estado = null): array
    {
        $sql = "SELECT e.*,
                       (SELECT count(*) FROM medico_especialidad me JOIN medico m ON m.id_medico = me.id_medico
                         WHERE me.id_especialidad = e.id_especialidad AND m.estado = 'activo') AS medicos_activos,
                       (SELECT count(*) FROM cita c WHERE c.id_especialidad = e.id_especialidad) AS total_citas
                  FROM especialidad e";
        $p = [];
        if ($estado) {
            $sql .= " WHERE e.estado = :estado";
            $p['estado'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY e.nombre", $p);
    }

    /** Especialidades activas que tienen al menos un médico activo (para programar citas). */
    public function conMedicosActivos(): array
    {
        return BaseDatos::todos(
            "SELECT DISTINCT e.id_especialidad, e.nombre, e.descripcion
               FROM especialidad e
               JOIN medico_especialidad me ON me.id_especialidad = e.id_especialidad
               JOIN medico m ON m.id_medico = me.id_medico AND m.estado = 'activo'
              WHERE e.estado = 'activo'
              ORDER BY e.nombre"
        );
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno("SELECT * FROM especialidad WHERE id_especialidad = :id", ['id' => $id]);
    }

    /** ¿Existe otra con el mismo nombre normalizado? (sin tildes, mayúsculas ni espacios extra) */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        return (bool) BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM especialidad
                             WHERE fn_normalizar_texto(nombre) = fn_normalizar_texto(:n)
                               AND id_especialidad <> :id)",
            ['n' => $nombre, 'id' => $excluirId ?? 0]
        );
    }

    public function crear(string $nombre, ?string $descripcion): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO especialidad (nombre, descripcion) VALUES (:n, :d) RETURNING id_especialidad",
            ['n' => $nombre, 'd' => $descripcion]
        );
    }

    public function actualizar(int $id, string $nombre, ?string $descripcion): void
    {
        BaseDatos::ejecutar(
            "UPDATE especialidad SET nombre = :n, descripcion = :d WHERE id_especialidad = :id",
            ['id' => $id, 'n' => $nombre, 'd' => $descripcion]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE especialidad SET estado = :e WHERE id_especialidad = :id", ['id' => $id, 'e' => $estado]);
    }

    public function citasPendientes(int $id): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita WHERE id_especialidad = :id AND fecha >= CURRENT_DATE
                AND estado IN ('programada','confirmada')",
            ['id' => $id]
        );
    }
}
