<?php
namespace App\Paquetes\Reportes\CasosUso;

use App\Nucleo\Controlador;
use App\Nucleo\Peticion;
use App\Nucleo\Respuesta;
use App\Nucleo\Validador;
use App\Paquetes\Reportes\Modelos\ReporteModelo;
use Throwable;

/**
 * CU14 · Generar reportes administrativos
 * Requisito: RF13 · Actor: Administrador
 *
 * Reportes: citas de una fecha/rango, por médico, por especialidad,
 * por estado, pacientes registrados, atendidas/canceladas/inasistencias
 * del período y agenda diaria de cada médico. Todos filtran por fecha o
 * rango y muestran totales. Exportación a CSV (Excel) y a PDF mediante
 * la vista de impresión del navegador ("Guardar como PDF").
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: reportes
 * Actores.........: Administrador
 * Requisitos......: RF13
 * Base de datos...: vistas v_cita_detalle, v_resumen_citas_dia; tablas cita y paciente
 * Rutas...........: GET /reportes?tipo=&desde=&hasta=[&formato=csv] (reportes.ver)
 * Vistas..........: frontend/vistas/reportes/reportes.php
 * Usa.............: ReporteModelo
 * Precondición....: El administrador inició sesión con «reportes.ver».
 * Postcondición...: Se obtiene la información del período seleccionado sin modificar los datos.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/reportes/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU14GenerarReportes extends Controlador
{
    public const TIPOS = [
        'fecha'        => 'Citas programadas por fecha',
        'medico'       => 'Citas por médico',
        'especialidad' => 'Citas por especialidad',
        'estado'       => 'Citas por estado',
        'pacientes'    => 'Pacientes registrados',
        'asistencia'   => 'Atendidas, canceladas e inasistencias',
        'agenda'       => 'Agenda diaria por médico',
    ];

    private ReporteModelo $reportes;

    public function __construct()
    {
        $this->reportes = new ReporteModelo();
    }

    /** GET /reportes?tipo=&desde=&hasta=[&formato=csv] */
    public function generar(): void
    {
        $tipo = Peticion::entrada('tipo', 'fecha');
        $tipo = array_key_exists($tipo, self::TIPOS) ? $tipo : 'fecha';
        try {
            $f = Validador::validar($_GET, [
                'desde'  => ['Desde', 'opcional|fecha'],
                'hasta'  => ['Hasta', 'opcional|fecha'],
                'estado' => ['Estado', 'opcional|en:programada,confirmada,atendida,cancelada,inasistencia'],
            ]);
        } catch (Throwable) {
            $f = ['desde' => null, 'hasta' => null, 'estado' => null];
        }
        // Por defecto: el mes en curso (la agenda diaria usa solo "desde").
        $f['desde'] ??= $tipo === 'agenda' || $tipo === 'fecha' ? date('Y-m-d') : date('Y-m-01');
        $f['hasta'] ??= $tipo === 'fecha' ? $f['desde'] : date('Y-m-t');
        if ($f['hasta'] < $f['desde']) {
            [$f['desde'], $f['hasta']] = [$f['hasta'], $f['desde']];
        }

        $datos = match ($tipo) {
            'fecha'        => ['filas' => $this->reportes->citasDelPeriodo($f['desde'], $f['hasta'], $f['estado'])],
            'medico'       => ['filas' => $this->reportes->citasAgrupadas('medico', $f['desde'], $f['hasta'])],
            'especialidad' => ['filas' => $this->reportes->citasAgrupadas('especialidad', $f['desde'], $f['hasta'])],
            'estado'       => ['filas' => $this->reportes->citasPorEstado($f['desde'], $f['hasta'])],
            'pacientes'    => ['filas' => $this->reportes->pacientesRegistrados($f['desde'], $f['hasta']),
                               'totales' => $this->reportes->totalesPacientes()],
            'asistencia'   => ['filas' => $this->reportes->resumenDiario($f['desde'], $f['hasta'])],
            'agenda'       => ['filas' => $this->reportes->agendaDiaria($f['desde'])],
        };

        if (Peticion::entrada('formato') === 'csv') {
            $this->exportarCsv($tipo, $f, $datos['filas']);
        }

        $this->vista('reportes/reportes', [
            'titulo'  => 'Reportes',
            'tipo'    => $tipo,
            'tipos'   => self::TIPOS,
            'filtros' => $f,
        ] + $datos);
    }

    /** Exportación opcional a CSV (se abre en Excel). */
    private function exportarCsv(string $tipo, array $f, array $filas): never
    {
        [$cab, $mapa] = match ($tipo) {
            'fecha', 'agenda' => [
                ['Código', 'Fecha', 'Hora', 'Paciente', 'CI', 'Médico', 'Especialidad', 'Estado', 'Motivo'],
                fn ($r) => [$r['codigo'], fecha_corta($r['fecha']), hora($r['hora_inicio']) . '-' . hora($r['hora_fin']),
                            $r['paciente'], $r['ci_paciente'], $r['medico'], $r['especialidad'], $r['estado'], $r['motivo']],
            ],
            'medico', 'especialidad' => [
                [$tipo === 'medico' ? 'Médico' : 'Especialidad', 'Total', 'Programadas', 'Confirmadas', 'Atendidas', 'Canceladas', 'Inasistencias'],
                fn ($r) => [$r['grupo'], $r['total'], $r['programadas'], $r['confirmadas'], $r['atendidas'], $r['canceladas'], $r['inasistencias']],
            ],
            'estado' => [['Estado', 'Total'], fn ($r) => [$r['estado'], $r['total']]],
            'pacientes' => [
                ['Código', 'CI', 'Paciente', 'Sexo', 'Teléfono', 'Estado', 'Registrado', 'Citas'],
                fn ($r) => [$r['codigo'], $r['ci'], $r['paciente'], $r['sexo'], $r['telefono'], $r['estado'], fecha_hora($r['creado_en']), $r['citas']],
            ],
            'asistencia' => [
                ['Fecha', 'Total', 'Atendidas', 'Canceladas', 'Inasistencias', 'Pendientes'],
                fn ($r) => [fecha_corta($r['fecha']), $r['total'], $r['atendidas'], $r['canceladas'], $r['inasistencias'],
                            (int) $r['programadas'] + (int) $r['confirmadas']],
            ],
        };
        Respuesta::csv("reporte_{$tipo}_{$f['desde']}_{$f['hasta']}.csv", $cab, array_map($mapa, $filas));
    }
}
