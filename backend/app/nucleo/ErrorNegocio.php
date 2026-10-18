<?php
namespace App\Nucleo;

use RuntimeException;

/**
 * Excepción para reglas de negocio o datos inválidos.
 * Su mensaje SÍ se muestra al usuario (es un mensaje explicativo).
 * $errores guarda los errores por campo del formulario.
 */
final class ErrorNegocio extends RuntimeException
{
    /** @param array<string,string> $errores */
    public function __construct(string $mensaje, private array $errores = [])
    {
        parent::__construct($mensaje);
    }

    /** @return array<string,string> */
    public function errores(): array
    {
        return $this->errores;
    }
}
