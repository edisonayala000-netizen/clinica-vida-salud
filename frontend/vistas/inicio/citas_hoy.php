<?php
/** Lista de citas de hoy con acciones rápidas (panel). */
use App\Nucleo\Autenticacion;
use App\Paquetes\Citas\Servicios\EstadoCita;
?>
<section class="tarjeta">
    <header class="tarjeta-cabecera">
        <h2>Citas de hoy</h2>
        <div class="acciones">
            <?php if (Autenticacion::puede('citas.ver')): ?>
                <a class="boton boton-texto" href="<?= e(url('/citas')) ?>">Ver todas</a>
            <?php else: ?>
                <a class="boton boton-texto" href="<?= e(url('/agenda')) ?>">Ver agenda</a>
            <?php endif; ?>
        </div>
    </header>
    <?php if (!$hoy): ?>
        <div class="vacio"><?= icono('sol') ?><h3>Sin citas para hoy</h3><p>Las citas programadas aparecerán aquí.</p></div>
    <?php else: ?>
        <ul class="lista-agenda">
            <?php foreach ($hoy as $c): $acc = EstadoCita::acciones($c); ?>
                <li>
                    <span class="hora"><?= e(hora($c['hora_inicio'])) ?></span>
                    <span>
                        <a class="quien" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= e($c['paciente']) ?></a>
                        <span class="detalle"><?= e($c['codigo']) ?> · <?= e($c['especialidad']) ?><?= $mostrarMedico ? ' · ' . e($c['medico']) : '' ?></span>
                    </span>
                    <span class="acciones">
                        <?php if (in_array('confirmada', $acc, true) && Autenticacion::puede('citas.confirmar')): ?>
                            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>">
                                <?= csrf_campo() ?><input type="hidden" name="estado" value="confirmada"><input type="hidden" name="volver" value="/panel">
                                <button class="boton boton-secundario boton-chico" type="submit">Confirmar</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('atendida', $acc, true) && Autenticacion::puede('citas.atender')): ?>
                            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>">
                                <?= csrf_campo() ?><input type="hidden" name="estado" value="atendida"><input type="hidden" name="volver" value="/panel">
                                <button class="boton boton-secundario boton-chico" type="submit"><?= icono('check') ?>Atendida</button>
                            </form>
                        <?php endif; ?>
                        <?= estado_cita($c['estado']) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
