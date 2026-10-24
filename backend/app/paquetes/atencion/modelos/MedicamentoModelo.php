<?php
namespace App\Paquetes\Atencion\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de MEDICAMENTO: catálogo que se usa al recetar (CU19).
 */
final class MedicamentoModelo
{
    public const PRESENTACIONES = ['tableta', 'cápsula', 'jarabe', 'suspensión', 'gotas', 'inyectable', 'crema',
                                   'pomada', 'inhalador', 'óvulo', 'sobre', 'solución'];

    public function listar(?string $texto = null, ?string $estado = null): array
    {
        $sql = "SELECT m.*, (SELECT count(*) FROM receta_detalle d WHERE d.id_medicamento = m.id_medicamento) AS recetado
                  FROM medicamento m WHERE 1 = 1";
        $p = [];
        if ($texto) {
            $sql .= " AND fn_normalizar_texto(m.nombre) LIKE '%' || fn_normalizar_texto(:t) || '%'";
            $p['t'] = $texto;
        }
        if ($estado) {
            $sql .= " AND m.estado = :e";
            $p['e'] = $estado;
        }
        return BaseDatos::todos($sql . " ORDER BY m.estado, m.nombre, m.presentacion, m.concentracion", $p);
    }

    /** @return array<int,array<string,mixed>> activos, para elegir al recetar */
    public function activos(): array
    {
        return BaseDatos::todos(
            "SELECT id_medicamento, nombre, presentacion, concentracion FROM medicamento
              WHERE estado = 'activo' ORDER BY nombre, presentacion, concentracion"
        );
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno("SELECT * FROM medicamento WHERE id_medicamento = :id", ['id' => $id]);
    }

    /** ¿Ya existe ese medicamento (mismo nombre normalizado, presentación y concentración)? */
    public function existe(string $nombre, string $presentacion, ?string $concentracion, ?int $excluirId = null): bool
    {
        return (bool) BaseDatos::valor(
            "SELECT EXISTS (SELECT 1 FROM medicamento
                             WHERE fn_normalizar_texto(nombre) = fn_normalizar_texto(:n) AND presentacion = :p
                               AND COALESCE(concentracion, '') = COALESCE(:c, '') AND id_medicamento <> :id)",
            ['n' => $nombre, 'p' => $presentacion, 'c' => $concentracion, 'id' => $excluirId ?? 0]
        );
    }

    public function crear(array $d): void
    {
        BaseDatos::ejecutar(
            "INSERT INTO medicamento (nombre, presentacion, concentracion) VALUES (:n, :p, :c)",
            ['n' => $d['nombre'], 'p' => $d['presentacion'], 'c' => $d['concentracion']]
        );
    }

    public function actualizar(int $id, array $d): void
    {
        BaseDatos::ejecutar(
            "UPDATE medicamento SET nombre = :n, presentacion = :p, concentracion = :c WHERE id_medicamento = :id",
            ['id' => $id, 'n' => $d['nombre'], 'p' => $d['presentacion'], 'c' => $d['concentracion']]
        );
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        BaseDatos::ejecutar("UPDATE medicamento SET estado = :e WHERE id_medicamento = :id", ['id' => $id, 'e' => $estado]);
    }
}
