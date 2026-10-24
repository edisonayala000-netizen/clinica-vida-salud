<?php
/** IU Bitácora de acciones (CU02): quién hizo qué, cuándo y desde qué IP, y qué datos cambió. Solo lectura. */
$f = $filtros;
$nombresAccion = [
    'inicio_sesion'    => 'Inicio de sesión',
    'inicio_fallido'   => 'Inicio fallido',
    'cuenta_bloqueada' => 'Cuenta bloqueada',
    'cierre_sesion'    => 'Cierre de sesión',
    'operacion'        => 'Operación',
    'exportacion'      => 'Exportación',
    'auditoria'        => 'Cambio de datos',
];
$nombresOperacion = ['INSERT' => 'Registro', 'UPDATE' => 'Modificación', 'DELETE' => 'Eliminación'];
/* Valor de un campo auditado para mostrarlo (los JSON anidados se muestran compactos). */
$valorDato = static fn (mixed $v): string => match (true) {
    $v === null  => '—',
    is_bool($v)  => $v ? 'sí' : 'no',
    is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
    default      => (string) $v,
};
$claseAccion = static function (array $r): string {
    if (in_array($r['accion'], ['inicio_fallido', 'cuenta_bloqueada'], true) || str_contains((string) $r['detalle'], '· con error')) {
        return 'estado-cancelada';
    }
    return match ($r['accion']) {
        'inicio_sesion' => 'estado-atendida',
        'cierre_sesion' => 'estado-inactivo',
        'exportacion'   => 'estado-confirmada',
        'auditoria'     => 'estado-inactivo',
        default         => 'estado-programada',
    };
};
$params = ['usuario' => $f['usuario'], 'modulo' => $f['modulo'], 'accion' => $f['accion'], 'desde' => $f['desde'], 'hasta' => $f['hasta']];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Bitácora</h1>
        <p>Registro de las acciones de los usuarios: inicios y cierres de sesión, intentos fallidos, cada operación realizada y cada cambio de datos con sus valores de antes y de después. No se puede modificar ni borrar.</p>
    </div>
    <div class="acciones"><a class="boton boton-secundario" href="<?= e(url('/bitacora', $params + ['formato' => 'csv'])) ?>"><?= icono('descargar') ?>Exportar CSV</a></div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/bitacora')) ?>" role="search">
        <div class="campo campo-buscar">
            <label for="usuario">Usuario</label>
            <div class="campo-con-icono"><?= icono('buscar') ?>
                <input id="usuario" name="usuario" type="search" placeholder="Nombre de usuario" value="<?= e($f['usuario']) ?>"></div>
        </div>
        <div class="campo"><label for="modulo">Módulo</label>
            <select id="modulo" name="modulo" data-autoenviar><option value="">Todos</option>
                <?php foreach ($modulos as $m): ?><option value="<?= e($m) ?>"<?= seleccionado($f['modulo'], $m) ?>><?= e(ucfirst($m)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="accion">Acción</label>
            <select id="accion" name="accion" data-autoenviar><option value="">Todas</option>
                <?php foreach ($acciones as $a): ?><option value="<?= e($a) ?>"<?= seleccionado($f['accion'], $a) ?>><?= e($nombresAccion[$a] ?? $a) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($f['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($f['hasta']) ?>"></div>
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Filtrar</button>
    </form>

    <?php if (!$registros): ?>
        <div class="vacio"><?= icono('historial') ?><h3>Sin registros</h3><p>No hay acciones que cumplan esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td class="numero"><?= e(date('d/m/Y', strtotime($r['fecha_hora']))) ?><span class="celda-sub"><?= e(date('H:i:s', strtotime($r['fecha_hora']))) ?></span></td>
                    <td><span class="celda-principal"><?= e($r['usuario'] ?? '—') ?></span>
                        <?php if ($r['nombre_completo']): ?><span class="celda-sub"><?= e($r['nombre_completo']) ?></span><?php endif; ?></td>
                    <td><span class="estado <?= $claseAccion($r) ?>"><?= e($nombresAccion[$r['accion']] ?? $r['accion']) ?></span></td>
                    <td><?= e(ucfirst($r['modulo'])) ?></td>
                    <td class="celda-detalle"><?= e($r['detalle'] ?? '') ?>
                        <?php if ($r['tabla']):
                            $antes = json_decode((string) $r['datos_antes'], true) ?? [];
                            $despues = json_decode((string) $r['datos_despues'], true) ?? []; ?>
                            <details class="cambios-datos">
                                <summary><?= e(($nombresOperacion[$r['operacion']] ?? $r['operacion']) . ' en ' . $r['tabla']) ?> · ver datos</summary>
                                <table>
                                    <thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
                                    <tbody>
                                    <?php foreach (array_unique(array_merge(array_keys($antes), array_keys($despues))) as $campo): ?>
                                        <tr><td><?= e($campo) ?></td>
                                            <td><?= e(array_key_exists($campo, $antes) ? $valorDato($antes[$campo]) : '—') ?></td>
                                            <td><?= e(array_key_exists($campo, $despues) ? $valorDato($despues[$campo]) : '—') ?></td></tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        <?php endif; ?></td>
                    <td class="numero"><?= e($r['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= \App\Nucleo\Vista::parcial('compartido/paginacion', [
        'pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/bitacora',
        'params' => $params, 'nombre' => 'registro(s)',
    ]) ?>
</section>
