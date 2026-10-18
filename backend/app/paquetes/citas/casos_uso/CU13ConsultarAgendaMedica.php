<?php
namespace App\Paquetes\Citas\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\Controlador;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\Disponibilidad;
use App\Paquetes\Citas\Servicios\EstadoCita;
use App\Paquetes\Medicos\Modelos\EspecialidadModelo;
use App\Paquetes\Medicos\Modelos\HorarioModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;
use Throwable;

/**
 * CU13 · Consultar agenda médica (incluye consultar citas por fecha y estado)
 * Requisito: RF11 · Actores: Médico (su agenda), Recepcionista, Administrador
 *
 * La agenda de un médico para una fecha muestra: hora, código de cita,
 * nombre del paciente, especialidad, estado y motivo breve (si el usuario
 * tiene permiso para verlo). Filtros: médico, fecha y estado (Prueba 8).
 * La agenda semanal muestra de lunes a domingo las citas de cada día y marca los
 * días no laborables y los días que el médico no atiende.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: citas
 * Actores.........: Médico, Recepcionista, Administrador
 * Requisitos......: RF11 · RF12
 * Base de datos...: vista v_cita_detalle, cita_historial, horario_atencion, dia_no_laborable
 * Rutas...........: GET /agenda?medico=&fecha=&estado= (agenda.ver | agenda.propia) · GET
 *                   /agenda/semana?medico=&fecha= (agenda.ver | agenda.propia) · GET
 *                   /citas?desde=&hasta=&medico=&estado= (citas.ver) · GET /citas/{id} (citas.ver |
 *                   agenda.propia | portal.mis_citas)
 * Vistas..........: frontend/vistas/citas/agenda.php, frontend/vistas/citas/agenda_semana.php,
 *                   frontend/vistas/citas/listado.php, frontend/vistas/citas/detalle.php
 * Usa.............: CitaModelo, Disponibilidad, EstadoCita, EspecialidadModelo, MedicoModelo, HorarioModelo
 * Precondición....: Usuario con «agenda.ver» (todos los médicos) o «agenda.propia» (médico).
 * Postcondición...: Se muestra la información; no se modifican datos.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/citas/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU13ConsultarAgendaMedica extends Controlador
{
    private CitaModelo $citas;
    private MedicoModelo $medicos;

    public function __construct()
    {
        $this->citas = new CitaModelo();
        $this->medicos = new MedicoModelo();
    }

    /** GET /agenda?medico=&fecha=&estado= */
    public function agenda(): void
    {
        $u = $this->usuario();
        $soloPropia = !$this->puede('agenda.ver');           // el médico solo ve su agenda
        $f = $this->filtros(['medico' => 'opcional|entero:1', 'fecha' => 'opcional|fecha',
                              'estado' => 'opcional|en:programada,confirmada,atendida,cancelada,inasistencia']);
        $fecha = $f['fecha'] ?? date('Y-m-d');
        $lista = $soloPropia ? [] : $this->medicos->listaSimple();
        $idMedico = $soloPropia ? (int) $u['id_medico'] : ($f['medico'] ?? (int) ($lista[0]['id_medico'] ?? 0));
        $medico = $idMedico ? $this->medicos->obtener($idMedico) : null;

        $citas = $medico ? $this->citas->agenda($idMedico, $fecha, $f['estado']) : [];
        // Intervalos libres del día (para ver los huecos de la agenda).
        $libres = [];
        if ($medico && !$f['estado'] && $fecha >= date('Y-m-d')) {
            $libres = array_values(array_filter(
                (new Disponibilidad())->delDia($idMedico, $fecha)['intervalos'],
                static fn ($i) => $i['libre']
            ));
        }

        $this->vista('citas/agenda', [
            'titulo'      => $soloPropia ? 'Mi agenda' : 'Agenda médica',
            'medicos'     => $lista,
            'medico'      => $medico,
            'fecha'       => $fecha,
            'estado'      => $f['estado'],
            'citas'       => $citas,
            'libres'      => $libres,
            'soloPropia'  => $soloPropia,
            'verMotivo'   => $this->puede(['citas.ver', 'agenda.propia']),
            'puedeAtender'=> $this->puede('citas.atender'),
            'puedeConfirmar' => $this->puede('citas.confirmar'),
            'puedeProgramar' => $this->puede('citas.programar'),
        ]);
    }

    /** GET /agenda/semana?medico=&fecha= — agenda de lunes a domingo de la semana que contiene la fecha */
    public function agendaSemanal(): void
    {
        $u = $this->usuario();
        $soloPropia = !$this->puede('agenda.ver');           // el médico solo ve su agenda
        $f = $this->filtros(['medico' => 'opcional|entero:1', 'fecha' => 'opcional|fecha']);
        $t = strtotime($f['fecha'] ?? date('Y-m-d'));
        $lunes = date('Y-m-d', strtotime('-' . ((int) date('N', $t) - 1) . ' days', $t));
        $domingo = date('Y-m-d', strtotime($lunes . ' +6 days'));
        $lista = $soloPropia ? [] : $this->medicos->listaSimple();
        $idMedico = $soloPropia ? (int) $u['id_medico'] : ($f['medico'] ?? (int) ($lista[0]['id_medico'] ?? 0));
        $medico = $idMedico ? $this->medicos->obtener($idMedico) : null;

        $dias = [];
        if ($medico) {
            $horarios = new HorarioModelo();
            $bloqueadas = $horarios->fechasBloqueadas($idMedico, $lunes, $domingo);
            $atiende = $horarios->diasQueAtiende($idMedico);
            for ($i = 0; $i < 7; $i++) {
                $fecha = date('Y-m-d', strtotime($lunes . " +{$i} days"));
                $dias[$fecha] = ['fecha' => $fecha, 'citas' => [], 'no_laborable' => $bloqueadas[$fecha] ?? null,
                                 'atiende' => isset($atiende[$i + 1])];
            }
            foreach ($this->citas->agendaSemana($idMedico, $lunes) as $c) {
                $dias[$c['fecha']]['citas'][] = $c;
            }
        }

        $this->vista('citas/agenda_semana', [
            'titulo'     => $soloPropia ? 'Mi agenda semanal' : 'Agenda semanal',
            'medicos'    => $lista,
            'medico'     => $medico,
            'lunes'      => $lunes,
            'domingo'    => $domingo,
            'dias'       => $dias,
            'soloPropia' => $soloPropia,
        ]);
    }

    /** GET /citas — citas por fecha, médico, especialidad y estado */
    public function listado(): void
    {
        $f = $this->filtros([
            'desde' => 'opcional|fecha', 'hasta' => 'opcional|fecha', 'medico' => 'opcional|entero:1',
            'especialidad' => 'opcional|entero:1', 'q' => 'opcional|texto:1:60',
            'estado' => 'opcional|en:programada,confirmada,atendida,cancelada,inasistencia',
        ]);
        if (!isset($_GET['desde']) && !isset($_GET['hasta'])) {
            $f['desde'] = $f['hasta'] = date('Y-m-d');   // por defecto: hoy
        }
        $pagina = max(1, Peticion::entero('pagina') ?? 1);
        $r = $this->citas->listar($f, $pagina);
        $this->vista('citas/listado', [
            'titulo'   => 'Citas',
            'citas'    => $r['filas'],
            'total'    => $r['total'],
            'resumen'  => $r['resumen'],
            'pagina'   => $pagina,
            'paginas'  => max(1, (int) ceil($r['total'] / CitaModelo::POR_PAGINA)),
            'filtros'  => $f,
            'medicos'  => $this->medicos->listaSimple(),
            'especialidades' => (new EspecialidadModelo())->listar(),
        ]);
    }

    /** GET /citas/{id} — detalle con historial de cambios */
    public function ver(int $id): void
    {
        $cita = $this->citas->obtener($id) ?? $this->noEncontrado('La cita');
        $u = $this->usuario();
        // Un médico solo ve sus citas; un paciente solo las suyas.
        if (($u['rol'] === 'medico' && (int) $cita['id_medico'] !== (int) $u['id_medico'] && !$this->puede('citas.ver'))
            || ($u['rol'] === 'paciente' && (int) $cita['id_paciente'] !== (int) $u['id_paciente'])) {
            Autenticacion::exigirPermiso('citas.ver');
        }
        $this->vista('citas/detalle', [
            'titulo'    => 'Cita ' . $cita['codigo'],
            'cita'      => $cita,
            'historial' => $this->citas->historial($id),
            'acciones'  => EstadoCita::acciones($cita),
            'paciente'  => (new \App\Paquetes\Pacientes\Modelos\PacienteModelo())->obtener((int) $cita['id_paciente']),
        ]);
    }

    private function filtros(array $reglas): array
    {
        $def = [];
        foreach ($reglas as $campo => $regla) {
            $def[$campo] = [$campo, $regla];
        }
        try {
            return Validador::validar($_GET, $def);
        } catch (Throwable) {
            return array_fill_keys(array_keys($reglas), null);
        }
    }
}
