<?php /** IU Cambiar contraseña (CU01, flujo alternativo) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Cambiar contraseña</h1>
        <p>Use al menos 8 caracteres con letras y números. No la comparta con nadie.</p>
    </div>
</div>
<form class="tarjeta" method="post" action="<?= e(url('/cuenta/clave')) ?>" data-validar novalidate style="max-width: 560px">
    <?= csrf_campo() ?>
    <div class="tarjeta-cuerpo formulario">
        <div class="grupo-campos">
            <div class="campo campo-12<?= clase_error($errores, 'clave_actual') ?>">
                <label for="clave_actual">Contraseña actual</label>
                <input id="clave_actual" name="clave_actual" type="password" required autocomplete="current-password">
                <?= error_campo($errores, 'clave_actual') ?>
            </div>
            <div class="campo campo-12<?= clase_error($errores, 'clave') ?>">
                <label for="clave">Nueva contraseña</label>
                <input id="clave" name="clave" type="password" required minlength="8" maxlength="64" autocomplete="new-password"
                       pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}" data-mensaje="De 8 a 64 caracteres, con letras y números.">
                <?= error_campo($errores, 'clave') ?>
            </div>
            <div class="campo campo-12<?= clase_error($errores, 'clave_confirmacion') ?>">
                <label for="clave_confirmacion">Repita la nueva contraseña</label>
                <input id="clave_confirmacion" name="clave_confirmacion" type="password" required data-igual-a="clave" autocomplete="new-password">
                <?= error_campo($errores, 'clave_confirmacion') ?>
            </div>
        </div>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <a class="boton boton-secundario" href="<?= e(url('/panel')) ?>">Cancelar</a>
        <button class="boton boton-primario" type="submit">Cambiar contraseña</button>
    </div>
</form>
