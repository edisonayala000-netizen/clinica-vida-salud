<?php
namespace App\Paquetes\Notificaciones\CasosUso;

use App\Nucleo\Autenticacion;
use App\Nucleo\BaseDatos;
use App\Nucleo\Bitacora;
use App\Nucleo\Controlador;
use App\Nucleo\EnlaceFirmado;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Sesion;
use App\Nucleo\Validador;
use App\Nucleo\Vista;
use App\Paquetes\Atencion\Modelos\RecetaModelo;
use App\Paquetes\Citas\CasosUso\CU11CancelarCita;
use App\Paquetes\Citas\Modelos\CitaModelo;
use App\Paquetes\Citas\Servicios\EstadoCita;
use App\Paquetes\Notificaciones\Modelos\NotificacionModelo;
use App\Paquetes\Notificaciones\Servicios\Notificador;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use Throwable;

/**
 * CU18 · Enviar notificaciones (recordatorios y avisos por correo)
 * Requisito: RF09 · RF10 · Actores: Sistema (tarea programada), Paciente (desde el correo), Administrador
 *
 * Flujo principal (tarea programada, cada 5 minutos: backend/tareas/enviar_notificaciones.php):
 *  1. El sistema anula los recordatorios con más de 6 horas de atraso.
 *  2. Toma cada notificación pendiente cuya hora llegó (recordatorio de cita, toma de medicamento).
 *  3. Vuelve a comprobar que siga correspondiendo (la cita no se canceló, el paciente no desactivó
 *     los avisos) y arma el correo con su plantilla y sus enlaces firmados.
 *  4. Lo envía con Brevo y marca la notificación como enviada (o con error, para reintentarla).
 *  5. Se detiene al llegar al límite diario de correos.
 * Flujos alternativos:
 *  A. Desde el recordatorio de 24 h, el paciente confirma su asistencia o cancela la cita (enlace
 *     firmado que vence al empezar la cita; primero se muestra la cita y luego se confirma la acción).
 *  B. Desde cualquier recordatorio, el paciente desactiva los avisos de citas, de todos sus
 *     medicamentos o de uno solo.
 *  C. El administrador consulta los correos, los envía «ahora» y reintenta los que fallaron.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: notificaciones
 * Actores.........: Sistema, Paciente, Administrador
 * Requisitos......: RF09 · RF10 · RN09
 * Base de datos...: notificacion (programadas por los triggers fn_tg_recordatorio_cita y fn_tg_programar_tomas),
 *                   paciente (recordatorio_cita, recordar_medicamentos), receta_detalle (recordar), cita, bitacora
 * Rutas...........: GET /notificaciones · POST /notificaciones/enviar · POST /notificaciones/{id}/reintentar
 *                   (notificaciones.ver) · GET/POST /correo/cita y GET/POST /correo/baja (públicas, enlace firmado)
 * Vistas..........: frontend/vistas/notificaciones/lista.php, cita_correo.php, baja.php y las plantillas de
 *                   frontend/vistas/correos/
 * Usa.............: Notificador, ContenidoCorreo, NotificacionModelo, EnlaceFirmado, CU11CancelarCita, EstadoCita
 * Precondición....: El paciente tiene correo; para recordatorios, los tiene activados.
 * Postcondición...: Cada correo queda registrado en notificacion con su resultado.
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU18EnviarNotificaciones extends Controlador
{
    private const TIPOS = [
        'recuperar_clave' => 'Recuperar contraseña', 'cita_registrada' => 'Cita registrada',
        'cita_reprogramada' => 'Cita reprogramada', 'cita_cancelada' => 'Cita cancelada',
        'recordatorio_cita' => 'Recordatorio 24 h', 'recordatorio_cita_1h' => 'Recordatorio 1 h',
        'receta' => 'Receta', 'toma_medicamento' => 'Toma de medicamento',
    ];

    private NotificacionModelo $notificaciones;

    public function __construct()
    {
        $this->notificaciones = new NotificacionModelo();
    }

    /* ================= Administrador (flujo C) ================= */

    /** GET /notificaciones */
    public function listar(): void
    {
        $f = [
            'tipo'   => array_key_exists((string) Peticion::entrada('tipo'), self::TIPOS) ? Peticion::entrada('tipo') : null,
            'estado' => in_array(Peticion::entrada('estado'), ['pendiente', 'enviada', 'error', 'anulada'], true) ? Peticion::entrada('estado') : null,
            'correo' => Peticion::entrada('correo'),
        ];
        $pagina = max(1, Peticion::entero('pagina') ?? 1);
        $total = $this->notificaciones->contar($f);
        $this->vista('notificaciones/lista', [
            'titulo'         => 'Notificaciones',
            'notificaciones' => $this->notificaciones->listar($f, $pagina),
            'resumen'        => $this->notificaciones->resumen(),
            'tipos'          => self::TIPOS,
            'filtros'        => $f,
            'total'          => $total,
            'pagina'         => $pagina,
            'paginas'        => max(1, (int) ceil($total / NotificacionModelo::POR_PAGINA)),
        ]);
    }

    /** POST /notificaciones/enviar — hace ahora lo mismo que la tarea programada */
    public function enviarPendientes(): void
    {
        try {
            $r = (new Notificador())->procesarPendientes();
            $this->exito(sprintf('Envío terminado: %d enviado(s), %d con error, %d anulado(s).%s',
                $r['enviadas'], $r['errores'], $r['anuladas'],
                $r['limite'] ? ' Se alcanzó el límite diario: el resto sale mañana.' : ''), '/notificaciones');
        } catch (Throwable $e) {
            $this->volverConError($e, '/notificaciones');
        }
    }

    /** POST /notificaciones/{id}/reintentar */
    public function reintentar(int $id): void
    {
        try {
            $ok = (new Notificador())->reintentar($id);
            $ok ? $this->exito('Correo enviado.', '/notificaciones')
                : throw new ErrorNegocio('No se pudo enviar: revise el motivo en la lista.');
        } catch (Throwable $e) {
            $this->volverConError($e, '/notificaciones');
        }
    }

    /* ================= Paciente desde el correo: confirmar o cancelar (flujo A) ================= */

    /** GET /correo/cita?e=… */
    public function verCita(): void
    {
        $token = (string) Peticion::entrada('e', '');
        $cita = $this->citaDelEnlace($token);
        Vista::mostrar('notificaciones/cita_correo', [
            'titulo'   => 'Su cita',
            'token'    => $token,
            'cita'     => $cita,
            'acciones' => $cita ? EstadoCita::acciones($cita) : [],
        ], 'simple');
    }

    /** POST /correo/cita — accion = confirmar | cancelar */
    public function responderCita(): void
    {
        $token = (string) ($_POST['e'] ?? '');
        $volver = '/correo/cita?e=' . rawurlencode($token);
        try {
            $cita = $this->citaDelEnlace($token)
                ?? throw new ErrorNegocio('El enlace no es válido o la cita ya comenzó.');
            $quien = ['id_usuario' => null, 'nombre_usuario' => 'paciente ' . $cita['codigo_paciente']];
            $accion = (string) ($_POST['accion'] ?? '');
            if ($accion === 'confirmar') {
                BaseDatos::transaccion(function () use ($cita) {
                    EstadoCita::validarCambio($cita, 'confirmada');
                    (new CitaModelo())->cambiarEstado((int) $cita['id_cita'], 'confirmada');
                }, 'El paciente confirmó su asistencia desde el correo');
                Bitacora::registrar('cita_confirmada_correo', 'citas', "Cita {$cita['codigo']} confirmada desde el correo.", $quien);
                $this->exito('¡Gracias! Su asistencia quedó confirmada.', $volver);
            }
            if ($accion === 'cancelar') {
                $motivo = trim((string) ($_POST['motivo'] ?? '')) ?: 'Cancelada por el paciente desde el correo';
                // Mismas reglas que en el portal: solo con 2 horas de anticipación (CU11).
                (new CU11CancelarCita())->ejecutar((int) $cita['id_cita'], $motivo, (int) $cita['id_paciente']);
                Bitacora::registrar('cita_cancelada_correo', 'citas', "Cita {$cita['codigo']} cancelada desde el correo.", $quien);
                (new Notificador())->avisarCita('cita_cancelada', (int) $cita['id_cita']);
                $this->exito('Su cita fue cancelada y el horario quedó libre.', $volver);
            }
            throw new ErrorNegocio('Elija confirmar o cancelar la cita.');
        } catch (Throwable $e) {
            $this->volverConError($e, $volver);
        }
    }

    /** Cita del enlace firmado (vigente: vence al empezar la cita), o null. */
    private function citaDelEnlace(string $token): ?array
    {
        $datos = EnlaceFirmado::leer($token);
        if (($datos['a'] ?? null) !== 'cita') {
            return null;
        }
        return (new CitaModelo())->obtener((int) $datos['c']);
    }

    /* ================= Paciente desde el correo: desactivar avisos (flujo B) ================= */

    /** GET /correo/baja?e=… */
    public function verBaja(): void
    {
        $token = (string) Peticion::entrada('e', '');
        Vista::mostrar('notificaciones/baja', [
            'titulo' => 'Recordatorios por correo',
            'token'  => $token,
            'baja'   => $this->bajaDelEnlace($token),
            'hecho'  => Sesion::obtener('baja_hecha') === $token,
        ], 'simple');
    }

    /** POST /correo/baja */
    public function confirmarBaja(): void
    {
        $token = (string) ($_POST['e'] ?? '');
        try {
            $b = $this->bajaDelEnlace($token) ?? throw new ErrorNegocio('El enlace no es válido o venció.');
            BaseDatos::transaccion(function () use ($b) {
                match ($b['tema']) {
                    'citas'        => (new PacienteModelo())->actualizar($b['id_paciente'], ['recordatorio_cita' => 'ninguno']),
                    'medicamentos' => (new PacienteModelo())->actualizar($b['id_paciente'], ['recordar_medicamentos' => false]),
                    'detalle'      => (new RecetaModelo())->cambiarRecordar($b['id_detalle'], $b['id_paciente'], false),
                };
            });
            Bitacora::registrar('avisos_desactivados', 'notificaciones', 'Desactivó desde el correo: ' . $b['descripcion'] . '.',
                ['id_usuario' => null, 'nombre_usuario' => 'paciente ' . $b['codigo_paciente']]);
            Sesion::guardar('baja_hecha', $token);
            $this->redirigir('/correo/baja?e=' . rawurlencode($token));
        } catch (Throwable $e) {
            $this->volverConError($e, '/correo/baja?e=' . rawurlencode($token));
        }
    }

    /** @return array{tema:string,id_paciente:int,id_detalle:?int,descripcion:string,paciente:string,codigo_paciente:string}|null */
    private function bajaDelEnlace(string $token): ?array
    {
        $datos = EnlaceFirmado::leer($token);
        if (($datos['a'] ?? null) !== 'baja' || !in_array($datos['t'] ?? null, ['citas', 'medicamentos', 'detalle'], true)) {
            return null;
        }
        $paciente = (new PacienteModelo())->obtener((int) $datos['p']);
        if ($paciente === null) {
            return null;
        }
        $descripcion = match ($datos['t']) {
            'citas'        => 'los recordatorios de sus citas',
            'medicamentos' => 'los recordatorios de todos sus medicamentos',
            'detalle'      => null,
        };
        if ($datos['t'] === 'detalle') {
            $d = (new RecetaModelo())->detalleDelPaciente((int) ($datos['d'] ?? 0), (int) $datos['p']);
            if ($d === null) {
                return null;
            }
            $descripcion = 'los recordatorios de ' . trim($d['medicamento'] . ' ' . ($d['concentracion'] ?? ''))
                . " (receta {$d['codigo_receta']})";
        }
        return [
            'tema'            => $datos['t'],
            'id_paciente'     => (int) $datos['p'],
            'id_detalle'      => isset($datos['d']) ? (int) $datos['d'] : null,
            'descripcion'     => $descripcion,
            'paciente'        => $paciente['nombres'] . ' ' . $paciente['apellidos'],
            'codigo_paciente' => $paciente['codigo'],
        ];
    }
}
