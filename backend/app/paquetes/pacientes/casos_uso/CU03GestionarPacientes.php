<?php
namespace App\Paquetes\Pacientes\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use App\Paquetes\Pacientes\ReglasPaciente;
use Throwable;

/**
 * CU03 · Gestionar pacientes
 * Requisito: RF02 · Actores: Recepcionista, Administrador
 *
 * Operaciones: registrar, listar, buscar por CI/nombre/apellido,
 * modificar, consultar citas anteriores y desactivar sin eliminar historial.
 * Regla RF02 / RN01: no se registran dos pacientes con el mismo CI
 * (lo valida el servidor y además la restricción UNIQUE de la BD).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: pacientes
 * Actores.........: Recepcionista, Administrador
 * Requisitos......: RF02 · RN01 · RN11
 * Base de datos...: persona (UNIQUE uq_persona_ci) + paciente (subtipo), vista v_paciente, contacto_emergencia
 * Rutas...........: GET /pacientes (pacientes.ver) · GET/POST /pacientes/nuevo · /pacientes
 *                   (pacientes.gestionar) · GET /pacientes/{id} (pacientes.ver) · GET/POST
 *                   /pacientes/{id}/editar · /pacientes/{id} (pacientes.gestionar) · POST
 *                   /pacientes/{id}/estado (pacientes.gestionar) · GET /api/pacientes?q= (citas.programar)
 * Vistas..........: frontend/vistas/pacientes/lista.php, frontend/vistas/pacientes/formulario.php,
 *                   frontend/vistas/pacientes/ficha.php
 * Usa.............: PacienteModelo, ReglasPaciente
 * Precondición....: La recepcionista inició sesión y tiene los permisos «pacientes.ver» y
 *                   «pacientes.gestionar».
 * Postcondición...: El paciente queda registrado o actualizado. Un paciente inactivo no recibe nuevas citas y
 *                   nunca se elimina físicamente.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/pacientes/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU03GestionarPacientes extends Controlador
{
    private PacienteModelo $pacientes;

    public function __construct()
    {
        $this->pacientes = new PacienteModelo();
    }

    /** GET /pacientes */
    public function listar(): void
    {
        $buscar = Peticion::entrada('q');
        $estado = Peticion::entrada('estado');
        $pagina = max(1, Peticion::entero('pagina') ?? 1);
        $r = $this->pacientes->buscar($buscar, $estado, $pagina);
        $this->vista('pacientes/lista', [
            'titulo'    => 'Pacientes',
            'pacientes' => $r['filas'],
            'total'     => $r['total'],
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($r['total'] / PacienteModelo::POR_PAGINA)),
            'filtros'   => compact('buscar', 'estado'),
            'puedeGestionar' => $this->puede('pacientes.gestionar'),
        ]);
    }

    /** GET /pacientes/{id} — ficha del paciente */
    public function ver(int $id): void
    {
        $p = $this->pacientes->obtener($id) ?? $this->noEncontrado('El paciente');
        $this->vista('pacientes/ficha', [
            'titulo'    => $p['nombres'] . ' ' . $p['apellidos'],
            'paciente'  => $p,
            'contactos' => $this->pacientes->contactos($id),
            'resumen'   => $this->pacientes->resumenEstados($id),
            'recientes' => array_slice($this->pacientes->historial($id), 0, 5),
            'atenciones' => $this->puede('atencion.ver')
                ? (new \App\Paquetes\Atencion\Modelos\AtencionModelo())->delPaciente($id) : null,
            'puedeGestionar' => $this->puede('pacientes.gestionar'),
        ]);
    }

    /** GET /pacientes/nuevo */
    public function nuevo(): void
    {
        $this->vista('pacientes/formulario', ['titulo' => 'Registrar paciente', 'paciente' => null, 'contacto' => null]);
    }

    /** GET /pacientes/{id}/editar */
    public function editar(int $id): void
    {
        $p = $this->pacientes->obtener($id) ?? $this->noEncontrado('El paciente');
        $this->vista('pacientes/formulario', [
            'titulo'   => 'Modificar paciente',
            'paciente' => $p,
            'contacto' => $this->pacientes->contactos($id)[0] ?? null,
        ]);
    }

    /** POST /pacientes — Prueba 1 (registrar) y Prueba 2 (CI duplicado) */
    public function crear(): void
    {
        try {
            $d = ReglasPaciente::datosPersonales($_POST, false) + ReglasPaciente::informacionMedica($_POST);
            $contacto = ReglasPaciente::contactoEmergencia($_POST);
            if ($d['ci']) {
                ReglasPaciente::exigirCiUnico($this->pacientes->existeCi($d['ci']));
            }
            $id = BaseDatos::transaccion(function () use ($d, $contacto) {
                $id = $this->pacientes->crear($d);
                $this->pacientes->guardarContactoPrincipal($id, $contacto);
                return $id;
            });
            $p = $this->pacientes->obtener($id);
            $this->exito("Paciente {$p['codigo']} · {$p['nombres']} {$p['apellidos']} registrado.", "/pacientes/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, '/pacientes/nuevo');
        }
    }

    /** POST /pacientes/{id} */
    public function actualizar(int $id): void
    {
        try {
            $this->pacientes->obtener($id) ?? throw new ErrorNegocio('El paciente no existe.');
            $d = ReglasPaciente::datosPersonales($_POST, false) + ReglasPaciente::informacionMedica($_POST);
            $contacto = ReglasPaciente::contactoEmergencia($_POST);
            if ($d['ci']) {
                ReglasPaciente::exigirCiUnico($this->pacientes->existeCi($d['ci'], $id));
            }
            BaseDatos::transaccion(function () use ($id, $d, $contacto) {
                $this->pacientes->actualizar($id, $d);
                $this->pacientes->guardarContactoPrincipal($id, $contacto);
            });
            $this->exito('Datos del paciente actualizados.', "/pacientes/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/pacientes/{$id}/editar");
        }
    }

    /** POST /pacientes/{id}/estado — desactivar sin eliminar historial (RN11) */
    public function cambiarEstado(int $id): void
    {
        try {
            $p = $this->pacientes->obtener($id) ?? throw new ErrorNegocio('El paciente no existe.');
            $nuevo = $p['estado'] === 'activo' ? 'inactivo' : 'activo';
            $canceladas = BaseDatos::transaccion(function () use ($id, $nuevo) {
                $n = $nuevo === 'inactivo'
                    ? $this->pacientes->cancelarCitasPendientes($id, 'Paciente desactivado por la clínica') : 0;
                $this->pacientes->cambiarEstado($id, $nuevo);
                return $n;
            }, 'Paciente desactivado');
            $this->exito(
                $nuevo === 'activo' ? 'Paciente reactivado.'
                    : 'Paciente desactivado. Su historial se conserva.' . ($canceladas ? " Se cancelaron {$canceladas} cita(s) pendiente(s)." : ''),
                "/pacientes/{$id}"
            );
        } catch (Throwable $e) {
            $this->volverConError($e, "/pacientes/{$id}");
        }
    }

    /** GET /api/pacientes?q= — búsqueda rápida (JSON) para programar citas */
    public function buscarJson(): void
    {
        $q = (string) Peticion::entrada('q', '');
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'pacientes' => []]);
        }
        $this->json(['ok' => true, 'pacientes' => array_map(static fn ($p) => [
            'id'     => (int) $p['id_paciente'],
            'codigo' => $p['codigo'],
            'ci'     => $p['ci'],
            'nombre' => $p['nombres'] . ' ' . $p['apellidos'],
            'detalle' => trim(($p['ci'] ? 'CI ' . $p['ci'] . ' · ' : '') . edad($p['fecha_nacimiento']) . ' · ' . $p['telefono']),
        ], $this->pacientes->busquedaRapida($q))]);
    }
}
