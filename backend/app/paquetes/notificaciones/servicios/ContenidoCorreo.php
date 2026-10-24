<?php
namespace App\Paquetes\Notificaciones\Servicios;

use App\Nucleo\EnlaceFirmado;
use App\Paquetes\Notificaciones\Modelos\NotificacionModelo;

/**
 * Arma el contenido de cada notificación justo antes de enviarla: la plantilla de
 * frontend/vistas/correos/, sus datos y los enlaces firmados. Si algo cambió desde que
 * se programó (la cita se canceló, el paciente desactivó los avisos…), devuelve null:
 * esa notificación ya no se envía.
 */
final class ContenidoCorreo
{
    /** Vigencia de los enlaces para desactivar avisos. */
    private const DIAS_ENLACE_BAJA = 180;

    public function __construct(private NotificacionModelo $modelo)
    {
    }

    /** @return array{plantilla:string,nombre:string,datos:array<string,mixed>}|null */
    public function para(array $n): ?array
    {
        return match ($n['tipo']) {
            'cita_registrada', 'cita_reprogramada', 'cita_cancelada',
            'recordatorio_cita', 'recordatorio_cita_1h' => $this->cita($n),
            'toma_medicamento'                          => $this->toma($n),
            'receta'                                    => $this->receta($n),
            default                                     => null,   // recuperar_clave se arma en CU15
        };
    }

    private function cita(array $n): ?array
    {
        $c = $this->modelo->cita((int) $n['id_cita']);
        if ($c === null) {
            return null;
        }
        $inicio = strtotime($c['fecha'] . ' ' . $c['hora_inicio']);
        $esRecordatorio = in_array($n['tipo'], ['recordatorio_cita', 'recordatorio_cita_1h'], true);
        if ($esRecordatorio && ($c['es_final'] || $c['recordatorio_cita'] === 'ninguno' || $inicio <= time())) {
            return null;
        }
        return [
            'plantilla' => 'cita',
            'nombre'    => $c['paciente'],
            'datos'     => [
                'tipo'       => $n['tipo'],
                'cita'       => $c,
                // Solo el recordatorio de 24 h trae Confirmar / Cancelar; el enlace vence al empezar la cita.
                'enlaceCita' => $n['tipo'] === 'recordatorio_cita'
                    ? url_absoluta('/correo/cita', ['e' => EnlaceFirmado::crear(['a' => 'cita', 'c' => (int) $c['id_cita']], $inicio)])
                    : null,
                'enlaceBaja' => $esRecordatorio ? $this->enlaceBaja((int) $c['id_paciente'], 'citas') : null,
            ],
        ];
    }

    private function toma(array $n): ?array
    {
        $d = $this->modelo->detalleReceta((int) $n['id_detalle']);
        if ($d === null || !$d['recordar'] || !$d['recordar_medicamentos']) {
            return null;
        }
        return [
            'plantilla' => 'toma_medicamento',
            'nombre'    => $d['paciente'],
            'datos'     => [
                'toma'            => $d,
                'momento'         => $n['programada_para'],
                'enlaceBaja'      => $this->enlaceBaja((int) $d['id_paciente'], 'detalle', (int) $d['id_detalle']),
                'enlaceBajaTodos' => $this->enlaceBaja((int) $d['id_paciente'], 'medicamentos'),
            ],
        ];
    }

    private function receta(array $n): ?array
    {
        $filas = $this->modelo->receta((int) $n['id_receta']);
        if (!$filas) {
            return null;
        }
        return [
            'plantilla' => 'receta',
            'nombre'    => $filas[0]['paciente'],
            'datos'     => ['receta' => $filas[0], 'detalles' => $filas, 'enlaceVer' => url_absoluta('/mis-recetas')],
        ];
    }

    /** Enlace firmado para desactivar avisos: tema 'citas', 'medicamentos' o 'detalle' (un medicamento). */
    private function enlaceBaja(int $idPaciente, string $tema, ?int $idDetalle = null): string
    {
        $datos = ['a' => 'baja', 'p' => $idPaciente, 't' => $tema] + ($idDetalle ? ['d' => $idDetalle] : []);
        return url_absoluta('/correo/baja', ['e' => EnlaceFirmado::crear($datos, time() + self::DIAS_ENLACE_BAJA * 86400)]);
    }
}
