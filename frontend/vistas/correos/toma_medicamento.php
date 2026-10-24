<?php
/**
 * Correo «Hora de su medicamento» (CU18): uno por cada toma programada.
 * Variables: $formato ('html' | 'texto'), $asunto, $nombre, $toma (fila de v_receta_detalle),
 *            $momento (hora programada de la toma), $enlaceBaja (solo este medicamento),
 *            $enlaceBajaTodos (todos los medicamentos).
 */
$t = $toma;
$medicamento = trim($t['medicamento'] . ' ' . ($t['concentracion'] ?? '')) . ' (' . $t['presentacion'] . ')';
$cuando = fecha_larga(substr($momento, 0, 10)) . ' a las ' . date('H:i', strtotime($momento));
$fin = date('d/m/Y', strtotime($t['fecha_inicio'] . ' +' . ((int) $t['duracion_dias'] - 1) . ' days'));
$posologia = "{$t['dosis']} por vía {$t['via']}, cada {$t['frecuencia_horas']} horas, hasta el {$fin}";

if ($formato === 'texto'): ?>
Hola, <?= $nombre ?>:

Es hora de tomar su medicamento (<?= $cuando ?>):

<?= $medicamento ?>

<?= $posologia ?>.
<?php if ($t['indicaciones']): ?>
Indicación: <?= $t['indicaciones'] ?>

<?php endif; ?>
Receta <?= $t['codigo_receta'] ?> · <?= $t['medico'] ?>


Dejar de recordarme este medicamento: <?= $enlaceBaja ?>

No quiero recibir recordatorios de medicamentos: <?= $enlaceBajaTodos ?>


Clínica Vida Salud
<?php return; endif;

ob_start(); ?>
<p style="margin:0 0 16px;">Hola, <strong><?= e($nombre) ?></strong>:</p>
<p style="margin:0 0 12px;">Es hora de tomar su medicamento <span style="color:#4D6662;">(<?= e($cuando) ?>)</span>:</p>
<div style="background:#E4F2EE;border-radius:10px;padding:16px 18px;margin:0 0 16px;">
    <div style="font-size:18px;font-weight:600;color:#0E4D47;"><?= e($medicamento) ?></div>
    <div style="margin-top:6px;font-size:14px;"><?= e($posologia) ?>.</div>
    <?php if ($t['indicaciones']): ?><div style="margin-top:6px;font-size:14px;"><strong>Indicación:</strong> <?= e($t['indicaciones']) ?></div><?php endif; ?>
</div>
<p style="margin:0;font-size:13px;color:#4D6662;">Receta <?= e($t['codigo_receta']) ?> · <?= e($t['medico']) ?>.
    Si tiene dudas o el medicamento le cae mal, consulte a su médico.</p>
<?php
$pie = '<a href="' . e($enlaceBaja) . '" style="color:#4D6662;">Dejar de recordarme este medicamento</a> · '
     . '<a href="' . e($enlaceBajaTodos) . '" style="color:#4D6662;">No quiero recibir recordatorios de medicamentos</a>';
echo \App\Nucleo\Vista::parcial('correos/base', ['asunto' => $asunto, 'contenido' => ob_get_clean(), 'pie' => $pie]);
