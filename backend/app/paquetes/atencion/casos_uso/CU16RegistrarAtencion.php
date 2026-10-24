<?php
namespace App\Paquetes\Atencion\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Validador;
use App\Paquetes\Atencion\Modelos\AtencionModelo;
use App\Paquetes\Atencion\Modelos\MedicamentoModelo;
use App\Paquetes\Atencion\Modelos\RecetaModelo;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\EstadoCita;
use App\Paquetes\Notificaciones\Servicios\Notificador;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use Throwable;

/**
 * CU16 · Registrar atención médica (diagnóstico, signos vitales y receta)
 * Requisito: RF10 · RF12 · Actor: Médico
 *
 * Flujo principal:
 *  1. El médico abre su cita (desde la agenda o el detalle) y elige «Atender».
 *  2. El sistema muestra los datos del paciente y sus alergias (si las comparte).
 *  3. El médico escribe el diagnóstico (obligatorio), el tratamiento y las observaciones.
 *  4. Opcionalmente registra los signos vitales (el IMC se calcula, no se guarda).
 *  5. Opcionalmente agrega los medicamentos de la receta con su posología y si se recuerdan.
 *  6. El sistema guarda todo en una transacción: la cita pasa a «atendida», la atención, los
 *     signos y la receta; el trigger fn_tg_programar_tomas programa un recordatorio por toma.
 *  7. Si hay receta y el paciente tiene correo, se la envía (Brevo).
 * Flujos alternativos:
 *  2a. La cita es de otro médico, todavía no es su día o ya tiene atención: no se permite.
 *  6a. Datos inválidos (signos fuera de rango, medicamento repetido…): se muestran los errores.
 *  7a. El paciente no tiene correo: la receta se imprime desde el sistema.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: atencion
 * Actores.........: Médico (y quien tenga atencion.ver para consultar la receta)
 * Requisitos......: RF10 · RF12 · RN12
 * Base de datos...: atencion (trigger fn_tg_validar_atencion), signo_vital (CHECK de rangos), receta,
 *                   receta_detalle (trigger fn_tg_programar_tomas), medicamento, cita, notificacion
 * Rutas...........: GET/POST /citas/{id}/atencion (atencion.registrar) · GET /recetas/{id} y POST
 *                   /recetas/{id}/enviar (atencion.ver o portal.recetas, solo las propias)
 * Vistas..........: frontend/vistas/atencion/formulario.php, frontend/vistas/atencion/receta.php,
 *                   frontend/vistas/correos/receta.php
 * Usa.............: AtencionModelo, RecetaModelo, MedicamentoModelo, CitaModelo, EstadoCita, Notificador
 * Precondición....: La cita es del médico, es de hoy o pasada y no tiene atención registrada.
 * Postcondición...: La cita queda atendida con su registro clínico; la receta queda enviada o lista para imprimir.
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU16RegistrarAtencion extends Controlador
{
    public const VIAS = ['oral', 'sublingual', 'tópica', 'inhalatoria', 'intramuscular', 'intravenosa',
                         'subcutánea', 'oftálmica', 'ótica', 'nasal', 'rectal', 'vaginal'];

    /** Signos vitales: campo => [etiqueta, mínimo, máximo, ¿entero?] (mismos rangos que los CHECK de la BD). */
    private const SIGNOS = [
        'peso_kg'             => ['Peso (kg)', 0.5, 400, false],
        'talla_cm'            => ['Talla (cm)', 30, 250, false],
        'presion_sistolica'   => ['Presión sistólica', 50, 260, true],
        'presion_diastolica'  => ['Presión diastólica', 30, 160, true],
        'temperatura_c'       => ['Temperatura (°C)', 30, 45, false],
        'frecuencia_cardiaca' => ['Frecuencia cardíaca', 20, 250, true],
        'saturacion_o2'       => ['Saturación de O₂ (%)', 50, 100, true],
    ];

    private CitaModelo $citas;
    private AtencionModelo $atenciones;
    private RecetaModelo $recetas;

    public function __construct()
    {
        $this->citas = new CitaModelo();
        $this->atenciones = new AtencionModelo();
        $this->recetas = new RecetaModelo();
    }

    /** GET /citas/{id}/atencion — pasos 1 y 2 */
    public function formulario(int $id): void
    {
        try {
            $cita = $this->citaParaAtender($id);
        } catch (ErrorNegocio $e) {
            \App\Nucleo\Sesion::flash('error', $e->getMessage());
            $this->redirigir("/citas/{$id}");
        }
        $this->vista('atencion/formulario', [
            'titulo'       => 'Atender cita ' . $cita['codigo'],
            'cita'         => $cita,
            'paciente'     => (new PacienteModelo())->obtener((int) $cita['id_paciente']),
            'medicamentos' => (new MedicamentoModelo())->activos(),
            'vias'         => self::VIAS,
            'scripts'      => ['atencion.js'],
        ]);
    }

    /** POST /citas/{id}/atencion — pasos 3 a 7 */
    public function guardar(int $id): void
    {
        try {
            $cita = $this->citaParaAtender($id);
            $d = Validador::validar($_POST, [
                'diagnostico'   => ['Diagnóstico', 'requerido|texto:3:500'],
                'tratamiento'   => ['Tratamiento', 'opcional|texto:3:1000'],
                'observaciones' => ['Observaciones', 'opcional|texto:3:1000'],
                'indicaciones'  => ['Indicaciones generales', 'opcional|texto:3:500'],
            ]);
            $errores = [];
            $signos = $this->validarSignos($errores);
            $receta = $this->validarReceta($cita, $errores);
            if ($errores) {
                throw new ErrorNegocio('Revise los datos marcados en el formulario.', $errores);
            }

            $idReceta = BaseDatos::transaccion(function () use ($cita, $d, $signos, $receta) {
                if ($cita['estado'] !== 'atendida') {
                    EstadoCita::validarCambio($cita, 'atendida');
                    $this->citas->cambiarEstado((int) $cita['id_cita'], 'atendida');
                }
                $idAtencion = $this->atenciones->crear((int) $cita['id_cita'], $d, Autenticacion::id());
                if ($signos !== null) {
                    $this->atenciones->agregarSignos($idAtencion, $signos);
                }
                if (!$receta) {
                    return null;
                }
                $r = $this->recetas->crear($idAtencion, $d['indicaciones']);
                foreach ($receta as $detalle) {
                    $this->recetas->agregarDetalle((int) $r['id_receta'], $detalle);
                }
                return (int) $r['id_receta'];
            }, 'Consulta realizada');

            $mensaje = 'Atención registrada y cita marcada como atendida.';
            if ($idReceta !== null) {
                $mensaje .= (new Notificador())->enviarReceta($idReceta)
                    ? ' La receta se envió al correo del paciente.'
                    : ' El paciente no tiene correo (o no se pudo enviar): imprima la receta.';
            }
            $this->exito($mensaje, "/citas/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/citas/{$id}/atencion");
        }
    }

    /** GET /recetas/{id} — ver e imprimir la receta */
    public function verReceta(int $id): void
    {
        $filas = $this->recetaPermitida($id);
        $this->vista('atencion/receta', [
            'titulo'   => 'Receta ' . $filas[0]['codigo_receta'],
            'receta'   => $filas[0],
            'detalles' => $filas,
            'paciente' => (new PacienteModelo())->obtener((int) $filas[0]['id_paciente']),
        ]);
    }

    /** POST /recetas/{id}/enviar — reenviar la receta por correo */
    public function reenviar(int $id): void
    {
        try {
            $filas = $this->recetaPermitida($id);
            if (!$filas[0]['correo_paciente']) {
                throw new ErrorNegocio('El paciente no tiene un correo registrado.');
            }
            (new Notificador())->enviarReceta($id)
                ? $this->exito('Receta enviada a ' . $filas[0]['correo_paciente'] . '.', "/recetas/{$id}")
                : throw new ErrorNegocio('No se pudo enviar el correo. Intente más tarde.');
        } catch (Throwable $e) {
            $this->volverConError($e, "/recetas/{$id}");
        }
    }

    /** Paso 2a: la cita debe ser del médico, poder atenderse y no tener atención. */
    private function citaParaAtender(int $id): array
    {
        $cita = $this->citas->obtener($id) ?? throw new ErrorNegocio('La cita no existe.');
        $u = $this->usuario();
        if ($u['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $u['id_medico']) {
            throw new ErrorNegocio('Solo puede registrar la atención de sus propias citas.');
        }
        if ($this->atenciones->deCita($id) !== null) {
            throw new ErrorNegocio('Esta cita ya tiene su atención registrada.');
        }
        if ($cita['estado'] !== 'atendida') {
            EstadoCita::validarCambio($cita, 'atendida');   // no es su día, está cancelada, etc.
        }
        return $cita;
    }

    /** @return array<string,mixed>|null null si no se escribió ningún signo vital */
    private function validarSignos(array &$errores): ?array
    {
        $signos = [];
        foreach (self::SIGNOS as $campo => [$etiqueta, $min, $max, $entero]) {
            $v = str_replace(',', '.', trim((string) ($_POST[$campo] ?? '')));
            if ($v === '') {
                $signos[$campo] = null;
                continue;
            }
            if (!is_numeric($v) || ($entero && (string) (int) $v !== $v) || $v < $min || $v > $max) {
                $errores[$campo] = "$etiqueta debe ser un número entre $min y $max" . ($entero ? ' (sin decimales).' : '.');
                continue;
            }
            $signos[$campo] = $entero ? (int) $v : round((float) $v, 2);
        }
        if (($signos['presion_sistolica'] ?? null) === null xor ($signos['presion_diastolica'] ?? null) === null) {
            $errores['presion_sistolica'] = 'Escriba la presión completa: sistólica y diastólica.';
        } elseif (($signos['presion_sistolica'] ?? 0) && $signos['presion_sistolica'] <= $signos['presion_diastolica']) {
            $errores['presion_sistolica'] = 'La presión sistólica debe ser mayor que la diastólica.';
        }
        return array_filter($signos, static fn ($v) => $v !== null) ? $signos : null;
    }

    /** @return array<int,array<string,mixed>> medicamentos válidos de la receta (las filas vacías se ignoran) */
    private function validarReceta(array $cita, array &$errores): array
    {
        $activos = array_column((new MedicamentoModelo())->activos(), null, 'id_medicamento');
        $receta = [];
        $usados = [];
        foreach ((array) ($_POST['receta'] ?? []) as $i => $fila) {
            $fila = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, (array) $fila);
            if (($fila['id_medicamento'] ?? '') === '' && ($fila['dosis'] ?? '') === '') {
                continue;                                   // fila vacía
            }
            $n = count($receta) + 1;
            try {
                $v = Validador::validar($fila, [
                    'id_medicamento'    => ["Medicamento {$n}", 'requerido|entero:1'],
                    'dosis'             => ["Dosis del medicamento {$n}", 'requerido|texto:1:40'],
                    'via'               => ["Vía del medicamento {$n}", 'requerido|en:' . implode(',', self::VIAS)],
                    'frecuencia_horas'  => ["Frecuencia del medicamento {$n}", 'requerido|entero:1:168'],
                    'duracion_dias'     => ["Duración del medicamento {$n}", 'requerido|entero:1:365'],
                    'fecha_inicio'      => ["Inicio del medicamento {$n}", 'requerido|fecha'],
                    'hora_primera_toma' => ["Primera toma del medicamento {$n}", 'requerido|hora'],
                    'indicaciones'      => ["Indicaciones del medicamento {$n}", 'opcional|texto:2:250'],
                    'recordar'          => ['Recordar', 'booleano'],
                ]);
            } catch (ErrorNegocio $e) {
                $errores["receta_{$i}"] = implode(' ', $e->errores());
                continue;
            }
            if (!isset($activos[$v['id_medicamento']])) {
                $errores["receta_{$i}"] = "El medicamento {$n} no está en el catálogo o está inactivo.";
            } elseif (in_array($v['id_medicamento'], $usados, true)) {
                $errores["receta_{$i}"] = "El medicamento {$n} ya está en la receta: indíquelo una sola vez.";
            } elseif ($v['fecha_inicio'] < $cita['fecha']) {
                $errores["receta_{$i}"] = "El medicamento {$n} no puede empezar antes de la consulta.";
            }
            $usados[] = $v['id_medicamento'];
            $receta[] = $v;
        }
        return $receta;
    }

    /** Receta que el usuario puede ver: el paciente solo la suya; los demás con atencion.ver. */
    private function recetaPermitida(int $id): array
    {
        $filas = $this->recetas->obtener($id);
        if (!$filas) {
            $this->noEncontrado('La receta');
        }
        $u = $this->usuario();
        if (!($u['rol'] === 'paciente' && (int) $filas[0]['id_paciente'] === (int) $u['id_paciente'])) {
            Autenticacion::exigirPermiso('atencion.ver');
        }
        return $filas;
    }
}
