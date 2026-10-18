<?php
/**
 * Configuración general del sistema.
 *
 * Para cambiar la conexión SIN tocar este archivo, cree
 * backend/config/config.local.php (no se sube a GitHub) devolviendo solo las
 * claves que quiera reemplazar, por ejemplo:
 *
 *   <?php return ['bd' => ['clave' => 'mi_clave_de_postgres']];
 */
$config = [
    'app' => [
        'nombre'       => 'Clínica Vida Salud',
        'zona_horaria' => 'America/La_Paz',
        // true solo en desarrollo: muestra el detalle técnico de los errores.
        'depuracion'   => false,
    ],

    // Conexión PDO con el controlador pdo_pgsql (requerimiento técnico 7)
    'bd' => [
        'host'    => '127.0.0.1',
        'puerto'  => 5432,
        'nombre'  => 'clinica_vida_salud',
        'usuario' => 'postgres',
        'clave'   => 'postgres',
    ],

    'sesion' => [
        'nombre'              => 'VIDASALUD_SESION',
        'minutos_inactividad' => 30,   // la sesión se cierra tras 30 min sin uso
    ],

    'seguridad' => [
        'max_intentos'    => 5,        // intentos fallidos antes de bloquear
        'minutos_bloqueo' => 15,
    ],

    'citas' => [
        'dias_maximos_reserva'          => 60, // hasta cuántos días adelante se reserva
        'horas_minimas_cancelar_portal' => 2,  // el paciente cancela con 2 h de anticipación
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
