<?php
/**
 * IU Programar cita (CU09) — la usan la recepción y el paciente en línea.
 * Pasos: paciente → especialidad y médico → fecha → horario disponible → detalles.
 * El JavaScript (assets/js/citas.js) consulta la disponibilidad al servidor;
 * al guardar, el servidor vuelve a validar todo (RN13).
 */
$portal = $modo === 'portal';
$a = $anterior;
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1><?= $portal ? 'Reservar cita' : 'Programar cita' ?></h1>
        <p>Después de elegir el médico y la fecha se muestran únicamente los horarios disponibles.</p>
    </div>
</div>

<form class="rejilla-lateral" method="post" action="<?= e($accion) ?>" id="form-cita" novalidate
      data-api="<?= e(url('/api')) ?>" data-hoy="<?= date('Y-m-d') ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="id_paciente" id="id_paciente" value="<?= e($a['id_paciente'] ?? ($paciente['id_paciente'] ?? '')) ?>">
    <input type="hidden" name="fecha" id="fecha" value="">
    <input type="hidden" name="hora_inicio" id="hora_inicio" value="">

    <section class="tarjeta">
        <div class="tarjeta-cuerpo pasos">
            <!-- Paso 1: paciente -->
            <div class="paso<?= $paciente ? ' listo' : '' ?>" id="paso-paciente">
                <h3>Paciente</h3>
                <div class="paciente-elegido" id="paciente-elegido"<?= $paciente ? '' : ' hidden' ?>>
                    <span class="avatar" id="pac-iniciales"><?= $paciente ? e(iniciales($paciente['nombres'] . ' ' . $paciente['apellidos'])) : '' ?></span>
                    <div><strong id="pac-nombre"><?= $paciente ? e($paciente['nombres'] . ' ' . $paciente['apellidos']) : '' ?></strong>
                        <span class="celda-sub" id="pac-detalle"><?= $paciente ? e($paciente['codigo'] . ($paciente['ci'] ? ' · CI ' . $paciente['ci'] : '') . ' · ' . edad($paciente['fecha_nacimiento'])) : '' ?></span></div>
                    <?php if (!$portal): ?>
                        <button class="boton boton-texto boton-chico" type="button" id="cambiar-paciente">Cambiar</button>
                    <?php endif; ?>
                </div>
                <?php if (!$portal): ?>
                <div class="selector-paciente" id="buscador-paciente"<?= $paciente ? ' hidden' : '' ?>>
                    <div class="campo-con-icono"><?= icono('buscar') ?>
                        <input type="search" id="buscar-paciente" placeholder="Escriba CI, nombre o apellido del paciente" autocomplete="off"
                               aria-label="Buscar paciente" aria-controls="resultados-paciente"></div>
                    <div class="resultados-busqueda" id="resultados-paciente" hidden role="listbox"></div>
                    <p class="ayuda">¿Es nuevo? <a href="<?= e(url('/pacientes/nuevo')) ?>">Regístrelo primero</a>.</p>
                    <?= error_campo($errores, 'id_paciente') ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Paso 2: especialidad y médico -->
            <div class="paso" id="paso-medico">
                <h3>Especialidad y médico</h3>
                <div class="grupo-campos">
                    <div class="campo<?= clase_error($errores, 'id_especialidad') ?>">
                        <label for="id_especialidad">Especialidad</label>
                        <select id="id_especialidad" name="id_especialidad" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= (int) $esp['id_especialidad'] ?>"<?= seleccionado($a['id_especialidad'] ?? '', $esp['id_especialidad']) ?>><?= e($esp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= error_campo($errores, 'id_especialidad') ?>
                    </div>
                    <div class="campo<?= clase_error($errores, 'id_medico') ?>">
                        <label for="id_medico">Médico</label>
                        <select id="id_medico" name="id_medico" required disabled data-anterior="<?= e($a['id_medico'] ?? '') ?>">
                            <option value="">Primero elija la especialidad</option>
                        </select>
                        <?= error_campo($errores, 'id_medico') ?>
                    </div>
                </div>
            </div>

            <!-- Paso 3: fecha -->
            <div class="paso" id="paso-fecha">
                <h3>Fecha</h3>
                <div id="zona-dias"><p class="texto-tenue">Elija un médico para ver sus días de atención.</p></div>
                <?= error_campo($errores, 'fecha') ?>
            </div>

            <!-- Paso 4: horario -->
            <div class="paso" id="paso-hora">
                <h3>Horario disponible</h3>
                <div id="zona-horarios"><p class="texto-tenue">Elija una fecha.</p></div>
                <div class="leyenda" aria-hidden="true"><span><i></i>Libre</span><span><i class="ocupado"></i>Ocupado</span><span><i class="seleccionado"></i>Elegido</span></div>
                <?= error_campo($errores, 'hora_inicio') ?>
            </div>

            <!-- Paso 5: detalles -->
            <div class="paso" id="paso-detalle">
                <h3>Detalles</h3>
                <div class="grupo-campos">
                    <div class="campo campo-12">
                        <span class="etiqueta-campo">Modalidad</span>
                        <div class="opciones-chips">
                            <label class="chip"><input type="radio" name="modalidad" value="presencial"<?= marcado(($a['modalidad'] ?? 'presencial') === 'presencial') ?>><span><?= icono('ubicacion') ?>Presencial</span></label>
                            <label class="chip"><input type="radio" name="modalidad" value="virtual"<?= marcado(($a['modalidad'] ?? '') === 'virtual') ?>><span><?= icono('video') ?>Virtual</span></label>
                        </div>
                    </div>
                    <div class="campo campo-12<?= clase_error($errores, 'motivo') ?>">
                        <label for="motivo">Motivo breve de la consulta <span class="opcional">(opcional)</span></label>
                        <textarea id="motivo" name="motivo" maxlength="250" placeholder="Ej.: control de presión, dolor de muela…"><?= viejo($a, 'motivo') ?></textarea>
                        <?= error_campo($errores, 'motivo') ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <aside class="tarjeta resumen-cita" aria-live="polite">
        <header class="tarjeta-cabecera"><h2>Resumen de la cita</h2></header>
        <div class="tarjeta-cuerpo">
            <dl class="datos">
                <dt>Paciente</dt><dd id="r-paciente"><?= $paciente ? e($paciente['nombres'] . ' ' . $paciente['apellidos']) : '—' ?></dd>
                <dt>Especialidad</dt><dd id="r-especialidad">—</dd>
                <dt>Médico</dt><dd id="r-medico">—</dd>
                <dt>Fecha</dt><dd id="r-fecha">—</dd>
                <dt>Horario</dt><dd id="r-hora">—</dd>
            </dl>
            <p class="texto-tenue">Al confirmar, el sistema vuelve a comprobar que el horario siga libre y genera el código de la cita.</p>
            <button class="boton boton-primario boton-bloque" type="submit" id="confirmar-cita" disabled><?= icono('check') ?><?= $portal ? 'Confirmar reserva' : 'Registrar cita' ?></button>
        </div>
    </aside>
</form>
