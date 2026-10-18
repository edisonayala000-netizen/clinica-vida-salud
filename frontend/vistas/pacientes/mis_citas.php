<?php
/** IU Mis citas — seguimiento del paciente (CU05) con cancelación en línea (CU11) */
$pestanas = ['proximas' => 'Próximas', 'pasadas' => 'Pasadas', 'canceladas' => 'Canceladas', 'inasistencias' => 'Inasistencias'];
$lista = $grupos[$pestana];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Mis citas</h1>
        <p>Siga el estado de sus citas: programadas, confirmadas, atendidas y canceladas.</p>
    </div>
    <div class="acciones"><a class="boton boton-primario" href="<?= e(url('/reservar')) ?>"><?= icono('calendario-mas') ?>Reservar cita</a></div>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p><?= e(implode(' ', $errores)) ?></p></div>
<?php endif; ?>

<section class="tarjeta">
    <nav class="pestanas" aria-label="Filtrar mis citas">
        <?php foreach ($pestanas as $k => $t): ?>
            <a class="<?= $k === $pestana ? 'activa' : '' ?>" href="<?= e(url('/mis-citas', ['ver' => $k])) ?>"><?= e($t) ?> <span class="contador"><?= count($grupos[$k]) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!$lista): ?>
        <div class="vacio"><?= icono('calendario') ?><h3>No hay citas en esta sección</h3>
            <?php if ($pestana === 'proximas'): ?><a class="boton boton-primario" href="<?= e(url('/reservar')) ?>">Reservar cita</a><?php endif; ?></div>
    <?php else: ?>
    <ul class="lista-agenda">
        <?php foreach ($lista as $c): ?>
            <li>
                <span class="hora"><?= e(hora($c['hora_inicio'])) ?></span>
                <span>
                    <span class="quien"><?= e(ucfirst(fecha_larga($c['fecha']))) ?></span>
                    <span class="detalle"><?= e($c['codigo']) ?> · <?= e($c['especialidad']) ?> · <?= e($c['medico']) ?> · <?= $c['modalidad'] === 'virtual' ? 'Virtual' : 'Presencial' ?></span>
                    <?php if ($c['estado'] === 'cancelada' && $c['motivo_cancelacion']): ?><span class="detalle">Motivo: <?= e($c['motivo_cancelacion']) ?></span><?php endif; ?>
                </span>
                <span class="acciones">
                    <?= estado_cita($c['estado']) ?>
                    <?php if ($pestana === 'proximas'): ?>
                        <button class="boton boton-peligro boton-chico" type="button" data-abrir-modal="modal-cancelar"
                                data-accion="<?= e(url('/mis-citas/' . $c['id_cita'] . '/cancelar')) ?>"
                                data-campo-resumen="<?= e($c['codigo'] . ' · ' . fecha_corta($c['fecha']) . ' ' . hora($c['hora_inicio']) . ' · ' . $c['medico']) ?>">Cancelar</button>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<dialog class="modal" id="modal-cancelar" aria-labelledby="t-cancelar">
    <form method="post" action="" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="modal-cabecera"><h2 id="t-cancelar">Cancelar cita</h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <p><strong data-mostrar="resumen"></strong></p>
            <p class="texto-suave">El horario quedará libre para otro paciente. Se cancela en línea con al menos 2 horas de anticipación.</p>
            <div class="campo"><label for="motivo">Motivo de la cancelación</label>
                <textarea id="motivo" name="motivo" required minlength="5" maxlength="250"></textarea></div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Volver</button>
            <button type="submit" class="boton boton-peligro-lleno">Cancelar cita</button>
        </div>
    </form>
</dialog>
