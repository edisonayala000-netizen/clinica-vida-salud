<?php
namespace App\Nucleo;

/**
 * Respuestas HTTP: redirección, JSON y descarga CSV.
 */
final class Respuesta
{
    public static function redirigir(string $ruta): never
    {
        header('Location: ' . url($ruta));
        exit;
    }

    public static function json(array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Descarga un CSV (RF13: exportación opcional). Se usa ";" como
     * separador y BOM UTF-8 para que Excel muestre bien las tildes.
     *
     * @param array<int,string> $encabezados
     * @param array<int,array<int,mixed>> $filas
     */
    public static function csv(string $nombreArchivo, array $encabezados, array $filas): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezados, ';');
        foreach ($filas as $fila) {
            fputcsv($salida, $fila, ';');
        }
        fclose($salida);
        exit;
    }
}
