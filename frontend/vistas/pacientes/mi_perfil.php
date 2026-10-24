<?php
/** IU Perfil del paciente (CU04) — secciones del punto 3.4 del caso */
use App\Paquetes\Pacientes\ReglasPaciente;

$p = $paciente;
$v = static fn (string $c) => viejo($anterior, $c, $p[$c] ?? '');
$secciones = [
    'personales' => ['Datos personales y contacto', 'usuario'],
    'medica'     => ['Información médica', 'especialidad'],
    'emergencia' => ['Contacto de emergencia', 'telefono'],
    'cuenta'     => ['Cuenta y privacidad', 'escudo'],
];
$seccion = array_key_exists($seccion, $secciones) ? $seccion : 'personales';
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <div class="perfil-cabecera">
            <span class="avatar" aria-hidden="true"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
            <div>
                <h1><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h1>
                <p><span class="codigo"><?= e($p['codigo']) ?></span> · CI <?= e($p['ci']) ?> · <?= e(edad($p['fecha_nacimiento'])) ?></p>
            </div>
        </div>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e(url('/cuenta/clave')) ?>"><?= icono('llave') ?>Cambiar contraseña</a>
    </div>
</div>

<section class="tarjeta">
    <nav class="pestanas" aria-label="Secciones del perfil">
        <?php foreach ($secciones as $k => [$t, $ico]): ?>
            <a class="<?= $k === $seccion ? 'activa' : '' ?>" href="<?= e(url('/mi-perfil', ['seccion' => $k])) ?>"<?= $k === $seccion ? ' aria-current="page"' : '' ?>><?= icono($ico) ?><?= e($t) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($seccion === 'personales'): ?>
    <form method="post" action="<?= e(url('/mi-perfil/personales')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo grupo-campos">
            <div class="campo campo-3"><label>CI</label><input value="<?= e($p['ci']) ?>" readonly></div>
            <div class="campo campo-3"><label>Fecha de nacimiento</label><input value="<?= e(fecha_corta($p['fecha_nacimiento'])) ?>" readonly></div>
            <p class="ayuda campo" style="align-self:end">El CI y la fecha de nacimiento identifican su historia clínica; para corregirlos acérquese a recepción.</p>
            <div class="campo campo-4<?= clase_error($errores, 'nombres') ?>"><label for="nombres">Nombres</label>
                <input id="nombres" name="nombres" required maxlength="60" value="<?= $v('nombres') ?>"><?= error_campo($errores, 'nombres') ?></div>
            <div class="campo campo-4<?= clase_error($errores, 'apellidos') ?>"><label for="apellidos">Apellidos</label>
                <input id="apellidos" name="apellidos" required maxlength="60" value="<?= $v('apellidos') ?>"><?= error_campo($errores, 'apellidos') ?></div>
            <div class="campo campo-4<?= clase_error($errores, 'sexo') ?>"><label for="sexo">Sexo</label>
                <select id="sexo" name="sexo" required>
                    <?php foreach (['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $k => $t): ?>
                        <option value="<?= $k ?>"<?= seleccionado($anterior['sexo'] ?? $p['sexo'], $k) ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo campo-4<?= clase_error($errores, 'telefono') ?>"><label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" type="tel" required pattern="[0-9+\-\s]{7,20}" value="<?= $v('telefono') ?>"><?= error_campo($errores, 'telefono') ?></div>
            <div class="campo campo-4<?= clase_error($errores, 'correo') ?>"><label for="correo">Correo</label>
                <input id="correo" name="correo" type="email" maxlength="120" value="<?= $v('correo') ?>"><?= error_campo($errores, 'correo') ?></div>
            <div class="campo campo-4<?= clase_error($errores, 'direccion') ?>"><label for="direccion">Dirección</label>
                <input id="direccion" name="direccion" maxlength="200" value="<?= $v('direccion') ?>"><?= error_campo($errores, 'direccion') ?></div>
        </div>
        <div class="tarjeta-pie pie-formulario"><button class="boton boton-primario" type="submit">Guardar cambios</button></div>
    </form>

    <?php elseif ($seccion === 'medica'): ?>
    <form method="post" action="<?= e(url('/mi-perfil/medica')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo grupo-campos">
            <div class="campo campo-3<?= clase_error($errores, 'tipo_sangre') ?>"><label for="tipo_sangre">Tipo de sangre</label>
                <select id="tipo_sangre" name="tipo_sangre"><option value="">No sé</option>
                    <?php foreach (ReglasPaciente::TIPOS_SANGRE as $t): ?><option<?= seleccionado($anterior['tipo_sangre'] ?? $p['tipo_sangre'], $t) ?>><?= $t ?></option><?php endforeach; ?>
                </select><?= error_campo($errores, 'tipo_sangre') ?></div>
            <div class="campo campo-8<?= clase_error($errores, 'alergias') ?>"><label for="alergias">Alergias</label><input id="alergias" name="alergias" maxlength="250" value="<?= $v('alergias') ?>" placeholder="Medicamentos, alimentos u otros"><?= error_campo($errores, 'alergias') ?></div>
            <div class="campo<?= clase_error($errores, 'enfermedades_cronicas') ?>"><label for="enfermedades_cronicas">Enfermedades crónicas</label><input id="enfermedades_cronicas" name="enfermedades_cronicas" maxlength="250" value="<?= $v('enfermedades_cronicas') ?>"><?= error_campo($errores, 'enfermedades_cronicas') ?></div>
            <div class="campo<?= clase_error($errores, 'medicamentos') ?>"><label for="medicamentos">Medicamentos que toma</label><input id="medicamentos" name="medicamentos" maxlength="250" value="<?= $v('medicamentos') ?>"><?= error_campo($errores, 'medicamentos') ?></div>
            <div class="campo campo-12<?= clase_error($errores, 'antecedentes') ?>"><label for="antecedentes">Antecedentes relevantes</label><textarea id="antecedentes" name="antecedentes" maxlength="500"><?= $v('antecedentes') ?></textarea><?= error_campo($errores, 'antecedentes') ?></div>
        </div>
        <div class="tarjeta-pie pie-formulario"><button class="boton boton-primario" type="submit">Guardar información médica</button></div>
    </form>

    <?php elseif ($seccion === 'emergencia'): ?>
    <form method="post" action="<?= e(url('/mi-perfil/emergencia')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo grupo-campos">
            <div class="campo<?= clase_error($errores, 'contacto_nombre') ?>"><label for="contacto_nombre">Nombre</label>
                <input id="contacto_nombre" name="contacto_nombre" required maxlength="120" value="<?= viejo($anterior, 'contacto_nombre', $contacto['nombre'] ?? '') ?>"><?= error_campo($errores, 'contacto_nombre') ?></div>
            <div class="campo campo-3<?= clase_error($errores, 'contacto_parentesco') ?>"><label for="contacto_parentesco">Parentesco</label>
                <input id="contacto_parentesco" name="contacto_parentesco" required maxlength="40" value="<?= viejo($anterior, 'contacto_parentesco', $contacto['parentesco'] ?? '') ?>"><?= error_campo($errores, 'contacto_parentesco') ?></div>
            <div class="campo campo-3<?= clase_error($errores, 'contacto_telefono') ?>"><label for="contacto_telefono">Teléfono</label>
                <input id="contacto_telefono" name="contacto_telefono" type="tel" required pattern="[0-9+\-\s]{7,20}" value="<?= viejo($anterior, 'contacto_telefono', $contacto['telefono'] ?? '') ?>"><?= error_campo($errores, 'contacto_telefono') ?></div>
        </div>
        <div class="tarjeta-pie pie-formulario"><button class="boton boton-primario" type="submit">Guardar contacto</button></div>
    </form>

    <?php else: ?>
    <form method="post" action="<?= e(url('/mi-perfil/cuenta')) ?>">
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo formulario">
            <?php $recCita = viejo($anterior, 'recordatorio_cita', $p['recordatorio_cita']); ?>
            <fieldset class="grupo-opciones">
                <legend><strong>Recordatorio de mis citas por correo</strong>
                    <span class="texto-suave">Se envía a <?= e($p['correo'] ?: 'su correo (aún no registró uno)') ?>.</span></legend>
                <?php foreach (['ninguno' => 'No enviar', '1h' => '1 hora antes', '24h' => '24 horas antes (con botones para confirmar o cancelar)',
                                '24h_1h' => '24 horas y 1 hora antes'] as $valor => $texto): ?>
                    <label class="opcion"><input type="radio" name="recordatorio_cita" value="<?= $valor ?>"<?= marcado($recCita === $valor) ?>> <?= e($texto) ?></label>
                <?php endforeach; ?>
                <p class="ayuda">El aviso de 1 hora antes no permite cancelar: en línea se cancela hasta 2 horas antes de la cita.</p>
            </fieldset>
            <label class="casilla"><input type="checkbox" name="recordar_medicamentos" value="1"<?= marcado((bool) $p['recordar_medicamentos']) ?>>
                <span><strong>Recordatorio de mis medicamentos</strong><br><span class="texto-suave">Un correo a la hora de cada toma de los medicamentos de mis recetas. En «Mis recetas» puede apagarlo para un medicamento en particular.</span></span></label>
            <label class="casilla"><input type="checkbox" name="compartir_info_medica" value="1"<?= marcado((bool) $p['compartir_info_medica']) ?>>
                <span><strong>Compartir mi información médica con mis médicos</strong><br><span class="texto-suave">Si lo desmarca, el médico solo verá sus datos básicos (nombre, edad y sexo) en su agenda.</span></span></label>
        </div>
        <div class="tarjeta-pie pie-formulario"><button class="boton boton-primario" type="submit">Guardar preferencias</button></div>
    </form>
    <?php endif; ?>
</section>
