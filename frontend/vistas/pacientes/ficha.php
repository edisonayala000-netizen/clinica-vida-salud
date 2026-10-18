<?php
/** IU Ficha del paciente (CU03) */
use App\Nucleo\Autenticacion;

$p = $paciente;
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <a class="miga" href="<?= e(url('/pacientes')) ?>"><?= icono('flecha-izq') ?>Pacientes</a>
        <div class="perfil-cabecera">
            <span class="avatar" aria-hidden="true"><?= e(iniciales($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
            <div>
                <h1><?= e($p['nombres'] . ' ' . $p['apellidos']) ?></h1>
                <p><span class="codigo"><?= e($p['codigo']) ?></span> · CI <?= e($p['ci'] ?: 'no registrado') ?> · <?= e(edad($p['fecha_nacimiento'])) ?> · <?= estado_registro($p['estado']) ?></p>
            </div>
        </div>
    </div>
    <div class="acciones">
        <?php if ($p['estado'] === 'activo' && Autenticacion::puede('citas.programar')): ?>
            <a class="boton boton-primario" href="<?= e(url('/citas/nueva', ['paciente' => $p['id_paciente']])) ?>"><?= icono('calendario-mas') ?>Programar cita</a>
        <?php endif; ?>
        <?php if (Autenticacion::puede('pacientes.historial')): ?>
            <a class="boton boton-secundario" href="<?= e(url('/pacientes/' . $p['id_paciente'] . '/historial')) ?>"><?= icono('historial') ?>Historial de citas</a>
        <?php endif; ?>
        <?php if ($puedeGestionar): ?>
            <a class="boton boton-secundario" href="<?= e(url('/pacientes/' . $p['id_paciente'] . '/editar')) ?>"><?= icono('editar') ?>Modificar</a>
            <form method="post" action="<?= e(url('/pacientes/' . $p['id_paciente'] . '/estado')) ?>"
                  data-confirmar-titulo="<?= $p['estado'] === 'activo' ? 'Desactivar paciente' : 'Reactivar paciente' ?>"
                  data-confirmar="<?= $p['estado'] === 'activo' ? 'El paciente no podrá recibir nuevas citas y sus citas pendientes se cancelarán. Su historial NO se elimina.' : 'El paciente podrá volver a recibir citas.' ?>"
                  data-confirmar-boton="<?= $p['estado'] === 'activo' ? 'Desactivar' : 'Reactivar' ?>" <?= $p['estado'] === 'activo' ? 'data-peligro="1"' : '' ?>>
                <?= csrf_campo() ?>
                <button class="boton <?= $p['estado'] === 'activo' ? 'boton-peligro' : 'boton-secundario' ?>" type="submit"><?= icono('power') ?><?= $p['estado'] === 'activo' ? 'Desactivar' : 'Reactivar' ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="indicadores">
    <div class="indicador"><div class="indicador-valor"><?= $resumen['programada'] + $resumen['confirmada'] ?></div><div class="indicador-etiqueta"><?= icono('calendario') ?>Citas pendientes</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['atendida'] ?></div><div class="indicador-etiqueta"><?= icono('check-circulo') ?>Atendidas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['cancelada'] ?></div><div class="indicador-etiqueta"><?= icono('calendario-x') ?>Canceladas</div></div>
    <div class="indicador"><div class="indicador-valor"><?= $resumen['inasistencia'] ?></div><div class="indicador-etiqueta"><?= icono('alerta') ?>Inasistencias</div></div>
</div>

<div class="rejilla-lateral">
    <div class="columna">
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Datos personales y contacto</h2></header>
            <div class="tarjeta-cuerpo">
                <dl class="datos">
                    <dt>Fecha de nacimiento</dt><dd><?= e(fecha_corta($p['fecha_nacimiento'])) ?> (<?= e(edad($p['fecha_nacimiento'])) ?>)</dd>
                    <dt>Sexo</dt><dd><?= e(ucfirst($p['sexo'])) ?></dd>
                    <dt>Teléfono</dt><dd><?= e($p['telefono']) ?></dd>
                    <dt>Correo</dt><dd><?= e($p['correo'] ?: '—') ?></dd>
                    <dt>Dirección</dt><dd><?= e($p['direccion'] ?: '—') ?></dd>
                    <dt>Cuenta en línea</dt><dd><?= $p['cuenta'] ? 'Sí (' . e($p['cuenta']) . ')' : 'No tiene' ?></dd>
                    <dt>Registrado</dt><dd><?= e(fecha_hora($p['creado_en'])) ?></dd>
                </dl>
            </div>
        </section>
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Últimas citas</h2>
                <div class="acciones"><a class="boton boton-texto" href="<?= e(url('/pacientes/' . $p['id_paciente'] . '/historial')) ?>">Ver historial completo</a></div></header>
            <?php if (!$recientes): ?>
                <div class="vacio"><?= icono('calendario') ?><h3>Aún no tiene citas</h3></div>
            <?php else: ?>
            <div class="tabla-contenedor"><table class="tabla">
                <thead><tr><th>Código</th><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($recientes as $c): ?>
                    <tr>
                        <td><a class="codigo" href="<?= e(url('/citas/' . $c['id_cita'])) ?>"><?= e($c['codigo']) ?></a></td>
                        <td class="numero"><?= e(fecha_corta($c['fecha'])) ?> <?= e(hora($c['hora_inicio'])) ?></td>
                        <td><?= e($c['medico']) ?></td>
                        <td><?= e($c['especialidad']) ?></td>
                        <td><?= estado_cita($c['estado']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    </div>
    <div class="columna">
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Información médica</h2></header>
            <div class="tarjeta-cuerpo">
                <dl class="ficha-medica" style="grid-template-columns:1fr">
                    <div><dt>Tipo de sangre</dt><dd><?= e($p['tipo_sangre'] ?: 'No registrado') ?></dd></div>
                    <div><dt>Alergias</dt><dd><?= e($p['alergias'] ?: 'Ninguna registrada') ?></dd></div>
                    <div><dt>Enfermedades crónicas</dt><dd><?= e($p['enfermedades_cronicas'] ?: 'Ninguna registrada') ?></dd></div>
                    <div><dt>Medicamentos</dt><dd><?= e($p['medicamentos'] ?: 'Ninguno registrado') ?></dd></div>
                    <div><dt>Antecedentes</dt><dd><?= e($p['antecedentes'] ?: 'Sin antecedentes registrados') ?></dd></div>
                </dl>
            </div>
        </section>
        <section class="tarjeta">
            <header class="tarjeta-cabecera"><h2>Contacto de emergencia</h2></header>
            <div class="tarjeta-cuerpo">
                <?php if (!$contactos): ?><p class="texto-suave">No registrado.</p><?php endif; ?>
                <?php foreach ($contactos as $c): ?>
                    <dl class="datos"><dt>Nombre</dt><dd><?= e($c['nombre']) ?></dd><dt>Parentesco</dt><dd><?= e($c['parentesco']) ?></dd><dt>Teléfono</dt><dd><?= e($c['telefono']) ?></dd></dl>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
