<?php /** IU Gestionar pacientes — listado y búsqueda (CU03) */ ?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Pacientes</h1>
        <p>Busque por CI, nombre, apellido o código. Los pacientes desactivados conservan todo su historial.</p>
    </div>
    <?php if ($puedeGestionar): ?>
    <div class="acciones"><a class="boton boton-primario" href="<?= e(url('/pacientes/nuevo')) ?>"><?= icono('mas') ?>Registrar paciente</a></div>
    <?php endif; ?>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/pacientes')) ?>" role="search">
        <div class="campo campo-buscar">
            <label for="q">Buscar paciente</label>
            <div class="campo-con-icono"><?= icono('buscar') ?>
                <input id="q" name="q" type="search" placeholder="CI, nombre o apellido" value="<?= e($filtros['buscar']) ?>" autofocus></div>
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

    <?php if (!$pacientes): ?>
        <div class="vacio"><?= icono('pacientes') ?><h3>No se encontraron pacientes</h3>
            <p><?= $filtros['buscar'] ? 'Revise el CI o el nombre escrito.' : 'Registre al primer paciente.' ?></p>
            <?php if ($puedeGestionar): ?><a class="boton boton-primario" href="<?= e(url('/pacientes/nuevo')) ?>">Registrar paciente</a><?php endif; ?></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Paciente</th><th>CI</th><th>Edad</th><th>Teléfono</th><th>Próxima cita</th><th>Estado</th><th class="col-acciones"></th></tr></thead>
            <tbody>
            <?php foreach ($pacientes as $p): ?>
                <tr class="<?= $p['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td><span class="codigo"><?= e($p['codigo']) ?></span></td>
                    <td><a class="celda-principal" href="<?= e(url('/pacientes/' . $p['id_paciente'])) ?>"><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></a>
                        <span class="celda-sub"><?= e(ucfirst($p['sexo'])) ?><?= $p['ultima_atencion'] ? ' · última atención ' . e(fecha_corta($p['ultima_atencion'])) : '' ?></span></td>
                    <td class="numero"><?= e($p['ci'] ?: '—') ?></td>
                    <td><?= e(edad($p['fecha_nacimiento'])) ?></td>
                    <td class="numero"><?= e($p['telefono']) ?></td>
                    <td><?= $p['proxima_cita'] ? e(fecha_corta($p['proxima_cita'])) : '<span class="texto-tenue">—</span>' ?></td>
                    <td><?= estado_registro($p['estado']) ?></td>
                    <td class="col-acciones"><div class="acciones">
                        <a class="boton boton-secundario boton-chico" href="<?= e(url('/pacientes/' . $p['id_paciente'])) ?>"><?= icono('ojo') ?>Ver</a>
                        <?php if ($p['estado'] === 'activo' && \App\Nucleo\Autenticacion::puede('citas.programar')): ?>
                            <a class="boton boton-secundario boton-chico" href="<?= e(url('/citas/nueva', ['paciente' => $p['id_paciente']])) ?>"><?= icono('calendario-mas') ?>Cita</a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?= \App\Nucleo\Vista::parcial('compartido/paginacion', [
        'pagina' => $pagina, 'paginas' => $paginas, 'total' => $total, 'ruta' => '/pacientes',
        'params' => ['q' => $filtros['buscar'], 'estado' => $filtros['estado']], 'nombre' => 'paciente(s)',
    ]) ?>
</section>
