<?php
namespace App\Paquetes\Pacientes\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Nucleo\Vista;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use App\Paquetes\Pacientes\ReglasPaciente;
use App\Paquetes\Seguridad\Modelos\RolModelo;
use App\Paquetes\Seguridad\Modelos\UsuarioModelo;
use Throwable;

/**
 * CU04 · Gestionar perfil del paciente (incluye registrarse en línea)
 * Requisito: punto 3.4 "Perfil de pacientes" · Actor: Paciente
 *
 * Secciones del perfil: datos personales, contacto, información médica,
 * contacto de emergencia y cuenta (notificaciones y privacidad).
 *
 * Registro en línea:
 *  - CI nuevo: se crea el paciente y su cuenta con rol "paciente".
 *  - CI ya registrado por recepción y sin cuenta: se vincula la cuenta
 *    solo si la fecha de nacimiento coincide (verificación de identidad).
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: pacientes
 * Actores.........: Paciente
 * Requisitos......: Punto 3.4 Perfil de pacientes
 * Base de datos...: persona + paciente (vista v_paciente), usuario (id_persona), rol, contacto_emergencia
 * Rutas...........: GET/POST /registro (público) · GET /mi-perfil (portal.perfil) · POST /mi-
 *                   perfil/personales · /medica · /emergencia · /cuenta (portal.perfil)
 * Vistas..........: frontend/vistas/pacientes/registro.php, frontend/vistas/pacientes/mi_perfil.php
 * Usa.............: PacienteModelo, ReglasPaciente, UsuarioModelo, RolModelo
 * Precondición....: Para el registro, el paciente no tiene cuenta. Para editar, inició sesión con rol
 *                   Paciente.
 * Postcondición...: El perfil queda actualizado; el CI y la fecha de nacimiento solo los corrige recepción.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/pacientes/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU04GestionarPerfilPaciente extends Controlador
{
    private PacienteModelo $pacientes;

    public function __construct()
    {
        $this->pacientes = new PacienteModelo();
    }

    /** GET /registro */
    public function formularioRegistro(): void
    {
        if (Autenticacion::hayUsuario()) {
            $this->redirigir('/panel');
        }
        Vista::mostrar('pacientes/registro', ['titulo' => 'Crear cuenta de paciente'], 'simple');
    }

    /** POST /registro */
    public function registrar(): void
    {
        try {
            $d = ReglasPaciente::datosPersonales($_POST, true);
            $cuenta = Validador::validar($_POST, [
                'correo'             => ['Correo', 'requerido|email|texto:5:120'],
                'nombre_usuario'     => ['Nombre de usuario', 'requerido|usuario'],
                'clave'              => ['Contraseña', 'requerido|clave'],
                'clave_confirmacion' => ['Confirmación', 'requerido'],
                'acepta'             => ['Aceptación', 'requerido'],
            ]);
            if ($cuenta['clave'] !== $cuenta['clave_confirmacion']) {
                throw new ErrorNegocio('Revise los datos.', ['clave_confirmacion' => 'La confirmación no coincide.']);
            }

            $existente = $this->pacientes->porCi($d['ci']);
            if ($existente && $existente['tiene_cuenta']) {
                throw new ErrorNegocio('Ya existe una cuenta para ese CI. Inicie sesión o pida ayuda en recepción.');
            }
            if ($existente && $existente['fecha_nacimiento'] !== $d['fecha_nacimiento']) {
                throw new ErrorNegocio('Ese CI ya está registrado en la clínica con otros datos. Acérquese a recepción para vincular su cuenta.');
            }
            if ($existente && $existente['estado'] !== 'activo') {
                throw new ErrorNegocio('Su registro de paciente está inactivo. Comuníquese con recepción.');
            }

            $rolPaciente = (new RolModelo())->obtenerPorNombre('paciente');
            $idUsuario = BaseDatos::transaccion(function () use ($d, $cuenta, $existente, $rolPaciente) {
                $d['correo'] = $cuenta['correo'];
                if ($existente) {
                    $idPaciente = (int) $existente['id_paciente'];
                    $this->pacientes->actualizar($idPaciente, [
                        'telefono' => $d['telefono'], 'correo' => $d['correo'], 'direccion' => $d['direccion'],
                    ]);
                } else {
                    $idPaciente = $this->pacientes->crear($d);
                }
                // La cuenta pertenece a la persona del paciente (id_paciente = id_persona).
                return (new UsuarioModelo())->crear([
                    'nombre_usuario' => $cuenta['nombre_usuario'],
                    'correo'         => $cuenta['correo'],
                    'password_hash'  => password_hash($cuenta['clave'], PASSWORD_DEFAULT),
                    'id_rol'         => (int) $rolPaciente['id_rol'],
                    'id_persona'     => $idPaciente,
                ]);
            });

            Autenticacion::iniciar($idUsuario);
            $this->exito('¡Bienvenido(a)! Su cuenta fue creada. Ya puede reservar sus citas.', '/panel');
        } catch (Throwable $e) {
            $this->volverConError($e, '/registro');
        }
    }

    /** GET /mi-perfil */
    public function ver(): void
    {
        $id = $this->idPaciente();
        $paciente = $this->pacientes->obtener($id);
        $this->vista('pacientes/mi_perfil', [
            'titulo'   => 'Mi perfil',
            'paciente' => $paciente,
            'contacto' => $this->pacientes->contactos($id)[0] ?? null,
            'seccion'  => Peticion::entrada('seccion', 'personales'),
        ]);
    }

    /** POST /mi-perfil/{seccion} */
    public function guardarDatos(): void
    {
        $this->guardar('personales', function (int $id) {
            $actual = $this->pacientes->obtener($id);
            // El CI y la fecha de nacimiento identifican al paciente: solo recepción los cambia.
            $d = ReglasPaciente::datosPersonales(
                ['ci' => $actual['ci'], 'fecha_nacimiento' => $actual['fecha_nacimiento']] + $_POST, true
            );
            unset($d['ci'], $d['fecha_nacimiento']);
            $this->pacientes->actualizar($id, $d);
        });
    }

    public function guardarMedica(): void
    {
        $this->guardar('medica', fn (int $id) => $this->pacientes->actualizar($id, ReglasPaciente::informacionMedica($_POST)));
    }

    public function guardarEmergencia(): void
    {
        $this->guardar('emergencia', function (int $id) {
            $c = ReglasPaciente::contactoEmergencia($_POST)
                ?? throw new ErrorNegocio('Revise los datos.', ['contacto_nombre' => 'Indique el nombre del contacto.']);
            $this->pacientes->guardarContactoPrincipal($id, $c);
        });
    }

    public function guardarPreferencias(): void
    {
        $this->guardar('cuenta', function (int $id) {
            $d = Validador::validar($_POST, [
                'recordatorio_cita'     => ['Recordatorio de citas', 'requerido|en:ninguno,1h,24h,24h_1h'],
                'recordar_medicamentos' => ['Recordatorio de medicamentos', 'booleano'],
                'compartir_info_medica' => ['Privacidad', 'booleano'],
            ]);
            $this->pacientes->actualizar($id, $d);
        });
    }

    private function guardar(string $seccion, callable $operacion): never
    {
        try {
            $id = $this->idPaciente();
            BaseDatos::transaccion(fn () => $operacion($id));
            $this->exito('Cambios guardados.', '/mi-perfil?seccion=' . $seccion);
        } catch (Throwable $e) {
            $this->volverConError($e, '/mi-perfil?seccion=' . $seccion);
        }
    }

    private function idPaciente(): int
    {
        $id = (int) ($this->usuario()['id_paciente'] ?? 0);
        if (!$id) {
            throw new ErrorNegocio('Su cuenta no está vinculada a un paciente.');
        }
        return $id;
    }
}
