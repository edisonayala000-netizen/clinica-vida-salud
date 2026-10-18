<?php
namespace App\Nucleo;

/**
 * Capa de presentación: arma la página HTML con una plantilla del
 * paquete y un diseño (layout) común.
 *
 *   Vista::mostrar('citas/programar', [...])
 *     -> frontend/vistas/citas/programar.php dentro de frontend/vistas/compartido/layout_principal.php
 */
final class Vista
{
    public static function mostrar(string $plantilla, array $datos = [], string $layout = 'principal'): void
    {
        echo self::generar($plantilla, $datos, $layout);
    }

    public static function generar(string $plantilla, array $datos = [], string $layout = 'principal'): string
    {
        $archivo = self::resolver($plantilla);
        [$anterior, $errores] = Sesion::tomarFormulario();
        $datos += ['anterior' => $anterior, 'errores' => $errores];

        $contenido = self::incluir($archivo, $datos);
        if ($layout === 'ninguno') {
            return $contenido;
        }
        $datos['contenido'] = $contenido;
        $datos['mensajes'] = Sesion::tomarFlash();
        return self::incluir(Aplicacion::proyecto("frontend/vistas/compartido/layout_{$layout}.php"), $datos);
    }

    /** Fragmento sin layout (componentes reutilizables). */
    public static function parcial(string $plantilla, array $datos = []): string
    {
        return self::incluir(self::resolver($plantilla), $datos);
    }

    private static function resolver(string $plantilla): string
    {
        // 'citas/programar' -> frontend/vistas/citas/programar.php ; 'compartido/menu' -> frontend/vistas/compartido/menu.php
        [$paquete, $nombre] = explode('/', $plantilla, 2);
        return Aplicacion::proyecto("frontend/vistas/{$paquete}/{$nombre}.php");
    }

    private static function incluir(string $__archivo, array $__datos): string
    {
        extract($__datos, EXTR_SKIP);
        ob_start();
        require $__archivo;
        return (string) ob_get_clean();
    }
}
