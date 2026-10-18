<?php
namespace App\Nucleo;

/**
 * Enrutador simple: relaciona "MÉTODO /ruta/{id}" con el caso de uso
 * (clase y método) que la atiende y con el permiso que exige.
 *
 * Acceso:
 *   'publico'      -> cualquiera (login, registro de paciente)
 *   'autenticado'  -> cualquier usuario con sesión
 *   'codigo'       -> el usuario debe tener ese permiso
 *   ['a','b']      -> basta con tener uno de esos permisos
 *
 * Cada petición POST queda en la bitácora (Bitacora::registrarOperacion).
 */
final class Enrutador
{
    /** @var array<int,array{metodo:string,patron:string,accion:array,acceso:string|array}> */
    private array $rutas = [];

    public function get(string $ruta, array $accion, string|array $acceso): void
    {
        $this->agregar('GET', $ruta, $accion, $acceso);
    }

    public function post(string $ruta, array $accion, string|array $acceso): void
    {
        $this->agregar('POST', $ruta, $accion, $acceso);
    }

    private function agregar(string $metodo, string $ruta, array $accion, string|array $acceso): void
    {
        $patron = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[0-9]+)', rtrim($ruta, '/') ?: '/') . '$#';
        $this->rutas[] = compact('metodo', 'patron', 'accion', 'acceso');
    }

    public function despachar(string $metodo, string $ruta): void
    {
        $ruta = rtrim($ruta, '/') ?: '/';
        foreach ($this->rutas as $r) {
            if ($r['metodo'] !== $metodo || !preg_match($r['patron'], $ruta, $coincidencias)) {
                continue;
            }

            // Bitácora (CU02): toda petición POST se registra al terminar, con su resultado.
            if ($metodo === 'POST') {
                $usuarioAntes = Autenticacion::usuario();
                $mensajesAntes = count($_SESSION['flash'] ?? []);
                register_shutdown_function(static fn () => Bitacora::registrarOperacion(
                    $ruta, $r['accion'][0], $r['accion'][1], $usuarioAntes, $mensajesAntes
                ));
            }

            // Control de acceso ANTES de ejecutar el caso de uso (RF01, RN12)
            if ($r['acceso'] !== 'publico') {
                Autenticacion::exigirSesion();
                if ($r['acceso'] !== 'autenticado') {
                    Autenticacion::exigirPermiso($r['acceso']);
                }
            }
            // Protección CSRF en todo formulario enviado por POST
            if ($metodo === 'POST') {
                Csrf::validar();
            }

            $parametros = array_map('intval', array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY));
            [$clase, $accion] = $r['accion'];
            (new $clase())->$accion(...$parametros);
            return;
        }

        http_response_code(404);
        Vista::mostrar('compartido/error', [
            'titulo'  => 'Página no encontrada',
            'codigo'  => 404,
            'mensaje' => 'La dirección que escribió no existe en el sistema.',
        ], Autenticacion::hayUsuario() ? 'principal' : 'simple');
    }
}
