<?php
namespace App\Paquetes\Inicio\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Paquetes\Citas\Modelos\CitaModelo;

/**
 * Panel principal: lo primero que ve cada usuario al iniciar sesión.
 * Muestra información distinta según el rol (RF01: opciones según el rol)
 * y, para el paciente, los recordatorios de sus próximas citas.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: inicio
 * Actores.........: Todos los usuarios con sesión (administrador, recepcionista, médico y paciente)
 * Requisitos......: RF01 (opciones y datos según el rol) · recordatorios del portal del paciente
 * Base de datos...: v_cita_detalle, v_resumen_citas_dia (solo lectura)
 * Rutas...........: GET / (publico: redirige al panel o al login) · GET /panel (autenticado)
 * Vistas..........: frontend/vistas/inicio/panel.php, frontend/vistas/inicio/citas_hoy.php
 * Usa.............: CitaModelo
 * Precondición....: Sesión iniciada.
 * Postcondición...: Se muestra el panel del rol; el paciente ve recordatorios de sus citas de las próximas 48
 *                   horas.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/inicio/
 * Diagramas.......: doc/staruml (Panel principal — clases de diseño)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class PanelPrincipal extends Controlador
{
    /** GET / */
    public function entrada(): void
    {
        $this->redirigir(Autenticacion::hayUsuario() ? '/panel' : '/login');
    }

    /** GET /panel */
    public function mostrar(): void
    {
        $u = $this->usuario();
        $datos = match ($u['rol']) {
            'paciente' => $this->datosPaciente((int) $u['id_paciente']),
            'medico'   => $this->datosMedico((int) $u['id_medico']),
            default    => $this->datosClinica(),
        };
        $this->vista('inicio/panel', ['titulo' => 'Panel principal', 'usuario' => $u] + $datos);
    }

    /** Administrador y recepción: visión general de la clínica. */
    private function datosClinica(): array
    {
        $k = BaseDatos::uno(
            "SELECT count(*) FILTER (WHERE fecha = CURRENT_DATE AND estado <> 'cancelada')                AS citas_hoy,
                    count(*) FILTER (WHERE fecha = CURRENT_DATE AND estado = 'atendida')                 AS atendidas_hoy,
                    count(*) FILTER (WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + 1 AND estado = 'programada') AS por_confirmar,
                    count(*) FILTER (WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)
                                      AND estado = 'atendida')                                             AS atendidas_mes,
                    count(*) FILTER (WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)
                                      AND estado = 'inasistencia')                                         AS inasistencias_mes
               FROM cita"
        );
        $k['pacientes'] = (int) BaseDatos::valor("SELECT count(*) FROM paciente WHERE estado = 'activo'");
        $k['medicos'] = (int) BaseDatos::valor("SELECT count(*) FROM medico WHERE estado = 'activo'");
        $base = (int) $k['atendidas_mes'] + (int) $k['inasistencias_mes'];
        $k['asistencia'] = $base ? round(100 * (int) $k['atendidas_mes'] / $base) : 0;

        $semanas = BaseDatos::todos(
            "SELECT d::DATE AS fecha, COALESCE(r.total - r.canceladas, 0) AS total
               FROM generate_series(CURRENT_DATE - 9, CURRENT_DATE + 4, INTERVAL '1 day') d
               LEFT JOIN v_resumen_citas_dia r ON r.fecha = d::DATE
              ORDER BY d"
        );
        $estadosMes = BaseDatos::todos(
            "SELECT estado, count(*) AS total FROM cita
              WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)
              GROUP BY estado ORDER BY total DESC"
        );
        return [
            'indicadores' => $k,
            'serie'       => $semanas,
            'estadosMes'  => $estadosMes,
            'hoy'         => (new CitaModelo())->deHoy(),
        ];
    }

    private function datosMedico(int $idMedico): array
    {
        $k = BaseDatos::uno(
            "SELECT count(*) FILTER (WHERE fecha = CURRENT_DATE AND estado <> 'cancelada')    AS citas_hoy,
                    count(*) FILTER (WHERE fecha = CURRENT_DATE AND estado = 'atendida')     AS atendidas_hoy,
                    count(*) FILTER (WHERE fecha BETWEEN CURRENT_DATE + 1 AND CURRENT_DATE + 7
                                      AND estado IN ('programada','confirmada'))             AS proxima_semana,
                    count(*) FILTER (WHERE date_trunc('month', fecha) = date_trunc('month', CURRENT_DATE)
                                      AND estado = 'atendida')                               AS atendidas_mes
               FROM cita WHERE id_medico = :m",
            ['m' => $idMedico]
        );
        return ['indicadores' => $k, 'hoy' => (new CitaModelo())->deHoy($idMedico)];
    }

    /** Paciente: recordatorios automáticos de las citas de las próximas 48 horas. */
    private function datosPaciente(int $idPaciente): array
    {
        $p = BaseDatos::uno("SELECT recordatorio_cita FROM paciente WHERE id_paciente = :id", ['id' => $idPaciente]);
        $proximas = BaseDatos::todos(
            "SELECT * FROM v_cita_detalle
              WHERE id_paciente = :id AND estado IN ('programada','confirmada') AND (fecha + hora_inicio) >= localtimestamp
              ORDER BY fecha, hora_inicio LIMIT 5",
            ['id' => $idPaciente]
        );
        $recordatorios = [];
        if ($p && $p['recordatorio_cita'] !== 'ninguno') {
            $limite = date('Y-m-d H:i', strtotime('+48 hours'));
            $recordatorios = array_filter($proximas, static fn ($c) => ($c['fecha'] . ' ' . hora($c['hora_inicio'])) <= $limite);
        }
        $k = BaseDatos::uno(
            "SELECT count(*) FILTER (WHERE estado = 'atendida') AS atendidas,
                    count(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
                    count(*) FILTER (WHERE estado IN ('programada','confirmada') AND fecha >= CURRENT_DATE) AS pendientes
               FROM cita WHERE id_paciente = :id",
            ['id' => $idPaciente]
        );
        return ['indicadores' => $k, 'proximas' => $proximas, 'recordatorios' => $recordatorios];
    }
}
