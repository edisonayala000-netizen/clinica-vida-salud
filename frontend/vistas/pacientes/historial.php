<?php /** IU Historial de citas del paciente (CU05) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/pacientes/' . $paciente['id_paciente'])) ?>"><?= icono('flecha-izq') ?><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?></a>
        <h1>Historial de citas</h1>
        <p><span class="codigo"><?= e($paciente['codigo']) ?></span> · CI <?= e($paciente['ci'] ?: '—') ?> · Incluye citas pasadas, canceladas e inasistencias.</p>
    </div>
    <div class="acciones">
        <button class="boton boton-secundario" type="button" data-imprimir><?= icono('imprimir') ?>Imprimir</button>
    </div>
</div>

<div class="indicadores">
    <div class="indicador"><div class="indicador-valor"><?= array_sum($resumen) ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Citas registradas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['atendida'] ?></div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Atendidas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['cancelada'] ?></div><div class="indicador-etiqueta"><?= icono('calendario-x') ?>Canceladas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['inasistencia'] ?></div><div class="indicador-etiqueta"><?= icono('alerta') ?>Inasistencias</div></div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/pacientes/' . $paciente['id_paciente'] . '/historial')) ?>">
        <div class="campo"><label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar>
                <option value="">Todos</option>
                <?php foreach (['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'] as $s): ?>
                    <option value="<?= $s ?>"<?= seleccionado($filtros['estado'], $s) ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Filtrar</button>
    </form>
    <?php if (!$citas): ?>
        <div class="vacio"><?= icono('historial') ?><h3>No hay citas con esos filtros</h3></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Fecha y hora</th><th>Médico asignado</th><th>Especialidad</th><th>Estado</th><th>Fecha de registro</th><th>Observación</th></tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td><a class="codigo" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= e($c['codigo']) ?></a></td>
                    <td class="numero"><?= e(fecha_corta($c['fecha'])) ?><span class="celda-sub"><?= e(hora($c['hora_inicio'])) ?>–<?= e(hora($c['hora_fin'])) ?></span></td>
                    <td><?= e($c['medico']) ?></td>
                    <td><?= e($c['especialidad']) ?></td>
                    <td><?= estado_cita($c['estado']) ?></td>
                    <td class="numero"><?= e(fecha_hora($c['fecha_registro'])) ?></td>
                    <td><span class="celda-sub"><?= e($c['estado'] === 'cancelada' ? $c['motivo_cancelacion'] : $c['motivo']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($citas) ?> cita(s)</span></div>
    <?php endif; ?>
</section>
