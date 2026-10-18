<?php
namespace App\Nucleo;

/**
 * Punto de arranque del sistema.
 *
 * 1. Registra el autocargador de clases.
 * 2. Carga la configuración y la zona horaria.
 * 3. Inicia la sesión segura.
 * 4. Registra las rutas de cada paquete y despacha la petición.
 */
final class Aplicacion
{
    /** @var array<string,mixed> */
    private static array $config = [];

    public static function config(string $clave, mixed $porDefecto = null): mixed
    {
        $valor = self::$config;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $porDefecto;
            }
            $valor = $valor[$parte];
        }
        return $valor;
    }

    /** Ruta absoluta a la carpeta backend/. */
    public static function raiz(string $sub = ''): string
    {
        return dirname(__DIR__, 2) . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /**
     * Ruta absoluta dentro del proyecto completo (carpeta que contiene backend/ y frontend/).
     * Ej.: proyecto('frontend/vistas') -> C:/xampp/htdocs/clinica-vida-salud/frontend/vistas
     */
    public static function proyecto(string $sub = ''): string
    {
        return dirname(__DIR__, 3) . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /**
     * Autocargador: App\Paquetes\Citas\CasosUso\CU09ProgramarCita
     *   -> app/paquetes/citas/casos_uso/CU09ProgramarCita.php
     * Los segmentos del namespace en CamelCase se convierten a snake_case.
     */
    public static function registrarAutocargador(): void
    {
        spl_autoload_register(static function (string $clase): void {
            if (!str_starts_with($clase, 'App\\')) {
                return;
            }
            $partes = explode('\\', substr($clase, 4));
            $archivo = array_pop($partes);
            $carpetas = array_map(
                static fn (string $p): string => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $p)),
                $partes
            );
            $ruta = self::raiz('app/' . implode('/', $carpetas) . '/' . $archivo . '.php');
            if (is_file($ruta)) {
                require $ruta;
            }
        });
    }

    /** Prepara el entorno (se usa también desde las pruebas por consola). */
    public static function iniciar(): void
    {
        self::registrarAutocargador();
        self::$config = require self::raiz('config/config.php');
        date_default_timezone_set(self::config('app.zona_horaria', 'America/La_Paz'));
        mb_internal_encoding('UTF-8');
        require_once __DIR__ . '/ayudantes.php';
    }

    /** Atiende una petición HTTP completa. */
    public static function ejecutar(): void
    {
        self::iniciar();
        ManejadorErrores::registrar();
        Sesion::iniciar();

        $enrutador = new Enrutador();
        foreach (glob(self::raiz('app/paquetes/*/rutas.php')) as $archivoRutas) {
            $registrar = require $archivoRutas;
            $registrar($enrutador);
        }

        $enrutador->despachar(Peticion::metodo(), Peticion::ruta());
    }
}
