<?php
namespace App\Paquetes\Seguridad\Modelos;

use App\Nucleo\BaseDatos;

/**
 * Clase ENTIDAD/acceso a datos de la tabla BITACORA (consulta de CU02).
 * Los registros se agregan con App\Nucleo\Bitacora; aquí solo se consultan.
 */
final class BitacoraModelo
{
    public const POR_PAGINA = 25;

    /**
     * Registros que cumplen los filtros, del más reciente al más antiguo.
     *
     * @param array{usuario:?string,modulo:?string,accion:?string,desde:?string,hasta:?string} $filtros
     * @param int|null $pagina null = todos los registros (exportación CSV)
     */
    public function listar(array $filtros, ?int $pagina = 1): array
    {
        [$where, $p] = $this->condiciones($filtros);
        $sql = "SELECT b.id_bitacora, b.fecha_hora, b.usuario, b.accion, b.modulo, b.detalle, b.ip,
                       u.nombre_completo
                  FROM bitacora b LEFT JOIN usuario u ON u.id_usuario = b.id_usuario" . $where . "
                 ORDER BY b.fecha_hora DESC, b.id_bitacora DESC";
        if ($pagina !== null) {
            $sql .= " LIMIT :lim OFFSET :off";
            $p += ['lim' => self::POR_PAGINA, 'off' => ($pagina - 1) * self::POR_PAGINA];
        }
        return BaseDatos::todos($sql, $p);
    }

    public function contar(array $filtros): int
    {
        [$where, $p] = $this->condiciones($filtros);
        return (int) BaseDatos::valor("SELECT count(*) FROM bitacora b" . $where, $p);
    }

    /** @return array<int,string> módulos que aparecen en la bitácora (para el filtro) */
    public function modulos(): array
    {
        return array_column(BaseDatos::todos("SELECT DISTINCT modulo FROM bitacora ORDER BY modulo"), 'modulo');
    }

    /** @return array<int,string> acciones que aparecen en la bitácora (para el filtro) */
    public function acciones(): array
    {
        return array_column(BaseDatos::todos("SELECT DISTINCT accion FROM bitacora ORDER BY accion"), 'accion');
    }

    /** @return array{0:string,1:array<string,mixed>} cláusula WHERE y parámetros */
    private function condiciones(array $f): array
    {
        $where = " WHERE 1 = 1";
        $p = [];
        if (!empty($f['usuario'])) {
            $where .= " AND b.usuario ILIKE :usuario";
            $p['usuario'] = '%' . $f['usuario'] . '%';
        }
        if (!empty($f['modulo'])) {
            $where .= " AND b.modulo = :modulo";
            $p['modulo'] = $f['modulo'];
        }
        if (!empty($f['accion'])) {
            $where .= " AND b.accion = :accion";
            $p['accion'] = $f['accion'];
        }
        if (!empty($f['desde'])) {
            $where .= " AND b.fecha_hora >= CAST(:desde AS DATE)";
            $p['desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $where .= " AND b.fecha_hora < CAST(:hasta AS DATE) + 1";
            $p['hasta'] = $f['hasta'];
        }
        return [$where, $p];
    }
}
