<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\Disponibilidad;
use App\Paquetes\Medicos\Modelos\EspecialidadModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use Throwable;

/**
 * CU09 · Programar cita (incluye consultar disponibilidad)
 * Requisitos: RF06, RF07 · Actores: Recepcionista, Administrador, Paciente (en línea)
 * Módulo principal del proyecto.
 *
 * Flujo:
 *  1. Seleccionar paciente (el paciente en línea es él mismo).
 *  2. Seleccionar especialidad -> se cargan sus médicos activos.
 *  3. Seleccionar médico y fecha -> se muestran SOLO los horarios disponibles.
 *  4. Elegir hora, modalidad y motivo breve (opcional).
 *  5. Al guardar, dentro de una transacción:
 *       a) se bloquea al médico (SELECT ... FOR UPDATE),
 *       b) se vuelve a comprobar la disponibilidad (RN13),
 *       c) se registra la cita con código único (CM-0001) y fecha de creación.
 *     La restricción EXCLUDE de PostgreSQL es la última barrera contra
 *     citas superpuestas (RF07, Prueba 4).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Recepcionista, Paciente
 * Requisitos......: RF06 · RF07 · RN03–RN09 · RN13
 * Base de datos...: cita (EXCLUDE ex_cita_medico_sin_solape y ex_cita_paciente_sin_solape, FK compuesta
 *                   médico–especialidad, trigger fn_tg_validar_cita, trigger fn_tg_historial_cita)
 * Rutas...........: GET /citas/nueva (citas.programar) · POST /citas (citas.programar) · GET/POST /reservar
 *                   (portal.reservar) · GET /api/medicos?especialidad= (citas.programar | portal.reservar) ·
 *                   GET /api/disponibilidad/dias?medico= (citas.programar | portal.reservar) · GET
 *                   /api/disponibilidad?medico=&fecha= (citas.programar | portal.reservar)
 * Vistas..........: frontend/vistas/citas/programar.php, frontend/assets/js/citas.js,
 *                   frontend/vistas/citas/detalle.php
 * Usa.............: CitaModelo, Disponibilidad, EspecialidadModelo, MedicoModelo, PacienteModelo
 * Precondición....: Usuario con permiso «citas.programar» (o «portal.reservar»); el paciente, el médico y la
 *                   especialidad están activos y el médico tiene horarios.
 * Postcondición...: La cita queda registrada sin conflictos de horario y aparece en la agenda del médico y en
 *                   el historial del paciente.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU09ProgramarCita extends Controlador
{
    private CitaModelo $citas;
    private Disponibilidad $disponibilidad;

    public function __construct()
    {
        $this->citas = new CitaModelo();
        $this->disponibilidad = new Disponibilidad();
    }

    /** GET /citas/nueva (recepción) */
    public function formulario(): void
    {
        $paciente = null;
        if ($id = Peticion::entero('paciente')) {
            $paciente = (new PacienteModelo())->obtener($id);
            if ($paciente && $paciente['estado'] !== 'activo') {
                $paciente = null;
            }
        }
        $this->vista('citas/programar', [
            'titulo'         => 'Programar cita',
            'modo'           => 'recepcion',
            'paciente'       => $paciente,
            'especialidades' => (new EspecialidadModelo())->conMedicosActivos(),
            'accion'         => url('/citas'),
            'scripts'        => ['citas.js'],
        ]);
    }

    /** GET /reservar (paciente en línea) */
    public function formularioPaciente(): void
    {
        $this->vista('citas/programar', [
            'titulo'         => 'Reservar cita',
            'modo'           => 'portal',
            'paciente'       => (new PacienteModelo())->obtener((int) $this->usuario()['id_paciente']),
            'especialidades' => (new EspecialidadModelo())->conMedicosActivos(),
            'accion'         => url('/reservar'),
            'scripts'        => ['citas.js'],
        ]);
    }

    /** POST /citas — Prueba 3 (horario disponible), 4 (superpuesta), 5 (fuera de horario) */
    public function registrar(): void
    {
        try {
            $cita = $this->guardar(Peticion::entero('id_paciente') ?? 0);
            $this->exito("Cita {$cita['codigo']} registrada.", "/citas/{$cita['id_cita']}");
        } catch (Throwable $e) {
            $this->volverConError($e, '/citas/nueva' . (Peticion::entero('id_paciente') ? '?paciente=' . Peticion::entero('id_paciente') : ''));
        }
    }

    /** POST /reservar — el paciente solo puede reservar para sí mismo */
    public function registrarPaciente(): void
    {
        try {
            $cita = $this->guardar((int) $this->usuario()['id_paciente']);
            $this->exito("Su cita {$cita['codigo']} quedó reservada. Le esperamos.", '/mis-citas');
        } catch (Throwable $e) {
            $this->volverConError($e, '/reservar');
        }
    }

    /**
     * Lógica común de registro (validación + transacción).
     * @return array{id_cita:int,codigo:string}
     */
    public function guardar(int $idPaciente): array
    {
        $d = Validador::validar($_POST + ['id_paciente' => $idPaciente], [
            'id_paciente'     => ['Paciente', 'requerido|entero:1'],
            'id_especialidad' => ['Especialidad', 'requerido|entero:1'],
            'id_medico'       => ['Médico', 'requerido|entero:1'],
            'fecha'           => ['Fecha', 'requerido|fecha|no_pasada'],
            'hora_inicio'     => ['Hora', 'requerido|hora'],
            'modalidad'       => ['Modalidad', 'requerido|en:presencial,virtual'],
            'motivo'          => ['Motivo', 'opcional|texto:3:250'],
        ]);
        $d['id_paciente'] = $idPaciente ?: $d['id_paciente'];

        // RN03: paciente y médico válidos y activos; el médico atiende la especialidad.
        $paciente = (new PacienteModelo())->obtener($d['id_paciente']);
        if (!$paciente || $paciente['estado'] !== 'activo') {
            throw new ErrorNegocio('Revise los datos.', ['id_paciente' => 'Seleccione un paciente activo.']);
        }
        $medico = (new MedicoModelo())->obtener($d['id_medico']);
        if (!$medico || !in_array($d['id_especialidad'], $medico['ids_especialidades'], true)) {
            throw new ErrorNegocio('El médico elegido no atiende esa especialidad.');
        }
        if ($medico['estado'] !== 'activo') {
            throw new ErrorNegocio('RN04: el médico está inactivo y no puede recibir nuevas citas.');
        }
        $especialidad = (new EspecialidadModelo())->obtener($d['id_especialidad']);
        if (!$especialidad || $especialidad['estado'] !== 'activo') {
            throw new ErrorNegocio('La especialidad elegida no está disponible.');
        }

        return BaseDatos::transaccion(function () use ($d) {
            $this->citas->bloquearMedico($d['id_medico']);
            // RN13: se vuelve a comprobar la disponibilidad dentro de la transacción.
            $d['hora_fin'] = $this->disponibilidad->verificar($d['id_medico'], $d['fecha'], $d['hora_inicio']);
            if ($otra = $this->citas->pacienteOcupado($d['id_paciente'], $d['fecha'], $d['hora_inicio'], $d['hora_fin'])) {
                throw new ErrorNegocio("El paciente ya tiene la cita {$otra['codigo']} a esa misma hora.");
            }
            $d['id_usuario'] = \App\Nucleo\Autenticacion::id();
            return $this->citas->crear($d);
        }, 'Cita registrada');
    }

    /* ------------------- Servicios JSON (consultar disponibilidad) ------------------- */

    /** GET /api/medicos?especialidad=ID */
    public function medicosJson(): void
    {
        $id = Peticion::entero('especialidad') ?? 0;
        $this->json(['ok' => true, 'medicos' => array_map(static fn ($m) => [
            'id' => (int) $m['id_medico'], 'nombre' => $m['nombre'], 'codigo' => $m['codigo'],
        ], (new MedicoModelo())->activosPorEspecialidad($id))]);
    }

    /** GET /api/disponibilidad/dias?medico=ID&desde=AAAA-MM-DD[&excluir=ID] */
    public function diasJson(): void
    {
        $medico = Peticion::entero('medico') ?? 0;
        $desde = (string) Peticion::entrada('desde', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || $desde < date('Y-m-d')) {
            $desde = date('Y-m-d');
        }
        $this->json(['ok' => true, 'dias' => $this->disponibilidad->proximosDias($medico, $desde, 14, Peticion::entero('excluir'))]);
    }

    /** GET /api/disponibilidad?medico=ID&fecha=AAAA-MM-DD[&excluir=ID] */
    public function horariosJson(): void
    {
        $medico = Peticion::entero('medico') ?? 0;
        $fecha = (string) Peticion::entrada('fecha', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->json(['ok' => false, 'mensaje' => 'Fecha no válida.'], 422);
        }
        $r = $this->disponibilidad->delDia($medico, $fecha, Peticion::entero('excluir'));
        $this->json(['ok' => true, 'fecha' => $fecha, 'fecha_texto' => fecha_larga($fecha)] + $r);
    }
}
