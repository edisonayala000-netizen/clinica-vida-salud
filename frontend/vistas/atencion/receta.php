<?php
/** IU Receta para ver, imprimir y reenviar por correo (CU16 · CU17) */
use App\Nucleo\Autenticacion;

$r = $receta;
$p = $paciente;
$esPaciente = Autenticacion::rol() === 'paciente';
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url($esPaciente ? '/mis-recetas' : '/citas/' . $r['id_cita'])) ?>"><?= icono('flecha-izq') ?><?= $esPaciente ? 'Mis recetas' : 'Cita ' . e($r['codigo_cita']) ?></a>
        <h1>Receta <?= e($r['codigo_receta']) ?></h1>
        <p>Emitida el <?= e(fecha_hora($r['fecha_emision'])) ?><?= $r['enviada_en'] ? ' · enviada por correo el ' . e(fecha_hora($r['enviada_en'])) : '' ?></p>
    </div>
    <div class="acciones">
        <button class="boton boton-secundario" type="button" data-imprimir><?= icono('imprimir') ?>Imprimir</button>
        <?php if ($r['correo_paciente']): ?>
            <form method="post" action="<?= e(url('/recetas/' . $r['id_receta'] . '/enviar')) ?>">
                <?= csrf_campo() ?>
                <button class="boton boton-primario" type="submit"><?= icono('correo') ?><?= $r['enviada_en'] ? 'Reenviar por correo' : 'Enviar por correo' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="tarjeta">
    <div class="reporte-cabecera-impresion">
        <img src="<?= e(recurso('img/logo.svg')) ?>" width="40" height="40" alt="">
        <div><strong>Clínica Vida Salud — Receta <?= e($r['codigo_receta']) ?></strong><br>Emitida el <?= e(fecha_hora($r['fecha_emision'])) ?></div>
    </div>
    <div class="tarjeta-cuerpo">
        <dl class="datos">
            <dt>Paciente</dt><dd><?= e($r['paciente']) ?> <span class="celda-sub"><?= e($p['codigo']) ?><?= $p['ci'] ? ' · CI ' . e($p['ci']) : '' ?> · <?= e(edad($p['fecha_nacimiento'])) ?></span></dd>
            <dt>Médico</dt><dd><?= e($r['medico']) ?></dd>
            <dt>Diagnóstico</dt><dd><?= e($r['diagnostico']) ?></dd>
        </dl>
        <h2 style="margin:22px 0 12px">Medicamentos</h2>
        <ol class="receta-medicamentos">
            <?php foreach ($detalles as $d): ?>
                <li><strong><?= e(trim($d['medicamento'] . ' ' . ($d['concentracion'] ?? ''))) ?></strong> · <?= e($d['presentacion']) ?>
                    <span class="celda-sub"><?= e($d['dosis']) ?> por vía <?= e($d['via']) ?>, cada <?= (int) $d['frecuencia_horas'] ?> horas
                        durante <?= (int) $d['duracion_dias'] ?> día(s) (<?= (int) $d['total_tomas'] ?> tomas), desde el
                        <?= e(fecha_corta($d['fecha_inicio'])) ?> a las <?= e(hora($d['hora_primera_toma'])) ?>.</span>
                    <?php if ($d['indicaciones']): ?><span class="celda-sub">Indicación: <?= e($d['indicaciones']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($r['indicaciones_generales']): ?>
            <p style="margin-top:16px"><strong>Indicaciones generales:</strong> <?= e($r['indicaciones_generales']) ?></p>
        <?php endif; ?>
        <p class="receta-firma"><?= e($r['medico']) ?></p>
    </div>
</section>
