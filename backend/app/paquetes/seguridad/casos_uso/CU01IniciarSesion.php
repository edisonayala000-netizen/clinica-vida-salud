<?php
namespace App\Paquetes\Seguridad\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Sesion;
use App\Nucleo\Validador;
use App\Paquetes\Seguridad\Modelos\UsuarioModelo;
use Throwable;

/**
 * CU01 · Iniciar sesión (incluye cerrar sesión y cambiar contraseña)
 * Requisito: RF01 · Actores: Administrador, Recepcionista, Médico, Paciente
 *
 * Flujo principal:
 *  1. El usuario escribe su nombre de usuario o correo y su contraseña.
 *  2. El sistema valida los datos y busca la cuenta.
 *  3. Verifica la contraseña con password_verify() (hash bcrypt).
 *  4. Verifica que la cuenta esté activa y no bloqueada.
 *  5. Inicia una sesión segura (nuevo id de sesión) e identifica el rol.
 *  6. Muestra el panel con el menú según los permisos del rol.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: seguridad
 * Actores.........: Usuario
 * Requisitos......: RF01
 * Base de datos...: usuario (vista v_usuario), rol, permiso, rol_permiso, bitacora (ingresos, intentos fallidos,
 *                   bloqueos y salidas)
 * Rutas...........: GET /login (público) · POST /login (público) · POST /salir (con sesión) · GET/POST
 *                   /cuenta/clave (con sesión)
 * Vistas..........: frontend/vistas/seguridad/login.php, frontend/vistas/seguridad/cambiar_clave.php,
 *                   frontend/vistas/inicio/panel.php
 * Usa.............: UsuarioModelo
 * Precondición....: El usuario tiene una cuenta registrada en estado «activo».
 * Postcondición...: El usuario accede solo a las opciones de su rol. Sin sesión válida no se puede entrar a
 *                   ninguna página interna.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/seguridad/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU01IniciarSesion extends Controlador
{
    /**
     * Hash ficticio: si el usuario no existe igual se ejecuta
     * password_verify(), así el tiempo de respuesta no revela
     * qué usuarios existen.
     */
    private const HASH_FICTICIO = '$2y$10$iRnbJMJ7z9wdzYQpMF33TeR.1L1s2ge1aTzeByX6.j5famAgwMlxW';

    private UsuarioModelo $usuarios;

    public function __construct()
    {
        $this->usuarios = new UsuarioModelo();
    }

    /** GET /login */
    public function formulario(): void
    {
        if (Autenticacion::hayUsuario()) {
            $this->redirigir('/panel');
        }
        \App\Nucleo\Vista::mostrar('seguridad/login', ['titulo' => 'Iniciar sesión'], 'simple');
    }

    /** POST /login */
    public function iniciar(): void
    {
        try {
            $datos = Validador::validar($_POST, [
                'usuario' => ['Usuario o correo', 'requerido|texto:3:120'],
                'clave'   => ['Contraseña',       'requerido|texto:1:64'],
            ]);

            $cuenta = $this->usuarios->buscarParaLogin($datos['usuario']);

            // Paso 3: verificación de la contraseña (siempre se ejecuta)
            $claveCorrecta = password_verify($datos['clave'], $cuenta['password_hash'] ?? self::HASH_FICTICIO);

            // Bitácora: a nombre de la cuenta encontrada o, si no existe, de lo que se escribió.
            $quien = ['id_usuario' => $cuenta['id_usuario'] ?? null, 'nombre_usuario' => $cuenta['nombre_usuario'] ?? $datos['usuario']];
            if ($cuenta && $cuenta['esta_bloqueado']) {
                Bitacora::registrar('cuenta_bloqueada', 'seguridad', 'Intento de ingreso con la cuenta bloqueada.', $quien);
                throw new ErrorNegocio(sprintf(
                    'La cuenta está bloqueada por varios intentos fallidos. Intente de nuevo en %d minuto(s).',
                    max(1, (int) $cuenta['minutos_restantes'])
                ));
            }
            if (!$cuenta || !$claveCorrecta) {
                Bitacora::registrar('inicio_fallido', 'seguridad',
                    $cuenta ? 'Contraseña incorrecta.' : 'El usuario o correo escrito no existe.', $quien);
                if ($cuenta && $this->usuarios->registrarIntentoFallido((int) $cuenta['id_usuario'])) {
                    Bitacora::registrar('cuenta_bloqueada', 'seguridad', sprintf(
                        'Cuenta bloqueada %d minuto(s) por %d intentos fallidos.',
                        (int) \App\Nucleo\Aplicacion::config('seguridad.minutos_bloqueo', 15),
                        (int) \App\Nucleo\Aplicacion::config('seguridad.max_intentos', 5)
                    ), $quien);
                }
                // Mismo mensaje en ambos casos: no se revela si el usuario existe.
                throw new ErrorNegocio('Usuario o contraseña incorrectos.');
            }

            // Paso 4: la cuenta (y su médico o paciente vinculado) debe estar activa.
            if ($cuenta['estado'] !== 'activo'
                || ($cuenta['estado_medico'] ?? 'activo') !== 'activo'
                || ($cuenta['estado_paciente'] ?? 'activo') !== 'activo') {
                Bitacora::registrar('inicio_fallido', 'seguridad', 'Cuenta inactiva.', $quien);
                throw new ErrorNegocio('Su cuenta está inactiva. Comuníquese con la administración de la clínica.');
            }

            // Si PHP mejoró su algoritmo, se actualiza el hash sin molestar al usuario.
            if (password_needs_rehash($cuenta['password_hash'], PASSWORD_DEFAULT)) {
                $this->usuarios->cambiarClave((int) $cuenta['id_usuario'], password_hash($datos['clave'], PASSWORD_DEFAULT));
            }

            // Paso 5: sesión segura
            $this->usuarios->registrarAccesoExitoso((int) $cuenta['id_usuario']);
            Autenticacion::iniciar((int) $cuenta['id_usuario']);
            Bitacora::registrar('inicio_sesion', 'seguridad', 'Ingresó al sistema.');

            $destino = Sesion::obtener('volver_a', '/panel');
            Sesion::quitar('volver_a');
            $this->redirigir(is_string($destino) && str_starts_with($destino, '/') ? $destino : '/panel');
        } catch (Throwable $e) {
            $this->volverConError($e, '/login');
        }
    }

    /** POST /salir — cierra la sesión */
    public function cerrar(): void
    {
        Bitacora::registrar('cierre_sesion', 'seguridad', 'Cerró sesión.');
        Autenticacion::cerrar();
        Sesion::iniciar();
        Sesion::flash('exito', 'Cerró sesión correctamente.');
        $this->redirigir('/login');
    }

    /** GET /cuenta/clave */
    public function formularioClave(): void
    {
        $this->vista('seguridad/cambiar_clave', ['titulo' => 'Cambiar contraseña']);
    }

    /** POST /cuenta/clave — flujo alternativo: cambiar contraseña */
    public function cambiarClave(): void
    {
        try {
            $d = Validador::validar($_POST, [
                'clave_actual'       => ['Contraseña actual', 'requerido'],
                'clave'              => ['Nueva contraseña', 'requerido|clave'],
                'clave_confirmacion' => ['Confirmación', 'requerido'],
            ]);
            $id = Autenticacion::id();
            if (!password_verify($d['clave_actual'], (string) $this->usuarios->hashActual($id))) {
                throw new ErrorNegocio('Revise los datos.', ['clave_actual' => 'La contraseña actual no es correcta.']);
            }
            if ($d['clave'] !== $d['clave_confirmacion']) {
                throw new ErrorNegocio('Revise los datos.', ['clave_confirmacion' => 'La confirmación no coincide.']);
            }
            if ($d['clave'] === $d['clave_actual']) {
                throw new ErrorNegocio('Revise los datos.', ['clave' => 'La nueva contraseña debe ser distinta de la actual.']);
            }
            BaseDatos::transaccion(fn () => $this->usuarios->cambiarClave($id, password_hash($d['clave'], PASSWORD_DEFAULT)));
            $this->exito('Contraseña actualizada.', '/panel');
        } catch (Throwable $e) {
            $this->volverConError($e, '/cuenta/clave');
        }
    }
}
