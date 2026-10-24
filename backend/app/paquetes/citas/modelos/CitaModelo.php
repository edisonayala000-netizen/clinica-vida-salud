<?php
namespace App\Paquetes\Citas\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de CITA y CITA_HISTORIAL.
 */
final class CitaModelo
{
    public const POR_PAGINA = 20;

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno(
            "SELECT v.*, c.id_usuario_registro, c.actualizado_en
               FROM v_cita_detalle v JOIN cita c ON c.id_cita = v.id_cita
              WHERE v.id_cita = :id",
            ['id' => $id]
        );
    }

    /**
     * Bloquea la fila del médico hasta terminar la transacción (SELECT ... FOR UPDATE).
     * Si dos recepcionistas reservan al mismo médico a la vez, la segunda espera
     * a que termine la primera y luego vuelve a comprobar la disponibilidad.
     */
    public function bloquearMedico(int $idMedico): ?array
    {
        return BaseDatos::uno(
            "SELECT id_medico, estado FROM medico WHERE id_medico = :id FOR UPDATE",
            ['id' => $idMedico]
        );
    }

    /** Citas que ocupan horario (todas menos las canceladas) de un médico en una fecha. */
    public function ocupadas(int $idMedico, string $fecha, ?int $excluirCita = null): array
    {
        return BaseDatos::todos(
            "SELECT id_cita, hora_inicio, hora_fin FROM cita
              WHERE id_medico = :m AND fecha = CAST(:f AS DATE) AND estado <> 'cancelada' AND id_cita <> :x
              ORDER BY hora_inicio",
            ['m' => $idMedico, 'f' => $fecha, 'x' => $excluirCita ?? 0]
        );
    }

    /** ¿El paciente ya tiene otra cita que se cruza con ese horario? */
    public function pacienteOcupado(int $idPaciente, string $fecha, string $inicio, string $fin, ?int $excluirCita = null): ?array
    {
        return BaseDatos::uno(
            "SELECT codigo, hora_inicio FROM cita
              WHERE id_paciente = :p AND fecha = CAST(:f AS DATE) AND estado <> 'cancelada' AND id_cita <> :x
                AND hora_inicio < CAST(:fin AS TIME) AND hora_fin > CAST(:ini AS TIME)",
            ['p' => $idPaciente, 'f' => $fecha, 'ini' => $inicio, 'fin' => $fin, 'x' => $excluirCita ?? 0]
        );
    }

    public function crear(array $d): array
    {
        return BaseDatos::uno(
            "INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin,
                               modalidad, motivo, id_usuario_registro)
             VALUES (:p, :m, :e, :f, :i, :h, :mod, :mot, :u)
             RETURNING id_cita, codigo",
            [
                'p' => $d['id_paciente'], 'm' => $d['id_medico'], 'e' => $d['id_especialidad'],
                'f' => $d['fecha'], 'i' => $d['hora_inicio'], 'h' => $d['hora_fin'],
                'mod' => $d['modalidad'], 'mot' => $d['motivo'], 'u' => $d['id_usuario'],
            ]
        );
    }

    public function reprogramar(int $id, string $fecha, string $inicio, string $fin): void
    {
        BaseDatos::ejecutar(
            "UPDATE cita SET fecha = :f, hora_inicio = :i, hora_fin = :h WHERE id_cita = :id",
            ['id' => $id, 'f' => $fecha, 'i' => $inicio, 'h' => $fin]
        );
    }

    public function cambiarEstado(int $id, string $estado, ?string $motivoCancelacion = null): void
    {
        BaseDatos::ejecutar(
            "UPDATE cita SET estado = :e, motivo_cancelacion = COALESCE(:m, motivo_cancelacion) WHERE id_cita = :id",
            ['id' => $id, 'e' => $estado, 'm' => $motivoCancelacion]
        );
    }

    public function historial(int $idCita): array
    {
        return BaseDatos::todos(
            "SELECT h.*, u.nombre_completo AS usuario
               FROM cita_historial h LEFT JOIN v_usuario u ON u.id_usuario = h.id_usuario
              WHERE h.id_cita = :id ORDER BY h.fecha_hora, h.id_historial",
            ['id' => $idCita]
        );
    }

    /**
     * Listado con filtros (RF11: por médico, fecha y estado; también especialidad y texto).
     * @return array{filas:array,total:int,resumen:array}
     */
    public function listar(array $f, int $pagina = 1): array
    {
        $where = " WHERE 1 = 1";
        $p = [];
        if (!empty($f['desde'])) { $where .= " AND fecha >= CAST(:desde AS DATE)"; $p['desde'] = $f['desde']; }
        if (!empty($f['hasta'])) { $where .= " AND fecha <= CAST(:hasta AS DATE)"; $p['hasta'] = $f['hasta']; }
        if (!empty($f['medico'])) { $where .= " AND id_medico = :medico"; $p['medico'] = (int) $f['medico']; }
        if (!empty($f['especialidad'])) { $where .= " AND id_especialidad = :esp"; $p['esp'] = (int) $f['especialidad']; }
        if (!empty($f['estado'])) { $where .= " AND estado = :estado"; $p['estado'] = $f['estado']; }
        if (!empty($f['q'])) {
            $where .= " AND (codigo ILIKE :q OR paciente ILIKE :q OR ci_paciente ILIKE :q)";
            $p['q'] = '%' . $f['q'] . '%';
        }
        $total = (int) BaseDatos::valor("SELECT count(*) FROM v_cita_detalle" . $where, $p);
        $resumen = BaseDatos::todos("SELECT estado, count(*) AS n FROM v_cita_detalle" . $where . " GROUP BY estado", $p);
        $filas = BaseDatos::todos(
            "SELECT * FROM v_cita_detalle" . $where . " ORDER BY fecha, hora_inicio, medico LIMIT :lim OFFSET :off",
            $p + ['lim' => self::POR_PAGINA, 'off' => ($pagina - 1) * self::POR_PAGINA]
        );
        return ['filas' => $filas, 'total' => $total, 'resumen' => array_column($resumen, 'n', 'estado')];
    }

    /** Agenda de un médico en una fecha (RF11). */
    public function agenda(int $idMedico, string $fecha, ?string $estado = null): array
    {
        $sql = "SELECT v.*, p.fecha_nacimiento, p.sexo, p.tipo_sangre, p.alergias, p.enfermedades_cronicas,
                       p.medicamentos, p.compartir_info_medica
                  FROM v_cita_detalle v JOIN v_paciente p ON p.id_paciente = v.id_paciente
                 WHERE v.id_medico = :m AND v.fecha = CAST(:f AS DATE)";
        $p = ['m' => $idMedico, 'f' => $fecha];
        if ($estado) {
            $sql .= " AND v.estado = :e";
            $p['e'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY v.hora_inicio", $p);
    }

    /** Agenda semanal (CU13): citas del médico de lunes a domingo, en orden de día y hora. */
    public function agendaSemana(int $idMedico, string $lunes): array
    {
        return BaseDatos::todos(
            "SELECT v.id_cita, v.codigo, v.fecha, v.hora_inicio, v.hora_fin, v.paciente, v.especialidad, v.estado
               FROM v_cita_detalle v
              WHERE v.id_medico = :m AND v.fecha BETWEEN CAST(:l AS DATE) AND CAST(:l AS DATE) + 6
              ORDER BY v.fecha, v.hora_inicio",
            ['m' => $idMedico, 'l' => $lunes]
        );
    }

    /** Citas de hoy (panel). */
    public function deHoy(?int $idMedico = null): array
    {
        return BaseDatos::todos(
            "SELECT * FROM v_cita_detalle WHERE fecha = CURRENT_DATE"
            . ($idMedico ? " AND id_medico = :m" : '') . " ORDER BY hora_inicio, medico",
            $idMedico ? ['m' => $idMedico] : []
        );
    }
}
