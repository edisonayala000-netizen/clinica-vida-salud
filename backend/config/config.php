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
        // Dirección completa del sistema para los enlaces de los correos, incluida su carpeta
        // (p. ej. http://localhost/clinica-vida-salud o https://clinica.ejemplo.com). Vacía = se toma de la
        // petición actual; la tarea programada (sin navegador) la necesita o recibe --url.
        'url'          => getenv('APP_URL') ?: '',
        // Clave para firmar los enlaces de los correos (confirmar o cancelar cita, desactivar avisos).
        // Vacía = se genera sola en storage/secreto.key (no se sube a GitHub).
        'secreto'      => getenv('APP_SECRETO') ?: '',
    ],

    // Conexión PDO con el controlador pdo_pgsql (requerimiento técnico 7).
    // En la nube cada dato llega por una variable de entorno (DB_HOST, DB_PORT...); en la PC se usan
    // estos valores o los de config.local.php.
    'bd' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'puerto'  => (int) (getenv('DB_PORT') ?: 5432),
        'nombre'  => getenv('DB_NOMBRE') ?: 'clinica_vida_salud',
        'usuario' => getenv('DB_USUARIO') ?: 'postgres',
        'clave'   => getenv('DB_CLAVE') ?: 'postgres',
        // Conexión cifrada: 'require' en Supabase. Vacío = la opción por defecto de PostgreSQL.
        'sslmode' => getenv('DB_SSLMODE') ?: '',
    ],

    'sesion' => [
        'nombre'              => 'VIDASALUD_SESION',
        'minutos_inactividad' => 30,   // la sesión se cierra tras 30 min sin uso
    ],

    'seguridad' => [
        'max_intentos'    => 5,        // intentos fallidos antes de bloquear
        'minutos_bloqueo' => 15,
    ],

    // Correos del sistema con la API de Brevo (CU15 y recordatorios). La clave y el remitente
    // verificado van en config.local.php o, en la nube, en BREVO_API_KEY y CORREO_REMITENTE.
    // Sin clave, los correos solo se escriben en storage/logs/correos.log (modo de desarrollo).
    'correo' => [
        'api_key'   => getenv('BREVO_API_KEY') ?: '',
        'remitente' => getenv('CORREO_REMITENTE') ?: '',
        'nombre'    => 'Clínica Vida Salud',
        // A estos dominios nunca se envía (datos de prueba): el correo va al log. vidasalud.bo es el dominio
        // ficticio del personal de demostración; así nadie recibe correos por error ni hay rebotes en Brevo.
        'dominios_sin_envio' => ['example.com', 'example.org', 'example.net', 'test', 'invalid', 'localhost', 'vidasalud.bo'],
        // Desarrollo: si tiene correos, SOLO a ellos se envía de verdad; los demás van al log.
        // Vacía = sin restricción (producción). Ej. en config.local.php: 'solo_a' => ['mi.correo@gmail.com']
        // o en la nube CORREO_SOLO_A=uno@gmail.com,otro@gmail.com
        'solo_a' => array_values(array_filter(array_map('trim', explode(',', getenv('CORREO_SOLO_A') ?: '')))),
    ],

    // CU18 · Envío de notificaciones (tarea programada cada 5 minutos)
    'notificaciones' => [
        'limite_diario'    => 280,  // correos reales por día (Brevo gratis: 300); deja margen para recuperar contraseñas
        'max_atraso_horas' => 6,    // un recordatorio con más atraso ya no se envía (se anula)
        'max_intentos'     => 3,    // reintentos automáticos de un correo que falló
        'por_ejecucion'    => 50,   // correos como máximo en cada ejecución de la tarea
    ],

    // CU15 · Recuperar contraseña
    'recuperacion' => [
        'minutos_vigencia'    => 30,   // vigencia del enlace enviado por correo
        'max_por_hora_cuenta' => 3,    // solicitudes por cuenta en una hora
        'max_por_hora_ip'     => 20,   // solicitudes desde una misma IP en una hora
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
