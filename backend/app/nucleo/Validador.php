<?php
namespace App\Nucleo;

use DateTimeImmutable;

/**
 * Validación de datos en el SERVIDOR (requerimiento técnico 7).
 * JavaScript valida en el navegador para comodidad del usuario, pero
 * la decisión final siempre se toma aquí: los datos que llegan pueden
 * haber sido alterados.
 *
 * Uso:
 *   $datos = Validador::validar($_POST, [
 *       'nombres' => ['Nombres', 'requerido|nombre|texto:2:60'],
 *       'correo'  => ['Correo',  'opcional|email'],
 *   ]);
 * Devuelve los datos limpios o lanza ErrorNegocio con los errores por campo.
 */
final class Validador
{
    /**
     * @param array<string,mixed> $entrada
     * @param array<string,array{0:string,1:string}> $reglas
     * @return array<string,mixed>
     */
    public static function validar(array $entrada, array $reglas): array
    {
        $limpio = [];
        $errores = [];

        foreach ($reglas as $campo => [$etiqueta, $definicion]) {
            $valor = $entrada[$campo] ?? null;
            if (is_string($valor)) {
                $valor = trim(preg_replace('/\s+/u', ' ', $valor));
            }
            $lista = explode('|', $definicion);
            $vacio = $valor === null || $valor === '' || $valor === [];

            if ($vacio) {
                if (in_array('requerido', $lista, true)) {
                    $errores[$campo] = "$etiqueta es un dato obligatorio.";
                } elseif (in_array('booleano', $lista, true)) {
                    $limpio[$campo] = false;
                } else {
                    $limpio[$campo] = null;
                }
                continue;
            }

            foreach ($lista as $regla) {
                $partes = explode(':', $regla);
                $nombre = array_shift($partes);
                $error = self::aplicar($nombre, $partes, $valor, $etiqueta);
                if ($error !== null) {
                    $errores[$campo] = $error;
                    break;
                }
            }
            $limpio[$campo] = $valor;
        }

        if ($errores) {
            throw new ErrorNegocio('Revise los datos marcados en el formulario.', $errores);
        }
        return $limpio;
    }

    /** Aplica una regla; puede normalizar $valor. Devuelve el error o null. */
    private static function aplicar(string $regla, array $p, mixed &$valor, string $etiqueta): ?string
    {
        switch ($regla) {
            case 'requerido':
            case 'opcional':
                return null;

            case 'texto':
                $largo = mb_strlen((string) $valor);
                $min = (int) ($p[0] ?? 0);
                $max = (int) ($p[1] ?? 255);
                return ($largo < $min || $largo > $max)
                    ? "$etiqueta debe tener entre $min y $max caracteres." : null;

            case 'nombre':
                return preg_match("/^[\p{L}][\p{L} .'-]*$/u", (string) $valor)
                    ? null : "$etiqueta solo admite letras y espacios.";

            case 'email':
                $valor = mb_strtolower((string) $valor);
                return filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : "$etiqueta no es un correo válido.";

            case 'telefono':
                return preg_match('/^[0-9+\-\s]{7,20}$/', (string) $valor)
                    ? null : "$etiqueta debe tener entre 7 y 20 dígitos.";

            case 'ci':
                $valor = mb_strtoupper(str_replace(' ', '', (string) $valor));
                return preg_match('/^[0-9]{5,12}([-A-Z0-9]{1,3})?$/', $valor)
                    ? null : "$etiqueta debe tener de 5 a 12 dígitos (puede llevar complemento, ej. 1234567-1B).";

            case 'usuario':
                $valor = mb_strtolower((string) $valor);
                return preg_match('/^[a-z0-9._]{4,40}$/', $valor)
                    ? null : "$etiqueta: de 4 a 40 caracteres (letras minúsculas, números, punto o guion bajo).";

            case 'clave':
                $c = (string) $valor;
                return (strlen($c) >= 8 && strlen($c) <= 64 && preg_match('/[A-Za-z]/', $c) && preg_match('/\d/', $c))
                    ? null : "$etiqueta debe tener de 8 a 64 caracteres, con letras y números.";

            case 'entero':
                if (!preg_match('/^-?\d+$/', (string) $valor)) {
                    return "$etiqueta debe ser un número entero.";
                }
                $valor = (int) $valor;
                if (isset($p[0]) && $valor < (int) $p[0]) {
                    return "$etiqueta no puede ser menor que {$p[0]}.";
                }
                if (isset($p[1]) && $valor > (int) $p[1]) {
                    return "$etiqueta no puede ser mayor que {$p[1]}.";
                }
                return null;

            case 'en':
                $opciones = explode(',', $p[0] ?? '');
                return in_array((string) $valor, $opciones, true) ? null : "$etiqueta tiene un valor no permitido.";

            case 'fecha':
                $f = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valor);
                return ($f && $f->format('Y-m-d') === $valor) ? null : "$etiqueta no es una fecha válida.";

            case 'no_futura':
                return ((string) $valor <= date('Y-m-d') && (string) $valor >= '1900-01-01')
                    ? null : "$etiqueta no puede ser una fecha futura.";

            case 'no_pasada':
                return (string) $valor >= date('Y-m-d') ? null : "$etiqueta no puede ser una fecha pasada.";

            case 'hora':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:00)?$/', (string) $valor)) {
                    return "$etiqueta no es una hora válida (HH:MM).";
                }
                $valor = substr((string) $valor, 0, 5);
                return null;

            case 'booleano':
                $valor = in_array($valor, ['1', 1, true, 'on', 'si', 'true'], true);
                return null;

            default:
                return null;
        }
    }
}
