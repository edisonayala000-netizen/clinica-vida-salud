<?php /** IU Ficha del médico (CU07) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/medicos')) ?>"><?= icono('flecha-izq') ?>Médicos</a>
        <div class="perfil-cabecera">
            <span class="avatar" aria-hidden="true"><?= e(iniciales($medico['nombres'] . ' ' . $medico['apellidos'])) ?></span>
            <div>
                <h1>Dr(a). <?= e($medico['nombres'] . ' ' . $medico['apellidos']) ?></h1>
                <p><span class="codigo"><?= e($medico['codigo']) ?></span> · <?= e($medico['especialidades']) ?> · <?= estado_registro($medico['estado']) ?></p>
            </div>
        </div>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e(url('/agenda', ['medico' => $medico['id_medico']])) ?>"><?= icono('agenda') ?>Consultar agenda</a>
        <?php if ($puedeGestionar): ?>
            <a class="boton boton-secundario" href="<?= e(url('/medicos/' . $medico['id_medico'] . '/editar')) ?>"><?= icono('editar') ?>Modificar</a>
            <form method="post" action="<?= e(url('/medicos/' . $medico['id_medico'] . '/estado')) ?>"
                  data-confirmar="<?= $medico['estado'] === 'activo'
                      ? 'El médico dejará de recibir citas nuevas.' . ($pendientes ? " Sus {$pendientes} cita(s) pendiente(s) se cancelarán y quedarán en el historial." : '') . ' Su historial se conserva.'
                      : '¿Activar al médico para que vuelva a recibir citas?' ?>"
                  data-confirmar-titulo="<?= $medico['estado'] === 'activo' ? 'Desactivar médico' : 'Activar médico' ?>"
                  data-confirmar-boton="<?= $medico['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" <?= $medico['estado'] === 'activo' ? 'data-peligro="1"' : '' ?>>
                <?= csrf_campo() ?>
                <button class="boton <?= $medico['estado'] === 'activo' ? 'boton-peligro' : 'boton-primario' ?>" type="submit"><?= icono('power') ?><?= $medico['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="rejilla-lateral">
    <section class="tarjeta">
        <header class="tarjeta-cabecera"><h2>Horarios de atención</h2>
            <?php if (\App\Nucleo\Autenticacion::puede('horarios.gestionar')): ?>
            <div class="acciones"><a class="boton boton-texto" href="<?= e(url('/horarios', ['medico' => $medico['id_medico']])) ?>">Gestionar horarios</a></div>
            <?php endif; ?></header>
        <?php if (!$horarios): ?>
            <div class="vacio"><?= icono('reloj') ?><h3>Sin horarios</h3><p>Defina en qué días y horas atiende para poder programarle citas.</p></div>
        <?php else: ?>
        <div class="tabla-contenedor"><table class="tabla">
            <thead><tr><th>Día</th><th>Horario</th><th>Duración de cita</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($horarios as $h): ?>
                <tr class="<?= $h['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td class="celda-principal"><?= e(DIAS_SEMANA[(int) $h['dia_semana']]) ?></td>
                    <td class="numero"><?= e(hora($h['hora_inicio'])) ?> – <?= e(hora($h['hora_fin'])) ?></td>
                    <td><?= (int) $h['duracion_cita'] ?> min</td>
                    <td><?= estado_registro($h['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <section class="tarjeta">
        <header class="tarjeta-cabecera"><h2>Datos</h2></header>
        <div class="tarjeta-cuerpo">
            <dl class="datos">
                <dt>Código</dt><dd><?= e($medico['codigo']) ?></dd>
                <dt>Registro prof.</dt><dd><?= e($medico['registro_profesional']) ?></dd>
                <dt>Teléfono</dt><dd><?= e($medico['telefono']) ?></dd>
                <dt>Correo</dt><dd><?= e($medico['correo']) ?></dd>
                <dt>Citas pendientes</dt><dd><?= (int) $pendientes ?></dd>
            </dl>
        </div>
    </section>
</div>
