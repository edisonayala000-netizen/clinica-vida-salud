<?php
/** IU Registro en línea del paciente (CU04) */
$v = static fn (string $c) => viejo($anterior, $c);
?>
<div class="registro-paciente">
    <div class="encabezado">
        <img src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="48" height="48">
        <div class="encabezado-texto">
            <h1>Crear mi cuenta de paciente</h1>
            <p>Con su cuenta podrá reservar citas, ver su historial y mantener su información médica al día.
               Si ya se atendió en la clínica, use el mismo CI y fecha de nacimiento que dio en recepción.</p>
        </div>
    </div>

    <form class="tarjeta" method="post" action="<?= e(url('/registro')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo formulario">
            <fieldset class="grupo-campos">
                <legend>Datos personales</legend>
                <div class="campo campo-4<?= clase_error($errores, 'ci') ?>">
                    <label for="ci">CI <span class="requerido">*</span></label>
                    <input id="ci" name="ci" required maxlength="15" pattern="[0-9]{5,12}([\-A-Za-z0-9]{1,3})?" data-mensaje="De 5 a 12 dígitos." value="<?= $v('ci') ?>">
                    <?= error_campo($errores, 'ci') ?>
                </div>
                <div class="campo campo-4<?= clase_error($errores, 'nombres') ?>">
                    <label for="nombres">Nombres <span class="requerido">*</span></label>
                    <input id="nombres" name="nombres" required minlength="2" maxlength="60" value="<?= $v('nombres') ?>">
                    <?= error_campo($errores, 'nombres') ?>
                </div>
                <div class="campo campo-4<?= clase_error($errores, 'apellidos') ?>">
                    <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                    <input id="apellidos" name="apellidos" required minlength="2" maxlength="60" value="<?= $v('apellidos') ?>">
                    <?= error_campo($errores, 'apellidos') ?>
                </div>
                <div class="campo campo-4<?= clase_error($errores, 'fecha_nacimiento') ?>">
                    <label for="fecha_nacimiento">Fecha de nacimiento <span class="requerido">*</span></label>
                    <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" required min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>">
                    <?= error_campo($errores, 'fecha_nacimiento') ?>
                </div>
                <div class="campo campo-4<?= clase_error($errores, 'sexo') ?>">
                    <label for="sexo">Sexo <span class="requerido">*</span></label>
                    <select id="sexo" name="sexo" required>
                        <option value="">Seleccione…</option>
                        <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $k => $t): ?>
                            <option value="<?= $k ?>"<?= seleccionado($anterior['sexo'] ?? '', $k) ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_campo($errores, 'sexo') ?>
                </div>
                <div class="campo campo-4<?= clase_error($errores, 'telefono') ?>">
                    <label for="telefono">Teléfono <span class="requerido">*</span></label>
                    <input id="telefono" name="telefono" type="tel" required pattern="[0-9+\-\s]{7,20}" value="<?= $v('telefono') ?>">
                    <?= error_campo($errores, 'telefono') ?>
                </div>
                <div class="campo campo-12<?= clase_error($errores, 'direccion') ?>">
                    <label for="direccion">Dirección <span class="opcional">(opcional)</span></label>
                    <input id="direccion" name="direccion" maxlength="200" value="<?= $v('direccion') ?>">
                </div>
            </fieldset>
            <fieldset class="grupo-campos">
                <legend>Datos de acceso</legend>
                <div class="campo<?= clase_error($errores, 'correo') ?>">
                    <label for="correo">Correo electrónico <span class="requerido">*</span></label>
                    <input id="correo" name="correo" type="email" required maxlength="120" autocomplete="email" value="<?= $v('correo') ?>">
                    <?= error_campo($errores, 'correo') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'nombre_usuario') ?>">
                    <label for="nombre_usuario">Nombre de usuario <span class="requerido">*</span></label>
                    <input id="nombre_usuario" name="nombre_usuario" required pattern="[a-z0-9._]{4,40}" maxlength="40" autocomplete="username"
                           data-mensaje="De 4 a 40 caracteres: minúsculas, números, punto o guion bajo." value="<?= $v('nombre_usuario') ?>">
                    <?= error_campo($errores, 'nombre_usuario') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'clave') ?>">
                    <label for="clave">Contraseña <span class="requerido">*</span></label>
                    <input id="clave" name="clave" type="password" required minlength="8" maxlength="64" autocomplete="new-password"
                           pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}" data-mensaje="De 8 a 64 caracteres, con letras y números.">
                    <?= error_campo($errores, 'clave') ?>
                </div>
                <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
                    <label for="clave_confirmacion">Repita la contraseña <span class="requerido">*</span></label>
                    <input id="clave_confirmacion" name="clave_confirmacion" type="password" required data-igual-a="clave" autocomplete="new-password">
                    <?= error_campo($errores, 'clave_confirmacion') ?>
                </div>
                <div class="campo campo-12<?= clase_error($errores, 'acepta') ?>">
                    <label class="casilla"><input type="checkbox" name="acepta" value="1" required>
                        <span>Acepto que la clínica guarde mis datos para gestionar mis citas. Mi información médica solo la ven el personal autorizado y mis médicos.</span></label>
                    <?= error_campo($errores, 'acepta') ?>
                </div>
            </fieldset>
        </div>
        <div class="tarjeta-pie pie-formulario">
            <a class="boton boton-secundario" href="<?= e(url('/login')) ?>">Ya tengo cuenta</a>
            <button class="boton boton-primario" type="submit">Crear mi cuenta</button>
        </div>
    </form>
</div>
