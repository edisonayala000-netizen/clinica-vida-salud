<?php
namespace App\Paquetes\Seguridad\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Respuesta;
use App\Nucleo\Sesion;
use App\Nucleo\Validador;
use App\Paquetes\Seguridad\Modelos\BitacoraModelo;
use App\Paquetes\Seguridad\Modelos\RolModelo;
use App\Paquetes\Seguridad\Modelos\UsuarioModelo;
use Throwable;

/**
 * CU02 · Gestionar usuarios y permisos
 * Requisito: RF01 / Alcance "Administrar usuarios y permisos básicos"
 * Actor: Administrador
 *
 *  - Registrar usuarios y asignarles un rol.
 *  - Vincular la cuenta a un médico o a un paciente según el rol.
 *  - Modificar, activar/desactivar y restablecer contraseñas.
 *  - Definir los permisos de cada rol (matriz rol x permiso).
 *  - Consultar la bitácora de acciones (quién, qué, cuándo, IP y detalle) con filtros y exportarla a CSV.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: seguridad
 * Actores.........: Administrador
 * Requisitos......: RF01 · Alcance: administrar usuarios y permisos básicos · bitácora de acciones
 * Base de datos...: usuario (toda cuenta pertenece a una persona), persona, rol, permiso, rol_permiso
 *                   (trigger fn_tg_validar_usuario_rol), bitacora (trigger fn_tg_bitacora_inalterable: no se
 *                   modifica ni se borra; trigger fn_tg_auditoria: cambios de datos con antes y después)
 * Rutas...........: GET /usuarios (usuarios.gestionar) · GET/POST /usuarios/nuevo · /usuarios
 *                   (usuarios.gestionar) · GET/POST /usuarios/{id}/editar · /usuarios/{id}
 *                   (usuarios.gestionar) · POST /usuarios/{id}/estado (usuarios.gestionar) · GET/POST
 *                   /permisos (usuarios.gestionar) · GET /bitacora?usuario=&modulo=&accion=&desde=&hasta=
 *                   [&formato=csv] (bitacora.ver)
 * Vistas..........: frontend/vistas/seguridad/usuarios_lista.php, frontend/vistas/seguridad/usuario_form.php,
 *                   frontend/vistas/seguridad/permisos.php, frontend/vistas/seguridad/bitacora.php
 * Usa.............: UsuarioModelo, RolModelo, BitacoraModelo, Bitacora (núcleo: registra las acciones)
 * Precondición....: El administrador inició sesión (CU01) y tiene el permiso «usuarios.gestionar».
 * Postcondición...: Las cuentas y los permisos quedan registrados; el menú y el acceso de cada usuario
 *                   dependen de su rol.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/seguridad/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU02GestionarUsuariosPermisos extends Controlador
{
    private UsuarioModelo $usuarios;
    private RolModelo $roles;

    public function __construct()
    {
        $this->usuarios = new UsuarioModelo();
        $this->roles = new RolModelo();
    }

    /** GET /usuarios */
    public function listar(): void
    {
        $buscar = Peticion::entrada('q');
        $rol = Peticion::entrada('rol');
        $estado = Peticion::entrada('estado');
        $this->vista('seguridad/usuarios_lista', [
            'titulo'   => 'Usuarios',
            'usuarios' => $this->usuarios->listar($buscar, $rol, $estado),
            'roles'    => $this->roles->listar(),
            'filtros'  => compact('buscar', 'rol', 'estado'),
        ]);
    }

    /** GET /usuarios/nuevo */
    public function nuevo(): void
    {
        $this->formulario(null);
    }

    /** GET /usuarios/{id}/editar */
    public function editar(int $id): void
    {
        $usuario = $this->usuarios->obtener($id) ?? $this->noEncontrado('El usuario');
        $this->formulario($usuario);
    }

    private function formulario(?array $usuario): void
    {
        $this->vista('seguridad/usuario_form', [
            'titulo'    => $usuario ? 'Modificar usuario' : 'Nuevo usuario',
            'usuario'   => $usuario,
            'roles'     => $this->roles->listar(),
            'medicos'   => $this->usuarios->medicosSinCuenta($usuario['id_medico'] ?? null),
            'pacientes' => $this->usuarios->pacientesSinCuenta($usuario['id_paciente'] ?? null),
        ]);
    }

    /** POST /usuarios */
    public function crear(): void
    {
        try {
            $d = $this->validarDatos(true);
            $d['password_hash'] = password_hash($d['clave'], PASSWORD_DEFAULT);
            BaseDatos::transaccion(function () use ($d) {
                // El personal (administrador o recepcionista) se registra como una persona nueva.
                $d['id_persona'] ??= $this->usuarios->crearPersona($d['nombres'], $d['apellidos'], $d['correo']);
                $this->usuarios->crear($d);
            });
            $this->exito("Usuario «{$d['nombre_usuario']}» registrado.", '/usuarios');
        } catch (Throwable $e) {
            $this->volverConError($e, '/usuarios/nuevo');
        }
    }

    /** POST /usuarios/{id} */
    public function actualizar(int $id): void
    {
        try {
            $actual = $this->usuarios->obtener($id) ?? throw new ErrorNegocio('El usuario no existe.');
            $d = $this->validarDatos(false);

            // Regla: el sistema no puede quedarse sin administradores activos.
            $rolNuevo = $this->roles->obtener($d['id_rol']);
            if ($actual['rol'] === 'administrador' && $rolNuevo['nombre'] !== 'administrador'
                && $actual['estado'] === 'activo' && $this->usuarios->administradoresActivos() <= 1) {
                throw new ErrorNegocio('Debe existir al menos un administrador activo.');
            }

            BaseDatos::transaccion(function () use ($id, $d, $actual) {
                if ($d['id_persona'] === null) {
                    // Personal: se corrige el nombre de su persona; si antes la cuenta era de un
                    // médico o paciente, el personal se registra como una persona nueva.
                    if ($this->usuarios->esMedicoOPaciente((int) $actual['id_persona'])) {
                        $d['id_persona'] = $this->usuarios->crearPersona($d['nombres'], $d['apellidos'], $d['correo']);
                    } else {
                        $d['id_persona'] = (int) $actual['id_persona'];
                        $this->usuarios->actualizarNombrePersona($d['id_persona'], $d['nombres'], $d['apellidos']);
                    }
                }
                $this->usuarios->actualizar($id, $d);
                if (!empty($d['clave'])) {
                    $this->usuarios->cambiarClave($id, password_hash($d['clave'], PASSWORD_DEFAULT));
                }
            });
            $this->exito("Usuario «{$d['nombre_usuario']}» actualizado.", '/usuarios');
        } catch (Throwable $e) {
            $this->volverConError($e, "/usuarios/{$id}/editar");
        }
    }

    /** POST /usuarios/{id}/estado — activar o desactivar */
    public function cambiarEstado(int $id): void
    {
        try {
            $u = $this->usuarios->obtener($id) ?? throw new ErrorNegocio('El usuario no existe.');
            $nuevo = $u['estado'] === 'activo' ? 'inactivo' : 'activo';
            if ($id === Autenticacion::id()) {
                throw new ErrorNegocio('No puede desactivar su propia cuenta.');
            }
            if ($nuevo === 'inactivo' && $u['rol'] === 'administrador' && $this->usuarios->administradoresActivos() <= 1) {
                throw new ErrorNegocio('Debe existir al menos un administrador activo.');
            }
            BaseDatos::transaccion(fn () => $this->usuarios->cambiarEstado($id, $nuevo));
            $this->exito(
                $nuevo === 'activo' ? "Cuenta «{$u['nombre_usuario']}» activada." : "Cuenta «{$u['nombre_usuario']}» desactivada.",
                '/usuarios'
            );
        } catch (Throwable $e) {
            $this->volverConError($e, '/usuarios');
        }
    }

    /** GET /permisos — matriz de permisos por rol */
    public function permisos(): void
    {
        $this->vista('seguridad/permisos', [
            'titulo'   => 'Permisos por rol',
            'roles'    => $this->roles->listar(),
            'permisos' => $this->roles->permisos(),
            'matriz'   => $this->roles->matrizPermisos(),
        ]);
    }

    /** POST /permisos */
    public function guardarPermisos(): void
    {
        try {
            $seleccion = $_POST['permisos'] ?? [];
            if (!is_array($seleccion)) {
                throw new ErrorNegocio('Datos de permisos inválidos.');
            }
            $idGestionar = $this->roles->idPermiso('usuarios.gestionar');

            BaseDatos::transaccion(function () use ($seleccion, $idGestionar) {
                foreach ($this->roles->listar() as $rol) {
                    $ids = array_map('intval', (array) ($seleccion[$rol['id_rol']] ?? []));
                    // Regla: el administrador nunca pierde la gestión de usuarios (evita quedar sin acceso).
                    if ($rol['nombre'] === 'administrador' && !in_array($idGestionar, $ids, true)) {
                        $ids[] = $idGestionar;
                    }
                    $this->roles->reemplazarPermisos((int) $rol['id_rol'], array_unique($ids));
                }
            });
            $this->exito('Permisos actualizados. Los cambios se aplican desde la próxima pantalla de cada usuario.', '/permisos');
        } catch (Throwable $e) {
            $this->volverConError($e, '/permisos');
        }
    }

    /** GET /bitacora?usuario=&modulo=&accion=&desde=&hasta=[&formato=csv] — bitácora de acciones */
    public function bitacora(): void
    {
        try {
            $f = Validador::validar($_GET, [
                'usuario' => ['Usuario', 'opcional|texto:1:120'],
                'modulo'  => ['Módulo', 'opcional|texto:1:30'],
                'accion'  => ['Acción', 'opcional|texto:1:40'],
                'desde'   => ['Desde', 'opcional|fecha'],
                'hasta'   => ['Hasta', 'opcional|fecha'],
            ]);
        } catch (Throwable) {
            $f = ['usuario' => null, 'modulo' => null, 'accion' => null, 'desde' => null, 'hasta' => null];
        }
        if ($f['desde'] && $f['hasta'] && $f['hasta'] < $f['desde']) {
            [$f['desde'], $f['hasta']] = [$f['hasta'], $f['desde']];
        }
        $bitacora = new BitacoraModelo();

        if (Peticion::entrada('formato') === 'csv') {
            Bitacora::registrar('exportacion', 'seguridad', 'Exportó la bitácora a CSV.');
            Respuesta::csv(
                'bitacora_' . date('Y-m-d_His') . '.csv',
                ['Fecha', 'Hora', 'Usuario', 'Acción', 'Módulo', 'Detalle', 'IP', 'Tabla', 'Operación', 'Datos antes', 'Datos después'],
                array_map(static fn ($r) => [
                    date('d/m/Y', strtotime($r['fecha_hora'])), date('H:i:s', strtotime($r['fecha_hora'])),
                    $r['usuario'], $r['accion'], $r['modulo'], $r['detalle'], $r['ip'],
                    $r['tabla'], $r['operacion'], $r['datos_antes'], $r['datos_despues'],
                ], $bitacora->listar($f, null))
            );
        }

        $pagina = max(1, Peticion::entero('pagina') ?? 1);
        $total = $bitacora->contar($f);
        $this->vista('seguridad/bitacora', [
            'titulo'    => 'Bitácora',
            'registros' => $bitacora->listar($f, $pagina),
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($total / BitacoraModelo::POR_PAGINA)),
            'filtros'   => $f,
            'modulos'   => $bitacora->modulos(),
            'acciones'  => $bitacora->acciones(),
        ]);
    }

    /**
     * Validación del formulario de usuario (en el servidor).
     * Según el rol se exige el vínculo con médico o paciente (su persona) o,
     * para el personal, sus nombres y apellidos. Deja en id_persona la persona
     * de la cuenta (null = personal: la resuelve crear() o actualizar()).
     */
    private function validarDatos(bool $esNuevo): array
    {
        $d = Validador::validar($_POST, [
            'nombres'            => ['Nombres', 'opcional|nombre|texto:2:60'],
            'apellidos'          => ['Apellidos', 'opcional|nombre|texto:2:60'],
            'nombre_usuario'     => ['Nombre de usuario', 'requerido|usuario'],
            'correo'             => ['Correo', 'requerido|email|texto:5:120'],
            'id_rol'             => ['Rol', 'requerido|entero:1'],
            'id_medico'          => ['Médico', 'opcional|entero:1'],
            'id_paciente'        => ['Paciente', 'opcional|entero:1'],
            'clave'              => ['Contraseña', ($esNuevo ? 'requerido' : 'opcional') . '|clave'],
            'clave_confirmacion' => ['Confirmación', 'opcional'],
        ]);

        if (!empty($d['clave']) && $d['clave'] !== $d['clave_confirmacion']) {
            throw new ErrorNegocio('Revise los datos.', ['clave_confirmacion' => 'La confirmación no coincide con la contraseña.']);
        }

        $rol = $this->roles->obtener($d['id_rol']) ?? throw new ErrorNegocio('Revise los datos.', ['id_rol' => 'Rol no válido.']);
        if ($rol['nombre'] === 'medico') {
            if (!$d['id_medico']) {
                throw new ErrorNegocio('Revise los datos.', ['id_medico' => 'Seleccione el médico de esta cuenta.']);
            }
            $d['id_persona'] = $d['id_medico'];
        } elseif ($rol['nombre'] === 'paciente') {
            if (!$d['id_paciente']) {
                throw new ErrorNegocio('Revise los datos.', ['id_paciente' => 'Seleccione el paciente de esta cuenta.']);
            }
            $d['id_persona'] = $d['id_paciente'];
        } else {
            $errores = [];
            foreach (['nombres' => 'Indique los nombres.', 'apellidos' => 'Indique los apellidos.'] as $campo => $mensaje) {
                if (!$d[$campo]) {
                    $errores[$campo] = $mensaje;
                }
            }
            if ($errores) {
                throw new ErrorNegocio('Revise los datos.', $errores);
            }
            $d['id_persona'] = null;
        }
        return $d;
    }
}
