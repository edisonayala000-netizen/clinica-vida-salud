<?php
namespace App\Paquetes\Atencion\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Atencion\Modelos\MedicamentoModelo;
use Throwable;

/**
 * CU19 · Gestionar el catálogo de medicamentos
 * Requisito: RF12 · Actor: Administrador (o el rol al que se le dé el permiso medicamentos.gestionar)
 *
 * Flujo principal:
 *  1. El usuario abre «Medicamentos» y busca por nombre.
 *  2. Registra un medicamento: nombre genérico, presentación y concentración.
 *  3. El sistema verifica que no exista otro igual (sin importar tildes ni mayúsculas) y lo guarda.
 * Flujos alternativos:
 *  2a. Modifica un medicamento existente.
 *  2b. Lo desactiva: ya no aparece al recetar, pero las recetas anteriores lo conservan.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: atencion
 * Actores.........: Administrador
 * Requisitos......: RF12
 * Base de datos...: medicamento (índice único uq_medicamento sobre el nombre normalizado, presentación y
 *                   concentración; CHECK de presentación)
 * Rutas...........: GET /medicamentos · POST /medicamentos · POST /medicamentos/{id} · POST /medicamentos/{id}/estado
 *                   (medicamentos.gestionar)
 * Vistas..........: frontend/vistas/atencion/medicamentos.php
 * Usa.............: MedicamentoModelo
 * Postcondición...: El catálogo con el que recetan los médicos queda actualizado.
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU19GestionarMedicamentos extends Controlador
{
    private MedicamentoModelo $medicamentos;

    public function __construct()
    {
        $this->medicamentos = new MedicamentoModelo();
    }

    /** GET /medicamentos */
    public function listar(): void
    {
        $q = Peticion::entrada('q');
        $estado = in_array(Peticion::entrada('estado'), ['activo', 'inactivo'], true) ? Peticion::entrada('estado') : null;
        $this->vista('atencion/medicamentos', [
            'titulo'         => 'Medicamentos',
            'medicamentos'   => $this->medicamentos->listar($q, $estado),
            'presentaciones' => MedicamentoModelo::PRESENTACIONES,
            'filtros'        => compact('q', 'estado'),
        ]);
    }

    /** POST /medicamentos — registrar */
    public function crear(): void
    {
        try {
            $d = $this->validar(null);
            BaseDatos::transaccion(fn () => $this->medicamentos->crear($d));
            $this->exito("Medicamento «{$d['nombre']}» registrado.", '/medicamentos');
        } catch (Throwable $e) {
            $this->volverConError($e, '/medicamentos');
        }
    }

    /** POST /medicamentos/{id} — modificar */
    public function actualizar(int $id): void
    {
        try {
            $this->medicamentos->obtener($id) ?? throw new ErrorNegocio('El medicamento no existe.');
            $d = $this->validar($id);
            BaseDatos::transaccion(fn () => $this->medicamentos->actualizar($id, $d));
            $this->exito("Medicamento «{$d['nombre']}» actualizado.", '/medicamentos');
        } catch (Throwable $e) {
            $this->volverConError($e, '/medicamentos');
        }
    }

    /** POST /medicamentos/{id}/estado — activar o desactivar */
    public function cambiarEstado(int $id): void
    {
        try {
            $m = $this->medicamentos->obtener($id) ?? throw new ErrorNegocio('El medicamento no existe.');
            $nuevo = $m['estado'] === 'activo' ? 'inactivo' : 'activo';
            BaseDatos::transaccion(fn () => $this->medicamentos->cambiarEstado($id, $nuevo));
            $this->exito("Medicamento «{$m['nombre']}» " . ($nuevo === 'activo' ? 'activado.' : 'desactivado: ya no aparece al recetar.'),
                '/medicamentos');
        } catch (Throwable $e) {
            $this->volverConError($e, '/medicamentos');
        }
    }

    private function validar(?int $id): array
    {
        $d = Validador::validar($_POST, [
            'nombre'        => ['Nombre genérico', 'requerido|texto:3:80'],
            'presentacion'  => ['Presentación', 'requerido|en:' . implode(',', MedicamentoModelo::PRESENTACIONES)],
            'concentracion' => ['Concentración', 'opcional|texto:1:40'],
        ]);
        $d['nombre'] = mb_strtoupper(mb_substr($d['nombre'], 0, 1)) . mb_substr($d['nombre'], 1);
        if ($this->medicamentos->existe($d['nombre'], $d['presentacion'], $d['concentracion'], $id)) {
            throw new ErrorNegocio('Ya existe ese medicamento con la misma presentación y concentración.');
        }
        return $d;
    }
}
