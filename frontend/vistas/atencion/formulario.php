<?php
/**
 * IU Registrar atención médica (CU16): diagnóstico, signos vitales y receta.
 * El JavaScript (assets/js/atencion.js) agrega y quita medicamentos, calcula el IMC
 * y cuenta las tomas; el servidor vuelve a validar todo.
 */
$c = $cita;
$p = $paciente;
$v = static fn (string $campo) => viejo($anterior, $campo);
$filas = array_values((array) ($anterior['receta'] ?? [])) ?: [[]];
$hoy = date('Y-m-d');
$proximaHora = date('H:00', strtotime('+1 hour'));

/* Una fila de la receta; $i es el índice ('__i__' en la plantilla que copia el JavaScript). */
$fila = static function (string|int $i, array $f) use ($medicamentos, $vias, $errores, $hoy, $proximaHora): string {
    $val = static fn (string $k, string $def = '') => e($f[$k] ?? $def);
    ob_start(); ?>
    <div class="fila-receta" data-fila-receta>
        <div class="campo campo-4">
            <label>Medicamento</label>
            <select name="receta[<?= $i ?>][id_medicamento]" data-medicamento>
                <option value="">Elija el medicamento…</option>
                <?php foreach ($medicamentos as $m): ?>
                    <option value="<?= (int) $m['id_medicamento'] ?>"<?= seleccionado($f['id_medicamento'] ?? '', $m['id_medicamento']) ?>>
                        <?= e($m['nombre'] . ' ' . ($m['concentracion'] ?? '') . ' · ' . $m['presentacion']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo campo-2"><label>Dosis</label>
            <input name="receta[<?= $i ?>][dosis]" maxlength="40" placeholder="1 tableta" value="<?= $val('dosis') ?>"></div>
        <div class="campo campo-2"><label>Vía</label>
            <select name="receta[<?= $i ?>][via]">
                <?php foreach ($vias as $via): ?><option value="<?= e($via) ?>"<?= seleccionado($f['via'] ?? 'oral', $via) ?>><?= e(ucfirst($via)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo campo-2"><label>Cada (horas)</label>
            <input name="receta[<?= $i ?>][frecuencia_horas]" type="number" min="1" max="168" value="<?= $val('frecuencia_horas', '8') ?>" data-frecuencia></div>
        <div class="campo campo-2"><label>Durante (días)</label>
            <input name="receta[<?= $i ?>][duracion_dias]" type="number" min="1" max="365" value="<?= $val('duracion_dias', '5') ?>" data-dias></div>
        <div class="campo campo-3"><label>Desde el día</label>
            <input name="receta[<?= $i ?>][fecha_inicio]" type="date" min="<?= e($hoy) ?>" value="<?= $val('fecha_inicio', $hoy) ?>"></div>
        <div class="campo campo-2"><label>Primera toma</label>
            <input name="receta[<?= $i ?>][hora_primera_toma]" type="time" value="<?= $val('hora_primera_toma', $proximaHora) ?>"></div>
        <div class="campo campo-5"><label>Indicaciones <span class="opcional">(opcional)</span></label>
            <input name="receta[<?= $i ?>][indicaciones]" maxlength="250" placeholder="Después de las comidas" value="<?= $val('indicaciones') ?>"></div>
        <div class="campo campo-2 fila-receta-acciones">
            <label class="casilla casilla-chica"><input type="checkbox" name="receta[<?= $i ?>][recordar]" value="1"<?= marcado(!$f || !empty($f['recordar'])) ?>>
                <span>Recordar por correo</span></label>
            <button type="button" class="boton boton-texto boton-chico" data-quitar-fila><?= icono('x') ?>Quitar</button>
        </div>
        <p class="fila-receta-resumen texto-suave" data-resumen-tomas></p>
        <?= error_campo($errores, 'receta_' . $i) ?>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= icono('flecha-izq') ?>Cita <?= e($c['codigo']) ?></a>
        <h1>Atender a <?= e($c['paciente']) ?></h1>
        <p><?= e($p['codigo']) ?> · <?= e(edad($p['fecha_nacimiento'])) ?> · <?= e(ucfirst((string) $p['sexo'])) ?> ·
           <?= e(ucfirst(fecha_larga($c['fecha']))) ?>, <?= e(hora($c['hora_inicio'])) ?> · <?= e($c['especialidad']) ?></p>
    </div>
</div>

<?php if ($p['compartir_info_medica'] && ($p['alergias'] || $p['enfermedades_cronicas'] || $p['medicamentos'])): ?>
    <div class="alerta alerta-aviso" role="note"><?= icono('alerta') ?>
        <p><?php if ($p['alergias']): ?><strong>Alergias: <?= e($p['alergias']) ?>.</strong> <?php endif; ?>
           <?php if ($p['enfermedades_cronicas']): ?>Enfermedades crónicas: <?= e($p['enfermedades_cronicas']) ?>. <?php endif; ?>
           <?php if ($p['medicamentos']): ?>Toma habitualmente: <?= e($p['medicamentos']) ?>.<?php endif; ?></p>
    </div>
<?php endif; ?>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p>Revise los datos marcados en el formulario.</p></div>
<?php endif; ?>

<form class="tarjeta" method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/atencion')) ?>" data-validar novalidate
      data-confirmar="¿Guardar la atención? La cita quedará como atendida y la atención no se podrá modificar." data-confirmar-boton="Guardar atención">
    <?= csrf_campo() ?>
    <div class="tarjeta-cuerpo formulario">
        <fieldset class="grupo-campos">
            <legend>Consulta</legend>
            <div class="campo campo-12<?= clase_error($errores, 'diagnostico') ?>">
                <label for="diagnostico">Diagnóstico <span class="requerido">*</span></label>
                <textarea id="diagnostico" name="diagnostico" required minlength="3" maxlength="500" rows="2"><?= $v('diagnostico') ?></textarea>
                <?= error_campo($errores, 'diagnostico') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'tratamiento') ?>">
                <label for="tratamiento">Tratamiento <span class="opcional">(opcional)</span></label>
                <textarea id="tratamiento" name="tratamiento" maxlength="1000" rows="3"><?= $v('tratamiento') ?></textarea>
                <?= error_campo($errores, 'tratamiento') ?>
            </div>
            <div class="campo<?= clase_error($errores, 'observaciones') ?>">
                <label for="observaciones">Observaciones <span class="opcional">(opcional)</span></label>
                <textarea id="observaciones" name="observaciones" maxlength="1000" rows="3"><?= $v('observaciones') ?></textarea>
                <?= error_campo($errores, 'observaciones') ?>
            </div>
        </fieldset>

        <fieldset class="grupo-campos" data-signos>
            <legend>Signos vitales <span class="opcional">(opcional)</span></legend>
            <?php foreach ([
                ['peso_kg', 'Peso (kg)', '0.1', '0.5', '400', '62.5'],
                ['talla_cm', 'Talla (cm)', '0.1', '30', '250', '160'],
                ['presion_sistolica', 'Presión sistólica', '1', '50', '260', '120'],
                ['presion_diastolica', 'Presión diastólica', '1', '30', '160', '80'],
                ['temperatura_c', 'Temperatura (°C)', '0.1', '30', '45', '36.5'],
                ['frecuencia_cardiaca', 'Frec. cardíaca (lpm)', '1', '20', '250', '72'],
                ['saturacion_o2', 'Saturación O₂ (%)', '1', '50', '100', '98'],
            ] as [$campo, $etiqueta, $paso, $min, $max, $ejemplo]): ?>
                <div class="campo campo-3<?= clase_error($errores, $campo) ?>">
                    <label for="<?= $campo ?>"><?= e($etiqueta) ?></label>
                    <input id="<?= $campo ?>" name="<?= $campo ?>" type="number" step="<?= $paso ?>" min="<?= $min ?>" max="<?= $max ?>"
                           placeholder="<?= $ejemplo ?>" value="<?= $v($campo) ?>">
                    <?= error_campo($errores, $campo) ?>
                </div>
            <?php endforeach; ?>
            <div class="campo campo-3">
                <span class="etiqueta-campo">IMC</span>
                <p class="valor-calculado" data-imc aria-live="polite">—</p>
            </div>
        </fieldset>

        <fieldset class="grupo-campos">
            <legend>Receta <span class="opcional">(opcional)</span></legend>
            <div class="campo-12 lista-receta" data-lista-receta>
                <?php foreach ($filas as $i => $f): ?><?= $fila($i, (array) $f) ?><?php endforeach; ?>
            </div>
            <div class="campo-12">
                <button type="button" class="boton boton-secundario" data-agregar-fila><?= icono('mas') ?>Agregar medicamento</button>
                <?php if (!$medicamentos): ?><p class="ayuda">El catálogo de medicamentos está vacío: pídale al administrador que los registre.</p><?php endif; ?>
            </div>
            <div class="campo campo-12<?= clase_error($errores, 'indicaciones') ?>">
                <label for="indicaciones">Indicaciones generales <span class="opcional">(opcional)</span></label>
                <textarea id="indicaciones" name="indicaciones" maxlength="500" rows="2" placeholder="Ej.: volver a control en 7 días"><?= $v('indicaciones') ?></textarea>
                <?= error_campo($errores, 'indicaciones') ?>
            </div>
        </fieldset>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <a class="boton boton-secundario" href="<?= e(url('/citas/' . $c['id_cita'])) ?>">Cancelar</a>
        <button class="boton boton-primario" type="submit"><?= icono('check-circulo') ?>Guardar atención</button>
    </div>
</form>

<template id="plantilla-fila-receta"><?= $fila('__i__', []) ?></template>
