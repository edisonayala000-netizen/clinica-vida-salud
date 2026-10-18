<?php
/** IU Agenda semanal (CU13): citas del médico de lunes a domingo, con días no laborables. */
$idM = $medico['id_medico'] ?? null;
$ruta = static fn (string $f) => url('/agenda/semana', ['medico' => $soloPropia ? null : $idM, 'fecha' => $f]);
$diaria = static fn (string $f) => url('/agenda', ['medico' => $soloPropia ? null : $idM, 'fecha' => $f]);
$anterior = date('Y-m-d', strtotime($lunes . ' -7 days'));
$siguiente = date('Y-m-d', strtotime($lunes . ' +7 days'));
$hoy = date('Y-m-d');
$totalActivas = 0;
foreach ($dias as $d) {
    $totalActivas += count(array_filter($d['citas'], static fn ($c) => $c['estado'] !== 'cancelada'));
}
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1><?= $soloPropia ? 'Mi agenda semanal' : 'Agenda semanal' ?></h1>
        <p><?= $medico ? e('Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos'] . ' · ' . $medico['especialidades']) : 'Seleccione un médico.' ?></p>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e($ruta($anterior)) ?>" aria-label="Semana anterior"><?= icono('chevron-izq') ?></a>
        <a class="boton boton-secundario" href="<?= e($ruta($hoy)) ?>">Esta semana</a>
        <a class="boton boton-secundario" href="<?= e($ruta($siguiente)) ?>" aria-label="Semana siguiente"><?= icono('chevron-der') ?></a>
        <a class="boton boton-secundario" href="<?= e($diaria($lunes <= $hoy && $hoy <= $domingo ? $hoy : $lunes)) ?>"><?= icono('agenda') ?>Vista diaria</a>
        <button class="boton boton-secundario" type="button" data-imprimir><?= icono('imprimir') ?>Imprimir</button>
    </div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/agenda/semana')) ?>">
        <?php if (!$soloPropia): ?>
        <div class="campo campo-buscar"><label for="medico">Médico</label>
            <select id="medico" name="medico" data-autoenviar>
                <?php foreach ($medicos as $m): ?><option value="<?= (int) $m['id_medico'] ?>"<?= seleccionado($idM, $m['id_medico']) ?>><?= e($m['nombre']) ?><?= $m['estado'] !== 'activo' ? ' (inactivo)' : '' ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="campo"><label for="fecha">Semana del día</label><input id="fecha" name="fecha" type="date" value="<?= e($lunes) ?>" data-autoenviar></div>
        <p class="texto-suave" style="align-self:center;margin-left:auto"><strong>Del <?= e(fecha_corta($lunes)) ?> al <?= e(fecha_corta($domingo)) ?></strong> · <?= $totalActivas ?> cita(s)</p>
    </form>

    <div class="reporte-cabecera-impresion"><img src="<?= e(recurso('img/logo.svg')) ?>" width="36" height="36" alt="">
        <div><strong>Clínica Vida Salud · Agenda semanal</strong><br><?= $medico ? e('Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos']) : '' ?> · del <?= e(fecha_corta($lunes)) ?> al <?= e(fecha_corta($domingo)) ?></div></div>

    <?php if (!$medico): ?>
        <div class="vacio"><?= icono('agenda') ?><h3>Seleccione un médico</h3></div>
    <?php else: ?>
    <div class="tarjeta-cuerpo">
        <div class="semana semana-agenda">
        <?php foreach ($dias as $d):
            $clases = 'semana-dia' . ($d['fecha'] === $hoy ? ' hoy' : '') . ($d['no_laborable'] ? ' no-laborable' : (!$d['atiende'] ? ' sin-atencion' : '')); ?>
            <div class="<?= $clases ?>">
                <h3><a href="<?= e($diaria($d['fecha'])) ?>"><?= e(ucfirst(fecha_larga($d['fecha'], false))) ?></a></h3>
                <?php if ($d['no_laborable']): ?>
                    <p class="aviso-dia"><?= icono('bloqueo') ?>No laborable: <?= e($d['no_laborable']) ?></p>
                <?php elseif (!$d['atiende']): ?>
                    <p class="aviso-dia texto-tenue">No atiende este día</p>
                <?php endif; ?>
                <?php if (!$d['citas'] && !$d['no_laborable'] && $d['atiende']): ?>
                    <p class="texto-tenue aviso-dia">Sin citas</p>
                <?php endif; ?>
                <?php foreach ($d['citas'] as $c): ?>
                    <a class="cita-semana<?= $c['estado'] === 'cancelada' ? ' cancelada' : '' ?>" href="<?= e(url('/citas/' . $c['id_cita'])) ?>">
                        <strong><?= e(hora($c['hora_inicio'])) ?></strong>
                        <span class="cita-semana-paciente"><?= e($c['paciente']) ?></span>
                        <?= estado_cita($c['estado']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
