<?php
namespace App\Paquetes\Citas\Servicios;

use App\Nucleo\ErrorNegocio;

/**
 * Máquina de estados de la cita (RF10).
 *
 *   programada ──► confirmada ──► atendida
 *       │              │    └───► inasistencia
 *       │              └────────► cancelada
 *       ├──► atendida | inasistencia | cancelada
 *
 * atendida, cancelada e inasistencia son estados FINALES.
 * La base de datos tiene la misma regla en el trigger
 * fn_tg_validar_transicion_cita (doble control).
 */
final class EstadoCita
{
    public const TRANSICIONES = [
        'programada'   => ['confirmada', 'atendida', 'cancelada', 'inasistencia'],
        'confirmada'   => ['atendida', 'cancelada', 'inasistencia'],
        'atendida'     => [],
        'cancelada'    => [],
        'inasistencia' => [],
    ];

    public const NOMBRES = [
        'programada' => 'programada', 'confirmada' => 'confirmada', 'atendida' => 'atendida',
        'cancelada' => 'cancelada', 'inasistencia' => 'inasistencia',
    ];

    /** ¿Ya empezó la hora de la cita? */
    public static function yaInicio(array $cita): bool
    {
        return ($cita['fecha'] . ' ' . substr($cita['hora_inicio'], 0, 5)) <= date('Y-m-d H:i');
    }

    public static function esPendiente(array $cita): bool
    {
        return in_array($cita['estado'], ['programada', 'confirmada'], true);
    }

    /** Valida el cambio de estado y explica por qué no se permite. */
    public static function validarCambio(array $cita, string $nuevo): void
    {
        $actual = $cita['estado'];
        if (!in_array($nuevo, self::TRANSICIONES[$actual] ?? [], true)) {
            throw new ErrorNegocio(match ($actual) {
                'cancelada'    => 'Una cita cancelada no puede marcarse como ' . $nuevo . '. Programe una nueva cita.',
                'atendida'     => 'Una cita atendida ya está cerrada y conserva su registro histórico.',
                'inasistencia' => 'La cita ya fue registrada como inasistencia.',
                default        => "No se puede pasar de «{$actual}» a «{$nuevo}».",
            });
        }
        if (in_array($nuevo, ['atendida', 'inasistencia'], true) && $cita['fecha'] > date('Y-m-d')) {
            throw new ErrorNegocio('Una cita futura no puede marcarse como ' . $nuevo . '.');
        }
        if ($nuevo === 'inasistencia' && !self::yaInicio($cita)) {
            throw new ErrorNegocio('La inasistencia se registra cuando ya pasó la hora de la cita.');
        }
        if ($nuevo === 'confirmada' && self::yaInicio($cita)) {
            throw new ErrorNegocio('Esta cita ya comenzó: registre si fue atendida o si hubo inasistencia.');
        }
        if ($nuevo === 'cancelada' && self::yaInicio($cita)) {
            throw new ErrorNegocio('La cita ya comenzó y no puede cancelarse; registre la asistencia o la inasistencia.');
        }
    }

    /** RF08: política de reprogramación. */
    public static function validarReprogramacion(array $cita): void
    {
        if (!self::esPendiente($cita)) {
            throw new ErrorNegocio("Una cita {$cita['estado']} no puede reprogramarse directamente. Política: programe una nueva cita para el paciente.");
        }
        if (self::yaInicio($cita)) {
            throw new ErrorNegocio('La hora de esta cita ya pasó: registre la asistencia o la inasistencia en lugar de reprogramarla.');
        }
    }

    /** Acciones posibles para mostrar botones en la interfaz. */
    public static function acciones(array $cita): array
    {
        $a = [];
        foreach (self::TRANSICIONES[$cita['estado']] as $nuevo) {
            try {
                self::validarCambio($cita, $nuevo);
                $a[] = $nuevo;
            } catch (ErrorNegocio) {
            }
        }
        try {
            self::validarReprogramacion($cita);
            $a[] = 'reprogramar';
        } catch (ErrorNegocio) {
        }
        return $a;
    }
}
