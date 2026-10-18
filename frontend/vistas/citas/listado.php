<?php
/** IU Citas por fecha y estado (CU13) */
$estados = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];
$params = array_filter($filtros, static fn ($v) => $v !== null && $v !== '');
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Citas</h1>
        <p>Consulte las citas por fecha, médico, especialidad y estado. Por defecto se muestran las de hoy.</p>
    </div>
    <div class="acciones">
        <a class="boton boton-primario" href="<?= e(url('/citas/nueva')) ?>"><?= icono('calendario-mas') ?>Programar cita</a>
    </div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/citas')) ?>">
        <div class="campo"><label for="desde">Desde</label><input id="desde" name="desde" type="date" value="<?= e($filtros['desde']) ?>"></div>
        <div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($filtros['hasta']) ?>"></div>
        <div class="campo"><label for="medico">Médico</label>
            <select id="medico" name="medico"><option value="">Todos</option>
                <?php foreach ($medicos as $m): ?><option value="<?= (int) $m['id_medico'] ?>"<?= seleccionado($filtros['medico'], $m['id_medico']) ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad"><option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?><option value="<?= (int) $esp['id_especialidad'] ?>"<?= seleccionado($filtros['especialidad'], $esp['id_especialidad']) ?>><?= e($esp['nombre']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="estado">Estado</label>
            <select id="estado" name="estado"><option value="">Todos</option>
                <?php foreach ($estados as $s): ?><option value="<?= $s ?>"<?= seleccionado($filtros['estado'], $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo campo-buscar"><label for="q">Paciente o código</label>
            <div class="campo-con-icono"><?= icono('buscar') ?><input id="q" name="q" type="search" value="<?= e($filtros['q']) ?>" placeholder="CM-0001, nombre o CI"></div></div>
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Filtrar</button>
    </form>

    <nav class="pestanas" aria-label="Filtrar por estado">
        <a class="<?= !$filtros['estado'] ? 'activa' : '' ?>" href="<?= e(url('/citas', array_diff_key($params, ['estado' => 1, 'pagina' => 1]))) ?>">Todas <span class="contador"><?= array_sum($resumen) ?></span></a>
        <?php foreach ($estados as $s): ?>
            <a class="<?= $filtros['estado'] === $s ? 'activa' : '' ?>" href="<?= e(url('/citas', ['estado' => $s] + $params)) ?>"><?= ucfirst($s) ?> <span class="contador"><?= (int) ($resumen[$s] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$citas): ?>
        <div class="vacio"><?= icono('calendario') ?><h3>No hay citas con esos filtros</h3><p>Cambie las fechas o el estado.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th class="col-acciones"></th></tr></thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr>
                    <td><a class="codigo" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= e($c['codigo']) ?></a></td>
                    <td class="numero"><?= e(fecha_corta($c['fecha'])) ?></td>
                    <td class="numero"><?= e(hora($c['hora_inicio'])) ?>–<?= e(hora($c['hora_fin'])) ?></td>
                    <td><span class="celda-principal"><?= e($c['paciente']) ?></span><span class="celda-sub"><?= e($c['ci_paciente'] ? 'CI ' . $c['ci_paciente'] : $c['codigo_paciente']) ?></span></td>
                    <td><?= e($c['medico']) ?></td>
                    <td><?= e($c['especialidad']) ?></td>
                    <td><?= estado_cita($c['estado']) ?></td>
                    <td class="col-acciones"><a class="boton boton-secundario boton-chico" href="<?= e(url('/citas/' . $c['id_cita'])) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= \App\Nucleo\Vista::parcial('compartido/paginacion', [
        'pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/citas', 'params' => $params, 'nombre' => 'cita(s)',
    ]) ?>
</section>
