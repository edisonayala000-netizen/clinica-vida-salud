<?php
/**
 * Correos de una cita (CU18): registrada, reprogramada, cancelada y recordatorios de 24 h y de 1 h.
 * Variables: $formato ('html' | 'texto'), $asunto, $nombre, $tipo, $cita (fila de v_cita_detalle),
 *            $enlaceCita (solo recordatorio de 24 h: confirmar o cancelar), $enlaceBaja (recordatorios).
 */
$c = $cita;
$cuando = fecha_larga($c['fecha']) . ' a las ' . hora($c['hora_inicio']);
$modalidad = $c['modalidad'] === 'virtual' ? 'Virtual (le enviaremos el enlace)' : 'Presencial en la clínica';
$intro = match ($tipo) {
    'cita_registrada'      => 'Su cita quedó registrada.',
    'cita_reprogramada'    => 'Su cita fue reprogramada. Estos son los nuevos datos:',
    'cita_cancelada'       => 'Su cita fue cancelada.',
    'recordatorio_cita'    => 'Le recordamos su cita de mañana.',
    'recordatorio_cita_1h' => 'Su cita es dentro de 1 hora.',
};
$datos = [
    'Código'       => $c['codigo'],
    'Fecha y hora' => $cuando,
    'Médico'       => $c['medico'],
    'Especialidad' => $c['especialidad'],
    'Modalidad'    => $modalidad,
] + ($tipo === 'cita_cancelada' && $c['motivo_cancelacion'] ? ['Motivo' => $c['motivo_cancelacion']] : []);

if ($formato === 'texto'): ?>
Hola, <?= $nombre ?>:

<?= $intro ?>

<?php foreach ($datos as $etiqueta => $valor): ?>
<?= $etiqueta ?>: <?= $valor ?>

<?php endforeach; ?>
<?php if ($enlaceCita): ?>
Para confirmar su asistencia o cancelar la cita abra: <?= $enlaceCita ?>

<?php endif; ?>
<?php if ($tipo !== 'cita_cancelada'): ?>
Por favor llegue 10 minutos antes con su carnet de identidad.
<?php else: ?>
Si desea una nueva cita, resérvela en el sistema o llame a recepción.
<?php endif; ?>
<?php if ($enlaceBaja): ?>

No quiero recibir más recordatorios de citas: <?= $enlaceBaja ?>
<?php endif; ?>

Clínica Vida Salud
<?php return; endif;

ob_start(); ?>
<p style="margin:0 0 16px;">Hola, <strong><?= e($nombre) ?></strong>:</p>
<p style="margin:0 0 16px;"><?= e($intro) ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D8E5E2;border-radius:8px;margin:0 0 20px;">
    <?php foreach ($datos as $etiqueta => $valor): ?>
    <tr>
        <td style="padding:8px 12px;font-size:13px;color:#4D6662;width:120px;border-bottom:1px solid #EDF3F1;"><?= e($etiqueta) ?></td>
        <td style="padding:8px 12px;font-size:14px;border-bottom:1px solid #EDF3F1;<?= $etiqueta === 'Fecha y hora' ? 'font-weight:600;' : '' ?>"><?= e($valor) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php if ($enlaceCita): ?>
<p style="margin:0 0 20px;text-align:center;">
    <a href="<?= e($enlaceCita) ?>" style="display:inline-block;background:#12806F;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:8px;margin:4px;">Confirmar asistencia</a>
    <a href="<?= e($enlaceCita) ?>" style="display:inline-block;background:#FFFFFF;color:#B3392B;text-decoration:none;font-weight:600;padding:11px 22px;border-radius:8px;border:1px solid #B3392B;margin:4px;">Cancelar cita</a>
</p>
<?php endif; ?>
<p style="margin:0;font-size:13px;color:#4D6662;">
    <?= $tipo === 'cita_cancelada'
        ? 'Si desea una nueva cita, resérvela en el sistema o llame a recepción.'
        : 'Por favor llegue 10 minutos antes con su carnet de identidad.' ?></p>
<?php
$pie = $enlaceBaja
    ? '<a href="' . e($enlaceBaja) . '" style="color:#4D6662;">No quiero recibir más recordatorios de citas</a>'
    : '';
echo \App\Nucleo\Vista::parcial('correos/base', ['asunto' => $asunto, 'contenido' => ob_get_clean(), 'pie' => $pie]);
