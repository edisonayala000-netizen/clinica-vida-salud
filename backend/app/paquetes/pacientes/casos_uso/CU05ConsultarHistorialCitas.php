<?php
namespace App\Paquetes\Pacientes\CasosUso;

use App\Nucleo\Controlador;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use Throwable;

/**
 * CU05 · Consultar historial de citas del paciente
 * Requisito: RF12 (y seguimiento de citas del punto 3.4)
 * Actores: Recepcionista, Administrador (cualquier paciente) y Paciente (solo el suyo)
 *
 * Muestra fecha y hora, médico, especialidad, estado y fecha de registro.
 * Incluye citas pasadas y canceladas sin perder la trazabilidad.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: pacientes
 * Actores.........: Recepcionista, Administrador, Paciente
 * Requisitos......: RF12 · RN10
 * Base de datos...: vista v_cita_detalle (cita + paciente + médico + especialidad)
 * Rutas...........: GET /pacientes/{id}/historial (pacientes.historial) · GET /mis-citas (portal.mis_citas)
 * Vistas..........: frontend/vistas/pacientes/historial.php, frontend/vistas/pacientes/mis_citas.php
 * Usa.............: PacienteModelo
 * Precondición....: Usuario con sesión y permiso «pacientes.historial» (personal) o «portal.mis_citas»
 *                   (paciente).
 * Postcondición...: Se muestra la información sin modificarla; las citas canceladas siguen visibles
 *                   (trazabilidad).
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/pacientes/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU05ConsultarHistorialCitas extends Controlador
{
    private PacienteModelo $pacientes;

    public function __construct()
    {
        $this->pacientes = new PacienteModelo();
    }

    /** GET /pacientes/{id}/historial — Prueba 7 */
    public function historialPaciente(int $id): void
    {
        $paciente = $this->pacientes->obtener($id) ?? $this->noEncontrado('El paciente');
        $f = $this->filtros();
        $this->vista('pacientes/historial', [
            'titulo'   => 'Historial de citas',
            'paciente' => $paciente,
            'citas'    => $this->pacientes->historial($id, $f['estado'], $f['desde'], $f['hasta']),
            'resumen'  => $this->pacientes->resumenEstados($id),
            'filtros'  => $f,
            'esPortal' => false,
        ]);
    }

    /** GET /mis-citas — seguimiento del paciente: próximas, pasadas, canceladas e inasistencias */
    public function misCitas(): void
    {
        $id = (int) ($this->usuario()['id_paciente'] ?? 0);
        $paciente = $this->pacientes->obtener($id) ?? $this->noEncontrado('El paciente');
        $pestana = Peticion::entrada('ver', 'proximas');
        $todas = $this->pacientes->historial($id);
        $ahora = date('Y-m-d H:i:s');

        $grupos = ['proximas' => [], 'pasadas' => [], 'canceladas' => [], 'inasistencias' => []];
        foreach ($todas as $c) {
            $clave = match ($c['estado']) {
                'programada', 'confirmada' => ($c['fecha'] . ' ' . $c['hora_inicio']) >= $ahora ? 'proximas' : 'pasadas',
                'atendida'     => 'pasadas',
                'cancelada'    => 'canceladas',
                'inasistencia' => 'inasistencias',
            };
            $grupos[$clave][] = $c;
        }
        $grupos['proximas'] = array_reverse($grupos['proximas']); // la más cercana primero

        $this->vista('pacientes/mis_citas', [
            'titulo'   => 'Mis citas',
            'paciente' => $paciente,
            'grupos'   => $grupos,
            'pestana'  => array_key_exists($pestana, $grupos) ? $pestana : 'proximas',
        ]);
    }

    private function filtros(): array
    {
        try {
            return Validador::validar($_GET, [
                'estado' => ['Estado', 'opcional|en:programada,confirmada,atendida,cancelada,inasistencia'],
                'desde'  => ['Desde', 'opcional|fecha'],
                'hasta'  => ['Hasta', 'opcional|fecha'],
            ]);
        } catch (Throwable) {
            return ['estado' => null, 'desde' => null, 'hasta' => null];
        }
    }
}
