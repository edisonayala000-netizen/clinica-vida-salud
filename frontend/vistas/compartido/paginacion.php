<?php
/** Paginación reutilizable. Variables: $pagina, $paginas, $total, $ruta, $params, $nombre */
if ($paginas <= 1) {
    echo '<div class="paginacion"><span>' . (int) $total . ' ' . e($nombre) . '</span></div>';
    return;
}
$desde = max(1, $pagina - 2);
$hasta = min($paginas, $pagina + 2);
?>
<div class="paginacion">
    <span><?= (int) $total ?> <?= e($nombre) ?> · página <?= (int) $pagina ?> de <?= (int) $paginas ?></span>
    <nav class="paginacion-enlaces" aria-label="Páginas">
        <?php if ($pagina > 1): ?>
            <a href="<?= e(url($ruta, $params + ['pagina' => $pagina - 1])) ?>" aria-label="Anterior"><?= icono('chevron-izq') ?></a>
        <?php endif; ?>
        <?php for ($i = $desde; $i <= $hasta; $i++): ?>
            <?php if ($i === $pagina): ?>
                <span class="actual" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= e(url($ruta, $params + ['pagina' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pagina < $paginas): ?>
            <a href="<?= e(url($ruta, $params + ['pagina' => $pagina + 1])) ?>" aria-label="Siguiente"><?= icono('chevron-der') ?></a>
        <?php endif; ?>
    </nav>
</div>
