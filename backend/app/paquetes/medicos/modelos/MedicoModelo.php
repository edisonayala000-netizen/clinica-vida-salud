<?php
namespace App\Paquetes\Medicos\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de MEDICO y MEDICO_ESPECIALIDAD.
 */
final class MedicoModelo
{
    /** RF03: buscar por nombre o especialidad. */
    public function buscar(?string $texto, ?int $idEspecialidad, ?string $estado): array
    {
        $sql = "SELECT v.*,
                       (SELECT count(*) FROM horario_atencion h WHERE h.id_medico = v.id_medico AND h.estado = 'activo') AS bloques,
                       (SELECT count(*) FROM cita c WHERE c.id_medico = v.id_medico AND c.fecha >= CURRENT_DATE
                           AND c.estado IN ('programada','confirmada')) AS citas_pendientes,
                       (SELECT u.nombre_usuario FROM usuario u WHERE u.id_medico = v.id_medico) AS cuenta
                  FROM v_medico_especialidades v
                 WHERE 1 = 1";
        $p = [];
        if ($texto) {
            $sql .= " AND (fn_normalizar_texto(v.nombres || ' ' || v.apellidos) LIKE '%' || fn_normalizar_texto(:n) || '%'
                           OR v.codigo ILIKE :t OR v.registro_profesional ILIKE :t
                           OR fn_normalizar_texto(v.especialidades) LIKE '%' || fn_normalizar_texto(:n) || '%')";
            $p['t'] = '%' . $texto . '%';
            $p['n'] = $texto;
        }
        if ($idEspecialidad) {
            $sql .= " AND EXISTS (SELECT 1 FROM medico_especialidad me
                                   WHERE me.id_medico = v.id_medico AND me.id_especialidad = :esp)";
            $p['esp'] = $idEspecialidad;
        }
        if ($estado) {
            $sql .= " AND v.estado = :estado";
            $p['estado'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY v.estado, v.apellidos, v.nombres", $p);
    }

    public function obtener(int $id): ?array
    {
        $m = BaseDatos::uno("SELECT * FROM v_medico_especialidades WHERE id_medico = :id", ['id' => $id]);
        if ($m) {
            $m['ids_especialidades'] = array_map('intval', array_column(BaseDatos::todos(
                "SELECT id_especialidad FROM medico_especialidad WHERE id_medico = :id", ['id' => $id]
            ), 'id_especialidad'));
        }
        return $m;
    }

    /** Médicos activos de una especialidad (para programar citas). */
    public function activosPorEspecialidad(int $idEspecialidad): array
    {
        return BaseDatos::todos(
            "SELECT m.id_medico, m.codigo, m.nombres, m.apellidos,
                    'Dr(a). ' || m.nombres || ' ' || m.apellidos AS nombre
               FROM medico m
               JOIN medico_especialidad me ON me.id_medico = m.id_medico
              WHERE me.id_especialidad = :e AND m.estado = 'activo'
                AND EXISTS (SELECT 1 FROM horario_atencion h WHERE h.id_medico = m.id_medico AND h.estado = 'activo')
              ORDER BY m.apellidos",
            ['e' => $idEspecialidad]
        );
    }

    public function listaSimple(bool $soloActivos = false): array
    {
        return BaseDatos::todos(
            "SELECT id_medico, codigo, estado, 'Dr(a). ' || nombres || ' ' || apellidos AS nombre
               FROM medico " . ($soloActivos ? "WHERE estado = 'activo' " : '') . "ORDER BY apellidos, nombres"
        );
    }

    public function crear(array $d): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO medico (registro_profesional, nombres, apellidos, telefono, correo)
             VALUES (:registro_profesional, :nombres, :apellidos, :telefono, :correo)
             RETURNING id_medico",
            $this->campos($d)
        );
    }

    public function actualizar(int $id, array $d): void
    {
        BaseDatos::ejecutar(
            "UPDATE medico SET registro_profesional = :registro_profesional, nombres = :nombres,
                    apellidos = :apellidos, telefono = :telefono, correo = :correo
              WHERE id_medico = :id",
            $this->campos($d) + ['id' => $id]
        );
    }

    private function campos(array $d): array
    {
        return [
            'registro_profesional' => $d['registro_profesional'],
            'nombres'              => $d['nombres'],
            'apellidos'            => $d['apellidos'],
            'telefono'             => $d['telefono'],
            'correo'               => $d['correo'],
        ];
    }

    /** Asigna exactamente las especialidades indicadas (agrega las nuevas, quita las retiradas). */
    public function sincronizarEspecialidades(int $idMedico, array $ids): void
    {
        BaseDatos::ejecutar(
            "DELETE FROM medico_especialidad WHERE id_medico = :m AND NOT (id_especialidad = ANY(CAST(:ids AS INT[])))",
            ['m' => $idMedico, 'ids' => '{' . implode(',', $ids) . '}']
        );
        foreach ($ids as $idEsp) {
            BaseDatos::ejecutar(
                "INSERT INTO medico_especialidad (id_medico, id_especialidad) VALUES (:m, :e) ON CONFLICT DO NOTHING",
                ['m' => $idMedico, 'e' => $idEsp]
            );
        }
    }

    /** Especialidades que el médico tiene con citas registradas (no se pueden quitar). */
    public function especialidadesConCitas(int $idMedico): array
    {
        return BaseDatos::todos(
            "SELECT DISTINCT e.id_especialidad, e.nombre FROM cita c
               JOIN especialidad e ON e.id_especialidad = c.id_especialidad
              WHERE c.id_medico = :m",
            ['m' => $idMedico]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE medico SET estado = :e WHERE id_medico = :id", ['id' => $id, 'e' => $estado]);
    }

    public function citasPendientes(int $id): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM cita WHERE id_medico = :id AND estado IN ('programada','confirmada')
                AND (fecha + hora_inicio) >= localtimestamp",
            ['id' => $id]
        );
    }

    /** Al desactivar: cancela sus citas pendientes (quedan en el historial). */
    public function cancelarCitasPendientes(int $id, string $motivo): int
    {
        return BaseDatos::ejecutar(
            "UPDATE cita SET estado = 'cancelada', motivo_cancelacion = :motivo
              WHERE id_medico = :id AND estado IN ('programada','confirmada')
                AND (fecha + hora_inicio) >= localtimestamp",
            ['id' => $id, 'motivo' => $motivo]
        )->rowCount();
    }
}
