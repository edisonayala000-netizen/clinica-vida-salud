<?php
namespace App\Paquetes\Notificaciones\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de NOTIFICACION: cola y registro de los correos del sistema,
 * y los datos que necesita cada correo (cita, receta, toma de un medicamento).
 */
final class NotificacionModelo
{
    public const POR_PAGINA = 25;

    /**
     * Registra un correo para enviarlo ahora (estado pendiente).
     * @param array{id_cita?:int,id_receta?:int,id_detalle?:int} $referencias
     */
    public function registrar(string $tipo, int $idPersona, string $correo, string $asunto, array $referencias = []): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_cita, id_receta, id_detalle)
             VALUES (:tipo, :persona, :correo, :asunto, :cita, :receta, :detalle)
             RETURNING id_notificacion",
            [
                'tipo'    => $tipo,
                'persona' => $idPersona,
                'correo'  => $correo,
                'asunto'  => mb_substr($asunto, 0, 150),
                'cita'    => $referencias['id_cita'] ?? null,
                'receta'  => $referencias['id_receta'] ?? null,
                'detalle' => $referencias['id_detalle'] ?? null,
            ]
        );
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno("SELECT * FROM notificacion WHERE id_notificacion = :id", ['id' => $id]);
    }

    public function marcarEnviada(int $id, ?string $idExterno): void
    {
        BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'enviada', enviada_en = now(), intentos = intentos + 1,
                    id_externo = :externo, error = NULL
              WHERE id_notificacion = :id",
            ['id' => $id, 'externo' => $idExterno !== null ? mb_substr($idExterno, 0, 100) : null]
        );
    }

    public function marcarError(int $id, string $error): void
    {
        BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'error', intentos = intentos + 1, error = :error WHERE id_notificacion = :id",
            ['id' => $id, 'error' => mb_substr($error, 0, 250)]
        );
    }

    public function anular(int $id, string $motivo): void
    {
        BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'anulada', error = :motivo WHERE id_notificacion = :id AND estado <> 'enviada'",
            ['id' => $id, 'motivo' => mb_substr($motivo, 0, 250)]
        );
    }

    /* ---------- Cola de envío (tarea programada) ---------- */

    /** Anula los pendientes con demasiado atraso (p. ej. la toma de ayer). @return int cuántos */
    public function anularVencidas(int $horas): int
    {
        return BaseDatos::ejecutar(
            "UPDATE notificacion SET estado = 'anulada', error = 'No se envió a tiempo (más de ' || :h || ' h de atraso).'
              WHERE estado IN ('pendiente', 'error') AND programada_para < now() - make_interval(hours => :h)",
            ['h' => $horas]
        )->rowCount();
    }

    /**
     * Toma la siguiente notificación lista para enviar y la bloquea hasta el fin de la transacción
     * (FOR UPDATE SKIP LOCKED: si dos tareas corren a la vez, no envían el mismo correo).
     * Incluye las que fallaron y aún tienen reintentos (salvo recuperar_clave: su enlace no se puede rehacer).
     */
    public function siguienteLista(int $maxIntentos): ?array
    {
        return BaseDatos::uno(
            "SELECT * FROM notificacion
              WHERE programada_para <= now()
                AND (estado = 'pendiente'
                     OR (estado = 'error' AND tipo <> 'recuperar_clave' AND intentos < :max))
              ORDER BY programada_para, id_notificacion
              LIMIT 1
              FOR UPDATE SKIP LOCKED",
            ['max' => $maxIntentos]
        );
    }

    /** Correos realmente enviados por Brevo hoy (los del log de desarrollo no cuentan). */
    public function enviadosHoy(): int
    {
        return (int) BaseDatos::valor(
            "SELECT count(*) FROM notificacion
              WHERE estado = 'enviada' AND enviada_en >= CURRENT_DATE AND id_externo NOT LIKE 'solo registrado%'"
        );
    }

    /* ---------- Datos para armar cada correo ---------- */

    public function persona(int $idPersona): ?array
    {
        return BaseDatos::uno(
            "SELECT id_persona, nombres, apellidos, correo FROM persona WHERE id_persona = :id",
            ['id' => $idPersona]
        );
    }

    /** Cita con sus datos legibles y el correo y la preferencia del paciente. */
    public function cita(int $idCita): ?array
    {
        return BaseDatos::uno(
            "SELECT v.*, pe.nombres AS nombres_paciente, pe.correo AS correo_paciente, pa.recordatorio_cita, e.es_final
               FROM v_cita_detalle v
               JOIN paciente pa    ON pa.id_paciente = v.id_paciente
               JOIN persona pe     ON pe.id_persona = v.id_paciente
               JOIN estado_cita e  ON e.codigo = v.estado
              WHERE v.id_cita = :id",
            ['id' => $idCita]
        );
    }

    /** Un medicamento recetado, con la preferencia del paciente. */
    public function detalleReceta(int $idDetalle): ?array
    {
        return BaseDatos::uno(
            "SELECT v.*, pa.recordar_medicamentos FROM v_receta_detalle v
               JOIN paciente pa ON pa.id_paciente = v.id_paciente
              WHERE v.id_detalle = :id",
            ['id' => $idDetalle]
        );
    }

    /** @return array<int,array<string,mixed>> medicamentos de una receta (la primera fila trae los datos generales) */
    public function receta(int $idReceta): array
    {
        return BaseDatos::todos(
            "SELECT * FROM v_receta_detalle WHERE id_receta = :id ORDER BY id_detalle",
            ['id' => $idReceta]
        );
    }

    public function marcarRecetaEnviada(int $idReceta): void
    {
        BaseDatos::ejecutar("UPDATE receta SET enviada_en = now() WHERE id_receta = :id", ['id' => $idReceta]);
    }

    /* ---------- Consulta del administrador (CU18) ---------- */

    /** @param array{tipo:?string,estado:?string,correo:?string} $f */
    public function listar(array $f, int $pagina): array
    {
        [$where, $p] = $this->condiciones($f);
        return BaseDatos::todos(
            "SELECT n.*, pe.nombres || ' ' || pe.apellidos AS destinatario
               FROM notificacion n JOIN persona pe ON pe.id_persona = n.id_persona" . $where . "
              ORDER BY n.programada_para DESC, n.id_notificacion DESC
              LIMIT :lim OFFSET :off",
            $p + ['lim' => self::POR_PAGINA, 'off' => ($pagina - 1) * self::POR_PAGINA]
        );
    }

    public function contar(array $f): int
    {
        [$where, $p] = $this->condiciones($f);
        return (int) BaseDatos::valor("SELECT count(*) FROM notificacion n" . $where, $p);
    }

    /** Indicadores de la pantalla: listas para enviar, programadas, enviadas hoy y con error. */
    public function resumen(): array
    {
        return BaseDatos::uno(
            "SELECT count(*) FILTER (WHERE estado = 'pendiente' AND programada_para <= now()) AS listas,
                    count(*) FILTER (WHERE estado = 'pendiente' AND programada_para > now())  AS programadas,
                    count(*) FILTER (WHERE estado = 'enviada' AND enviada_en >= CURRENT_DATE)  AS enviadas_hoy,
                    count(*) FILTER (WHERE estado = 'error')                                    AS con_error
               FROM notificacion"
        );
    }

    private function condiciones(array $f): array
    {
        $where = " WHERE 1 = 1";
        $p = [];
        if (!empty($f['tipo'])) {
            $where .= " AND n.tipo = :tipo";
            $p['tipo'] = $f['tipo'];
        }
        if (!empty($f['estado'])) {
            $where .= " AND n.estado = :estado";
            $p['estado'] = $f['estado'];
        }
        if (!empty($f['correo'])) {
            $where .= " AND n.correo_destino ILIKE :correo";
            $p['correo'] = '%' . $f['correo'] . '%';
        }
        return [$where, $p];
    }
}
