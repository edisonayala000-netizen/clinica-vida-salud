<?php /** IU Recuperar contraseña: pedir el enlace por correo (CU15) */ ?>
<div class="acceso">
    <?= \App\Nucleo\Vista::parcial('compartido/portada_acceso') ?>

    <section class="acceso-formulario">
        <form class="tarjeta-acceso" method="post" action="<?= e(url('/recuperar')) ?>" data-validar novalidate>
            <?= csrf_campo() ?>
            <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
            <h1>Recuperar contraseña</h1>
            <p class="texto-suave">Escriba su usuario o el correo de su cuenta. Le enviaremos un enlace para crear una
               nueva contraseña.</p>

            <div class="campo<?= clase_error($errores, 'usuario') ?>">
                <label for="usuario">Usuario o correo</label>
                <div class="campo-con-icono">
                    <?= icono('correo') ?>
                    <input id="usuario" name="usuario" type="text" autocomplete="username" required minlength="3" maxlength="120"
                           value="<?= viejo($anterior, 'usuario') ?>" autofocus>
                </div>
                <?= error_campo($errores, 'usuario') ?>
            </div>

            <button class="boton boton-primario boton-bloque" type="submit">Enviar enlace</button>

            <p class="acceso-registro">¿Recordó su contraseña?
                <a href="<?= e(url('/login')) ?>">Volver a iniciar sesión</a></p>
        </form>
        <p class="acceso-pie">El enlace vence en 30 minutos y sirve una sola vez.</p>
    </section>
</div>
