<?php
namespace App\Paquetes\Medicos\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Medicos\Modelos\HorarioModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;
use Throwable;

/**
 * CU08 · Gestionar horarios de atención (incluye días no laborables)
 * Requisito: RF05 · Actor: Administrador
 *
 * Reglas:
 *  - La hora final debe ser posterior a la inicial; no se aceptan horarios inválidos.
 *  - La duración de cada cita se define por bloque (15, 20, 30, 45 o 60 min)
 *    y el bloque debe alcanzar al menos para una cita.
 *  - Un médico no puede tener bloques superpuestos el mismo día.
 *  - No se desactiva ni se recorta un bloque que deja fuera citas pendientes.
 *  - Feriados o ausencias bloquean la agenda de ese día.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: medicos
 * Actores.........: Administrador
 * Requisitos......: RF05 · RN05 · RN08
 * Base de datos...: horario_atencion (CHECK ck_horario_rango, EXCLUDE ex_horario_sin_solape),
 *                   dia_no_laborable
 * Rutas...........: GET /horarios?medico= (horarios.gestionar) · POST /horarios (horarios.gestionar) · POST
 *                   /horarios/{id} · /horarios/{id}/estado (horarios.gestionar) · POST /feriados ·
 *                   /feriados/{id}/quitar (horarios.gestionar)
 * Vistas..........: frontend/vistas/medicos/horarios.php
 * Usa.............: HorarioModelo, MedicoModelo
 * Precondición....: El administrador inició sesión con «horarios.gestionar»; el médico está activo.
 * Postcondición...: La agenda del médico queda definida; solo se ofrecerán citas dentro de sus horarios y
 *                   fuera de las fechas bloqueadas.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/medicos/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU08GestionarHorarios extends Controlador
{
    private HorarioModelo $horarios;
    private MedicoModelo $medicos;

    public function __construct()
    {
        $this->horarios = new HorarioModelo();
        $this->medicos = new MedicoModelo();
    }

    /** GET /horarios?medico=ID */
    public function mostrar(): void
    {
        $lista = $this->medicos->listaSimple();
        $idMedico = Peticion::entero('medico') ?? (int) ($lista[0]['id_medico'] ?? 0);
        $medico = $idMedico ? $this->medicos->obtener($idMedico) : null;

        $this->vista('medicos/horarios', [
            'titulo'    => 'Horarios de atención',
            'medicos'   => $lista,
            'medico'    => $medico,
            'bloques'   => $medico ? $this->horarios->delMedico($idMedico) : [],
            'feriados'  => $this->horarios->diasNoLaborables($medico ? $idMedico : null),
        ]);
    }

    /** POST /horarios — agregar bloque */
    public function crear(): void
    {
        $idMedico = Peticion::entero('id_medico') ?? 0;
        try {
            $d = $this->validar();
            $d['id_medico'] = $idMedico;
            $medico = $this->medicos->obtener($idMedico) ?? throw new ErrorNegocio('Seleccione un médico válido.');
            if ($medico['estado'] !== 'activo') {
                throw new ErrorNegocio('El médico está inactivo: actívelo antes de asignarle horarios.');
            }
            if ($this->horarios->haySolape($idMedico, $d['dia_semana'], $d['hora_inicio'], $d['hora_fin'])) {
                throw new ErrorNegocio('El bloque se superpone con otro horario del médico ese día.');
            }
            BaseDatos::transaccion(fn () => $this->horarios->crear($d));
            $this->exito('Horario agregado: ' . DIAS_SEMANA[$d['dia_semana']] . " de {$d['hora_inicio']} a {$d['hora_fin']}.", "/horarios?medico={$idMedico}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/horarios?medico={$idMedico}");
        }
    }

    /** POST /horarios/{id} — modificar bloque */
    public function actualizar(int $id): void
    {
        $bloque = $this->horarios->obtener($id);
        $idMedico = (int) ($bloque['id_medico'] ?? 0);
        try {
            $bloque ?? throw new ErrorNegocio('El horario no existe.');
            $d = $this->validar();
            if ($this->horarios->haySolape($idMedico, $d['dia_semana'], $d['hora_inicio'], $d['hora_fin'], $id)) {
                throw new ErrorNegocio('El bloque se superpone con otro horario del médico ese día.');
            }
            if (($n = $this->horarios->citasQueQuedanFuera($bloque, $d['hora_inicio'], $d['hora_fin'], $d['dia_semana'])) > 0) {
                throw new ErrorNegocio("El cambio deja fuera {$n} cita(s) pendiente(s). Reprográmelas antes de modificar el horario.");
            }
            BaseDatos::transaccion(fn () => $this->horarios->actualizar($id, $d));
            $this->exito('Horario actualizado.', "/horarios?medico={$idMedico}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/horarios?medico={$idMedico}");
        }
    }

    /** POST /horarios/{id}/estado — activar / desactivar bloque */
    public function cambiarEstado(int $id): void
    {
        $bloque = $this->horarios->obtener($id);
        $idMedico = (int) ($bloque['id_medico'] ?? 0);
        try {
            $bloque ?? throw new ErrorNegocio('El horario no existe.');
            $nuevo = $bloque['estado'] === 'activo' ? 'inactivo' : 'activo';
            if ($nuevo === 'inactivo' && ($n = $this->horarios->citasFuturasEnBloque($bloque)) > 0) {
                throw new ErrorNegocio("No se puede desactivar: hay {$n} cita(s) pendiente(s) en este horario. Reprográmelas primero.");
            }
            if ($nuevo === 'activo' && $this->horarios->haySolape($idMedico, (int) $bloque['dia_semana'], $bloque['hora_inicio'], $bloque['hora_fin'], $id)) {
                throw new ErrorNegocio('No se puede activar: se superpone con otro horario activo.');
            }
            BaseDatos::transaccion(fn () => $this->horarios->cambiarEstado($id, $nuevo));
            $this->exito($nuevo === 'activo' ? 'Horario activado.' : 'Horario desactivado.', "/horarios?medico={$idMedico}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/horarios?medico={$idMedico}");
        }
    }

    /** POST /feriados — bloquear una fecha (toda la clínica o un médico) */
    public function agregarFeriado(): void
    {
        $idMedicoVolver = Peticion::entero('volver_medico');
        try {
            $d = Validador::validar($_POST, [
                'fecha'       => ['Fecha', 'requerido|fecha|no_pasada'],
                'descripcion' => ['Descripción', 'requerido|texto:3:120'],
                'id_medico'   => ['Médico', 'opcional|entero:1'],
            ]);
            if (($n = $this->horarios->citasEnFecha($d['fecha'], $d['id_medico'])) > 0) {
                throw new ErrorNegocio("Hay {$n} cita(s) pendiente(s) ese día. Reprográmelas o cancélelas antes de bloquear la fecha.");
            }
            BaseDatos::transaccion(fn () => $this->horarios->agregarDiaNoLaborable($d['fecha'], $d['id_medico'], $d['descripcion']));
            $this->exito('Fecha bloqueada: ' . fecha_corta($d['fecha']) . '.', '/horarios' . ($idMedicoVolver ? "?medico={$idMedicoVolver}" : ''));
        } catch (Throwable $e) {
            $this->volverConError($e, '/horarios' . ($idMedicoVolver ? "?medico={$idMedicoVolver}" : ''));
        }
    }

    /** POST /feriados/{id}/quitar */
    public function quitarFeriado(int $id): void
    {
        $idMedicoVolver = Peticion::entero('volver_medico');
        BaseDatos::transaccion(fn () => $this->horarios->quitarDiaNoLaborable($id));
        $this->exito('La fecha volvió a estar disponible.', '/horarios' . ($idMedicoVolver ? "?medico={$idMedicoVolver}" : ''));
    }

    /** Validación del bloque horario (RF05). */
    private function validar(): array
    {
        $d = Validador::validar($_POST, [
            'dia_semana'    => ['Día', 'requerido|entero:1:7'],
            'hora_inicio'   => ['Hora inicial', 'requerido|hora'],
            'hora_fin'      => ['Hora final', 'requerido|hora'],
            'duracion_cita' => ['Duración de cada cita', 'requerido|en:15,20,30,45,60'],
        ]);
        $d['duracion_cita'] = (int) $d['duracion_cita'];

        if ($d['hora_fin'] <= $d['hora_inicio']) {
            throw new ErrorNegocio('Revise los datos.', ['hora_fin' => 'La hora final debe ser posterior a la hora inicial.']);
        }
        if ($d['hora_inicio'] < '06:00' || $d['hora_fin'] > '22:00') {
            throw new ErrorNegocio('Revise los datos.', ['hora_inicio' => 'La clínica atiende entre 06:00 y 22:00.']);
        }
        $minutos = (strtotime($d['hora_fin']) - strtotime($d['hora_inicio'])) / 60;
        if ($minutos < $d['duracion_cita']) {
            throw new ErrorNegocio('Revise los datos.', ['duracion_cita' => 'El bloque es más corto que la duración de una cita.']);
        }
        return $d;
    }
}
