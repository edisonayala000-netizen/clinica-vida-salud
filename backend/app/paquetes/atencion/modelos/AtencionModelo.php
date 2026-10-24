<?php
namespace App\Paquetes\Atencion\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de ATENCION y SIGNO_VITAL (CU16).
 * El médico y el paciente de una atención se obtienen de su cita.
 */
final class AtencionModelo
{
    /** Atención de una cita (una por cita) con quién la registró, o null. */
    public function deCita(int $idCita): ?array
    {
        return BaseDatos::uno(
            "SELECT a.*, u.nombre_completo AS registrado_por,
                    r.id_receta, r.codigo AS codigo_receta, r.enviada_en
               FROM atencion a
               LEFT JOIN v_usuario u ON u.id_usuario = a.id_usuario_registro
               LEFT JOIN receta r    ON r.id_atencion = a.id_atencion
              WHERE a.id_cita = :c",
            ['c' => $idCita]
        );
    }

    /** @return array<int,array<string,mixed>> signos vitales con el IMC calculado (vista v_signo_vital) */
    public function signos(int $idAtencion): array
    {
        return BaseDatos::todos(
            "SELECT * FROM v_signo_vital WHERE id_atencion = :a ORDER BY fecha_hora, id_signo_vital",
            ['a' => $idAtencion]
        );
    }

    public function crear(int $idCita, array $d, ?int $idUsuario): int
    {
        return (int) BaseDatos::valor(
            "INSERT INTO atencion (id_cita, diagnostico, tratamiento, observaciones, id_usuario_registro)
             VALUES (:c, :diag, :trat, :obs, :u) RETURNING id_atencion",
            ['c' => $idCita, 'diag' => $d['diagnostico'], 'trat' => $d['tratamiento'],
             'obs' => $d['observaciones'], 'u' => $idUsuario]
        );
    }

    /** @param array<string,int|float|null> $s peso_kg, talla_cm, presion_sistolica, ... */
    public function agregarSignos(int $idAtencion, array $s): void
    {
        BaseDatos::ejecutar(
            "INSERT INTO signo_vital (id_atencion, peso_kg, talla_cm, presion_sistolica, presion_diastolica,
                                      temperatura_c, frecuencia_cardiaca, saturacion_o2)
             VALUES (:a, :peso, :talla, :sis, :dia, :temp, :fc, :sat)",
            [
                'a' => $idAtencion,
                'peso' => $s['peso_kg'] === null ? null : (string) $s['peso_kg'],
                'talla' => $s['talla_cm'] === null ? null : (string) $s['talla_cm'],
                'sis' => $s['presion_sistolica'], 'dia' => $s['presion_diastolica'],
                'temp' => $s['temperatura_c'] === null ? null : (string) $s['temperatura_c'],
                'fc' => $s['frecuencia_cardiaca'], 'sat' => $s['saturacion_o2'],
            ]
        );
    }

    /** @return array<int,array<string,mixed>> atenciones del paciente, de la más reciente a la más antigua */
    public function delPaciente(int $idPaciente): array
    {
        return BaseDatos::todos(
            "SELECT a.id_atencion, a.diagnostico, a.tratamiento, v.id_cita, v.codigo AS codigo_cita, v.fecha,
                    v.medico, v.especialidad, r.id_receta, r.codigo AS codigo_receta
               FROM atencion a
               JOIN v_cita_detalle v ON v.id_cita = a.id_cita
               LEFT JOIN receta r    ON r.id_atencion = a.id_atencion
              WHERE v.id_paciente = :p
              ORDER BY v.fecha DESC, v.hora_inicio DESC",
            ['p' => $idPaciente]
        );
    }
}
