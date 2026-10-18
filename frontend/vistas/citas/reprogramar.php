<?php /** IU Reprogramar cita (CU10) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/citas/' . $cita['id_cita'])) ?>"><?= icono('flecha-izq') ?>Cita <?= e($cita['codigo']) ?></a>
        <h1>Reprogramar cita <?= e($cita['codigo']) ?></h1>
        <p>Elija la nueva fecha y hora con el mismo médico. El horario actual de esta cita aparece como libre.</p>
    </div>
</div>

<form class="rejilla-lateral" method="post" action="<?= e(url('/citas/' . $cita['id_cita'] . '/reprogramar')) ?>" id="form-cita" novalidate
      data-api="<?= e(url('/api')) ?>" data-hoy="<?= date('Y-m-d') ?>" data-excluir="<?= (int) $cita['id_cita'] ?>" data-medico-fijo="<?= (int) $cita['id_medico'] ?>">
    <?= csrf_campo() ?>
    <input type="hidden" id="id_paciente" value="<?= (int) $cita['id_paciente'] ?>">
    <input type="hidden" name="fecha" id="fecha" value="">
    <input type="hidden" name="hora_inicio" id="hora_inicio" value="">

    <section class="tarjeta">
        <div class="tarjeta-cuerpo pasos">
            <div class="paso listo">
                <h3>Cita actual</h3>
                <dl class="datos">
                    <dt>Paciente</dt><dd><?= e($cita['paciente']) ?></dd>
                    <dt>Médico</dt><dd><?= e($cita['medico']) ?> · <?= e($cita['especialidad']) ?></dd>
                    <dt>Fecha y hora</dt><dd><?= e(ucfirst(fecha_larga($cita['fecha']))) ?>, <?= e(hora($cita['hora_inicio'])) ?>–<?= e(hora($cita['hora_fin'])) ?></dd>
                    <dt>Estado</dt><dd><?= estado_cita($cita['estado']) ?></dd>
                </dl>
            </div>
            <div class="paso" id="paso-fecha"><h3>Nueva fecha</h3><div id="zona-dias"><p class="cargando">Consultando días de atención…</p></div>
                <?= error_campo($errores, 'fecha') ?></div>
            <div class="paso" id="paso-hora"><h3>Nuevo horario</h3><div id="zona-horarios"><p class="texto-tenue">Elija una fecha.</p></div>
                <div class="leyenda" aria-hidden="true"><span><i></i>Libre</span><span><i class="ocupado"></i>Ocupado</span><span><i class="seleccionado"></i>Elegido</span></div>
                <?= error_campo($errores, 'hora_inicio') ?></div>
            <div class="paso" id="paso-detalle"><h3>Motivo del cambio</h3>
                <div class="campo<?= clase_error($errores, 'motivo_cambio') ?>">
                    <label for="motivo_cambio">Motivo <span class="opcional">(opcional)</span></label>
                    <input id="motivo_cambio" name="motivo_cambio" maxlength="200" placeholder="Ej.: el paciente pidió cambiar por viaje" value="<?= viejo($anterior, 'motivo_cambio') ?>">
                    <?= error_campo($errores, 'motivo_cambio') ?>
                </div>
            </div>
        </div>
    </section>

    <aside class="tarjeta resumen-cita" aria-live="polite">
        <header class="tarjeta-cabecera"><h2>Nuevo horario</h2></header>
        <div class="tarjeta-cuerpo">
            <dl class="datos">
                <dt>Antes</dt><dd><?= e(fecha_corta($cita['fecha'])) ?> · <?= e(hora($cita['hora_inicio'])) ?></dd>
                <dt>Fecha</dt><dd id="r-fecha">—</dd>
                <dt>Horario</dt><dd id="r-hora">—</dd>
            </dl>
            <p class="texto-tenue">El cambio queda registrado en el historial de la cita con la fecha anterior y la nueva.</p>
            <button class="boton boton-primario boton-bloque" type="submit" id="confirmar-cita" disabled><?= icono('repetir') ?>Reprogramar</button>
        </div>
    </aside>
</form>
