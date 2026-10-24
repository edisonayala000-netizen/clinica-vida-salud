<?php
namespace App\Paquetes\Atencion\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Paquetes\Atencion\Modelos\RecetaModelo;
use App\Paquetes\Pacientes\Modelos\PacienteModelo;
use Throwable;

/**
 * CU17 · Consultar mis recetas (portal del paciente)
 * Requisito: RF12 · Actor: Paciente
 *
 * Flujo principal:
 *  1. El paciente abre «Mis recetas».
 *  2. El sistema muestra sus recetas (de la más reciente a la más antigua) con el médico, el
 *     diagnóstico y cada medicamento con su horario, las tomas que faltan y la próxima.
 *  3. El paciente puede imprimir una receta o pedir que se la reenvíen por correo (CU16).
 * Flujo alternativo:
 *  3a. El paciente activa o desactiva el recordatorio de un medicamento; el trigger
 *      fn_tg_programar_tomas anula o vuelve a programar sus tomas pendientes.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: atencion
 * Actores.........: Paciente
 * Requisitos......: RF12
 * Base de datos...: vista v_receta_detalle, receta_detalle (recordar), notificacion, paciente (recordar_medicamentos)
 * Rutas...........: GET /mis-recetas · POST /mis-recetas/{id}/recordar (portal.recetas)
 * Vistas..........: frontend/vistas/atencion/mis_recetas.php
 * Usa.............: RecetaModelo, PacienteModelo
 * Precondición....: La cuenta del paciente está vinculada a su registro de paciente.
 * Postcondición...: El paciente conoce su tratamiento; sus recordatorios quedan como los eligió.
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU17ConsultarRecetas extends Controlador
{
    /** GET /mis-recetas */
    public function listar(): void
    {
        $id = $this->idPaciente();
        $this->vista('atencion/mis_recetas', [
            'titulo'   => 'Mis recetas',
            'recetas'  => (new RecetaModelo())->delPaciente($id),
            'paciente' => (new PacienteModelo())->obtener($id),
        ]);
    }

    /** POST /mis-recetas/{id}/recordar — paso 3a ({id} = medicamento de la receta) */
    public function cambiarRecordar(int $id): void
    {
        try {
            $idPaciente = $this->idPaciente();
            $recetas = new RecetaModelo();
            $detalle = $recetas->detalleDelPaciente($id, $idPaciente) ?? throw new ErrorNegocio('Ese medicamento no está en sus recetas.');
            $activar = Peticion::entrada('recordar') === '1';
            BaseDatos::transaccion(fn () => $recetas->cambiarRecordar($id, $idPaciente, $activar));
            $nombre = trim($detalle['medicamento'] . ' ' . ($detalle['concentracion'] ?? ''));
            $this->exito($activar ? "Le recordaremos cada toma de {$nombre}." : "Ya no le enviaremos recordatorios de {$nombre}.",
                '/mis-recetas');
        } catch (Throwable $e) {
            $this->volverConError($e, '/mis-recetas');
        }
    }

    private function idPaciente(): int
    {
        $id = (int) ($this->usuario()['id_paciente'] ?? 0);
        if (!$id) {
            throw new ErrorNegocio('Su cuenta no está vinculada a un paciente.');
        }
        return $id;
    }
}
