<?php
namespace App\Paquetes\Pacientes\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de PACIENTE y CONTACTO_EMERGENCIA.
 */
final class PacienteModelo
{
    public const POR_PAGINA = 15;

    /**
     * RF02: buscar por CI, nombre o apellido (y código).
     * @return array{filas:array,total:int}
     */
    public function buscar(?string $texto, ?string $estado, int $pagina = 1): array
    {
        $where = " WHERE 1 = 1";
        $p = [];
        if ($texto) {
            // fn_normalizar_texto ignora tildes y mayúsculas: "lopez" encuentra a "López".
            $where .= " AND (p.ci ILIKE :t OR p.codigo ILIKE :t
                             OR fn_normalizar_texto(p.nombres || ' ' || p.apellidos) LIKE '%' || fn_normalizar_texto(:n) || '%'
                             OR fn_normalizar_texto(p.apellidos || ' ' || p.nombres) LIKE '%' || fn_normalizar_texto(:n) || '%')";
            $p['t'] = '%' . $texto . '%';
            $p['n'] = $texto;
        }
        if ($estado) {
            $where .= " AND p.estado = :estado";
            $p['estado'] = $estado;
        }
        $total = (int) BaseDatos::valor("SELECT count(*) FROM paciente p" . $where, $p);
        $filas = BaseDatos::todos(
            "SELECT p.*,
                    (SELECT max(c.fecha) FROM cita c WHERE c.id_paciente = p.id_paciente AND c.estado = 'atendida') AS ultima_atencion,
                    (SELECT min(c.fecha) FROM cita c WHERE c.id_paciente = p.id_paciente AND c.fecha >= CURRENT_DATE
                        AND c.estado IN ('programada','confirmada')) AS proxima_cita
               FROM paciente p" . $where . "
              ORDER BY p.estado, p.apellidos, p.nombres
              LIMIT :lim OFFSET :off",
            $p + ['lim' => self::POR_PAGINA, 'off' => ($pagina - 1) * self::POR_PAGINA]
        );
        return ['filas' => $filas, 'total' => $total];
    }

    /** Búsqueda rápida para programar citas (solo activos). */
    public function busquedaRapida(string $texto): array
    {
        return BaseDatos::todos(
            "SELECT id_paciente, codigo, ci, nombres, apellidos, telefono, fecha_nacimiento
               FROM paciente
              WHERE estado = 'activo'
                AND (ci ILIKE :t OR codigo ILIKE :t
                     OR fn_normalizar_texto(nombres || ' ' || apellidos) LIKE '%' || fn_normalizar_texto(:n) || '%'
                     OR fn_normalizar_texto(apellidos || ' ' || nombres) LIKE '%' || fn_normalizar_texto(:n) || '%')
              ORDER BY apellidos, nombres LIMIT 8",
            ['t' => '%' . $texto . '%', 'n' => $texto]
        );
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno(
            "SELECT p.*, (SELECT u.nombre_usuario FROM usuario u WHERE u.id_paciente = p.id_paciente) AS cuenta
               FROM paciente p WHERE p.id_paciente = :id",
            ['id' => $id]
        );
    }

    public function porCi(string $ci): ?array
    {
        return BaseDatos::uno(
            "SELECT p.*, EXISTS (SELECT 1 FROM usuario u WHERE u.id_paciente = p.id_paciente) AS tiene_cuenta
               FROM paciente p WHERE p.ci = :ci",
            ['ci' => $ci]
        );
    }

    public function existeCi(string $ci, ?int $excluirId = null): bool
    {
        return (bool) BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM paciente WHERE ci = :ci AND id_paciente <> :id)",
            ['ci' => $ci, 'id' => $excluirId ?? 0]
        );
    }

    private const CAMPOS = ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'correo', 'direccion',
                            'tipo_sangre', 'alergias', 'enfermedades_cronicas', 'medicamentos', 'antecedentes'];

    public function crear(array $d): int
    {
        $campos = array_values(array_filter(self::CAMPOS, static fn ($c) => array_key_exists($c, $d)));
        $sql = 'INSERT INTO paciente (' . implode(', ', $campos) . ') VALUES (:'
             . implode(', :', $campos) . ') RETURNING id_paciente';
        return (int) BaseDatos::valor($sql, array_intersect_key($d, array_flip($campos)));
    }

    /** Actualiza solo los campos presentes en $d. */
    public function actualizar(int $id, array $d): void
    {
        $permitidos = array_merge(self::CAMPOS, ['recibir_recordatorios', 'compartir_info_medica']);
        $campos = array_values(array_filter($permitidos, static fn ($c) => array_key_exists($c, $d)));
        if (!$campos) {
            return;
        }
        $sets = implode(', ', array_map(static fn ($c) => "$c = :$c", $campos));
        BaseDatos::ejecutar(
            "UPDATE paciente SET $sets WHERE id_paciente = :id",
            array_intersect_key($d, array_flip($campos)) + ['id' => $id]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE paciente SET estado = :e WHERE id_paciente = :id", ['id' => $id, 'e' => $estado]);
    }

    public function cancelarCitasPendientes(int $id, string $motivo): int
    {
        return BaseDatos::ejecutar(
            "UPDATE cita SET estado = 'cancelada', motivo_cancelacion = :m
              WHERE id_paciente = :id AND estado IN ('programada','confirmada') AND (fecha + hora_inicio) >= localtimestamp",
            ['id' => $id, 'm' => $motivo]
        )->rowCount();
    }

    /* ---------- Contacto de emergencia ---------- */

    public function contactos(int $idPaciente): array
    {
        return BaseDatos::todos(
            "SELECT * FROM contacto_emergencia WHERE id_paciente = :id ORDER BY id_contacto",
            ['id' => $idPaciente]
        );
    }

    /** Guarda el contacto principal (reemplaza el anterior). */
    public function guardarContactoPrincipal(int $idPaciente, ?array $c): void
    {
        BaseDatos::ejecutar("DELETE FROM contacto_emergencia WHERE id_paciente = :id", ['id' => $idPaciente]);
        if ($c && !empty($c['nombre'])) {
            BaseDatos::ejecutar(
                "INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono) VALUES (:id, :n, :p, :t)",
                ['id' => $idPaciente, 'n' => $c['nombre'], 'p' => $c['parentesco'], 't' => $c['telefono']]
            );
        }
    }

    /* ---------- Historial de citas (RF12) ---------- */

    public function historial(int $idPaciente, ?string $estado = null, ?string $desde = null, ?string $hasta = null): array
    {
        $sql = "SELECT * FROM v_cita_detalle WHERE id_paciente = :id";
        $p = ['id' => $idPaciente];
        if ($estado) {
            $sql .= " AND estado = :estado";
            $p['estado'] = $estado;
        }
        if ($desde) {
            $sql .= " AND fecha >= CAST(:desde AS DATE)";
            $p['desde'] = $desde;
        }
        if ($hasta) {
            $sql .= " AND fecha <= CAST(:hasta AS DATE)";
            $p['hasta'] = $hasta;
        }
        return BaseDatos::todos($sql . " ORDER BY fecha DESC, hora_inicio DESC", $p);
    }

    /** @return array<string,int> cantidad de citas por estado */
    public function resumenEstados(int $idPaciente): array
    {
        $r = ['programada' => 0, 'confirmada' => 0, 'atendida' => 0, 'cancelada' => 0, 'inasistencia' => 0];
        foreach (BaseDatos::todos(
            "SELECT estado, count(*) AS n FROM cita WHERE id_paciente = :id GROUP BY estado",
            ['id' => $idPaciente]
        ) as $f) {
            $r[$f['estado']] = (int) $f['n'];
        }
        return $r;
    }

    public function totalActivos(): int
    {
        return (int) BaseDatos::valor("SELECT count(*) FROM paciente WHERE estado = 'activo'");
    }
}
