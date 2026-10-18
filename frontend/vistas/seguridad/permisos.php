<?php
/** IU Permisos por rol (CU02) */
$nombresRol = ['administrador' => 'Administrador', 'recepcionista' => 'Recepcionista', 'medico' => 'Médico', 'paciente' => 'Paciente'];
$modulos = ['seguridad' => 'Seguridad', 'pacientes' => 'Pacientes', 'medicos' => 'Médicos y horarios', 'citas' => 'Citas', 'reportes' => 'Reportes', 'portal' => 'Portal del paciente'];
$modulo = null;
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/usuarios')) ?>"><?= icono('flecha-izq') ?>Usuarios</a>
        <h1>Permisos por rol</h1>
        <p>Marque lo que puede hacer cada rol. El menú de cada usuario y el acceso a cada página dependen de estos permisos.
           Por ejemplo, el médico solo marca citas como atendidas si tiene «Marcar citas como atendidas».</p>
    </div>
</div>

<form class="tarjeta" method="post" action="<?= e(url('/permisos')) ?>"
      data-confirmar="Los cambios se aplican de inmediato a todos los usuarios de cada rol." data-confirmar-boton="Guardar permisos">
    <?= csrf_campo() ?>
    <div class="tabla-contenedor">
        <table class="tabla matriz">
            <thead><tr><th>Permiso</th>
                <?php foreach ($roles as $r): ?><th><?= e($nombresRol[$r['nombre']]) ?><span class="celda-sub"><?= (int) $r['usuarios'] ?> usuario(s)</span></th><?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($permisos as $p): ?>
                <?php if ($p['modulo'] !== $modulo): $modulo = $p['modulo']; ?>
                    <tr class="modulo"><td colspan="<?= count($roles) + 1 ?>"><?= e($modulos[$modulo] ?? $modulo) ?></td></tr>
                <?php endif; ?>
                <tr>
                    <td><span class="celda-principal"><?= e($p['nombre']) ?></span><span class="celda-sub"><?= e($p['codigo']) ?></span></td>
                    <?php foreach ($roles as $r):
                        $fijo = $r['nombre'] === 'administrador' && $p['codigo'] === 'usuarios.gestionar'; ?>
                        <td>
                            <input type="checkbox" name="permisos[<?= (int) $r['id_rol'] ?>][]" value="<?= (int) $p['id_permiso'] ?>"
                                   aria-label="<?= e($p['nombre'] . ' — ' . $nombresRol[$r['nombre']]) ?>"
                                   <?= marcado(in_array((int) $p['id_permiso'], $matriz[(int) $r['id_rol']] ?? [], true)) ?>
                                   <?= $fijo ? 'checked disabled title="El administrador siempre gestiona usuarios"' : '' ?>>
                            <?php if ($fijo): ?><input type="hidden" name="permisos[<?= (int) $r['id_rol'] ?>][]" value="<?= (int) $p['id_permiso'] ?>"><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="tarjeta-pie pie-formulario">
        <button class="boton boton-primario" type="submit"><?= icono('check') ?>Guardar permisos</button>
    </div>
</form>
