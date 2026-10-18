<?php
namespace App\Paquetes\Medicos\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Medicos\Modelos\EspecialidadModelo;
use App\Paquetes\Medicos\Modelos\HorarioModelo;
use App\Paquetes\Medicos\Modelos\MedicoModelo;
use Throwable;

/**
 * CU07 · Gestionar médicos
 * Requisito: RF03 · Actor: Administrador (la recepcionista solo consulta)
 *
 * Operaciones: registrar, listar, buscar por nombre o especialidad,
 * modificar, consultar su agenda y activar/desactivar.
 * Regla RF03/RN11: un médico inactivo no recibe nuevas citas, pero sus
 * citas históricas se conservan (nunca se elimina un médico).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: medicos
 * Actores.........: Administrador, Recepcionista
 * Requisitos......: RF03 · RN02 · RN04 · RN11
 * Base de datos...: medico, medico_especialidad, vista v_medico_especialidades
 * Rutas...........: GET /medicos (medicos.ver) · GET /medicos/{id} (medicos.ver) · GET/POST /medicos/nuevo ·
 *                   /medicos (medicos.gestionar) · GET/POST /medicos/{id}/editar · /medicos/{id}
 *                   (medicos.gestionar) · POST /medicos/{id}/estado (medicos.gestionar)
 * Vistas..........: frontend/vistas/medicos/medicos_lista.php, frontend/vistas/medicos/medico_form.php,
 *                   frontend/vistas/medicos/medico_ficha.php
 * Usa.............: MedicoModelo, EspecialidadModelo, HorarioModelo
 * Precondición....: El administrador inició sesión con «medicos.gestionar» (la recepcionista solo consulta
 *                   con «medicos.ver»).
 * Postcondición...: El médico queda registrado con sus especialidades; un médico inactivo no recibe nuevas
 *                   citas.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/medicos/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU07GestionarMedicos extends Controlador
{
    private MedicoModelo $medicos;
    private EspecialidadModelo $especialidades;

    public function __construct()
    {
        $this->medicos = new MedicoModelo();
        $this->especialidades = new EspecialidadModelo();
    }

    /** GET /medicos */
    public function listar(): void
    {
        $f = [
            'q'            => Peticion::entrada('q'),
            'especialidad' => Peticion::entero('especialidad'),
            'estado'       => Peticion::entrada('estado'),
        ];
        $this->vista('medicos/medicos_lista', [
            'titulo'         => 'Médicos',
            'medicos'        => $this->medicos->buscar($f['q'], $f['especialidad'], $f['estado']),
            'especialidades' => $this->especialidades->listar(),
            'filtros'        => $f,
            'puedeGestionar' => $this->puede('medicos.gestionar'),
        ]);
    }

    /** GET /medicos/{id} — ficha con horarios */
    public function ver(int $id): void
    {
        $medico = $this->medicos->obtener($id) ?? $this->noEncontrado('El médico');
        $this->vista('medicos/medico_ficha', [
            'titulo'   => $medico['nombres'] . ' ' . $medico['apellidos'],
            'medico'   => $medico,
            'horarios' => (new HorarioModelo())->delMedico($id),
            'pendientes' => $this->medicos->citasPendientes($id),
            'puedeGestionar' => $this->puede('medicos.gestionar'),
        ]);
    }

    /** GET /medicos/nuevo */
    public function nuevo(): void
    {
        $this->vista('medicos/medico_form', [
            'titulo' => 'Registrar médico', 'medico' => null,
            'especialidades' => $this->especialidades->listar('activo'),
        ]);
    }

    /** GET /medicos/{id}/editar */
    public function editar(int $id): void
    {
        $medico = $this->medicos->obtener($id) ?? $this->noEncontrado('El médico');
        $this->vista('medicos/medico_form', [
            'titulo' => 'Modificar médico', 'medico' => $medico,
            'especialidades' => $this->especialidades->listar(),
        ]);
    }

    /** POST /medicos */
    public function crear(): void
    {
        try {
            $d = $this->validar();
            $id = BaseDatos::transaccion(function () use ($d) {
                $id = $this->medicos->crear($d);
                $this->medicos->sincronizarEspecialidades($id, $d['especialidades']);
                return $id;
            });
            $this->exito("Médico registrado. Ahora defina sus horarios de atención.", "/horarios?medico={$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, '/medicos/nuevo');
        }
    }

    /** POST /medicos/{id} */
    public function actualizar(int $id): void
    {
        try {
            $this->medicos->obtener($id) ?? throw new ErrorNegocio('El médico no existe.');
            $d = $this->validar();

            // No se puede quitar una especialidad con la que ya tiene citas (integridad del historial).
            foreach ($this->medicos->especialidadesConCitas($id) as $esp) {
                if (!in_array((int) $esp['id_especialidad'], $d['especialidades'], true)) {
                    throw new ErrorNegocio("No puede quitar «{$esp['nombre']}»: el médico tiene citas registradas en esa especialidad.");
                }
            }
            BaseDatos::transaccion(function () use ($id, $d) {
                $this->medicos->actualizar($id, $d);
                $this->medicos->sincronizarEspecialidades($id, $d['especialidades']);
            });
            $this->exito('Datos del médico actualizados.', "/medicos/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/medicos/{$id}/editar");
        }
    }

    /** POST /medicos/{id}/estado — activar / desactivar */
    public function cambiarEstado(int $id): void
    {
        try {
            $m = $this->medicos->obtener($id) ?? throw new ErrorNegocio('El médico no existe.');
            $nuevo = $m['estado'] === 'activo' ? 'inactivo' : 'activo';
            $canceladas = BaseDatos::transaccion(function () use ($id, $nuevo) {
                $n = 0;
                if ($nuevo === 'inactivo') {
                    // Sus citas pendientes se cancelan, pero quedan en el historial (RN10, RN11).
                    $n = $this->medicos->cancelarCitasPendientes($id, 'El médico fue dado de baja en la clínica');
                }
                $this->medicos->cambiarEstado($id, $nuevo);
                return $n;
            }, 'Médico desactivado');

            $msg = $nuevo === 'activo'
                ? 'Médico activado: ya puede recibir nuevas citas.'
                : 'Médico desactivado: no recibirá nuevas citas; su historial se conserva.'
                  . ($canceladas ? " Se cancelaron {$canceladas} cita(s) pendiente(s)." : '');
            $this->exito($msg, "/medicos/{$id}");
        } catch (Throwable $e) {
            $this->volverConError($e, "/medicos/{$id}");
        }
    }

    private function validar(): array
    {
        $d = Validador::validar($_POST, [
            'registro_profesional' => ['Registro profesional', 'requerido|texto:3:30'],
            'nombres'              => ['Nombres', 'requerido|nombre|texto:2:60'],
            'apellidos'            => ['Apellidos', 'requerido|nombre|texto:2:60'],
            'telefono'             => ['Teléfono', 'requerido|telefono'],
            'correo'               => ['Correo', 'requerido|email|texto:5:120'],
        ]);
        $d['registro_profesional'] = mb_strtoupper($d['registro_profesional']);
        $d['especialidades'] = Peticion::enteros('especialidades');
        if (!$d['especialidades']) {
            throw new ErrorNegocio('Revise los datos.', ['especialidades' => 'Seleccione al menos una especialidad.']);
        }
        return $d;
    }
}
