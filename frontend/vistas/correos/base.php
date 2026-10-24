<?php
/**
 * Diseño común de los correos del sistema (HTML).
 * Los programas de correo ignoran las hojas de estilo: todo va con estilos en línea
 * y tablas. Colores de la clínica: pino #0E4D47, verde #12806F, menta #E4F2EE.
 * Variables: $asunto, $contenido (HTML ya escapado) y $pie (opcional, HTML: enlaces para desactivar avisos).
 */
$pie = $pie ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($asunto) ?></title>
</head>
<body style="margin:0;padding:0;background:#F3F7F6;font-family:Segoe UI,Arial,Helvetica,sans-serif;color:#12302C;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F7F6;padding:24px 12px;">
<tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #D8E5E2;border-radius:12px;overflow:hidden;">
        <tr><td style="background:#0E4D47;padding:20px 28px;">
            <span style="font-size:20px;font-weight:600;color:#FFFFFF;">Clínica Vida Salud</span><br>
            <span style="font-size:13px;color:#A9D5CC;">Sistema de citas médicas</span>
        </td></tr>
        <tr><td style="padding:28px;font-size:15px;line-height:1.55;">
            <?= $contenido ?>
        </td></tr>
        <tr><td style="background:#E4F2EE;padding:16px 28px;font-size:12px;line-height:1.5;color:#4D6662;">
            <?php if ($pie !== ''): ?><?= $pie ?><br><br><?php endif; ?>
            Este es un correo automático del sistema de la Clínica Vida Salud; por favor no lo responda.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
