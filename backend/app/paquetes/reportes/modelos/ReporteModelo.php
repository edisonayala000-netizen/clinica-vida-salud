<?php
namespace App\Paquetes\Reportes\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Consultas de los reportes administrativos (RF13).
 * Todas reciben un rango de fechas [desde, hasta].
 */
final class ReporteModelo
{
    /** 1. Citas programadas para una fecha o rango (detalle). */
    public function citasDelPeriodo(string $desde, string $hasta, ?string $estado = null): array
    {
        $sql = "SELECT * FROM v_cita_detalle WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)";
        $p = ['a' => $desde, 'b' => $hasta];
        if ($estado) {
            $sql .= " AND estado = :e";
            $p['e'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY fecha, hora_inicio, medico", $p);
    }

    /** 2 y 3. Citas agrupadas por médico o por especialidad, con su desglose por estado. */
    public function citasAgrupadas(string $campo, string $desde, string $hasta): array
    {
        $col = $campo === 'medico' ? 'medico' : 'especialidad';
        return BaseDatos::todos(
            "SELECT $col AS grupo,
                    count(*) AS total,
                    count(*) FILTER (WHERE estado = 'programada')   AS programadas,
                    count(*) FILTER (WHERE estado = 'confirmada')   AS confirmadas,
                    count(*) FILTER (WHERE estado = 'atendida')     AS atendidas,
                    count(*) FILTER (WHERE estado = 'cancelada')    AS canceladas,
                    count(*) FILTER (WHERE estado = 'inasistencia') AS inasistencias
               FROM v_cita_detalle
              WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
              GROUP BY $col
              ORDER BY total DESC, grupo",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /** 4. Citas por estado. */
    public function citasPorEstado(string $desde, string $hasta): array
    {
        return BaseDatos::todos(
            "SELECT e.estado, COALESCE(c.n, 0) AS total
               FROM (VALUES (1,'programada'),(2,'confirmada'),(3,'atendida'),(4,'cancelada'),(5,'inasistencia')) AS e(orden, estado)
               LEFT JOIN (SELECT estado, count(*) AS n FROM cita
                           WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE) GROUP BY estado) c
                      ON c.estado = e.estado
              ORDER BY e.orden",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /** 5. Pacientes registrados en el período. */
    public function pacientesRegistrados(string $desde, string $hasta): array
    {
        return BaseDatos::todos(
            "SELECT p.codigo, p.ci, p.nombres || ' ' || p.apellidos AS paciente, p.sexo, p.fecha_nacimiento,
                    p.telefono, p.estado, p.creado_en,
                    (SELECT count(*) FROM cita c WHERE c.id_paciente = p.id_paciente) AS citas
               FROM v_paciente p
              WHERE p.creado_en::DATE BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE)
              ORDER BY p.creado_en DESC",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    public function totalesPacientes(): array
    {
        return BaseDatos::uno(
            "SELECT count(*) AS total,
                    count(*) FILTER (WHERE estado = 'activo') AS activos,
                    count(*) FILTER (WHERE estado = 'inactivo') AS inactivos
               FROM paciente"
        );
    }

    /** 6. Atendidas, canceladas e inasistencias por día dentro del período. */
    public function resumenDiario(string $desde, string $hasta): array
    {
        return BaseDatos::todos(
            "SELECT * FROM v_resumen_citas_dia WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE) ORDER BY fecha",
            ['a' => $desde, 'b' => $hasta]
        );
    }

    /** 7. Agenda diaria de cada médico (una fecha). */
    public function agendaDiaria(string $fecha): array
    {
        return BaseDatos::todos(
            "SELECT * FROM v_cita_detalle WHERE fecha = CAST(:f AS DATE) AND estado <> 'cancelada'
              ORDER BY medico, hora_inicio",
            ['f' => $fecha]
        );
    }
}
