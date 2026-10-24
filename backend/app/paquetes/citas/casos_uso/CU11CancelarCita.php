<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\Aplicacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Validador;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\EstadoCita;
use App\Paquetes\Notificaciones\Servicios\Notificador;
use Throwable;

/**
 * CU11 · Cancelar cita
 * Requisito: RF09 · Actores: Recepcionista, Administrador, Paciente (sus citas)
 *
 * Reglas:
 *  - No se elimina físicamente la cita: cambia a estado "cancelada"
 *    y se guarda el motivo (Prueba 6).
 *  - La cita cancelada deja de ocupar el horario (RN09).
 *  - Queda disponible para consultas históricas.
 *  - Una cita atendida no puede cancelarse.
 *  - El paciente cancela en línea con al menos 2 horas de anticipación.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Paciente
 * Requisitos......: RF09 · RN09 · RN10
 * Base de datos...: cita (CHECK ck_cita_cancelada exige motivo; trigger impide DELETE), cita_historial
 * Rutas...........: POST /citas/{id}/cancelar (citas.cancelar) · POST /mis-citas/{id}/cancelar
 *                   (portal.mis_citas)
 * Vistas..........: frontend/vistas/citas/detalle.php, frontend/vistas/pacientes/mis_citas.php
 * Usa.............: CitaModelo, EstadoCita
 * Precondición....: La cita existe y el usuario tiene «citas.cancelar» (o es el paciente dueño de la cita).
 * Postcondición...: La cita queda «cancelada» con su motivo; deja de ocupar el horario y permanece en el
 *                   historial.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU11CancelarCita extends Controlador
{
    private CitaModelo $citas;

    public function __construct()
    {
        $this->citas = new CitaModelo();
    }

    /** POST /citas/{id}/cancelar (recepción) */
    public function cancelar(int $id): void
    {
        try {
            $this->ejecutar($id, (string) ($_POST['motivo'] ?? ''), null);
            (new Notificador())->avisarCita('cita_cancelada', $id);   // correo al paciente (CU18)
            $this->exito('Cita cancelada. El horario quedó libre y la cita se conserva en el historial.', "/citas/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/citas/{$id}");
        }
    }

    /** POST /mis-citas/{id}/cancelar (paciente) */
    public function cancelarPaciente(int $id): void
    {
        try {
            $this->ejecutar($id, (string) ($_POST['motivo'] ?? ''), (int) $this->usuario()['id_paciente']);
            (new Notificador())->avisarCita('cita_cancelada', $id);
            $this->exito('Su cita fue cancelada.', '/mis-citas');
        } catch (Throwable $e) {
            $this->volverConError($e, '/mis-citas');
        }
    }

    /** Lógica del caso de uso. $soloPaciente limita a las citas de ese paciente. */
    public function ejecutar(int $id, string $motivo, ?int $soloPaciente): void
    {
        $d = Validador::validar(['motivo' => $motivo], [
            'motivo' => ['Motivo de cancelación', 'requerido|texto:5:250'],
        ]);
        BaseDatos::transaccion(function () use ($id, $d, $soloPaciente) {
            $cita = $this->citas->obtener($id) ?? throw new ErrorNegocio('La cita no existe.');
            if ($soloPaciente !== null && (int) $cita['id_paciente'] !== $soloPaciente) {
                throw new ErrorNegocio('Solo puede cancelar sus propias citas.');
            }
            EstadoCita::validarCambio($cita, 'cancelada');
            if ($soloPaciente !== null) {
                $horas = (int) Aplicacion::config('citas.horas_minimas_cancelar_portal', 2);
                if (strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) - time() < $horas * 3600) {
                    throw new ErrorNegocio("Las citas se cancelan en línea con al menos {$horas} horas de anticipación. Llame a recepción.");
                }
            }
            $this->citas->cambiarEstado($id, 'cancelada', $d['motivo']);
        }, 'Cancelada: ' . $d['motivo']);
    }
}
