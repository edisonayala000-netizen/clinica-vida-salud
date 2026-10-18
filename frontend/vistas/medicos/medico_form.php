<?php
/** IU Formulario de médico (CU07) */
$es = $medico !== null;
$v = static fn (string $c) => viejo($anterior, $c, $medico[$c] ?? '');
$elegidas = isset($anterior['especialidades']) ? array_map('intval', (array) $anterior['especialidades']) : ($medico['ids_especialidades'] ?? []);
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url($es ? '/medicos/' . $medico['id_medico'] : '/medicos')) ?>"><?= icono('flecha-izq') ?><?= $es ? 'Ficha del médico' : 'Médicos' ?></a>
        <h1><?= $es ? 'Modificar médico ' . e($medico['codigo']) : 'Registrar médico' ?></h1>
        <p><?= $es ? 'Actualice los datos y especialidades.' : 'El código del médico (MED-001…) se genera automáticamente.' ?></p>
    </div>
</div>

<form class="tarjeta" method="post" action="<?= e(url($es ? '/medicos/' . $medico['id_medico'] : '/medicos')) ?>" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="tarjeta-cuerpo formulario">
        <fieldset class="grupo-campos">
            <legend>Datos del médico</legend>
            <div class="campo<?= clase_error($errores, 'nombres') ?>">
                <label for="nombres">Nombres <span class="requerido">*</span></label>
                <input id="nombres" name="nombres" required minlength="2" maxlength="60" value="<?= $v('nombres') ?>">
                <?= error_campo($errores, 'nombres') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                <input id="apellidos" name="apellidos" required minlength="2" maxlength="60" value="<?= $v('apellidos') ?>">
                <?= error_campo($errores, 'apellidos') ?>
            </div>
            <div class="campo campo-4<?= clase_error($errores, 'registro_profesional') ?>">
                <label for="registro_profesional">N.º de registro profesional <span class="requerido">*</span></label>
                <input id="registro_profesional" name="registro_profesional" required minlength="3" maxlength="30" placeholder="MP-12345" value="<?= $v('registro_profesional') ?>">
                <?= error_campo($errores, 'registro_profesional') ?>
            </div>
            <div class="campo campo-4<?= clase_error($errores, 'telefono') ?>">
                <label for="telefono">Teléfono <span class="requerido">*</span></label>
                <input id="telefono" name="telefono" type="tel" required pattern="[0-9+\-\s]{7,20}" data-mensaje="Entre 7 y 20 dígitos." value="<?= $v('telefono') ?>">
                <?= error_campo($errores, 'telefono') ?>
            </div>
            <div class="campo campo-4<?= clase_error($errores, 'correo') ?>">
                <label for="correo">Correo electrónico <span class="requerido">*</span></label>
                <input id="correo" name="correo" type="email" required maxlength="120" value="<?= $v('correo') ?>">
                <?= error_campo($errores, 'correo') ?>
            </div>
        </fieldset>
        <fieldset class="grupo-campos">
            <legend>Especialidades</legend>
            <div class="campo campo-12<?= clase_error($errores, 'especialidades') ?>">
                <span class="etiqueta-campo">Seleccione una o varias <span class="requerido">*</span></span>
                <div class="opciones-chips">
                    <?php foreach ($especialidades as $esp): ?>
                        <label class="chip"><input type="checkbox" name="especialidades[]" value="<?= (int) $esp['id_especialidad'] ?>"<?= marcado(in_array((int) $esp['id_especialidad'], $elegidas, true)) ?>>
                            <span><?= e($esp['nombre']) ?><?= $esp['estado'] !== 'activo' ? ' (inactiva)' : '' ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?= error_campo($errores, 'especialidades') ?>
            </div>
        </fieldset>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <a class="boton boton-secundario" href="<?= e(url($es ? '/medicos/' . $medico['id_medico'] : '/medicos')) ?>">Cancelar</a>
        <button class="boton boton-primario" type="submit"><?= icono('check') ?><?= $es ? 'Guardar cambios' : 'Registrar médico' ?></button>
    </div>
</form>
