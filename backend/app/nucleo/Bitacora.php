<?php
namespace App\Nucleo;

use Throwable;

/**
 * Bitácora de acciones de los usuarios (parte de CU02).
 *
 * Guarda quién hizo qué, cuándo y desde qué IP: inicios y cierres de
 * sesión, intentos fallidos, bloqueos y toda operación enviada por POST
 * (la registra el Enrutador). Nunca guarda contraseñas ni el contenido
 * de los formularios. Si la bitácora falla, la operación principal sigue.
 * La tabla no se puede modificar ni borrar (trigger fn_tg_bitacora_inalterable).
 */
final class Bitacora
{
    /**
     * @param array{id_usuario:?int,nombre_usuario:string}|null $usuario a nombre de quién se registra;
     *        por defecto, el usuario de la sesión (se indica en un login fallido o al cerrar sesión).
     */
    public static function registrar(string $accion, string $modulo, string $detalle = '', ?array $usuario = null): void
    {
        try {
            $usuario ??= Autenticacion::usuario();
            BaseDatos::ejecutar(
                "INSERT INTO bitacora (id_usuario, usuario, accion, modulo, detalle, ip)
                 VALUES (:id, :usuario, :accion, :modulo, :detalle, :ip)",
                [
                    'id'      => isset($usuario['id_usuario']) ? (int) $usuario['id_usuario'] : null,
                    'usuario' => mb_substr((string) ($usuario['nombre_usuario'] ?? 'anónimo'), 0, 120),
                    'accion'  => mb_substr($accion, 0, 40),
                    'modulo'  => mb_substr($modulo, 0, 30),
                    'detalle' => $detalle === '' ? null : mb_substr($detalle, 0, 1000),
                    'ip'      => mb_substr(Peticion::ip(), 0, 45),
                ]
            );
        } catch (Throwable $e) {
            // La bitácora nunca impide la operación principal: solo se anota el error técnico.
            error_log('[bitacora] ' . $e->getMessage());
        }
    }

    /**
     * Registra una petición POST al terminar (la llama el Enrutador al cierre de la petición,
     * porque los casos de uso terminan con una redirección). El resultado se deduce del
     * código HTTP y de los mensajes que dejó el caso de uso.
     *
     * @param array<string,mixed>|null $usuarioAntes usuario de la sesión antes de ejecutar el caso de uso
     */
    public static function registrarOperacion(string $ruta, string $clase, string $metodo, ?array $usuarioAntes, int $mensajesAntes): void
    {
        try {
            $mensajes = $_SESSION['flash'] ?? [];
            $nuevos = count($mensajes) >= $mensajesAntes ? array_slice($mensajes, $mensajesAntes) : $mensajes;
            $codigo = (int) http_response_code();
            $error = null;
            foreach ($nuevos as $m) {
                if (in_array($m['tipo'] ?? '', ['error', 'aviso'], true)) {
                    $error = (string) $m['mensaje'];
                }
            }
            if ($codigo >= 400) {
                $resultado = "con error (HTTP {$codigo})";
            } elseif ($error !== null) {
                $resultado = 'con error: ' . mb_substr($error, 0, 150);
            } else {
                $exito = end($nuevos);
                $resultado = 'correcto' . ($exito ? ': ' . mb_substr((string) $exito['mensaje'], 0, 150) : '');
            }
            $partes = explode('\\', $clase);
            $casoUso = end($partes);
            $modulo = strtolower($partes[2] ?? 'sistema');   // App\Paquetes\<Paquete>\CasosUso\CUxx
            $usuario = Autenticacion::usuario() ?? $usuarioAntes;
            self::registrar('operacion', $modulo, "POST {$ruta} → {$casoUso}::{$metodo} · {$resultado}", $usuario);
        } catch (Throwable $e) {
            error_log('[bitacora] ' . $e->getMessage());
        }
    }
}
