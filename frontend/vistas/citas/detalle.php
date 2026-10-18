<?php
/** IU Detalle de la cita con acciones de CU10, CU11 y CU12 e historial de cambios. */
use App\Nucleo\Autenticacion;

$c = $cita;
$p = $paciente;
$u = Autenticacion::usuario();
$esPaciente = $u['rol'] === 'paciente';
$accionesHistorial = ['registro' => 'Cita registrada', 'cambio_estado' => 'Cambio de estado', 'reprogramacion' => 'Reprogramación'];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url($esPaciente ? '/mis-citas' : (Autenticacion::puede('citas.ver') ? '/citas' : '/agenda'))) ?>"><?= icono('flecha-izq') ?><?= $esPaciente ? 'Mis citas' : (Autenticacion::puede('citas.ver') ? 'Citas' : 'Agenda') ?></a>
        <h1>Cita médica <?= e($c['codigo']) ?> <?= estado_cita($c['estado']) ?></h1>
        <p>Registrada el <?= e(fecha_hora($c['fecha_registro'])) ?><?= $c['registrado_por'] ? ' por ' . e($c['registrado_por']) : '' ?>.</p>
    </div>
    <?php if (!$esPaciente): ?>
    <div class="acciones">
        <?php if (in_array('confirmada', $acciones, true) && Autenticacion::puede('citas.confirmar')): ?>
            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>"><?= csrf_campo() ?><input type="hidden" name="estado" value="confirmada">
                <button class="boton boton-secundario" type="submit"><?= icono('check') ?>Confirmar asistencia</button></form>
        <?php endif; ?>
        <?php if (in_array('atendida', $acciones, true) && Autenticacion::puede('citas.atender')): ?>
            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>" data-confirmar="¿Marcar la cita <?= e($c['codigo']) ?> como atendida? Es un estado final." data-confirmar-boton="Marcar atendida">
                <?= csrf_campo() ?><input type="hidden" name="estado" value="atendida">
                <button class="boton boton-primario" type="submit"><?= icono('check-circulo') ?>Marcar atendida</button></form>
        <?php endif; ?>
        <?php if (in_array('inasistencia', $acciones, true) && Autenticacion::puede('citas.confirmar')): ?>
            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>" data-confirmar="¿Registrar que el paciente no se presentó?" data-confirmar-boton="Registrar inasistencia">
                <?= csrf_campo() ?><input type="hidden" name="estado" value="inasistencia">
                <button class="boton boton-secundario" type="submit"><?= icono('alerta') ?>Inasistencia</button></form>
        <?php endif; ?>
        <?php if (in_array('reprogramar', $acciones, true) && Autenticacion::puede('citas.reprogramar')): ?>
            <a class="boton boton-secundario" href="<?= e(url('/citas/' . $c['id_cita'] . '/reprogramar')) ?>"><?= icono('repetir') ?>Reprogramar</a>
        <?php endif; ?>
        <?php if (in_array('cancelada', $acciones, true) && Autenticacion::puede('citas.cancelar')): ?>
            <button class="boton boton-peligro" type="button" data-abrir-modal="modal-cancelar"><?= icono('calendario-x') ?>Cancelar cita</button>
        <?php endif; ?>
        <?php if (!$acciones && Autenticacion::puede('citas.programar') && $p['estado'] === 'activo'): ?>
            <a class="boton boton-primario" href="<?= e(url('/citas/nueva', ['paciente' => $c['id_paciente']])) ?>"><?= icono('calendario-mas') ?>Programar nueva cita</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p><?= e(implode(' ', $errores)) ?></p></div>
<?php endif; ?>

