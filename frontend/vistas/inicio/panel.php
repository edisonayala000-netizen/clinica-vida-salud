<?php
/** IU Panel principal: contenido según el rol del usuario. */
use App\Nucleo\Autenticacion;

$nombre = explode(' ', preg_replace('/^(Dr|Dra)\.\s+/u', '', $usuario['nombre_completo']))[0];
$rol = $usuario['rol'];
?>
<div class="bienvenida">
    <div class="encabezado-texto">
        <h1><?= e(saludo()) ?>, <?= e($rol === 'medico' ? $usuario['nombre_completo'] : $nombre) ?></h1>
        <p><?= match ($rol) {
            'administrador' => 'Resumen de la actividad de la clínica.',
            'recepcionista' => 'Estas son las citas del día. Confirme las de hoy y mañana y registre la asistencia.',
            'medico'        => 'Su agenda de hoy en la Clínica Vida Salud.',
            default         => 'Desde aquí reserva sus citas y revisa su historial.',
        } ?></p>
    </div>
    <div class="acciones">
        <?php if (Autenticacion::puede('citas.programar')): ?>
            <a class="boton boton-primario" href="<?= e(url('/citas/nueva')) ?>"><?= icono('calendario-mas') ?>Programar cita</a>
        <?php endif; ?>
        <?php if (Autenticacion::puede('portal.reservar')): ?>
            <a class="boton boton-primario" href="<?= e(url('/reservar')) ?>"><?= icono('calendario-mas') ?>Reservar cita</a>
        <?php endif; ?>
        <?php if ($rol === 'medico'): ?>
            <a class="boton boton-secundario" href="<?= e(url('/agenda')) ?>"><?= icono('agenda') ?>Ver mi agenda</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($rol === 'paciente'): ?>
    <?php foreach ($recordatorios as $r): ?>
        <div class="recordatorio" role="status">
            <?= icono('campana') ?>
            <div>
                <strong>Recordatorio: tiene cita <?= $r['fecha'] === date('Y-m-d') ? 'hoy' : ($r['fecha'] === date('Y-m-d', strtotime('+1 day')) ? 'mañana' : 'el ' . e(fecha_larga($r['fecha'], false))) ?> a las <?= e(hora($r['hora_inicio'])) ?></strong>
                <span class="texto-suave"><?= e($r['especialidad']) ?> con <?= e($r['medico']) ?> · <?= $r['modalidad'] === 'virtual' ? 'Consulta virtual' : 'Preséntese 10 minutos antes' ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="indicadores">
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['pendientes'] ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Citas pendientes</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['atendidas'] ?></div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Consultas realizadas</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['canceladas'] ?></div><div class="indicador-etiqueta"><?= icono('calendario-x') ?>Canceladas</div></div>
        <a class="indicador" href="<?= e(url('/mi-perfil')) ?>"><div class="indicador-valor"><?= icono('usuario') ?></div><div class="indicador-etiqueta">Actualizar mi perfil</div></a>
    </div>

    <section class="tarjeta">
        <header class="tarjeta-cabecera"><h2>Mis próximas citas</h2>
            <div class="acciones"><a class="boton boton-texto" href="<?= e(url('/mis-citas')) ?>">Ver todas</a></div></header>
        <?php if (!$proximas): ?>
            <div class="vacio"><?= icono('calendario') ?><h3>No tiene citas pendientes</h3>
                <p>Elija especialidad, médico y horario en pocos pasos.</p>
                <a class="boton boton-primario" href="<?= e(url('/reservar')) ?>">Reservar cita</a></div>
        <?php else: ?>
            <ul class="lista-agenda">
                <?php foreach ($proximas as $c): ?>
                    <li>
                        <span class="hora"><?= e(hora($c['hora_inicio'])) ?></span>
                        <span><span class="quien"><?= e(ucfirst(fecha_larga($c['fecha']))) ?></span>
                            <span class="detalle"><?= e($c['especialidad']) ?> · <?= e($c['medico']) ?> · <?= e($c['codigo']) ?></span></span>
                        <?= estado_cita($c['estado']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php elseif ($rol === 'medico'): ?>
    <div class="indicadores">
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['citas_hoy'] ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Citas de hoy</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['atendidas_hoy'] ?></div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Atendidas hoy</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['proxima_semana'] ?></div><div class="indicador-etiqueta"><?= icono('agenda') ?>Próximos 7 días</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['atendidas_mes'] ?></div><div class="indicador-etiqueta"><?= icono('pulso') ?>Atendidas este mes</div></div>
    </div>
    <?= \App\Nucleo\Vista::parcial('inicio/citas_hoy', ['hoy' => $hoy, 'mostrarMedico' => false]) ?>

<?php else: ?>
    <div class="indicadores">
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['citas_hoy'] ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Citas de hoy</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['por_confirmar'] ?></div><div class="indicador-etiqueta"><?= icono('campana') ?>Por confirmar (hoy y mañana)</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['pacientes'] ?></div><div class="indicador-etiqueta"><?= icono('pacientes') ?>Pacientes activos</div></div>
        <div class="indicador"><div class="indicador-valor"><?= (int) $indicadores['asistencia'] ?>%</div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Asistencia del mes</div></div>
    </div>

    <div class="rejilla-lateral">
        <?= \App\Nucleo\Vista::parcial('inicio/citas_hoy', ['hoy' => $hoy, 'mostrarMedico' => true]) ?>
        <div class="columna">
            <section class="tarjeta">
                <header class="tarjeta-cabecera"><h2>Citas por día</h2></header>
                <div class="tarjeta-cuerpo">
                    <?php $max = max(1, ...array_map(static fn ($s) => (int) $s['total'], $serie)); ?>
                    <div class="grafico-columnas" role="img" aria-label="Citas por día de los últimos 10 días y próximos 4">
                        <?php foreach ($serie as $s): $hoyCol = $s['fecha'] === date('Y-m-d'); ?>
                            <div class="columna-grafico">
                                <span class="cifra"><?= (int) $s['total'] ?></span>
                                <div class="col<?= $hoyCol ? ' hoy' : '' ?>" style="height: <?= max(3, round(120 * (int) $s['total'] / $max)) ?>px"></div>
                                <small><?= $hoyCol ? 'Hoy' : e(date('d', strtotime($s['fecha']))) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <section class="tarjeta">
                <header class="tarjeta-cabecera"><h2>Estados del mes</h2></header>
                <div class="tarjeta-cuerpo grafico-barras">
                    <?php $totalMes = max(1, array_sum(array_column($estadosMes, 'total'))); ?>
                    <?php foreach ($estadosMes as $e): ?>
                        <div class="barra-fila">
                            <span><?= e(ucfirst($e['estado'])) ?></span>
                            <span class="barra-pista"><span class="barra-relleno <?= e($e['estado']) ?>" style="width: <?= round(100 * $e['total'] / $totalMes) ?>%"></span></span>
                            <span class="barra-valor"><?= (int) $e['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
<?php endif; ?>
