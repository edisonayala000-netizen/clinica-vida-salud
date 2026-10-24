<?php /** IU Crear nueva contraseña con el enlace del correo (CU15) */ ?>
<div class="acceso">
    <?= \App\Nucleo\Vista::parcial('compartido/portada_acceso') ?>

    <section class="acceso-formulario">
        <?php if ($token === null): ?>
            <div class="tarjeta-acceso">
                <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
                <h1>Enlace no válido</h1>
                <div class="acceso-aviso" role="alert">
                    <?= icono('alerta') ?>
                    <p>El enlace no es válido, ya se usó o venció. Por seguridad, cada enlace sirve una sola vez y
                       durante 30 minutos.</p>
                </div>
                <a class="boton boton-primario boton-bloque" href="<?= e(url('/recuperar')) ?>">Solicitar un enlace nuevo</a>
                <p class="acceso-registro"><a href="<?= e(url('/login')) ?>">Volver a iniciar sesión</a></p>
            </div>
        <?php else: ?>
            <form class="tarjeta-acceso" method="post" action="<?= e(url('/restablecer')) ?>" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
                <h1>Crear nueva contraseña</h1>
                <p class="texto-suave">Hola, <?= e($cuenta['nombres']) ?>. Escriba la nueva contraseña de su cuenta
                   <strong><?= e($cuenta['nombre_usuario']) ?></strong>.</p>

                <div class="campo<?= clase_error($errores, 'clave') ?>">
                    <label for="clave">Nueva contraseña</label>
                    <div class="campo-con-icono">
                        <?= icono('llave') ?>
                        <input id="clave" name="clave" type="password" required minlength="8" maxlength="64" autocomplete="new-password"
                               pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}" data-mensaje="De 8 a 64 caracteres, con letras y números." autofocus>
                        <button type="button" class="ver-clave" data-ver-clave="clave" aria-label="Mostrar contraseña"><?= icono('ojo') ?></button>
                    </div>
                    <p class="ayuda">De 8 a 64 caracteres, con letras y números.</p>
                    <?= error_campo($errores, 'clave') ?>
                </div>

                <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
                    <label for="clave_confirmacion">Repita la nueva contraseña</label>
                    <div class="campo-con-icono">
                        <?= icono('llave') ?>
                        <input id="clave_confirmacion" name="clave_confirmacion" type="password" required maxlength="64"
                               autocomplete="new-password" data-igual-a="clave">
                    </div>
                    <?= error_campo($errores, 'clave_confirmacion') ?>
                </div>

                <button class="boton boton-primario boton-bloque" type="submit">Guardar nueva contraseña</button>
                <p class="acceso-registro"><a href="<?= e(url('/login')) ?>">Volver a iniciar sesión</a></p>
            </form>
        <?php endif; ?>
        <p class="acceso-pie">Después de cambiarla, los demás enlaces que haya pedido dejan de funcionar.</p>
    </section>
</div>
