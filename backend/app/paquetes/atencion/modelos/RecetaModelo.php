<?php
namespace App\Paquetes\Atencion\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de RECETA y RECETA_DETALLE (CU16, CU17).
 * Al agregar un medicamento, el trigger fn_tg_programar_tomas programa sus recordatorios.
 */
final class RecetaModelo
{
    /** @return array{id_receta:int,codigo:string} */
    public function crear(int $idAtencion, ?string $indicaciones): array
    {
        return BaseDatos::uno(
            "INSERT INTO receta (id_atencion, indicaciones) VALUES (:a, :i) RETURNING id_receta, codigo",
            ['a' => $idAtencion, 'i' => $indicaciones]
        );
    }

    public function agregarDetalle(int $idReceta, array $d): void
    {
        BaseDatos::ejecutar(
            "INSERT INTO receta_detalle (id_receta, id_medicamento, dosis, via, frecuencia_horas, duracion_dias,
                                         fecha_inicio, hora_primera_toma, indicaciones, recordar)
             VALUES (:r, :m, :dosis, :via, :frec, :dias, :inicio, :hora, :ind, :recordar)",
            [
                'r' => $idReceta, 'm' => $d['id_medicamento'], 'dosis' => $d['dosis'], 'via' => $d['via'],
                'frec' => $d['frecuencia_horas'], 'dias' => $d['duracion_dias'], 'inicio' => $d['fecha_inicio'],
                'hora' => $d['hora_primera_toma'], 'ind' => $d['indicaciones'], 'recordar' => $d['recordar'],
            ]
        );
    }

    /** @return array<int,array<string,mixed>> medicamentos de la receta (vista v_receta_detalle) */
    public function obtener(int $idReceta): array
    {
        return BaseDatos::todos("SELECT * FROM v_receta_detalle WHERE id_receta = :r ORDER BY id_detalle", ['r' => $idReceta]);
    }

    /**
     * Recetas del paciente agrupadas, con las tomas que faltan y la próxima toma de cada medicamento.
     * @return array<int,array{receta:array,detalles:array}>
     */
    public function delPaciente(int $idPaciente): array
    {
        $filas = BaseDatos::todos(
            "SELECT v.*,
                    (SELECT count(*) FROM notificacion n
                      WHERE n.id_detalle = v.id_detalle AND n.estado = 'pendiente') AS avisos_pendientes,
                    (SELECT min(n.programada_para) FROM notificacion n
                      WHERE n.id_detalle = v.id_detalle AND n.estado = 'pendiente') AS proximo_aviso,
                    (v.fecha_inicio + v.duracion_dias) > CURRENT_DATE AS vigente
               FROM v_receta_detalle v
              WHERE v.id_paciente = :p
              ORDER BY v.fecha_emision DESC, v.id_receta DESC, v.id_detalle",
            ['p' => $idPaciente]
        );
        $recetas = [];
        foreach ($filas as $f) {
            $recetas[$f['id_receta']]['receta'] ??= $f;
            $recetas[$f['id_receta']]['detalles'][] = $f;
        }
        return array_values($recetas);
    }

    /** Un medicamento recetado, solo si pertenece a ese paciente. */
    public function detalleDelPaciente(int $idDetalle, int $idPaciente): ?array
    {
        return BaseDatos::uno(
            "SELECT * FROM v_receta_detalle WHERE id_detalle = :d AND id_paciente = :p",
            ['d' => $idDetalle, 'p' => $idPaciente]
        );
    }

    /**
     * Activa o desactiva los recordatorios de un medicamento del paciente; el trigger
     * fn_tg_programar_tomas anula o vuelve a programar sus tomas pendientes.
     */
    public function cambiarRecordar(int $idDetalle, int $idPaciente, bool $recordar): void
    {
        BaseDatos::ejecutar(
            "UPDATE receta_detalle d SET recordar = :r
               FROM receta re JOIN atencion a ON a.id_atencion = re.id_atencion JOIN cita c ON c.id_cita = a.id_cita
              WHERE d.id_detalle = :d AND re.id_receta = d.id_receta AND c.id_paciente = :p",
            ['d' => $idDetalle, 'p' => $idPaciente, 'r' => $recordar]
        );
    }
}
