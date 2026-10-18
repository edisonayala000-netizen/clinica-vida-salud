<?php
/** IU Gestionar usuarios (CU02) */
use App\Nucleo\Autenticacion;

$nombresRol = ['administrador' => 'Administrador', 'recepcionista' => 'Recepcionista', 'medico' => 'Médico', 'paciente' => 'Paciente'];
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Usuarios</h1>
        <p>Cuentas de acceso al sistema. Cada usuario entra con su nombre de usuario o correo y ve solo las opciones de su rol.</p>
    </div>
    <div class="acciones">
        <a class="boton boton-secundario" href="<?= e(url('/permisos')) ?>"><?= icono('escudo') ?>Permisos por rol</a>
        <a class="boton boton-primario" href="<?= e(url('/usuarios/nuevo')) ?>"><?= icono('mas') ?>Nuevo usuario</a>
    </div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/usuarios')) ?>">
        <div class="campo campo-buscar">
            <label for="q">Buscar</label>
            <div class="campo-con-icono"><?= icono('buscar') ?>
                <input id="q" name="q" type="search" placeholder="Nombre, usuario o correo" value="<?= e($filtros['buscar']) ?>"></div>
        </div>
        <div class="campo">
            <label for="rol">Rol</label>
            <select id="rol" name="rol" data-autoenviar>
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['nombre']) ?>"<?= seleccionado($filtros['rol'], $r['nombre']) ?>><?= e($nombresRol[$r['nombre']]) ?></option>
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
        <button class="boton boton-secundario" type="submit"><?= icono('filtro') ?>Filtrar</button>
    </form>

    <?php if (!$usuarios): ?>
        <div class="vacio"><?= icono('usuario') ?><h3>No hay usuarios con esos filtros</h3></div>
    <?php else: ?>
    <div class="tabla-contenedor">
        <table class="tabla">
            <thead><tr><th>Usuario</th><th>Rol</th><th>Vinculado a</th><th>Último acceso</th><th>Estado</th><th class="col-acciones">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['estado'] !== 'activo' ? 'inactivo' : '' ?>">
                    <td><span class="celda-principal"><?= e($u['nombre_completo']) ?></span>
                        <span class="celda-sub"><?= e($u['nombre_usuario']) ?> · <?= e($u['correo']) ?></span></td>
                    <td><span class="etiqueta"><?= e($nombresRol[$u['rol']]) ?></span></td>
                    <td><?= $u['codigo_medico'] ? e($u['codigo_medico']) : ($u['codigo_paciente'] ? e($u['codigo_paciente']) : '<span class="texto-tenue">—</span>') ?></td>
                    <td class="numero"><?= $u['ultimo_acceso'] ? e(fecha_hora($u['ultimo_acceso'])) : '<span class="texto-tenue">Nunca</span>' ?></td>
                    <td><?= estado_registro($u['estado']) ?><?= $u['esta_bloqueado'] ? ' <span class="estado estado-inasistencia">Bloqueado</span>' : '' ?></td>
                    <td class="col-acciones"><div class="acciones">
                        <a class="boton boton-secundario boton-chico" href="<?= e(url('/usuarios/' . $u['id_usuario'] . '/editar')) ?>"><?= icono('editar') ?>Editar</a>
                        <?php if ((int) $u['id_usuario'] !== Autenticacion::id()): ?>
                        <form method="post" action="<?= e(url('/usuarios/' . $u['id_usuario'] . '/estado')) ?>"
                              data-confirmar="<?= $u['estado'] === 'activo' ? '¿Desactivar la cuenta de ' . e($u['nombre_completo']) . '? No podrá iniciar sesión.' : '¿Activar la cuenta de ' . e($u['nombre_completo']) . '?' ?>"
                              data-confirmar-boton="<?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" <?= $u['estado'] === 'activo' ? 'data-peligro="1"' : '' ?>>
                            <?= csrf_campo() ?>
                            <button class="boton <?= $u['estado'] === 'activo' ? 'boton-peligro' : 'boton-secundario' ?> boton-chico" type="submit"><?= icono('power') ?><?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginacion"><span><?= count($usuarios) ?> usuario(s)</span></div>
    <?php endif; ?>
</section>
