<?php
/**
 * Funciones de ayuda para las vistas (capa de interfaz).
 */

use App\Nucleo\Aplicacion;
use App\Nucleo\Csrf;
use App\Nucleo\Peticion;

/** Escapa texto para HTML (evita XSS). Úsela SIEMPRE al imprimir datos. */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta dentro del sistema: url('/citas/nueva'). */
function url(string $ruta = '/', array $parametros = []): string
{
    $u = Peticion::base() . '/' . ltrim($ruta, '/');
    if ($parametros) {
        $u .= '?' . http_build_query(array_filter($parametros, static fn ($v) => $v !== null && $v !== ''));
    }
    return $u === '' ? '/' : $u;
}

/** URL de un archivo estático de frontend/assets con versión para caché. */
function recurso(string $archivo): string
{
    $fisico = Aplicacion::proyecto('frontend/assets/' . $archivo);
    $v = is_file($fisico) ? filemtime($fisico) : 1;
    return Peticion::base() . '/assets/' . $archivo . '?v=' . $v;
}

function csrf_campo(): string
{
    return Csrf::campo();
}

/** Valor anterior del formulario (si hubo error) o el valor actual. */
function viejo(array $anterior, string $campo, mixed $actual = ''): string
{
    return e($anterior[$campo] ?? $actual);
}

/** Mensaje de error debajo de un campo. */
function error_campo(array $errores, string $campo): string
{
    return isset($errores[$campo])
        ? '<p class="campo-error" role="alert">' . e($errores[$campo]) . '</p>'
        : '';
}

function clase_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' con-error' : '';
}

function seleccionado(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function marcado(bool $condicion): string
{
    return $condicion ? ' checked' : '';
}

const DIAS_SEMANA = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** 2026-11-15 -> 15/11/2026 */
function fecha_corta(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }
    $t = strtotime($fecha);
    return $t ? date('d/m/Y', $t) : '';
}

/** 2026-11-15 -> lunes 15 de noviembre de 2026 */
function fecha_larga(?string $fecha, bool $conAnio = true): string
{
    if (!$fecha) {
        return '';
    }
    $t = strtotime($fecha);
    $dia = mb_strtolower(DIAS_SEMANA[(int) date('N', $t)]);
    return sprintf('%s %d de %s%s', $dia, date('j', $t), MESES[(int) date('n', $t)], $conAnio ? ' de ' . date('Y', $t) : '');
}

function fecha_hora(?string $valor): string
{
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
}

/** 09:00:00 -> 09:00 */
function hora(?string $h): string
{
    return $h ? substr($h, 0, 5) : '';
}

function edad(?string $fechaNacimiento): string
{
    if (!$fechaNacimiento) {
        return '';
    }
    $anios = (new DateTime($fechaNacimiento))->diff(new DateTime('today'))->y;
    return $anios === 1 ? '1 año' : "$anios años";
}

/** Etiqueta de color para el estado de una cita. */
function estado_cita(string $estado): string
{
    $textos = [
        'programada'   => 'Programada',
        'confirmada'   => 'Confirmada',
        'atendida'     => 'Atendida',
        'cancelada'    => 'Cancelada',
        'inasistencia' => 'Inasistencia',
    ];
    return '<span class="estado estado-' . e($estado) . '">' . e($textos[$estado] ?? $estado) . '</span>';
}

function estado_registro(string $estado): string
{
    return '<span class="estado estado-' . ($estado === 'activo' ? 'activo' : 'inactivo') . '">'
        . ($estado === 'activo' ? 'Activo' : ucfirst(e($estado))) . '</span>';
}

/** Ícono SVG del sprite frontend/assets/img/iconos.svg */
function icono(string $nombre, string $clase = ''): string
{
    return '<svg class="ico ' . e($clase) . '" aria-hidden="true"><use href="'
        . e(Peticion::base() . '/assets/img/iconos.svg#' . $nombre) . '"></use></svg>';
}

/** Iniciales para el avatar: "María López" -> "ML" */
function iniciales(string $nombre): string
{
    $nombre = preg_replace('/^(Dr|Dra|Dr\(a\))\.?\s+/u', '', $nombre);
    $partes = preg_split('/\s+/u', trim($nombre));
    $ini = mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1);
    return mb_strtoupper($ini);
}

/** Saludo según la hora del día. */
function saludo(): string
{
    $h = (int) date('G');
    return $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
}
