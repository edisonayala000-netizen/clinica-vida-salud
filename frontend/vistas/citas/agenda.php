<?php
/** IU Agenda médica (CU13): citas de un médico en una fecha + horarios libres. */
use App\Paquetes\Citas\Servicios\EstadoCita;

// Se mezclan citas y huecos libres en orden de hora.
$filas = [];
foreach ($citas as $c) {
    $filas[] = ['hora' => hora($c['hora_inicio']), 'tipo' => 'cita', 'c' => $c];
}
foreach ($libres as $l) {
    $filas[] = ['hora' => $l['inicio'], 'tipo' => 'libre', 'l' => $l];
}
usort($filas, static fn ($a, $b) => strcmp($a['hora'], $b['hora']));
$anteriorDia = date('Y-m-d', strtotime($fecha . ' -1 day'));
$siguienteDia = date('Y-m-d', strtotime($fecha . ' +1 day'));
$idM = $medico['id_medico'] ?? null;
$ruta = static fn (string $f) => url('/agenda', ['medico' => $soloPropia ? null : $idM, 'fecha' => $f, 'estado' => $estado]);
$activas = array_filter($citas, static fn ($c) => $c['estado'] !== 'cancelada');
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1><?= $soloPropia ? 'Mi agenda' : 'Agenda médica' ?></h1>
        <p><?= $medico ? e('Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos'] . ' · ' . $medico['especialidades']) : 'Seleccione un médico.' ?></p>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e($ruta($anteriorDia)) ?>" aria-label="Día anterior"><?= icono('chevron-izq') ?></a>
        <a class="boton boton-secundario" href="<?= e($ruta(date('Y-m-d'))) ?>">Hoy</a>
        <a class="boton boton-secundario" href="<?= e($ruta($siguienteDia)) ?>" aria-label="Día siguiente"><?= icono('chevron-der') ?></a>
        <a class="boton boton-secundario" href="<?= e(url('/agenda/semana', ['medico' => $soloPropia ? null : $idM, 'fecha' => $fecha])) ?>"><?= icono('tabla') ?>Vista semanal</a>
        <button class="boton boton-secundario" type="button" data-imprimir><?= icono('imprimir') ?>Imprimir</button>
    </div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/agenda')) ?>">
        <?php if (!$soloPropia): ?>
        <div class="campo campo-buscar"><label for="medico">Médico</label>
            <select id="medico" name="medico" data-autoenviar>
                <?php foreach ($medicos as $m): ?><option value="<?= (int) $m['id_medico'] ?>"<?= seleccionado($idM, $m['id_medico']) ?>><?= e($m['nombre']) ?><?= $m['estado'] !== 'activo' ? ' (inactivo)' : '' ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="campo"><label for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" value="<?= e($fecha) ?>" data-autoenviar></div>
        <div class="campo"><label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar><option value="">Todos</option>
                <?php foreach (['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'] as $s): ?><option value="<?= $s ?>"<?= seleccionado($estado, $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select></div>
        <p class="texto-suave" style="align-self:center;margin-left:auto"><strong><?= e(ucfirst(fecha_larga($fecha))) ?></strong> · <?= count($activas) ?> cita(s)<?= $libres ? ' · ' . count($libres) . ' horario(s) libre(s)' : '' ?></p>
    </form>

    <div class="reporte-cabecera-impresion"><img src="<?= e(recurso('img/logo.svg')) ?>" width="36" height="36" alt="">
        <div><strong>Clínica Vida Salud · Agenda médica</strong><br><?= $medico ? e('Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos']) : '' ?> · <?= e(ucfirst(fecha_larga($fecha))) ?></div></div>

    <?php if (!$filas): ?>
        <div class="vacio"><?= icono('agenda') ?><h3>Sin citas para esta fecha</h3><p>Pruebe con otro día u otro estado.</p></div>
    <?php else: ?>
    <div class="agenda-dia">
        <?php foreach ($filas as $f): ?>
            <?php if ($f['tipo'] === 'libre'): $l = $f['l']; ?>
                <div class="agenda-fila libre">
                    <span class="agenda-hora"><?= e($l['inicio']) ?><small><?= e($l['fin']) ?></small></span>
                    <span class="texto-tenue">Horario libre</span>
                    <?php if ($puedeProgramar): ?><a class="boton boton-texto boton-chico no-imprimir" href="<?= e(url('/citas/nueva')) ?>">Programar</a><?php else: ?><span></span><?php endif; ?>
                </div>
            <?php else: $c = $f['c']; $acc = EstadoCita::acciones($c); ?>
                <div class="agenda-fila">
                    <span class="agenda-hora"><?= e(hora($c['hora_inicio'])) ?><small><?= e(hora($c['hora_fin'])) ?></small></span>
                    <span>
                        <a class="celda-principal" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= e($c['paciente']) ?></a>
                        <span class="celda-sub"><span class="codigo"><?= e($c['codigo']) ?></span> · <?= e($c['especialidad']) ?> · <?= e(edad($c['fecha_nacimiento'])) ?> · <?= $c['modalidad'] === 'virtual' ? 'Virtual' : 'Presencial' ?></span>
                        <?php if ($verMotivo && $c['motivo']): ?><span class="celda-sub">Motivo: <?= e($c['motivo']) ?></span><?php endif; ?>
                    </span>
                    <span class="acciones">
                        <?php if (\App\Nucleo\Autenticacion::puede('atencion.registrar') && !$c['tiene_atencion']
                                  && (in_array('atendida', $acc, true) || $c['estado'] === 'atendida')): ?>
                            <a class="boton boton-primario boton-chico" href="<?= e(url('/citas/' . $c['id_cita'] . '/atencion')) ?>"><?= icono('pulso') ?>Atender</a>
                        <?php endif; ?>
                        <?php if (in_array('atendida', $acc, true) && $puedeAtender): ?>
                            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>" class="no-imprimir">
                                <?= csrf_campo() ?><input type="hidden" name="estado" value="atendida">
                                <input type="hidden" name="volver" value="<?= e('/agenda?' . http_build_query(array_filter(['medico' => $soloPropia ? null : $idM, 'fecha' => $fecha]))) ?>">
                                <button class="boton boton-secundario boton-chico" type="submit"><?= icono('check') ?>Atendida</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('inasistencia', $acc, true) && $puedeConfirmar): ?>
                            <form method="post" action="<?= e(url('/citas/' . $c['id_cita'] . '/estado')) ?>" class="no-imprimir">
                                <?= csrf_campo() ?><input type="hidden" name="estado" value="inasistencia">
                                <input type="hidden" name="volver" value="<?= e('/agenda?' . http_build_query(array_filter(['medico' => $soloPropia ? null : $idM, 'fecha' => $fecha]))) ?>">
                                <button class="boton boton-secundario boton-chico" type="submit">No vino</button>
                            </form>
                        <?php endif; ?>
                        <?= estado_cita($c['estado']) ?>
                    </span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