<div class="rejilla-lateral">
    <div class="columna">
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Datos de la cita</h2></header>
            <div class="tarjeta-cuerpo">
                <dl class="datos">
                    <dt>Paciente</dt><dd><?php if (Autenticacion::puede('pacientes.ver')): ?><a href="<?= e(url('/pacientes/' . $c['id_paciente'])) ?>"><?= e($c['paciente']) ?></a><?php else: ?><?= e($c['paciente']) ?><?php endif; ?>
                        <span class="celda-sub"><?= e($c['codigo_paciente']) ?><?= $c['ci_paciente'] ? ' · CI ' . e($c['ci_paciente']) : '' ?> · <?= e(edad($p['fecha_nacimiento'])) ?> · <?= e(ucfirst($p['sexo'])) ?></span></dd>
                    <dt>Especialidad</dt><dd><?= e($c['especialidad']) ?></dd>
                    <dt>Médico</dt><dd><?= e($c['medico']) ?></dd>
                    <dt>Fecha</dt><dd><?= e(ucfirst(fecha_larga($c['fecha']))) ?></dd>
                    <dt>Horario</dt><dd class="numero"><?= e(hora($c['hora_inicio'])) ?> – <?= e(hora($c['hora_fin'])) ?></dd>
                    <dt>Modalidad</dt><dd><?= $c['modalidad'] === 'virtual' ? 'Virtual' : 'Presencial' ?></dd>
                    <dt>Motivo</dt><dd><?= e($c['motivo'] ?: 'Sin motivo indicado') ?></dd>
                    <?php if ($c['estado'] === 'cancelada'): ?>
                        <dt>Motivo de cancelación</dt><dd><?= e($c['motivo_cancelacion']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </section>
        <?php if ($u['rol'] === 'medico'): ?>
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Datos básicos del paciente</h2></header>
            <div class="tarjeta-cuerpo">
                <?php if ($p['compartir_info_medica']): ?>
                    <dl class="ficha-medica">
                        <div><dt>Tipo de sangre</dt><dd><?= e($p['tipo_sangre'] ?: 'No registrado') ?></dd></div>
                        <div><dt>Alergias</dt><dd><?= e($p['alergias'] ?: 'Ninguna') ?></dd></div>
                        <div><dt>Enfermedades crónicas</dt><dd><?= e($p['enfermedades_cronicas'] ?: 'Ninguna') ?></dd></div>
                        <div><dt>Medicamentos</dt><dd><?= e($p['medicamentos'] ?: 'Ninguno') ?></dd></div>
                    </dl>
                <?php else: ?>
                    <p class="privado"><?= icono('escudo') ?>El paciente eligió no compartir su información médica en el sistema.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
    <section class="tarjeta">
        <header class="tarjeta-cabecera"><h2>Historial de la cita</h2></header>
        <div class="tarjeta-cuerpo">
            <ol class="linea-tiempo">
                <?php foreach ($historial as $h): ?>
                    <li>
                        <strong><?= e($accionesHistorial[$h['accion']]) ?></strong>
                        <?php if ($h['accion'] === 'cambio_estado'): ?>
                            <span class="texto-suave">de <?= e($h['estado_anterior']) ?> a <?= e($h['estado_nuevo']) ?></span>
                        <?php elseif ($h['accion'] === 'reprogramacion'): ?>
                            <span class="texto-suave">del <?= e(fecha_corta($h['fecha_anterior'])) ?> <?= e(hora($h['hora_anterior'])) ?> al <?= e(fecha_corta($h['fecha_nueva'])) ?> <?= e(hora($h['hora_nueva'])) ?></span>
                        <?php endif; ?>
                        <?php if ($h['detalle']): ?><span class="celda-sub"><?= e($h['detalle']) ?></span><?php endif; ?>
                        <span class="cuando"><?= e(fecha_hora($h['fecha_hora'])) ?><?= $h['usuario'] ? ' · ' . e($h['usuario']) : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
</div>

<dialog class="modal" id="modal-cancelar" aria-labelledby="t-cancelar">
    <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/cancelar')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="modal-cabecera"><h2 id="t-cancelar">Cancelar cita <?= e($c['codigo']) ?></h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <p class="texto-suave">La cita no se borra: queda como «cancelada» en el historial y el horario se libera para otro paciente.</p>
            <div class="campo"><label for="motivo">Motivo de la cancelación</label>
                <textarea id="motivo" name="motivo" required minlength="5" maxlength="250" placeholder="Ej.: el paciente avisó que no podrá asistir"></textarea></div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Volver</button>
            <button type="submit" class="boton boton-peligro-lleno">Cancelar cita</button>
        </div>
    </form>
</dialog>
