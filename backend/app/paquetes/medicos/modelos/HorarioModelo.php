<?php
namespace App\Paquetes\Medicos\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de HORARIO_ATENCION y DIA_NO_LABORABLE.
 */
final class HorarioModelo
{
    public function delMedico(int $idMedico): array
    {
        return BaseDatos::todos(
            "SELECT * FROM horario_atencion WHERE id_medico = :m ORDER BY dia_semana, hora_inicio",
            ['m' => $idMedico]
        );
    }

    /** Bloques activos de un médico para un día de la semana (1 = lunes). */
    public function activosDelDia(int $idMedico, int $diaSemana): array
    {
        return BaseDatos::todos(
            "SELECT * FROM horario_atencion
              WHERE id_medico = :m AND dia_semana = :d AND estado = 'activo'
              ORDER BY hora_inicio",
            ['m' => $idMedico, 'd' => $diaSemana]
        );
    }

    /** @return array<int,bool> días de la semana en que atiende el médico */
    public function diasQueAtiende(int $idMedico): array
    {
        $dias = [];
        foreach (BaseDatos::todos(
            "SELECT DISTINCT dia_semana FROM horario_atencion WHERE id_medico = :m AND estado = 'activo'",
            ['m' => $idMedico]
        ) as $f) {
            $dias[(int) $f['dia_semana']] = true;
        }
        return $dias;
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno("SELECT * FROM horario_atencion WHERE id_horario = :id", ['id' => $id]);
    }

    /** ¿Se superpone con otro bloque activo del mismo médico y día? */
    public function haySolape(int $idMedico, int $dia, string $inicio, string $fin, ?int $excluirId = null): bool
    {
        return (bool) BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM horario_atencion
                             WHERE id_medico = :m AND dia_semana = :d AND estado = 'activo'
                               AND id_horario <> :x
                               AND hora_inicio < CAST(:fin AS TIME) AND hora_fin > CAST(:ini AS TIME))",
            ['m' => $idMedico, 'd' => $dia, 'ini' => $inicio, 'fin' => $fin, 'x' => $excluirId ?? 0]
        );
    }

    public function crear(array $d): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
             VALUES (:m, :d, :i, :f, :dur) RETURNING id_horario",
            ['m' => $d['id_medico'], 'd' => $d['dia_semana'], 'i' => $d['hora_inicio'], 'f' => $d['hora_fin'], 'dur' => $d['duracion_cita']]
        );
    }

    public function actualizar(int $id, array $d): void
    {
        BaseDatos::ejecutar(
            "UPDATE horario_atencion SET dia_semana = :d, hora_inicio = :i, hora_fin = :f, duracion_cita = :dur
              WHERE id_horario = :id",
            ['id' => $id, 'd' => $d['dia_semana'], 'i' => $d['hora_inicio'], 'f' => $d['hora_fin'], 'dur' => $d['duracion_cita']]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE horario_atencion SET estado = :e WHERE id_horario = :id", ['id' => $id, 'e' => $estado]);
    }

    /**
     * Citas futuras pendientes que caen dentro de un bloque (antes de
     * desactivarlo o recortarlo hay que reprogramarlas).
     */
    public function citasFuturasEnBloque(array $bloque): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita
              WHERE id_medico = :m AND estado IN ('programada','confirmada')
                AND fecha >= CURRENT_DATE AND EXTRACT(ISODOW FROM fecha) = :d
                AND hora_inicio >= CAST(:i AS TIME) AND hora_fin <= CAST(:f AS TIME)",
            ['m' => $bloque['id_medico'], 'd' => $bloque['dia_semana'], 'i' => $bloque['hora_inicio'], 'f' => $bloque['hora_fin']]
        );
    }

    /** Citas futuras que quedarían FUERA del nuevo rango del bloque. */
    public function citasQueQuedanFuera(array $bloqueActual, string $nuevoInicio, string $nuevoFin, int $nuevoDia): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita
              WHERE id_medico = :m AND estado IN ('programada','confirmada')
                AND fecha >= CURRENT_DATE AND EXTRACT(ISODOW FROM fecha) = :d
                AND hora_inicio >= CAST(:i AS TIME) AND hora_fin <= CAST(:f AS TIME)
                AND NOT (EXTRACT(ISODOW FROM fecha) = :nd AND hora_inicio >= CAST(:ni AS TIME) AND hora_fin <= CAST(:nf AS TIME))",
            [
                'm' => $bloqueActual['id_medico'], 'd' => $bloqueActual['dia_semana'],
                'i' => $bloqueActual['hora_inicio'], 'f' => $bloqueActual['hora_fin'],
                'nd' => $nuevoDia, 'ni' => $nuevoInicio, 'nf' => $nuevoFin,
            ]
        );
    }

    /* ---------- Días no laborables (feriados y ausencias) ---------- */

    public function diasNoLaborables(?int $idMedico): array
    {
        return BaseDatos::todos(
            "SELECT d.*, CASE WHEN d.id_medico IS NULL THEN 'Toda la clínica'
                              ELSE 'Dr(a). ' || m.nombres || ' ' || m.apellidos END AS alcance
               FROM dia_no_laborable d LEFT JOIN medico m ON m.id_medico = d.id_medico
              WHERE d.fecha >= CURRENT_DATE AND (d.id_medico IS NULL OR d.id_medico = :m)
              ORDER BY d.fecha",
            ['m' => $idMedico ?? 0]
        );
    }

    /** Fechas bloqueadas (Y-m-d => descripción) entre dos fechas para un médico. */
    public function fechasBloqueadas(int $idMedico, string $desde, string $hasta): array
    {
        $r = [];
        foreach (BaseDatos::todos(
            "SELECT fecha, descripcion FROM dia_no_laborable
              WHERE fecha BETWEEN CAST(:a AS DATE) AND CAST(:b AS DATE) AND (id_medico IS NULL OR id_medico = :m)",
            ['a' => $desde, 'b' => $hasta, 'm' => $idMedico]
        ) as $f) {
            $r[$f['fecha']] = $f['descripcion'];
        }
        return $r;
    }

    public function citasEnFecha(string $fecha, ?int $idMedico): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita WHERE fecha = CAST(:f AS DATE) AND estado IN ('programada','confirmada')
                AND (CAST(:m AS INT) IS NULL OR id_medico = CAST(:m AS INT))",
            ['f' => $fecha, 'm' => $idMedico]
        );
    }

    public function agregarDiaNoLaborable(string $fecha, ?int $idMedico, string $descripcion): void
    {
        BaseDatos::ejecutar(
            "INSERT INTO dia_no_laborable (fecha, id_medico, descripcion) VALUES (:f, :m, :d)",
            ['f' => $fecha, 'm' => $idMedico, 'd' => $descripcion]
        );
    }

    public function quitarDiaNoLaborable(int $id): void
    {
        BaseDatos::ejecutar("DELETE FROM dia_no_laborable WHERE id_dia_no_laborable = :id", ['id' => $id]);
    }
}
