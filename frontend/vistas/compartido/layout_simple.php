<?php
/** Diseño para páginas sin sesión: inicio de sesión, registro y errores. */
$titulo = $titulo ?? 'Clínica Vida Salud';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Clínica Vida Salud</title>
    <link rel="icon" href="<?= e(recurso('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(recurso('css/app.css')) ?>">
</head>
<body class="pagina-simple">
    <?php if ($mensajes): ?>
    <div class="alertas-flotantes">
        <?php foreach ($mensajes as $m): ?>
            <div class="alerta alerta-<?= e($m['tipo']) ?>" role="<?= $m['tipo'] === 'error' ? 'alert' : 'status' ?>">
                <?= icono($m['tipo'] === 'exito' ? 'check' : ($m['tipo'] === 'error' ? 'alerta' : 'info')) ?>
                <p><?= e($m['mensaje']) ?></p>
                <button type="button" class="alerta-cerrar" aria-label="Cerrar mensaje" data-cerrar-alerta><?= icono('x') ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?= $contenido ?>
    <script src="<?= e(recurso('js/app.js')) ?>"></script>
</body>
</html>
