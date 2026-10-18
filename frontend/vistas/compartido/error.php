<section class="pagina-error">
    <p class="pagina-error-codigo"><?= (int) $codigo ?></p>
    <h1><?= e($titulo) ?></h1>
    <p><?= e($mensaje) ?></p>
    <?php if (!empty($detalle)): ?>
        <pre class="detalle-tecnico"><?= e($detalle) ?></pre>
    <?php endif; ?>
    <div class="acciones">
        <a class="boton boton-primario" href="<?= e(url(\App\Nucleo\Autenticacion::hayUsuario() ? '/panel' : '/login')) ?>">
            <?= \App\Nucleo\Autenticacion::hayUsuario() ? 'Ir al panel' : 'Ir a iniciar sesión' ?>
        </a>
    </div>
</section>
