<?php /** IU Gestionar médicos — listado y búsqueda (CU07) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Médicos</h1>
        <p>Busque por nombre, código, registro profesional o especialidad. Un médico inactivo conserva su historial pero no recibe citas nuevas.</p>
    </div>
    <?php if ($puedeGestionar): ?>
    <div class="acciones">
        <a class="boton boton-primario" href="<?= e(url('/medicos/nuevo')) ?>"><?= icono('mas') ?>Registrar médico</a>
    </div>
    <?php endif; ?>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/medicos')) ?>">
        <div class="campo campo-buscar">
            <label for="q">Buscar</label>
            <div class="campo-con-icono"><?= icono('buscar') ?>
                <input id="q" name="q" type="search" placeholder="Nombre, código o especialidad" value="<?= e($filtros['q']) ?>"></div>
        </div>
        <div class="campo">
            <label for="especialidad">Especialidad</label>
            <select id="especialidad" name="especialidad" data-autoenviar>
                <option value="">Todas</option>
                <?php foreach ($especialidades as $esp): ?>
                    <option value="<?= (int) $esp['id_especialidad'] ?>"<?= seleccionado($filtros['especialidad'], $esp['id_especialidad']) ?>><?= e($esp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" data-autoenviar>
                <option value="">Todos</option>
                <option value="activo"<?= seleccionado($filtros['estado'], 'activo') ?>>Activos</option>
                <option value="inactivo"<?= seleccionado($filtros['estado'], 'inactivo') ?>>Inactivos</option>
            </select>
        </div>
        <button class="boton boton-secundario" type="submit"><?= icono('buscar') ?>Buscar</button>
    </form>

    <?php if (!$medicos): ?>
        <div class="vacio"><?= icono('medico') ?><h3>No se encontraron médicos</h3><p>Pruebe con otro nombre o especialidad.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Médico</th><th>Especialidades</th><th>Contacto</th><th>Horarios</th><th>Pendientes</th><th>Estado</th><th class="col-acciones"></th></tr></thead>
            <tbody>
            <?php foreach ($medicos as $m): ?>
                <tr class="<?= $m['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td><span class="codigo"><?= e($m['codigo']) ?></span></td>
                    <td><a class="celda-principal" href="<?= e(url('/medicos/' . $m['id_medico'])) ?>">Dr(a). <?= e($m['nombres'] . ' ' . $m['apellidos']) ?></a>
                        <span class="celda-sub">Reg. <?= e($m['registro_profesional']) ?></span></td>
                    <td><?= e($m['especialidades']) ?></td>
                    <td><span class="celda-sub"><?= e($m['telefono']) ?></span><span class="celda-sub"><?= e($m['correo']) ?></span></td>
                    <td class="numero"><?= (int) $m['bloques'] ?> bloque(s)</td>
                    <td class="numero"><?= (int) $m['citas_pendientes'] ?></td>
                    <td><?= estado_registro($m['estado']) ?></td>
                    <td class="col-acciones"><div class="acciones">
                        <a class="boton boton-secundario boton-chico" href="<?= e(url('/medicos/' . $m['id_medico'])) ?>"><?= icono('ojo') ?>Ver</a>
                        <a class="boton boton-secundario boton-chico" href="<?= e(url('/agenda', ['medico' => $m['id_medico']])) ?>"><?= icono('agenda') ?>Agenda</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($medicos) ?> médico(s)</span></div>
    <?php endif; ?>
</section>
