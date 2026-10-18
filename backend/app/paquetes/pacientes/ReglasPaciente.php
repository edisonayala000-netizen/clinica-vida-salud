<?php
namespace App\Paquetes\Pacientes;

use App\Nucleo\ErrorNegocio;
use App\Nucleo\Validador;

/**
 * Reglas de validación del paciente, compartidas por CU03 (recepción)
 * y CU04 (perfil del paciente) para que ambos validen igual.
 */
final class ReglasPaciente
{
    public const TIPOS_SANGRE = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** Datos personales y de contacto (RF02). */
    public static function datosPersonales(array $entrada, bool $ciObligatorio): array
    {
        $d = Validador::validar($entrada, [
            'ci'               => ['CI', ($ciObligatorio ? 'requerido' : 'opcional') . '|ci'],
            'nombres'          => ['Nombres', 'requerido|nombre|texto:2:60'],
            'apellidos'        => ['Apellidos', 'requerido|nombre|texto:2:60'],
            'fecha_nacimiento' => ['Fecha de nacimiento', 'requerido|fecha|no_futura'],
            'sexo'             => ['Sexo', 'requerido|en:femenino,masculino,otro'],
            'telefono'         => ['Teléfono', 'requerido|telefono'],
            'correo'           => ['Correo', 'opcional|email|texto:5:120'],
            'direccion'        => ['Dirección', 'opcional|texto:3:200'],
        ]);
        $d['nombres'] = self::titulo($d['nombres']);
        $d['apellidos'] = self::titulo($d['apellidos']);
        return $d;
    }

    /** Información médica del perfil (punto 3.4 del caso). */
    public static function informacionMedica(array $entrada): array
    {
        return Validador::validar($entrada, [
            'tipo_sangre'           => ['Tipo de sangre', 'opcional|en:' . implode(',', self::TIPOS_SANGRE)],
            'alergias'              => ['Alergias', 'opcional|texto:2:250'],
            'enfermedades_cronicas' => ['Enfermedades crónicas', 'opcional|texto:2:250'],
            'medicamentos'          => ['Medicamentos', 'opcional|texto:2:250'],
            'antecedentes'          => ['Antecedentes', 'opcional|texto:2:500'],
        ]);
    }

    /** Contacto de emergencia: si se llena uno de sus datos, se exigen los tres. */
    public static function contactoEmergencia(array $entrada): ?array
    {
        $c = [
            'nombre'     => trim((string) ($entrada['contacto_nombre'] ?? '')),
            'parentesco' => trim((string) ($entrada['contacto_parentesco'] ?? '')),
            'telefono'   => trim((string) ($entrada['contacto_telefono'] ?? '')),
        ];
        if ($c['nombre'] === '' && $c['parentesco'] === '' && $c['telefono'] === '') {
            return null;
        }
        $v = Validador::validar([
            'contacto_nombre' => $c['nombre'], 'contacto_parentesco' => $c['parentesco'], 'contacto_telefono' => $c['telefono'],
        ], [
            'contacto_nombre'     => ['Nombre del contacto', 'requerido|nombre|texto:3:120'],
            'contacto_parentesco' => ['Parentesco', 'requerido|texto:3:40'],
            'contacto_telefono'   => ['Teléfono del contacto', 'requerido|telefono'],
        ]);
        return ['nombre' => self::titulo($v['contacto_nombre']), 'parentesco' => $v['contacto_parentesco'], 'telefono' => $v['contacto_telefono']];
    }

    /** "maría  LÓPEZ" -> "María López" */
    public static function titulo(string $texto): string
    {
        return mb_convert_case(mb_strtolower(trim($texto)), MB_CASE_TITLE, 'UTF-8');
    }

    public static function exigirCiUnico(bool $existe): void
    {
        if ($existe) {
            throw new ErrorNegocio('Ya existe un paciente registrado con ese CI.', ['ci' => 'Este CI ya pertenece a otro paciente.']);
        }
    }
}
