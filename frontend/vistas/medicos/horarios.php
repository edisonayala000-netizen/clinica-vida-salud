<?php
/** IU Gestionar horarios de atención y días no laborables (CU08) */
$porDia = array_fill(1, 7, []);
foreach ($bloques as $b) {
    $porDia[(int) $b['dia_semana']][] = $b;
}
$idM = $medico['id_medico'] ?? null;
?>
<div class="encabezado">
    <div class="encabezado-texto">
        <h1>Horarios de atención</h1>
        <p>Defina en qué días y horas atiende cada médico y cuánto dura cada cita. El sistema calcula con esto los horarios disponibles.</p>
    </div>
</div>

<section class="tarjeta">
    <form class="filtros" method="get" action="<?= e(url('/horarios')) ?>">
        <div class="campo campo-buscar">
            <label for="medico">Médico</label>
            <select id="medico" name="medico" data-autoenviar>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= (int) $m['id_medico'] ?>"<?= seleccionado($idM, $m['id_medico']) ?>><?= e($m['codigo'] . ' · ' . $m['nombre']) ?><?= $m['estado'] !== 'activo' ? ' (inactivo)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($medico): ?>
            <p class="texto-suave" style="align-self:center"><?= e($medico['especialidades']) ?> · <?= estado_registro($medico['estado']) ?></p>
            <div class="acciones" style="margin-left:auto">
                <button class="boton boton-primario" type="button" data-abrir-modal="modal-bloque" data-accion="<?= e(url('/horarios')) ?>"
                        data-campo-titulo-bloque="Agregar horario" data-campo-dia_semana="1" data-campo-hora_inicio="08:00"
                        data-campo-hora_fin="12:00" data-campo-duracion_cita="30"
                        <?= $medico['estado'] !== 'activo' ? 'disabled' : '' ?>><?= icono('mas') ?>Agregar horario</button>
            </div>
        <?php endif; ?>
    </form>

    <div class="tarjeta-cuerpo">
        <?php if ($errores): ?>
            <div class="alerta alerta-error" role="alert"><?= icono('alerta') ?><p><?= e(implode(' ', $errores)) ?></p></div>
        <?php endif; ?>
        <div class="semana">
            <?php foreach (DIAS_SEMANA as $n => $nombreDia): ?>
                <div class="semana-dia">
                    <h3><?= e($nombreDia) ?></h3>
                    <?php foreach ($porDia[$n] as $b): ?>
                        <div class="bloque<?= $b['estado'] !== 'activo' ? ' inactivo' : '' ?>">
                            <strong><?= e(hora($b['hora_inicio'])) ?> – <?= e(hora($b['hora_fin'])) ?></strong>
                            <span class="texto-tenue">Citas de <?= (int) $b['duracion_cita'] ?> min<?= $b['estado'] !== 'activo' ? ' · inactivo' : '' ?></span>
                            <div class="acciones">
                                <button class="boton-icono" type="button" title="Modificar" aria-label="Modificar horario"
                                        data-abrir-modal="modal-bloque" data-accion="<?= e(url('/horarios/' . $b['id_horario'])) ?>"
                                        data-campo-titulo-bloque="Modificar horario" data-campo-dia_semana="<?= (int) $b['dia_semana'] ?>"
                                        data-campo-hora_inicio="<?= e(hora($b['hora_inicio'])) ?>" data-campo-hora_fin="<?= e(hora($b['hora_fin'])) ?>"
                                        data-campo-duracion_cita="<?= (int) $b['duracion_cita'] ?>"><?= icono('editar') ?></button>
                                <form method="post" action="<?= e(url('/horarios/' . $b['id_horario'] . '/estado')) ?>"
                                      data-confirmar="<?= $b['estado'] === 'activo' ? '¿Desactivar este horario? No se ofrecerán citas en él.' : '¿Activar este horario?' ?>"
                                      data-confirmar-boton="<?= $b['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>">
                                    <?= csrf_campo() ?>
                                    <button class="boton-icono" type="submit" title="<?= $b['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $b['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> horario"><?= icono('power') ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$porDia[$n]): ?><p class="texto-tenue">No atiende</p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="tarjeta">
    <header class="tarjeta-cabecera">
        <h2>Feriados y ausencias</h2>
        <div class="acciones"><button class="boton boton-secundario" type="button" data-abrir-modal="modal-feriado"><?= icono('bloqueo') ?>Bloquear una fecha</button></div>
    </header>
    <?php if (!$feriados): ?>
        <div class="vacio"><?= icono('calendario') ?><h3>Sin fechas bloqueadas</h3><p>Los feriados bloquean la agenda de toda la clínica o de un médico.</p></div>
    <?php else: ?>
    <div class="tabla-contenedor"><table class="tabla">
        <thead><tr><th>Fecha</th><th>Motivo</th><th>Alcance</th><th class="col-acciones"></th></tr></thead>
        <tbody>
        <?php foreach ($feriados as $f): ?>
            <tr>
                <td class="celda-principal"><?= e(ucfirst(fecha_larga($f['fecha']))) ?></td>
                <td><?= e($f['descripcion']) ?></td>
                <td><?= e($f['alcance']) ?></td>
                <td class="col-acciones">
                    <form method="post" action="<?= e(url('/feriados/' . $f['id_dia_no_laborable'] . '/quitar')) ?>"
                          data-confirmar="¿Volver a habilitar el <?= e(fecha_corta($f['fecha'])) ?>?" data-confirmar-boton="Habilitar">
                        <?= csrf_campo() ?><input type="hidden" name="volver_medico" value="<?= (int) $idM ?>">
                        <button class="boton boton-secundario boton-chico" type="submit">Quitar bloqueo</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<dialog class="modal" id="modal-bloque" aria-labelledby="titulo-bloque">
    <form method="post" action="<?= e(url('/horarios')) ?>" data-validar novalidate>
        <?= csrf_campo() ?><input type="hidden" name="id_medico" value="<?= (int) $idM ?>">
        <div class="modal-cabecera"><h2 id="titulo-bloque" data-mostrar="tituloBloque">Agregar horario</h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <div class="campo">
                <label for="dia_semana">Día de la semana</label>
                <select id="dia_semana" name="dia_semana" required>
                    <?php foreach (DIAS_SEMANA as $n => $d): ?><option value="<?= $n ?>"><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="rejilla-2">
                <div class="campo"><label for="hora_inicio">Hora inicial</label><input id="hora_inicio" name="hora_inicio" type="time" required min="06:00" max="22:00" step="300"></div>
                <div class="campo"><label for="hora_fin">Hora final</label><input id="hora_fin" name="hora_fin" type="time" required min="06:00" max="22:00" step="300"></div>
            </div>
            <div class="campo">
                <label for="duracion_cita">Duración de cada cita</label>
                <select id="duracion_cita" name="duracion_cita" required>
                    <?php foreach ([15, 20, 30, 45, 60] as $min): ?><option value="<?= $min ?>"><?= $min ?> minutos</option><?php endforeach; ?>
                </select>
                <p class="ayuda">La hora final debe ser posterior a la inicial y el bloque no puede cruzarse con otro del mismo día.</p>
            </div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar horario</button>
        </div>
    </form>
