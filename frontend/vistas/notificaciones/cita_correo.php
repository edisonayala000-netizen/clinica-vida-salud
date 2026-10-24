<?php
/**
 * IU Confirmar o cancelar la cita desde el correo (CU18, sin iniciar sesión).
 * El enlace solo muestra la cita: la acción se hace con un botón (POST), así un programa
 * que abre los enlaces del correo para revisarlos no confirma ni cancela nada.
 */
$c = $cita;
?>
<div class="acceso">
    <?= \App\Nucleo\Vista::parcial('compartido/portada_acceso') ?>

    <section class="acceso-formulario">
        <div class="tarjeta-acceso">
            <img class="acceso-logo-movil" src="<?= e(recurso('img/logo.svg')) ?>" alt="" width="44" height="44">
            <?php if (!$c): ?>
                <h1>Enlace no válido</h1>
                <div class="acceso-aviso" role="alert"><?= icono('alerta') ?>
                    <p>El enlace no es válido o la cita ya comenzó. Si necesita ayuda, llame a recepción o ingrese al sistema.</p></div>
                <a class="boton boton-primario boton-bloque" href="<?= e(url('/login')) ?>">Ir al sistema</a>
            <?php else: ?>
                <h1>Su cita <?= e($c['codigo']) ?></h1>
                <dl class="datos">
                    <dt>Paciente</dt><dd><?= e($c['paciente']) ?></dd>
                    <dt>Fecha</dt><dd><?= e(ucfirst(fecha_larga($c['fecha']))) ?>, <?= e(hora($c['hora_inicio'])) ?></dd>
                    <dt>Médico</dt><dd><?= e($c['medico']) ?> · <?= e($c['especialidad']) ?></dd>
                    <dt>Estado</dt><dd><?= estado_cita($c['estado']) ?></dd>
                </dl>

                <?php if (in_array('confirmada', $acciones, true)): ?>
                    <form method="post" action="<?= e(url('/correo/cita')) ?>">
                        <?= csrf_campo() ?><input type="hidden" name="e" value="<?= e($token) ?>"><input type="hidden" name="accion" value="confirmar">
                        <button class="boton boton-primario boton-bloque" type="submit"><?= icono('check') ?>Confirmar mi asistencia</button>
                    </form>
                <?php elseif ($c['estado'] === 'confirmada'): ?>
                    <p class="texto-suave">Su asistencia ya está confirmada. ¡Le esperamos!</p>
                <?php endif; ?>

                <?php if (in_array('cancelada', $acciones, true)): ?>
                    <details class="cancelar-desde-correo">
                        <summary>No podré asistir: cancelar la cita</summary>
                        <form method="post" action="<?= e(url('/correo/cita')) ?>" data-confirmar="¿Cancelar la cita <?= e($c['codigo']) ?>? El horario quedará libre para otro paciente." data-confirmar-boton="Cancelar cita" data-peligro="1">
                            <?= csrf_campo() ?><input type="hidden" name="e" value="<?= e($token) ?>"><input type="hidden" name="accion" value="cancelar">
                            <div class="campo"><label for="motivo">Motivo <span class="opcional">(opcional)</span></label>
                                <textarea id="motivo" name="motivo" maxlength="250" placeholder="Ej.: tuve un viaje imprevisto"></textarea></div>
                            <button class="boton boton-peligro boton-bloque" type="submit">Cancelar la cita</button>
                        </form>
                    </details>
                <?php elseif (!in_array($c['estado'], ['cancelada', 'atendida', 'inasistencia'], true)): ?>
                    <p class="texto-suave">Las citas se cancelan en línea con al menos 2 horas de anticipación. Para cancelarla ahora, llame a recepción.</p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="acceso-registro"><a href="<?= e(url('/login')) ?>">Ingresar al sistema</a></p>
        </div>
    </section>
</div>
