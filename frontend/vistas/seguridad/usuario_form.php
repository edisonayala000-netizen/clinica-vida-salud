<?php
/** IU Formulario de usuario (CU02) */
$es = $usuario !== null;
$v = static fn (string $c) => viejo($anterior, $c, $usuario[$c] ?? '');
$rolActual = $anterior['id_rol'] ?? ($usuario['id_rol'] ?? '');
$nombresRol = ['administrador' => 'Administrador', 'recepcionista' => 'Recepcionista', 'medico' => 'Médico', 'paciente' => 'Paciente'];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/usuarios')) ?>"><?= icono('flecha-izq') ?>Usuarios</a>
        <h1><?= $es ? 'Modificar usuario' : 'Nuevo usuario' ?></h1>
        <p>La contraseña se guarda cifrada con <code>password_hash()</code>; nadie puede verla, solo reemplazarla.</p>
    </div>
</div>

<form class="tarjeta" method="post" action="<?= e(url($es ? '/usuarios/' . $usuario['id_usuario'] : '/usuarios')) ?>" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="tarjeta-cuerpo formulario">
        <fieldset class="grupo-campos">
            <legend>Datos de la cuenta</legend>
            <div class="campo<?= clase_error($errores, 'nombre_completo') ?>">
                <label for="nombre_completo">Nombre completo <span class="requerido">*</span></label>
                <input id="nombre_completo" name="nombre_completo" required minlength="3" maxlength="120" value="<?= $v('nombre_completo') ?>">
                <?= error_campo($errores, 'nombre_completo') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'nombre_usuario') ?>">
                <label for="nombre_usuario">Nombre de usuario <span class="requerido">*</span></label>
                <input id="nombre_usuario" name="nombre_usuario" required pattern="[a-z0-9._]{4,40}" maxlength="40" autocomplete="off"
                       data-mensaje="De 4 a 40 caracteres: minúsculas, números, punto o guion bajo." value="<?= $v('nombre_usuario') ?>">
                <?= error_campo($errores, 'nombre_usuario') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'correo') ?>">
                <label for="correo">Correo <span class="requerido">*</span></label>
                <input id="correo" name="correo" type="email" required maxlength="120" value="<?= $v('correo') ?>">
                <?= error_campo($errores, 'correo') ?>
            </div>
            <div class="campo campo-4<?= clase_error($errores, 'id_rol') ?>">
                <label for="id_rol">Rol <span class="requerido">*</span></label>
                <select id="id_rol" name="id_rol" required data-rol>
                    <option value="">Seleccione…</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id_rol'] ?>" data-nombre="<?= e($r['nombre']) ?>"<?= seleccionado($rolActual, $r['id_rol']) ?>><?= e($nombresRol[$r['nombre']]) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error_campo($errores, 'id_rol') ?>
            </div>
            <div class="campo campo-8<?= clase_error($errores, 'id_medico') ?>" data-vinculo="medico" hidden>
                <label for="id_medico">Médico vinculado <span class="requerido">*</span></label>
                <select id="id_medico" name="id_medico">
                    <option value="">Seleccione el médico…</option>
                    <?php foreach ($medicos as $m): ?>
                        <option value="<?= (int) $m['id_medico'] ?>"<?= seleccionado($anterior['id_medico'] ?? ($usuario['id_medico'] ?? ''), $m['id_medico']) ?>><?= e($m['codigo'] . ' · ' . $m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="ayuda">Solo aparecen médicos activos que aún no tienen cuenta.</p>
                <?= error_campo($errores, 'id_medico') ?>
            </div>
            <div class="campo campo-8<?= clase_error($errores, 'id_paciente') ?>" data-vinculo="paciente" hidden>
                <label for="id_paciente">Paciente vinculado <span class="requerido">*</span></label>
                <select id="id_paciente" name="id_paciente">
                    <option value="">Seleccione el paciente…</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= (int) $p['id_paciente'] ?>"<?= seleccionado($anterior['id_paciente'] ?? ($usuario['id_paciente'] ?? ''), $p['id_paciente']) ?>><?= e($p['codigo'] . ' · ' . $p['nombre'] . ($p['ci'] ? ' · CI ' . $p['ci'] : '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error_campo($errores, 'id_paciente') ?>
            </div>
        </fieldset>

        <fieldset class="grupo-campos">
            <legend><?= $es ? 'Cambiar contraseña (opcional)' : 'Contraseña inicial' ?></legend>
            <div class="campo<?= clase_error($errores, 'clave') ?>">
                <label for="clave">Contraseña <?= $es ? '' : '<span class="requerido">*</span>' ?></label>
                <input id="clave" name="clave" type="password" <?= $es ? '' : 'required' ?> minlength="8" maxlength="64" autocomplete="new-password"
                       pattern="(?=.*[A-Za-z])(?=.*\d).{8,64}" data-mensaje="De 8 a 64 caracteres, con letras y números.">
                <p class="ayuda">De 8 a 64 caracteres, con letras y números.<?= $es ? ' Déjela vacía para no cambiarla.' : '' ?></p>
                <?= error_campo($errores, 'clave') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'clave_confirmacion') ?>">
                <label for="clave_confirmacion">Repita la contraseña</label>
                <input id="clave_confirmacion" name="clave_confirmacion" type="password" maxlength="64" autocomplete="new-password" data-igual-a="clave">
                <?= error_campo($errores, 'clave_confirmacion') ?>
            </div>
        </fieldset>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <a class="boton boton-secundario" href="<?= e(url('/usuarios')) ?>">Cancelar</a>
        <button class="boton boton-primario" type="submit"><?= icono('check') ?><?= $es ? 'Guardar cambios' : 'Registrar usuario' ?></button>
    </div>
</form>

<script>
/* Muestra el selector de médico o de paciente según el rol elegido. */
(function () {
    const rol = document.querySelector('[data-rol]');
    function actualizar() {
        const nombre = rol.selectedOptions[0] ? rol.selectedOptions[0].dataset.nombre : '';
        document.querySelectorAll('[data-vinculo]').forEach(function (c) {
            const ver = c.dataset.vinculo === nombre;
            c.hidden = !ver;
            c.querySelector('select').required = ver;
            if (!ver) c.querySelector('select').value = '';
        });
    }
    rol.addEventListener('change', actualizar);
    actualizar();
})();
</script>
