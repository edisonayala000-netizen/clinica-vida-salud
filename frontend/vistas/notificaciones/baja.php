<?php
/**
 * IU Desactivar recordatorios desde el correo (CU18, sin iniciar sesión).
 * Primero se pregunta y luego se desactiva con un botón (POST).
 */
?>
<div class="acceso">
    <?= \App\Nucleo\Vista::parcial('compartido/portada_acceso') ?>

    <section class="acceso-formulario">
        <div class="tarjeta-acceso">
            <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
            <?php if (!$baja): ?>
                <h1>Enlace no válido</h1>
                <div class="acceso-aviso" role="alert"><?= icono('alerta') ?>
                    <p>El enlace no es válido o venció. Puede cambiar sus recordatorios en Mi perfil → Cuenta.</p></div>
            <?php elseif ($hecho): ?>
                <h1>Recordatorios desactivados</h1>
                <p class="texto-suave">Listo, <?= e($baja['paciente']) ?>: ya no le enviaremos <?= e($baja['descripcion']) ?>.
                   Puede volver a activarlos cuando quiera en <strong>Mi perfil → Cuenta</strong><?= $baja['tema'] === 'detalle' ? ' o en <strong>Mis recetas</strong>' : '' ?>.</p>
            <?php else: ?>
                <h1>Dejar de recibir recordatorios</h1>
                <p class="texto-suave"><?= e($baja['paciente']) ?>, ¿desea dejar de recibir <?= e($baja['descripcion']) ?>?</p>
                <form method="post" action="<?= e(url('/correo/baja')) ?>">
                    <?= csrf_campo() ?><input type="hidden" name="e" value="<?= e($token) ?>">
                    <button class="boton boton-primario boton-bloque" type="submit">Sí, desactivar</button>
                </form>
                <p class="ayuda">Seguirá recibiendo la confirmación de sus citas y sus recetas.</p>
            <?php endif; ?>
            <p class="acceso-registro"><a href="<?= e(url('/login')) ?>">Ingresar al sistema</a></p>
        </div>
    </section>
</div>
