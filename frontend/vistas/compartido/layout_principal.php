<?php
/**
 * Diseño común de las páginas internas: barra lateral con el menú,
 * barra superior con el usuario y el área de contenido.
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Csrf;
use App\Nucleo\Vista;

$u = Autenticacion::usuario();
$nombresRol = ['administrador' => 'Administrador', 'recepcionista' => 'Recepción', 'medico' => 'Médico', 'paciente' => 'Paciente'];
$titulo = $titulo ?? 'Clínica Vida Salud';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($titulo) ?> · Clínica Vida Salud</title>
    <link rel="icon" href="<?= e(recurso('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(recurso('css/app.css')) ?>">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="app">
    <aside class="barra-lateral" id="barra-lateral">
        <a class="marca" href="<?= e(url('/panel')) ?>">
            <img src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="40" height="40">
            <span class="marca-texto">Vida Salud<small>Clínica · Santa Cruz</small></span>
        </a>
        <?= Vista::parcial('compartido/menu') ?>
        <div class="barra-lateral-pie">
            <svg class="pulso" viewBox="0 0 200 30" aria-hidden="true"><path d="M0 15h60l8-11 10 22 9-16 6 5h107"/></svg>
        </div>
    </aside>

    <div class="principal">
        <header class="barra-superior">
            <button class="boton-icono menu-movil" type="button" data-alternar-menu aria-controls="barra-lateral" aria-label="Abrir menú">
                <?= icono('menu') ?>
            </button>
            <p class="fecha-hoy"><?= icono('calendario') ?><?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?></p>
            <div class="usuario-actual">
                <span class="avatar" aria-hidden="true"><?= e(iniciales($u['nombre_completo'] ?? '')) ?></span>
                <span class="usuario-datos">
                    <strong><?= e($u['nombre_completo'] ?? '') ?></strong>
                    <small><?= e($nombresRol[$u['rol'] ?? ''] ?? '') ?></small>
                </span>
                <details class="menu-usuario">
                    <summary class="boton-icono" aria-label="Opciones de la cuenta"><?= icono('chevron-abajo') ?></summary>
                    <div class="menu-usuario-lista">
                        <?php if (($u['rol'] ?? '') === 'paciente'): ?>
                            <a href="<?= e(url('/mi-perfil')) ?>"><?= icono('usuario') ?>Mi perfil</a>
                        <?php endif; ?>
                        <a href="<?= e(url('/cuenta/clave')) ?>"><?= icono('llave') ?>Cambiar contraseña</a>
                        <form method="post" action="<?= e(url('/salir')) ?>">
                            <?= csrf_campo() ?>
                            <button type="submit"><?= icono('salir') ?>Cerrar sesión</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="contenido" id="contenido">
            <?php foreach ($mensajes as $m): ?>
                <div class="alerta alerta-<?= e($m['tipo']) ?>" role="<?= $m['tipo'] === 'error' ? 'alert' : 'status' ?>">
                    <?= icono($m['tipo'] === 'exito' ? 'check' : ($m['tipo'] === 'error' ? 'alerta' : 'info')) ?>
                    <p><?= e($m['mensaje']) ?></p>
                    <button type="button" class="alerta-cerrar" aria-label="Cerrar mensaje" data-cerrar-alerta><?= icono('x') ?></button>
                </div>
            <?php endforeach; ?>
            <?= $contenido ?>
        </main>
    </div>
</div>
<div class="fondo-menu" data-alternar-menu hidden></div>
<script src="<?= e(recurso('js/app.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $js): ?>
<script src="<?= e(recurso('js/' . $js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
