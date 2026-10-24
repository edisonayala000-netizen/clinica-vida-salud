<?php
/** IU Notificaciones (CU18): correos enviados y programados, con envío manual y reintentos. */
$f = $filtros;
$claseEstado = ['pendiente' => 'estado-programada', 'enviada' => 'estado-atendida', 'error' => 'estado-cancelada', 'anulada' => 'estado-inactivo'];
$params = ['tipo' => $f['tipo'], 'estado' => $f['estado'], 'correo' => $f['correo']];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Notificaciones</h1>
        <p>Correos del sistema: confirmaciones de citas, recetas y los recordatorios que envía la tarea programada cada 5 minutos.</p>
    </div>
    <div class="acciones">
        <form method="post" action="<?= e(url('/notificaciones/enviar')) ?>">
            <?= csrf_campo() ?>
            <button class="boton boton-primario" type="submit"><?= icono('correo') ?>Enviar pendientes ahora</button>
        </form>
    </div>
</div>

<div class="indicadores">
    <div class="indicador"><div class="indicador-valor"><?= (int) $resumen['listas'] ?></div><div class="indicador-etiqueta"><?= icono('reloj') ?>Listas para enviar</div></div>
    <div class="indicador"><div class="indicador-valor"><?= (int) $resumen['programadas'] ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Programadas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= (int) $resumen['enviadas_hoy'] ?></div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Enviadas hoy</div></div>
    <div class="indicador"><div class="indicador-valor"><?= (int) $resumen['con_error'] ?></div><div class="indicador-etiqueta"><?= icono('alerta') ?>Con error</div></div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/notificaciones')) ?>">
        <div class="campo"><label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" data-autoenviar><option value="">Todos</option>
                <?php foreach ($tipos as $clave => $nombre): ?><option value="<?= e($clave) ?>"<?= seleccionado($f['tipo'], $clave) ?>><?= e($nombre) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar><option value="">Todos</option>
                <?php foreach (['pendiente', 'enviada', 'error', 'anulada'] as $s): ?><option value="<?= $s ?>"<?= seleccionado($f['estado'], $s) ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select></div>
        <div class="campo"><label for="correo">Correo</label>
            <input id="correo" name="correo" type="search" placeholder="Destinatario" value="<?= e($f['correo']) ?>"></div>
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Filtrar</button>
    </form>

    <?php if (!$notificaciones): ?>
        <div class="vacio"><?= icono('correo') ?><h3>Sin correos</h3><p>No hay notificaciones que cumplan esos filtros.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Programada para</th><th>Tipo</th><th>Destinatario</th><th>Asunto</th><th>Estado</th><th class="col-acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($notificaciones as $n): ?>
                <tr>
                    <td class="numero"><?= e(date('d/m/Y', strtotime($n['programada_para']))) ?><span class="celda-sub"><?= e(date('H:i', strtotime($n['programada_para']))) ?></span></td>
                    <td><?= e($tipos[$n['tipo']] ?? $n['tipo']) ?></td>
                    <td><span class="celda-principal"><?= e($n['destinatario']) ?></span><span class="celda-sub"><?= e($n['correo_destino']) ?></span></td>
                    <td class="celda-detalle"><?= e($n['asunto']) ?>
                        <?php if ($n['error']): ?><span class="celda-sub"><?= e($n['error']) ?></span><?php endif; ?></td>
                    <td><span class="estado <?= $claseEstado[$n['estado']] ?>"><?= e(ucfirst($n['estado'])) ?></span>
                        <?php if ($n['enviada_en']): ?><span class="celda-sub"><?= e(fecha_hora($n['enviada_en'])) ?></span><?php endif; ?></td>
                    <td class="col-acciones">
                        <?php if ($n['estado'] === 'error' && $n['tipo'] !== 'recuperar_clave'): ?>
                            <form method="post" action="<?= e(url('/notificaciones/' . $n['id_notificacion'] . '/reintentar')) ?>">
                                <?= csrf_campo() ?><button class="boton boton-secundario boton-chico" type="submit"><?= icono('repetir') ?>Reintentar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= \App\Nucleo\Vista::parcial('compartido/paginacion', [
        'pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/notificaciones',
        'params' => $params, 'nombre' => 'correo(s)',
    ]) ?>
</section>
