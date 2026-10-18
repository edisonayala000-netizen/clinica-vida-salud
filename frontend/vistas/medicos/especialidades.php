<?php /** IU Gestionar especialidades (CU06) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Especialidades</h1>
        <p>Servicios que ofrece la clínica. No se repiten nombres aunque cambien tildes o mayúsculas.</p>
    </div>
    <div class="acciones">
        <button class="boton boton-primario" type="button" data-abrir-modal="modal-especialidad"
                data-accion="<?= e(url('/especialidades')) ?>" data-campo-nombre="" data-campo-descripcion=""
                data-campo-titulo-modal="Nueva especialidad"><?= icono('mas') ?>Nueva especialidad</button>
    </div>
</div>

<?php if ($errores): ?>
    <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p><?= e(implode(' ', $errores)) ?></p></div>
<?php endif; ?>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/especialidades')) ?>">
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar>
                <option value="">Todas</option>
                <option value="activo"<?= seleccionado($estado, 'activo') ?>>Activas</option>
                <option value="inactivo"<?= seleccionado($estado, 'inactivo') ?>>Inactivas</option>
            </select>
        </div>
    </form>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>ID</th><th>Especialidad</th><th>Médicos activos</th><th>Citas registradas</th><th>Estado</th><th class="col-acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($especialidades as $e): ?>
                <tr class="<?= $e['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td class="numero"><?= (int) $e['id_especialidad'] ?></td>
                    <td><span class="celda-principal"><?= e($e['nombre']) ?></span><span class="celda-sub"><?= e($e['descripcion'] ?: 'Sin descripción') ?></span></td>
                    <td class="numero"><?= (int) $e['medicos_activos'] ?></td>
                    <td class="numero"><?= (int) $e['total_citas'] ?></td>
                    <td><?= estado_registro($e['estado']) ?></td>
                    <td class="col-acciones"><div class="acciones">
                        <button class="boton boton-secundario boton-chico" type="button" data-abrir-modal="modal-especialidad"
                                data-accion="<?= e(url('/especialidades/' . $e['id_especialidad'])) ?>"
                                data-campo-nombre="<?= e($e['nombre']) ?>" data-campo-descripcion="<?= e($e['descripcion']) ?>"
                                data-campo-titulo-modal="Modificar especialidad"><?= icono('editar') ?>Editar</button>
                        <form method="post" action="<?= e(url('/especialidades/' . $e['id_especialidad'] . '/estado')) ?>"
                              data-confirmar="<?= $e['estado'] === 'activo' ? '¿Desactivar «' . e($e['nombre']) . '»? Ya no podrá elegirse para nuevas citas.' : '¿Activar «' . e($e['nombre']) . '»?' ?>"
                              data-confirmar-boton="<?= $e['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" <?= $e['estado'] === 'activo' ? 'data-peligro="1"' : '' ?>>
                            <?= csrf_campo() ?>
                            <button class="boton <?= $e['estado'] === 'activo' ? 'boton-peligro' : 'boton-secundario' ?> boton-chico" type="submit"><?= icono('power') ?><?= $e['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($especialidades) ?> especialidad(es)</span></div>
</section>

<dialog class="modal" id="modal-especialidad" aria-labelledby="titulo-modal-esp">
    <form method="post" action="<?= e(url('/especialidades')) ?>" data-validar novalidate>
        <?= csrf_campo() ?>
        <div class="modal-cabecera"><h2 id="titulo-modal-esp" data-mostrar="tituloModal">Nueva especialidad</h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <div class="campo">
                <label for="esp-nombre">Nombre <span class="requerido">*</span></label>
                <input id="esp-nombre" name="nombre" required minlength="3" maxlength="80" autofocus>
            </div>
            <div class="campo">
                <label for="esp-descripcion">Descripción <span class="opcional">(opcional)</span></label>
                <textarea id="esp-descripcion" name="descripcion" maxlength="250"></textarea>
            </div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar</button>
        </div>
    </form>
</dialog>
