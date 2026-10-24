<?php
/**
 * Correo de CU15 · Recuperar contraseña.
 * Variables: $formato ('html' | 'texto'), $asunto, $nombre, $usuario, $enlace, $minutos.
 */
if ($formato === 'texto'): ?>
Hola, <?= $nombre ?>:

Recibimos una solicitud para cambiar la contraseña de su cuenta «<?= $usuario ?>» en la Clínica Vida Salud.

Para crear una nueva contraseña abra este enlace (vence en <?= $minutos ?> minutos y sirve una sola vez):
<?= $enlace ?>


Si usted no lo pidió, ignore este correo: su contraseña actual sigue igual.

Clínica Vida Salud
<?php return; endif;

ob_start(); ?>
<p style="margin:0 0 16px;">Hola, <strong><?= e($nombre) ?></strong>:</p>
<p style="margin:0 0 16px;">Recibimos una solicitud para cambiar la contraseña de su cuenta
    <strong><?= e($usuario) ?></strong> en la Clínica Vida Salud.</p>
<p style="margin:24px 0;text-align:center;">
    <a href="<?= e($enlace) ?>" style="display:inline-block;background:#12806F;color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:8px;">Crear nueva contraseña</a>
</p>
<p style="margin:0 0 16px;font-size:13px;color:#4D6662;">El enlace vence en <?= (int) $minutos ?> minutos y sirve una sola vez.
    Si el botón no funciona, copie esta dirección en su navegador:<br>
    <a href="<?= e($enlace) ?>" style="color:#12806F;word-break:break-all;"><?= e($enlace) ?></a></p>
<p style="margin:0;padding:12px 14px;background:#FFF8E6;border-radius:8px;font-size:13px;">
    Si usted no pidió este cambio, ignore este correo: su contraseña actual sigue igual.</p>
<?php
echo \App\Nucleo\Vista::parcial('correos/base', ['asunto' => $asunto, 'contenido' => ob_get_clean()]);