</dialog>

<dialog class="modal" id="modal-feriado" aria-labelledby="titulo-feriado">
    <form method="post" action="<?= e(url('/feriados')) ?>" data-validar novalidate>
        <?= csrf_campo() ?><input type="hidden" name="volver_medico" value="<?= (int) $idM ?>">
        <div class="modal-cabecera"><h2 id="titulo-feriado">Bloquear una fecha</h2>
            <button type="button" class="boton-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x') ?></button></div>
        <div class="modal-cuerpo">
            <div class="campo"><label for="f-fecha">Fecha</label><input id="f-fecha" name="fecha" type="date" required min="<?= date('Y-m-d') ?>"></div>
            <div class="campo"><label for="f-desc">Motivo</label><input id="f-desc" name="descripcion" required minlength="3" maxlength="120" placeholder="Feriado nacional, vacaciones del médico…"></div>
            <div class="campo">
                <label for="f-medico">Alcance</label>
                <select id="f-medico" name="id_medico">
                    <option value="">Toda la clínica</option>
                    <?php if ($medico): ?><option value="<?= (int) $idM ?>">Solo <?= e($medico['nombres'] . ' ' . $medico['apellidos']) ?></option><?php endif; ?>
                </select>
            </div>
        </div>
        <div class="modal-pie">
            <button type="button" class="boton boton-secundario" data-cerrar-modal>Cancelar</button>
            <button type="submit" class="boton boton-primario">Bloquear fecha</button>
        </div>
    </form>
</dialog>
