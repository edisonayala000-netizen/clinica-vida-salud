<?php
/**
 * Correo con la receta de la consulta (CU16).
 * Variables: $formato ('html' | 'texto'), $asunto, $nombre, $receta (datos generales: primera fila de
 *            v_receta_detalle), $detalles (todas las filas: un medicamento por fila), $enlaceVer.
 */
$r = $receta;
$linea = static fn (array $d): string => trim($d['medicamento'] . ' ' . ($d['concentracion'] ?? '')) . " ({$d['presentacion']})";
$posologia = static fn (array $d): string => "{$d['dosis']} por vía {$d['via']}, cada {$d['frecuencia_horas']} h durante "
    . "{$d['duracion_dias']} día(s), desde el " . date('d/m/Y', strtotime($d['fecha_inicio'])) . ' a las ' . hora($d['hora_primera_toma']);
$conAvisos = array_filter($detalles, static fn ($d) => $d['recordar']);

if ($formato === 'texto'): ?>
Hola, <?= $nombre ?>:

Le enviamos la receta de su consulta del <?= date('d/m/Y', strtotime($r['fecha_emision'])) ?> con <?= $r['medico'] ?>.

Receta <?= $r['codigo_receta'] ?> · Cita <?= $r['codigo_cita'] ?>

<?php foreach ($detalles as $i => $d): ?>
<?= $i + 1 ?>. <?= $linea($d) ?>

   <?= $posologia($d) ?>.
<?php if ($d['indicaciones']): ?>
   Indicación: <?= $d['indicaciones'] ?>

<?php endif; ?>
<?php endforeach; ?>
<?php if ($r['indicaciones_generales']): ?>

Indicaciones generales: <?= $r['indicaciones_generales'] ?>

<?php endif; ?>
<?php if ($conAvisos): ?>

Le enviaremos un correo a la hora de cada toma. Puede desactivarlos en «Mis recetas» o desde el mismo correo.
<?php endif; ?>

Vea sus recetas en: <?= $enlaceVer ?>


Clínica Vida Salud
<?php return; endif;

ob_start(); ?>
<p style="margin:0 0 16px;">Hola, <strong><?= e($nombre) ?></strong>:</p>
<p style="margin:0 0 16px;">Le enviamos la receta de su consulta del <?= e(date('d/m/Y', strtotime($r['fecha_emision']))) ?>
    con <?= e($r['medico']) ?>.</p>
<p style="margin:0 0 10px;font-size:13px;color:#4D6662;">Receta <strong style="color:#12302C;"><?= e($r['codigo_receta']) ?></strong>
    · Cita <?= e($r['codigo_cita']) ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D8E5E2;border-radius:8px;margin:0 0 16px;">
    <?php foreach ($detalles as $i => $d): ?>
    <tr><td style="padding:12px 14px;border-bottom:1px solid #EDF3F1;">
        <div style="font-weight:600;color:#0E4D47;"><?= $i + 1 ?>. <?= e($linea($d)) ?></div>
        <div style="font-size:14px;margin-top:4px;"><?= e($posologia($d)) ?>.</div>
        <?php if ($d['indicaciones']): ?><div style="font-size:13px;margin-top:4px;color:#4D6662;">Indicación: <?= e($d['indicaciones']) ?></div><?php endif; ?>
    </td></tr>
    <?php endforeach; ?>
</table>
<?php if ($r['indicaciones_generales']): ?>
<p style="margin:0 0 16px;"><strong>Indicaciones generales:</strong> <?= e($r['indicaciones_generales']) ?></p>
<?php endif; ?>
<?php if ($conAvisos): ?>
<p style="margin:0 0 16px;padding:12px 14px;background:#E4F2EE;border-radius:8px;font-size:13px;">
    Le enviaremos un correo a la hora de cada toma. Puede desactivarlos en «Mis recetas» o desde el mismo correo.</p>
<?php endif; ?>
<p style="margin:0;text-align:center;">
    <a href="<?= e($enlaceVer) ?>" style="display:inline-block;background:#12806F;color:#FFFFFF;text-decoration:none;font-weight:600;padding:11px 22px;border-radius:8px;">Ver mis recetas</a>
</p>
<?php
echo \App\Nucleo\Vista::parcial('correos/base', ['asunto' => $asunto, 'contenido' => ob_get_clean()]);
