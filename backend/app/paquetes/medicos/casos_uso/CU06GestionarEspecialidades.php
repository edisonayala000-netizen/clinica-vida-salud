<?php
namespace App\Paquetes\Medicos\CasosUso;

use App\Nucleo\BaseDatos;
use App\Nucleo\Controlador;
use App\Nucleo\ErrorNegocio;
use App\Nucleo\Peticion;
use App\Nucleo\Validador;
use App\Paquetes\Medicos\Modelos\EspecialidadModelo;
use Throwable;

/**
 * CU06 · Gestionar especialidades médicas
 * Requisito: RF04 · Actor: Administrador
 *
 * Reglas:
 *  - Cada especialidad tiene identificador, nombre, descripción opcional y estado.
 *  - No se permiten dos especialidades con el mismo nombre normalizado
 *    ("Pediatría" = "pediatria" = "  PEDIATRÍA ").
 *  - No se desactiva una especialidad con citas pendientes.
 *
 * ──────────────── Ficha del caso de uso ────────────────
 * Paquete.........: medicos
 * Actores.........: Administrador
 * Requisitos......: RF04
 * Base de datos...: especialidad (índice único uq_especialidad_nombre_normalizado sobre
 *                   fn_normalizar_texto(nombre))
 * Rutas...........: GET /especialidades (especialidades.gestionar) · POST /especialidades
 *                   (especialidades.gestionar) · POST /especialidades/{id} (especialidades.gestionar) · POST
 *                   /especialidades/{id}/estado (especialidades.gestionar)
 * Vistas..........: frontend/vistas/medicos/especialidades.php
 * Usa.............: EspecialidadModelo
 * Precondición....: El administrador inició sesión y tiene el permiso «especialidades.gestionar».
 * Postcondición...: La especialidad queda registrada o actualizada; las desactivadas conservan sus citas
 *                   históricas.
 * Flujo...........: rutas.php -> este archivo -> modelos/servicios -> PostgreSQL -> vista en
 *                   frontend/vistas/medicos/
 * Diagramas.......: doc/staruml (clases de diseño) y doc/Grupo13_SI1_Clinica_Vida_Salud.docx (6.2 secuencia,
 *                   7.3 subsistema)
 * Estado..........: IMPLEMENTADO y probado.
 */
final class CU06GestionarEspecialidades extends Controlador
{
    private EspecialidadModelo $especialidades;

    public function __construct()
    {
        $this->especialidades = new EspecialidadModelo();
    }

    /** GET /especialidades */
    public function listar(): void
    {
        $this->vista('medicos/especialidades', [
            'titulo'         => 'Especialidades',
            'especialidades' => $this->especialidades->listar(Peticion::entrada('estado')),
            'estado'         => Peticion::entrada('estado'),
        ]);
    }

    /** POST /especialidades — registrar */
    public function crear(): void
    {
        try {
            $d = $this->validar(null);
            BaseDatos::transaccion(fn () => $this->especialidades->crear($d['nombre'], $d['descripcion']));
            $this->exito("Especialidad «{$d['nombre']}» registrada.", '/especialidades');
        } catch (Throwable $e) {
            $this->volverConError($e, '/especialidades');
        }
    }

    /** POST /especialidades/{id} — modificar */
    public function actualizar(int $id): void
    {
        try {
            $this->especialidades->obtener($id) ?? throw new ErrorNegocio('La especialidad no existe.');
            $d = $this->validar($id);
            BaseDatos::transaccion(fn () => $this->especialidades->actualizar($id, $d['nombre'], $d['descripcion']));
            $this->exito("Especialidad «{$d['nombre']}» actualizada.", '/especialidades');
        } catch (Throwable $e) {
            $this->volverConError($e, '/especialidades');
        }
    }

    /** POST /especialidades/{id}/estado — activar o desactivar */
    public function cambiarEstado(int $id): void
    {
        try {
            $esp = $this->especialidades->obtener($id) ?? throw new ErrorNegocio('La especialidad no existe.');
            $nuevo = $esp['estado'] === 'activo' ? 'inactivo' : 'activo';
            if ($nuevo === 'inactivo' && ($n = $this->especialidades->citasPendientes($id)) > 0) {
                throw new ErrorNegocio("No se puede desactivar «{$esp['nombre']}»: tiene {$n} cita(s) pendiente(s). Cancélelas o atiéndalas primero.");
            }
            BaseDatos::transaccion(fn () => $this->especialidades->cambiarEstado($id, $nuevo));
            $this->exito("Especialidad «{$esp['nombre']}» " . ($nuevo === 'activo' ? 'activada.' : 'desactivada.'), '/especialidades');
        } catch (Throwable $e) {
            $this->volverConError($e, '/especialidades');
        }
    }

    private function validar(?int $id): array
    {
        $d = Validador::validar($_POST, [
            'nombre'      => ['Nombre', 'requerido|nombre|texto:3:80'],
            'descripcion' => ['Descripción', 'opcional|texto:0:250'],
        ]);
        // Se guarda con la primera letra en mayúscula: "pediatría" -> "Pediatría"
        $d['nombre'] = mb_strtoupper(mb_substr($d['nombre'], 0, 1)) . mb_substr($d['nombre'], 1);
        if ($this->especialidades->existeNombre($d['nombre'], $id)) {
            throw new ErrorNegocio('Ya existe una especialidad con ese nombre (no se distinguen tildes, mayúsculas ni espacios).');
        }
        return $d;
    }
}
