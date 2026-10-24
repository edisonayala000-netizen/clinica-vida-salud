<?php
/** IU Mis recetas (CU17): recetas del paciente, horario de cada medicamento y sus recordatorios. */
$avisosActivos = (bool) $paciente['recordar_medicamentos'];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Mis recetas</h1>
        <p>Los medicamentos que le recetaron, con su horario. Le enviamos un correo a la hora de cada toma, salvo los que desactive.</p>
    </div>
</div>

<?php if (!$avisosActivos): ?>
    <div class="alerta alerta-info" role="note"><?= icono('info') ?>
        <p>Los recordatorios de medicamentos están desactivados. Puede activarlos en
           <a href="<?= e(url('/mi-perfil', ['seccion' => 'cuenta'])) ?>">Mi perfil → Cuenta</a>.</p></div>
<?php elseif (!$paciente['correo']): ?>
    <div class="alerta alerta-aviso" role="note"><?= icono('alerta') ?>
        <p>No tiene un correo registrado: registre uno en <a href="<?= e(url('/mi-perfil')) ?>">Mi perfil</a> para recibir los recordatorios.</p></div>
<?php endif; ?>

<?php if (!$recetas): ?>
    <section class="tarjeta"><div class="vacio"><?= icono('reporte') ?><h3>Aún no tiene recetas</h3>
        <p>Cuando un médico le recete medicamentos en una consulta, aparecerán aquí.</p></div></section>
<?php endif; ?>

<?php foreach ($recetas as $grupo): $r = $grupo['receta']; ?>
<section class="tarjeta">
    <header class="tarjeta-cabecera">
        <h2>Receta <?= e($r['codigo_receta']) ?></h2>
        <span class="texto-suave"><?= e(fecha_corta(substr($r['fecha_emision'], 0, 10))) ?> · <?= e($r['medico']) ?></span>
        <div class="acciones"><a class="boton boton-secundario boton-chico" href="<?= e(url('/recetas/' . $r['id_receta'])) ?>"><?= icono('imprimir') ?>Ver e imprimir</a></div>
    </header>
    <div class="tarjeta-cuerpo">
        <p><strong>Diagnóstico:</strong> <?= e($r['diagnostico']) ?></p>
        <div class="tabla-contenedor">
            <table class="tabla">
                <thead><tr><th>Medicamento</th><th>Cómo tomarlo</th><th>Próximo aviso</th><th class="col-acciones">Recordatorio</th></tr></thead>
                <tbody>
                <?php foreach ($grupo['detalles'] as $d): ?>
                    <tr class="<?= $d['vigente'] ? '' : 'inactivo' ?>">
                        <td><span class="celda-principal"><?= e(trim($d['medicamento'] . ' ' . ($d['concentracion'] ?? ''))) ?></span>
                            <span class="celda-sub"><?= e($d['presentacion']) ?><?= $d['vigente'] ? '' : ' · tratamiento terminado' ?></span></td>
                        <td><?= e($d['dosis']) ?> cada <?= (int) $d['frecuencia_horas'] ?> h durante <?= (int) $d['duracion_dias'] ?> día(s)
                            <span class="celda-sub">Desde el <?= e(fecha_corta($d['fecha_inicio'])) ?> a las <?= e(hora($d['hora_primera_toma'])) ?><?= $d['indicaciones'] ? ' · ' . e($d['indicaciones']) : '' ?></span></td>
                        <td><?= $d['proximo_aviso'] ? e(fecha_hora($d['proximo_aviso'])) . '<span class="celda-sub">' . (int) $d['avisos_pendientes'] . ' aviso(s) pendiente(s)</span>' : '<span class="texto-tenue">—</span>' ?></td>
                        <td class="col-acciones">
                            <?php if ($d['vigente']): ?>
                            <form method="post" action="<?= e(url('/mis-recetas/' . $d['id_detalle'] . '/recordar')) ?>" class="interruptor-recordar">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="recordar" value="<?= $d['recordar'] ? '0' : '1' ?>">
                                <span class="etiqueta<?= $d['recordar'] ? '' : ' etiqueta-gris' ?>"><?= $d['recordar'] ? 'Activado' : 'Desactivado' ?></span>
                                <button class="boton boton-secundario boton-chico" type="submit"><?= $d['recordar'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <?php else: ?><span class="texto-tenue">—</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($r['indicaciones_generales']): ?><p style="margin-top:12px"><strong>Indicaciones:</strong> <?= e($r['indicaciones_generales']) ?></p><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
