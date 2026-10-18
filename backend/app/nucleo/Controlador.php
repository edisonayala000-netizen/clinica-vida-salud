<?php
namespace App\Nucleo;

use Throwable;

/**
 * Clase base de todos los casos de uso (clases de CONTROL).
 * Reúne las tareas repetidas: mostrar vistas, redirigir, responder JSON
 * y convertir errores en mensajes para el usuario.
 */
abstract class Controlador
{
    protected function vista(string $plantilla, array $datos = []): void
    {
        Vista::mostrar($plantilla, $datos);
    }

    protected function redirigir(string $ruta): never
    {
        Respuesta::redirigir($ruta);
    }

    protected function json(array $datos, int $codigo = 200): never
    {
        Respuesta::json($datos, $codigo);
    }

    protected function exito(string $mensaje, string $ruta): never
    {
        Sesion::flash('exito', $mensaje);
        $this->redirigir($ruta);
    }

    /**
     * Ante un error: guarda los datos del formulario y vuelve a mostrarlo
     * con el mensaje explicativo (los errores técnicos no se muestran).
     */
    protected function volverConError(Throwable $e, string $ruta): never
    {
        $error = ManejadorErrores::traducir($e);
        Sesion::guardarFormulario($_POST, $error->errores());
        Sesion::flash('error', $error->getMessage());
        $this->redirigir($ruta);
    }

    /** Usuario con sesión (siempre existe dentro de rutas protegidas). */
    protected function usuario(): array
    {
        return Autenticacion::usuario() ?? [];
    }

    protected function puede(string|array $permiso): bool
    {
        return Autenticacion::puede($permiso);
    }

    /** Recurso inexistente -> página 404 */
    protected function noEncontrado(string $que = 'El registro'): never
    {
        http_response_code(404);
        Vista::mostrar('compartido/error', [
            'titulo'  => 'No encontrado',
            'codigo'  => 404,
            'mensaje' => "$que que busca no existe o fue retirado.",
        ]);
        exit;
    }
}
