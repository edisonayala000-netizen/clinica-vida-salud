<?php
/**
 * Menú lateral: cada opción aparece solo si el usuario tiene el permiso
 * (RF01: "mostrar un menú según los permisos correspondientes").
 */
use App\Nucleo\Autenticacion;
use App\Nucleo\Peticion;

$grupos = [
    'Inicio' => [
        ['/panel', 'Panel principal', 'inicio', 'autenticado'],
    ],
    'Mi espacio' => [
        ['/reservar',  'Reservar cita', 'calendario-mas', 'portal.reservar'],
        ['/mis-citas', 'Mis citas',     'calendario',     'portal.mis_citas'],
        ['/mis-recetas', 'Mis recetas', 'reporte',        'portal.recetas'],
        ['/mi-perfil', 'Mi perfil',     'usuario',        'portal.perfil'],
    ],
    'Citas' => [
        ['/citas/nueva', 'Programar cita', 'calendario-mas', 'citas.programar'],
        ['/citas',       'Citas',          'calendario',     'citas.ver'],
        ['/agenda',      'Agenda médica',  'agenda',         ['agenda.ver', 'agenda.propia']],
    ],
    'Registros' => [
        ['/pacientes',      'Pacientes',      'pacientes',    'pacientes.ver'],
        ['/medicos',        'Médicos',        'medico',       'medicos.ver'],
        ['/especialidades', 'Especialidades', 'especialidad', 'especialidades.gestionar'],
        ['/horarios',       'Horarios',       'reloj',        'horarios.gestionar'],
        ['/medicamentos',   'Medicamentos',   'gota',         'medicamentos.gestionar'],
    ],
    'Administración' => [
        ['/reportes', 'Reportes',            'reporte', 'reportes.ver'],
        ['/usuarios', 'Usuarios',            'usuario', 'usuarios.gestionar'],
        ['/permisos', 'Permisos por rol',    'escudo',  'usuarios.gestionar'],
        ['/bitacora', 'Bitácora',            'historial', 'bitacora.ver'],
        ['/notificaciones', 'Notificaciones', 'correo', 'notificaciones.ver'],
    ],
];

$rutaActual = Peticion::ruta();
?>
<nav class="menu" aria-label="Menú principal">
<?php foreach ($grupos as $titulo => $opciones):
    $visibles = array_filter($opciones, static fn ($o) => $o[3] === 'autenticado' || Autenticacion::puede($o[3]));
    if (!$visibles) { continue; } ?>
    <div class="menu-grupo">
        <p class="menu-titulo"><?= e($titulo) ?></p>
        <?php foreach ($visibles as [$ruta, $texto, $ico]):
            $activo = $rutaActual === $ruta || ($ruta !== '/panel' && $ruta !== '/citas' && str_starts_with($rutaActual, $ruta . '/'))
                   || ($ruta === '/citas' && preg_match('#^/citas/\d+#', $rutaActual)); ?>
            <a class="menu-enlace<?= $activo ? ' activo' : '' ?>" href="<?= e(url($ruta)) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
                <?= icono($ico) ?><span><?= e($texto) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</nav>
