<?php
namespace App\Paquetes\Seguridad\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Acceso a datos de ROL, PERMISO y ROL_PERMISO.
 */
final class RolModelo
{
    public function listar(): array
    {
        return BaseDatos::todos(
            "SELECT r.id_rol, r.nombre, r.descripcion,
                    (SELECT count(*) FROM usuario u WHERE u.id_rol = r.id_rol) AS usuarios
               FROM rol r ORDER BY r.id_rol"
        );
    }

    public function obtenerPorNombre(string $nombre): ?array
    {
        return BaseDatos::uno("SELECT * FROM rol WHERE nombre = :n", ['n' => $nombre]);
    }

    public function obtener(int $id): ?array
    {
        return BaseDatos::uno("SELECT * FROM rol WHERE id_rol = :id", ['id' => $id]);
    }

    public function permisos(): array
    {
        return BaseDatos::todos("SELECT * FROM permiso ORDER BY modulo, id_permiso");
    }

    /** @return array<int,array<int,int>> id_rol => [id_permiso, ...] */
    public function matrizPermisos(): array
    {
        $matriz = [];
        foreach (BaseDatos::todos("SELECT id_rol, id_permiso FROM rol_permiso") as $f) {
            $matriz[(int) $f['id_rol']][] = (int) $f['id_permiso'];
        }
        return $matriz;
    }

    /** Reemplaza los permisos de un rol (se llama dentro de una transacción). */
    public function reemplazarPermisos(int $idRol, array $idsPermisos): void
    {
        BaseDatos::ejecutar("DELETE FROM rol_permiso WHERE id_rol = :r", ['r' => $idRol]);
        foreach ($idsPermisos as $idPermiso) {
            BaseDatos::ejecutar(
                "INSERT INTO rol_permiso (id_rol, id_permiso)
                 SELECT :r, id_permiso FROM permiso WHERE id_permiso = :p",
                ['r' => $idRol, 'p' => (int) $idPermiso]
            );
        }
    }

    public function idPermiso(string $codigo): ?int
    {
        $id = BaseDatos::valor("SELECT id_permiso FROM permiso WHERE codigo = :c", ['c' => $codigo]);
        return $id === null ? null : (int) $id;
    }
}
