<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Validador;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\Disponibilidad;
use App\Paquetes\Citas\Servicios\EstadoCita;
use App\Paquetes\Notificaciones\Servicios\Notificador;
use Throwable;

/**
 * CU10 · Reprogramar cita
 * Requisito: RF08 · Actores: Recepcionista, Administrador
 *
 *  1. Recupera los datos de la cita.
 *  2. Muestra las nuevas fechas y horas disponibles del MISMO médico
 *     (el horario actual de la cita se considera libre).
 *  3. Verifica que el médico pueda atender en el nuevo horario y que no
 *     haya conflictos (dentro de una transacción con bloqueo).
 *  4. Guarda el cambio; el trigger registra fecha/hora anterior y nueva
 *     en CITA_HISTORIAL (integridad y trazabilidad).
 * Política: una cita atendida, cancelada o con inasistencia NO se
 * reprograma; se programa una nueva cita (CU09).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Administrador
 * Requisitos......: RF08 · RN13
 * Base de datos...: cita, cita_historial (trigger fn_tg_historial_cita registra la reprogramación)
 * Rutas...........: GET /citas/{id}/reprogramar (citas.reprogramar) · POST /citas/{id}/reprogramar
 *                   (citas.reprogramar)
 * Vistas..........: frontend/vistas/citas/reprogramar.php, frontend/assets/js/citas.js
 * Usa.............: CitaModelo, Disponibilidad, EstadoCita
 * Precondición....: La cita existe en estado programada o confirmada y el usuario tiene «citas.reprogramar».
 * Postcondición...: La cita queda con la nueva fecha y hora; el historial conserva el cambio.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU10ReprogramarCita extends Controlador
{
    private CitaModelo $citas;

    public function __construct()
    {
        $this->citas = new CitaModelo();
    }

    /** GET /citas/{id}/reprogramar */
    public function formulario(int $id): void
    {
        $cita = $this->citas->obtener($id) ?? $this->noEncontrado('La cita');
        try {
            EstadoCita::validarReprogramacion($cita);
        } catch (ErrorNegocio $e) {
            \App\Nucleo\Sesion::flash('aviso', $e->getMessage());
            $this->redirigir("/citas/{$id}");
        }
        $this->vista('citas/reprogramar', [
            'titulo'  => 'Reprogramar cita ' . $cita['codigo'],
            'cita'    => $cita,
            'scripts' => ['citas.js'],
        ]);
    }

    /** POST /citas/{id}/reprogramar */
    public function guardar(int $id): void
    {
        try {
            $this->reprogramar($id, $_POST);
            (new Notificador())->avisarCita('cita_reprogramada', $id);   // correo al paciente (CU18)
            $cita = $this->citas->obtener($id);
            $this->exito("Cita {$cita['codigo']} reprogramada para el " . fecha_corta($cita['fecha']) . ' a las ' . hora($cita['hora_inicio']) . '.', "/citas/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/citas/{$id}/reprogramar");
        }
    }

    /** Lógica del caso de uso (también la usan las pruebas automáticas). */
    public function reprogramar(int $id, array $entrada): void
    {
        $d = Validador::validar($entrada, [
            'fecha'        => ['Nueva fecha', 'requerido|fecha|no_pasada'],
            'hora_inicio'  => ['Nueva hora', 'requerido|hora'],
            'motivo_cambio'=> ['Motivo del cambio', 'opcional|texto:3:200'],
        ]);

        BaseDatos::transaccion(function () use ($id, $d) {
            $cita = $this->citas->obtener($id) ?? throw new ErrorNegocio('La cita no existe.');
            EstadoCita::validarReprogramacion($cita);
            if ($cita['fecha'] === $d['fecha'] && hora($cita['hora_inicio']) === $d['hora_inicio']) {
                throw new ErrorNegocio('Elija una fecha u hora distinta a la actual.');
            }
            $this->citas->bloquearMedico((int) $cita['id_medico']);
            $horaFin = (new Disponibilidad())->verificar((int) $cita['id_medico'], $d['fecha'], $d['hora_inicio'], $id);
            if ($otra = $this->citas->pacienteOcupado((int) $cita['id_paciente'], $d['fecha'], $d['hora_inicio'], $horaFin, $id)) {
                throw new ErrorNegocio("El paciente ya tiene la cita {$otra['codigo']} a esa hora.");
            }
            $this->citas->reprogramar($id, $d['fecha'], $d['hora_inicio'], $horaFin);
        }, $d['motivo_cambio'] ? 'Reprogramada: ' . $d['motivo_cambio'] : 'Cita reprogramada');
    }
}
