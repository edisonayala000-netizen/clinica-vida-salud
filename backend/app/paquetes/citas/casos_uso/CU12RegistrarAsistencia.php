<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\EstadoCita;
use Throwable;

/**
 * CU12 · Registrar asistencia (controlar el estado de la cita)
 * Requisito: RF10 · Actores: Recepcionista (confirmar, inasistencia, atendida)
 *            y Médico (marcar atendida SOLO si tiene el permiso citas.atender)
 *
 * Estados: programada, confirmada, atendida, cancelada, inasistencia.
 * Las transiciones válidas están en EstadoCita::TRANSICIONES; por ejemplo,
 * una cita cancelada no puede marcarse como atendida.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Médico
 * Requisitos......: RF10 · RN12
 * Base de datos...: cita (trigger fn_tg_validar_transicion_cita), cita_historial
 * Rutas...........: POST /citas/{id}/estado (citas.confirmar | citas.atender)
 * Vistas..........: frontend/vistas/citas/detalle.php, frontend/vistas/citas/agenda.php,
 *                   frontend/vistas/inicio/citas_hoy.php
 * Usa.............: CitaModelo, EstadoCita
 * Precondición....: La cita existe y el usuario tiene el permiso correspondiente.
 * Postcondición...: La cita queda en el nuevo estado; los estados atendida, cancelada e inasistencia son
 *                   finales.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU12RegistrarAsistencia extends Controlador
{
    /** Permiso necesario para cada nuevo estado. */
    private const PERMISO = [
        'confirmada'   => 'citas.confirmar',
        'inasistencia' => 'citas.confirmar',
        'atendida'     => 'citas.atender',
    ];

    /** POST /citas/{id}/estado */
    public function cambiar(int $id): void
    {
        $volver = Peticion::entrada('volver', "/citas/{$id}");
        $volver = is_string($volver) && str_starts_with($volver, '/') ? $volver : "/citas/{$id}";
        try {
            $nuevo = (string) Peticion::entrada('estado', '');
            $cita = $this->ejecutar($id, $nuevo);
            $textos = ['confirmada' => 'confirmada', 'atendida' => 'marcada como atendida', 'inasistencia' => 'registrada como inasistencia'];
            $this->exito("Cita {$cita['codigo']} {$textos[$nuevo]}.", $volver);
        } catch (Throwable $e) {
            $this->volverConError($e, $volver);
        }
    }

    public function ejecutar(int $id, string $nuevo): array
    {
        if (!isset(self::PERMISO[$nuevo])) {
            throw new ErrorNegocio('Estado no válido.');
        }
        // RN12: cada operación solo para usuarios autorizados.
        if (!Autenticacion::puede(self::PERMISO[$nuevo])) {
            throw new ErrorNegocio('No tiene permiso para marcar la cita como ' . $nuevo . '.');
        }
        return BaseDatos::transaccion(function () use ($id, $nuevo) {
            $cita = (new CitaModelo())->obtener($id) ?? throw new ErrorNegocio('La cita no existe.');
            // Un médico solo actúa sobre sus propias citas.
            $u = Autenticacion::usuario();
            if ($u['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $u['id_medico']) {
                throw new ErrorNegocio('Solo puede registrar la atención de sus propias citas.');
            }
            EstadoCita::validarCambio($cita, $nuevo);
            (new CitaModelo())->cambiarEstado($id, $nuevo);
            return $cita;
        }, match ($nuevo) {
            'confirmada'   => 'El paciente confirmó su asistencia',
            'atendida'     => 'Consulta realizada',
            'inasistencia' => 'El paciente no se presentó',
        });
    }
}
