<?php
/** IU Generar reportes (CU14) — vista en pantalla, impresión/PDF y CSV */
$f = $filtros;
$soloFecha = $tipo === 'agenda';
$csv = url('/reportes', ['tipo' => $tipo, 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'estado' => $f['estado'], 'formato' => 'csv']);
$estadosTxt = ['programada' => 'Programadas', 'confirmada' => 'Confirmadas', 'atendida' => 'Atendidas', 'cancelada' => 'Canceladas', 'inasistencia' => 'Inasistencias'];
$periodo = $soloFecha || $f['desde'] === $f['hasta'] ? fecha_corta($f['desde']) : fecha_corta($f['desde']) . ' al ' . fecha_corta($f['hasta']);
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Reportes</h1>
        <p>Elija el reporte y el período. Puede imprimirlo o guardarlo como PDF, o descargarlo en CSV para Excel.</p>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e($csv) ?>"><?= icono('descargar') ?>Descargar CSV</a>
        <button class="boton boton-primario" type="button" data-imprimir><?= icono('imprimir') ?>Imprimir / PDF</button>
    </div>
</div>

<section class="tarjeta">
    <nav class="pestanas" aria-label="Tipo de reporte">
        <?php foreach ($tipos as $k => $t): ?>
            <a class="<?= $k === $tipo ? 'activa' : '' ?>" href="<?= e(url('/reportes', ['tipo' => $k])) ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="filtros" method="get" action="<?= e(url('/reportes')) ?>">
        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
        <div class="campo"><label for="desde"><?= $soloFecha ? 'Fecha' : 'Desde' ?></label><input id="desde" name="desde" type="date" value="<?= e($f['desde']) ?>"></div>
        <?php if (!$soloFecha): ?>
            <div class="campo"><label for="hasta">Hasta</label><input id="hasta" name="hasta" type="date" value="<?= e($f['hasta']) ?>"></div>
        <?php endif; ?>
        <?php if ($tipo === 'fecha'): ?>
            <div class="campo"><label for="estado">Estado</label>
                <select id="estado" name="estado"><option value="">Todos</option>
                    <?php foreach ($estadosTxt as $k => $t): ?><option value="<?= $k ?>"<?= seleccionado($f['estado'], $k) ?>><?= $t ?></option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Generar</button>
    </form>

    <div class="reporte-cabecera-impresion">
        <img src="<?= e(recurso('img/logo.svg')) ?>" width="40" height="40" alt="">
        <div><strong>Clínica Vida Salud — <?= e($tipos[$tipo]) ?></strong><br>Período: <?= e($periodo) ?> · Generado el <?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="tarjeta-cuerpo" style="padding-bottom:0"><h2><?= e($tipos[$tipo]) ?> <span class="texto-tenue" style="font-family:var(--fuente);font-size:.9rem">· <?= e($periodo) ?></span></h2></div>

    <?php if (!$filas): ?>
        <div class="vacio"><?= icono('reporte') ?><h3>Sin datos para este período</h3><p>Pruebe con otro rango de fechas.</p></div>

    <?php elseif ($tipo === 'fecha' || $tipo === 'agenda'): ?>
        <?php $medicoActual = null; ?>
        <div class="tabla-contenedor"><table class="tabla">
            <thead><tr><?php if ($tipo === 'fecha'): ?><th>Fecha</th><?php endif; ?><th>Hora</th><th>Código</th><th>Paciente</th><?php if ($tipo === 'fecha'): ?><th>Médico</th><?php endif; ?><th>Especialidad</th><th>Estado</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <?php if ($tipo === 'agenda' && $r['medico'] !== $medicoActual): $medicoActual = $r['medico']; ?>
                    <tr class="matriz modulo"><td colspan="6" style="background:#F6FAF9;font-weight:700;color:var(--pino)"><?= e($r['medico']) ?></td></tr>
                <?php endif; ?>
                <tr>
                    <?php if ($tipo === 'fecha'): ?><td class="numero"><?= e(fecha_corta($r['fecha'])) ?></td><?php endif; ?>
                    <td class="numero"><?= e(hora($r['hora_inicio'])) ?>–<?= e(hora($r['hora_fin'])) ?></td>
                    <td><span class="codigo"><?= e($r['codigo']) ?></span></td>
                    <td><?= e($r['paciente']) ?></td>
                    <?php if ($tipo === 'fecha'): ?><td><?= e($r['medico']) ?></td><?php endif; ?>
                    <td><?= e($r['especialidad']) ?></td>
                    <td><?= estado_cita($r['estado']) ?></td>
                    <td><span class="celda-sub"><?= e($r['motivo']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="totales"><span>Total de citas: <strong><?= count($filas) ?></strong></span>
            <?php foreach (array_count_values(array_column($filas, 'estado')) as $s => $n): ?><span><?= e($estadosTxt[$s]) ?>: <strong><?= $n ?></strong></span><?php endforeach; ?></div>

    <?php elseif ($tipo === 'medico' || $tipo === 'especialidad'): ?>
        <?php $tot = ['total' => 0, 'programadas' => 0, 'confirmadas' => 0, 'atendidas' => 0, 'canceladas' => 0, 'inasistencias' => 0]; ?>
        <div class="tarjeta-cuerpo grafico-barras">
            <?php $max = max(1, ...array_map(static fn ($r) => (int) $r['total'], $filas)); ?>
            <?php foreach ($filas as $r): ?>
                <div class="barra-fila" style="grid-template-columns: 220px 1fr 44px">
                    <span><?= e($r['grupo']) ?></span>
                    <span class="barra-pista"><span class="barra-relleno" style="width: <?= round(100 * $r['total'] / $max) ?>%"></span></span>
                    <span class="barra-valor"><?= (int) $r['total'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="tabla-contenedor"><table class="tabla">
            <thead><tr><th><?= $tipo === 'medico' ? 'Médico' : 'Especialidad' ?></th><th>Total</th><th>Programadas</th><th>Confirmadas</th><th>Atendidas</th><th>Canceladas</th><th>Inasistencias</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $r): foreach ($tot as $k => $_) { $tot[$k] += (int) $r[$k]; } ?>
                <tr><td class="celda-principal"><?= e($r['grupo']) ?></td><td class="numero"><strong><?= (int) $r['total'] ?></strong></td>
                    <td class="numero"><?= (int) $r['programadas'] ?></td><td class="numero"><?= (int) $r['confirmadas'] ?></td>
                    <td class="numero"><?= (int) $r['atendidas'] ?></td><td class="numero"><?= (int) $r['canceladas'] ?></td><td class="numero"><?= (int) $r['inasistencias'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>Totales</td><?php foreach ($tot as $v): ?><td class="numero"><?= $v ?></td><?php endforeach; ?></tr></tfoot>
        </table></div>

    <?php elseif ($tipo === 'estado'): ?>
        <?php $total = array_sum(array_column($filas, 'total')); ?>
        <div class="tarjeta-cuerpo grafico-barras">
            <?php foreach ($filas as $r): ?>
                <div class="barra-fila" style="grid-template-columns: 140px 1fr 90px">
                    <span><?= estado_cita($r['estado']) ?></span>
                    <span class="barra-pista"><span class="barra-relleno <?= e($r['estado']) ?>" style="width: <?= $total ? round(100 * $r['total'] / $total) : 0 ?>%"></span></span>
                    <span class="barra-valor"><?= (int) $r['total'] ?> · <?= $total ? round(100 * $r['total'] / $total) : 0 ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="totales"><span>Total de citas del período: <strong><?= $total ?></strong></span></div>

    <?php elseif ($tipo === 'pacientes'): ?>
        <div class="tabla-contenedor"><table class="tabla">
            <thead><tr><th>Código</th><th>CI</th><th>Paciente</th><th>Sexo</th><th>Edad</th><th>Teléfono</th><th>Registrado</th><th>Citas</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr><td><span class="codigo"><?= e($r['codigo']) ?></span></td><td class="numero"><?= e($r['ci'] ?: '—') ?></td><td><?= e($r['paciente']) ?></td>
                    <td><?= e(ucfirst($r['sexo'])) ?></td><td><?= e(edad($r['fecha_nacimiento'])) ?></td><td class="numero"><?= e($r['telefono']) ?></td>
                    <td class="numero"><?= e(fecha_hora($r['creado_en'])) ?></td><td class="numero"><?= (int) $r['citas'] ?></td><td><?= estado_registro($r['estado']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="totales"><span>Registrados en el período: <strong><?= count($filas) ?></strong></span>
            <span>Total en el sistema: <strong><?= (int) $totales['total'] ?></strong></span>
            <span>Activos: <strong><?= (int) $totales['activos'] ?></strong></span><span>Inactivos: <strong><?= (int) $totales['inactivos'] ?></strong></span></div>

    <?php elseif ($tipo === 'asistencia'): ?>
        <?php $t = ['total' => 0, 'atendidas' => 0, 'canceladas' => 0, 'inasistencias' => 0]; foreach ($filas as $r) { foreach ($t as $k => $_) { $t[$k] += (int) $r[$k]; } } ?>
        <div class="tarjeta-cuerpo">
            <div class="indicadores" style="margin-bottom:0">
                <div class="indicador"><div class="indicador-valor"><?= $t['total'] ?></div><div class="indicador-etiqueta">Citas del período</div></div>
                <div class="indicador"><div class="indicador-valor"><?= $t['atendidas'] ?></div><div class="indicador-etiqueta">Atendidas</div></div>
                <div class="indicador"><div class="indicador-valor"><?= $t['canceladas'] ?></div><div class="indicador-etiqueta">Canceladas</div></div>
                <div class="indicador"><div class="indicador-valor"><?= $t['inasistencias'] ?></div><div class="indicador-etiqueta">Inasistencias</div></div>
            </div>
        </div>
        <div class="tabla-contenedor"><table class="tabla">
            <thead><tr><th>Fecha</th><th>Total</th><th>Atendidas</th><th>Canceladas</th><th>Inasistencias</th><th>Pendientes</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $r): ?>
                <tr><td class="numero"><?= e(ucfirst(fecha_larga($r['fecha'], false))) ?></td><td class="numero"><strong><?= (int) $r['total'] ?></strong></td>
                    <td class="numero"><?= (int) $r['atendidas'] ?></td><td class="numero"><?= (int) $r['canceladas'] ?></td>
                    <td class="numero"><?= (int) $r['inasistencias'] ?></td><td class="numero"><?= (int) $r['programadas'] + (int) $r['confirmadas'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>Totales</td><td class="numero"><?= $t['total'] ?></td><td class="numero"><?= $t['atendidas'] ?></td><td class="numero"><?= $t['canceladas'] ?></td><td class="numero"><?= $t['inasistencias'] ?></td><td class="numero"><?= $t['total'] - $t['atendidas'] - $t['canceladas'] - $t['inasistencias'] ?></td></tr></tfoot>
        </table></div>
    <?php endif; ?>
</section>
