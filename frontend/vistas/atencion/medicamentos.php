<?php /** IU Gestionar el catálogo de medicamentos (CU19) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Medicamentos</h1>
        <p>Catálogo con el que recetan los médicos. Un medicamento desactivado ya no aparece al recetar, pero las recetas anteriores lo conservan.</p>
    </div>
    <div class="acciones">
        <button class="boton boton-primario" type="button" data-abrir-modal="modal-medicamento"
                data-accion="<?= e(url('/medicamentos')) ?>" data-campo-nombre="" data-campo-presentacion="tableta" data-campo-concentracion=""
                data-campo-titulo-modal="Nuevo medicamento"><?= icono('mas') ?>Nuevo medicamento</button>
    </div>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p><?= e(implode(' ', $errores)) ?></p></div>
<?php endif; ?>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/medicamentos')) ?>">
        <div class="campo"><label for="q">Buscar</label>
            <input id="q" name="q" type="search" placeholder="Nombre genérico" value="<?= e($filtros['q']) ?>"></div>
        <div class="campo"><label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar>
                <option value="">Todos</option>
                <option value="activo"<?= seleccionado($filtros['estado'], 'activo') ?>>Activos</option>
                <option value="inactivo"<?= seleccionado($filtros['estado'], 'inactivo') ?>>Inactivos</option>
            </select></div>
        <button class="boton boton-secundario" type="submit"><?= icono('buscar') ?>Buscar</button>
    </form>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Medicamento</th><th>Presentación</th><th>Concentración</th><th>Veces recetado</th><th>Estado</th><th class="col-acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($medicamentos as $m): ?>
                <tr class="<?= $m['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td><span class="celda-principal"><?= e($m['nombre']) ?></span></td>
                    <td><?= e(ucfirst($m['presentacion'])) ?></td>
                    <td><?= e($m['concentracion'] ?: '—') ?></td>
                    <td class="numero"><?= (int) $m['recetado'] ?></td>
                    <td><?= estado_registro($m['estado']) ?></td>
                    <td class="col-acciones"><div class="acciones">
                        <button class="boton boton-secundario boton-chico" type="button" data-abrir-modal="modal-medicamento"
                                data-accion="<?= e(url('/medicamentos/' . $m['id_medicamento'])) ?>"
                                data-campo-nombre="<?= e($m['nombre']) ?>" data-campo-presentacion="<?= e($m['presentacion']) ?>"
                                data-campo-concentracion="<?= e($m['concentracion']) ?>"
                                data-campo-titulo-modal="Modificar medicamento"><?= icono('editar') ?>Editar</button>
                        <form method="post" action="<?= e(url('/medicamentos/' . $m['id_medicamento'] . '/estado')) ?>"
                              data-confirmar="<?= $m['estado'] === 'activo' ? '¿Desactivar «' . e($m['nombre']) . '»? Ya no se podrá recetar.' : '¿Activar «' . e($m['nombre']) . '»?' ?>"
                              data-confirmar-boton="<?= $m['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" <?= $m['estado'] === 'activo' ? 'data-peligro="1"' : '' ?>>
                            <?= csrf_campo() ?>
                            <button class="boton <?= $m['estado'] === 'activo' ? 'boton-peligro' : 'boton-secundario' ?> boton-chico" type="submit"><?= icono('power') ?><?= $m['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($medicamentos) ?> medicamento(s)</span></div>
</section>

<dialog class="modal" id="modal-medicamento" aria-labelledby="titulo-modal-med">
    <form method="post" action="<?= e(url('/medicamentos')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="modal-cabecera"><h2 id="titulo-modal-med" data-mostrar="tituloModal">Nuevo medicamento</h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <div class="campo">
                <label for="med-nombre">Nombre genérico <span class="requerido">*</span></label>
                <input id="med-nombre" name="nombre" required minlength="3" maxlength="80" placeholder="Paracetamol" autofocus>
            </div>
            <div class="campo">
                <label for="med-presentacion">Presentación <span class="requerido">*</span></label>
                <select id="med-presentacion" name="presentacion" required>
                    <?php foreach ($presentaciones as $pr): ?><option value="<?= e($pr) ?>"><?= e(ucfirst($pr)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="med-concentracion">Concentración <span class="opcional">(opcional)</span></label>
                <input id="med-concentracion" name="concentracion" maxlength="40" placeholder="500 mg">
            </div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar</button>
        </div>
    </form>
</dialog>
