<?php
namespace App\Paquetes\Citas\Servicios;

use App\Nucleo\Aplicacion;
use App\Nucleo\ErrorNegocio;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Medicos\Modelos\HorarioModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;

/**
 * Cálculo de horarios disponibles de un médico (RF05, RF06, RF07).
 *
 * Algoritmo para una fecha:
 *  1. Se toman los bloques activos del médico para ese día de la semana.
 *  2. Cada bloque se divide en intervalos de su duración (p. ej. 30 min):
 *     08:00-08:30, 08:30-09:00, ...  (RN08: intervalos configurados)
 *  3. Se marcan como OCUPADOS los intervalos que se cruzan con una cita
 *     no cancelada (RN07, RN09: la cancelada libera el horario).
 *  4. Se descartan los intervalos que ya pasaron (RN06).
 *  5. Si la fecha es feriado o el médico está inactivo, no hay horarios (RN04).
 */
final class Disponibilidad
{
    private HorarioModelo $horarios;
    private CitaModelo $citas;
    private MedicoModelo $medicos;

    public function __construct()
    {
        $this->horarios = new HorarioModelo();
        $this->citas = new CitaModelo();
        $this->medicos = new MedicoModelo();
    }

    /**
     * Intervalos del día con su situación.
     * @return array{bloqueo:?string, intervalos:array<int,array{inicio:string,fin:string,libre:bool}>}
     */
    public function delDia(int $idMedico, string $fecha, ?int $excluirCita = null): array
    {
        $medico = $this->medicos->obtener($idMedico);
        if (!$medico || $medico['estado'] !== 'activo') {
            return ['bloqueo' => 'El médico está inactivo y no recibe nuevas citas.', 'intervalos' => []];
        }
        $hoy = date('Y-m-d');
        if ($fecha < $hoy) {
            return ['bloqueo' => 'No se ofrecen citas en fechas pasadas.', 'intervalos' => []];
        }
        $limite = date('Y-m-d', strtotime('+' . (int) Aplicacion::config('citas.dias_maximos_reserva', 60) . ' days'));
        if ($fecha > $limite) {
            return ['bloqueo' => 'Solo se reservan citas hasta el ' . fecha_corta($limite) . '.', 'intervalos' => []];
        }
        $feriado = $this->horarios->fechasBloqueadas($idMedico, $fecha, $fecha)[$fecha] ?? null;
        if ($feriado) {
            return ['bloqueo' => "Fecha bloqueada: {$feriado}.", 'intervalos' => []];
        }

        $bloques = $this->horarios->activosDelDia($idMedico, (int) date('N', strtotime($fecha)));
        if (!$bloques) {
            return ['bloqueo' => 'El médico no atiende ese día.', 'intervalos' => []];
        }

        $ocupadas = $this->citas->ocupadas($idMedico, $fecha, $excluirCita);
        $ahora = date('H:i');
        $intervalos = [];
        foreach ($bloques as $b) {
            $paso = (int) $b['duracion_cita'] * 60;
            $fin = strtotime($b['hora_fin']);
            for ($t = strtotime($b['hora_inicio']); $t + $paso <= $fin; $t += $paso) {
                $ini = date('H:i', $t);
                $fi = date('H:i', $t + $paso);
                if ($fecha === $hoy && $ini <= $ahora) {
                    continue; // RN06: ya pasó
                }
                $libre = true;
                foreach ($ocupadas as $c) {
                    if (substr($c['hora_inicio'], 0, 5) < $fi && substr($c['hora_fin'], 0, 5) > $ini) {
                        $libre = false;
                        break;
                    }
                }
                $intervalos[] = ['inicio' => $ini, 'fin' => $fi, 'libre' => $libre];
            }
        }
        return ['bloqueo' => $intervalos ? null : 'Ya no quedan horarios ese día.', 'intervalos' => $intervalos];
    }

    /**
     * Resumen de los próximos días para la tira de fechas.
     * @return array<int,array{fecha:string,atiende:bool,libres:int,motivo:?string}>
     */
    public function proximosDias(int $idMedico, string $desde, int $cantidad = 14, ?int $excluirCita = null): array
    {
        $dias = [];
        $atiende = $this->horarios->diasQueAtiende($idMedico);
        $hasta = date('Y-m-d', strtotime($desde . ' +' . ($cantidad - 1) . ' days'));
        $bloqueadas = $this->horarios->fechasBloqueadas($idMedico, $desde, $hasta);
        for ($i = 0; $i < $cantidad; $i++) {
            $f = date('Y-m-d', strtotime($desde . " +{$i} days"));
            $dia = ['fecha' => $f, 'atiende' => isset($atiende[(int) date('N', strtotime($f))]), 'libres' => 0, 'motivo' => null];
            if (isset($bloqueadas[$f])) {
                $dia['motivo'] = $bloqueadas[$f];
            } elseif ($dia['atiende']) {
                $r = $this->delDia($idMedico, $f, $excluirCita);
                $dia['libres'] = count(array_filter($r['intervalos'], static fn ($x) => $x['libre']));
                $dia['motivo'] = $r['bloqueo'];
            }
            $dias[] = $dia;
        }
        return $dias;
    }

    /**
     * Verificación en el SERVIDOR antes de guardar (RN13). Devuelve la hora
     * final del intervalo o lanza un error con la regla incumplida.
     */
    public function verificar(int $idMedico, string $fecha, string $horaInicio, ?int $excluirCita = null): string
    {
        if (($fecha . ' ' . $horaInicio) <= date('Y-m-d H:i')) {
            throw new ErrorNegocio('RN06: no se pueden registrar citas en fechas u horas pasadas.');
        }
        $r = $this->delDia($idMedico, $fecha, $excluirCita);
        if ($r['bloqueo'] && !$r['intervalos']) {
            throw new ErrorNegocio($r['bloqueo']);
        }
        foreach ($r['intervalos'] as $i) {
            if ($i['inicio'] === $horaInicio) {
                if (!$i['libre']) {
                    throw new ErrorNegocio("RN07: el médico ya tiene una cita de {$i['inicio']} a {$i['fin']}. Elija otro horario.");
                }
                return $i['fin'];
            }
        }
        throw new ErrorNegocio('RN05: la hora elegida está fuera del horario de atención del médico o no respeta los intervalos configurados.');
    }
}
