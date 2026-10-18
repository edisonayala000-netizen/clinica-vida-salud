<?php
/** IU Registrar / modificar paciente (CU03) */
use App\Paquetes\Pacientes\ReglasPaciente;

$es = $paciente !== null;
$v = static fn (string $c) => viejo($anterior, $c, $paciente[$c] ?? '');
$vc = static fn (string $c, string $k) => viejo($anterior, $c, $contacto[$k] ?? '');
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url($es ? '/pacientes/' . $paciente['id_paciente'] : '/pacientes')) ?>"><?= icono('flecha-izq') ?><?= $es ? 'Ficha del paciente' : 'Pacientes' ?></a>
        <h1><?= $es ? 'Modificar paciente ' . e($paciente['codigo']) : 'Registrar paciente' ?></h1>
        <p>Los campos con <span class="requerido" style="color:var(--rojo)">*</span> son obligatorios. El CI no puede repetirse.</p>
    </div>
</div>

<form class="tarjeta" method="post" action="<?= e(url($es ? '/pacientes/' . $paciente['id_paciente'] : '/pacientes')) ?>" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="tarjeta-cuerpo formulario">
        <fieldset class="grupo-campos">
            <legend>Datos personales</legend>
            <div class="campo campo-3<?= clase_error($errores, 'ci') ?>">
                <label for="ci">CI <span class="opcional">(si tiene)</span></label>
                <input id="ci" name="ci" maxlength="15" pattern="[0-9]{5,12}([\-A-Za-z0-9]{1,3})?" data-mensaje="De 5 a 12 dígitos (puede llevar complemento)." value="<?= $v('ci') ?>">
                <?= error_campo($errores, 'ci') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'nombres') ?>">
                <label for="nombres">Nombres <span class="requerido">*</span></label>
                <input id="nombres" name="nombres" required minlength="2" maxlength="60" value="<?= $v('nombres') ?>">
                <?= error_campo($errores, 'nombres') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'apellidos') ?>">
                <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                <input id="apellidos" name="apellidos" required minlength="2" maxlength="60" value="<?= $v('apellidos') ?>">
                <?= error_campo($errores, 'apellidos') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'fecha_nacimiento') ?>">
                <label for="fecha_nacimiento">Fecha de nacimiento <span class="requerido">*</span></label>
                <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" required min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>">
                <?= error_campo($errores, 'fecha_nacimiento') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'sexo') ?>">
                <label for="sexo">Sexo <span class="requerido">*</span></label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione…</option>
                    <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $k => $t): ?>
                        <option value="<?= $k ?>"<?= seleccionado($anterior['sexo'] ?? ($paciente['sexo'] ?? ''), $k) ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <?= error_campo($errores, 'sexo') ?>
            </div>
        </fieldset>

        <fieldset class="grupo-campos">
            <legend>Contacto</legend>
            <div class="campo campo-3<?= clase_error($errores, 'telefono') ?>">
                <label for="telefono">Teléfono <span class="requerido">*</span></label>
                <input id="telefono" name="telefono" type="tel" required pattern="[0-9+\-\s]{7,20}" data-mensaje="Entre 7 y 20 dígitos." value="<?= $v('telefono') ?>">
                <?= error_campo($errores, 'telefono') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'correo') ?>">
                <label for="correo">Correo <span class="opcional">(opcional)</span></label>
                <input id="correo" name="correo" type="email" maxlength="120" value="<?= $v('correo') ?>">
                <?= error_campo($errores, 'correo') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'direccion') ?>">
                <label for="direccion">Dirección <span class="opcional">(opcional)</span></label>
                <input id="direccion" name="direccion" maxlength="200" value="<?= $v('direccion') ?>">
                <?= error_campo($errores, 'direccion') ?>
            </div>
        </fieldset>

        <fieldset class="grupo-campos">
            <legend>Información médica <span class="opcional" style="font-family:var(--fuente);font-size:.85rem;font-weight:400;color:var(--tinta-tenue)">(opcional)</span></legend>
            <div class="campo campo-3<?= clase_error($errores, 'tipo_sangre') ?>">
                <label for="tipo_sangre">Tipo de sangre</label>
                <select id="tipo_sangre" name="tipo_sangre">
                    <option value="">No sabe</option>
                    <?php foreach (ReglasPaciente::TIPOS_SANGRE as $t): ?>
                        <option value="<?= $t ?>"<?= seleccionado($anterior['tipo_sangre'] ?? ($paciente['tipo_sangre'] ?? ''), $t) ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'alergias') ?>">
                <label for="alergias">Alergias</label>
                <input id="alergias" name="alergias" maxlength="250" value="<?= $v('alergias') ?>">
            </div>
            <div class="campo<?= clase_error($errores, 'enfermedades_cronicas') ?>">
                <label for="enfermedades_cronicas">Enfermedades crónicas</label>
                <input id="enfermedades_cronicas" name="enfermedades_cronicas" maxlength="250" value="<?= $v('enfermedades_cronicas') ?>">
            </div>
            <div class="campo<?= clase_error($errores, 'medicamentos') ?>">
                <label for="medicamentos">Medicamentos que toma</label>
                <input id="medicamentos" name="medicamentos" maxlength="250" value="<?= $v('medicamentos') ?>">
            </div>
            <div class="campo<?= clase_error($errores, 'antecedentes') ?>">
                <label for="antecedentes">Antecedentes relevantes</label>
                <input id="antecedentes" name="antecedentes" maxlength="500" value="<?= $v('antecedentes') ?>">
            </div>
        </fieldset>

        <fieldset class="grupo-campos">
            <legend>Contacto de emergencia <span class="opcional" style="font-family:var(--fuente);font-size:.85rem;font-weight:400;color:var(--tinta-tenue)">(opcional)</span></legend>
            <div class="campo<?= clase_error($errores, 'contacto_nombre') ?>">
                <label for="contacto_nombre">Nombre</label>
                <input id="contacto_nombre" name="contacto_nombre" maxlength="120" value="<?= $vc('contacto_nombre', 'nombre') ?>">
                <?= error_campo($errores, 'contacto_nombre') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'contacto_parentesco') ?>">
                <label for="contacto_parentesco">Parentesco</label>
                <input id="contacto_parentesco" name="contacto_parentesco" maxlength="40" value="<?= $vc('contacto_parentesco', 'parentesco') ?>">
                <?= error_campo($errores, 'contacto_parentesco') ?>
            </div>
            <div class="campo campo-3<?= clase_error($errores, 'contacto_telefono') ?>">
                <label for="contacto_telefono">Teléfono</label>
                <input id="contacto_telefono" name="contacto_telefono" type="tel" pattern="[0-9+\-\s]{7,20}" value="<?= $vc('contacto_telefono', 'telefono') ?>">
                <?= error_campo($errores, 'contacto_telefono') ?>
            </div>
        </fieldset>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <a class="boton boton-secundario" href="<?= e(url($es ? '/pacientes/' . $paciente['id_paciente'] : '/pacientes')) ?>">Cancelar</a>
        <button class="boton boton-primario" type="submit"><?= icono('check') ?><?= $es ? 'Guardar cambios' : 'Registrar paciente' ?></button>
    </div>
</form>
