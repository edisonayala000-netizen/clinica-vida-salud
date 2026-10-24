# =====================================================================
#  Imagen para desplegar el sistema en la nube (Railway).
#  PHP 8.2 + Apache, igual que XAMPP: el .htaccess de la raíz envía
#  /assets/... a frontend/assets y todo lo demás a backend/public/index.php.
#
#  La conexión a la base, la clave de Brevo, APP_URL y APP_SECRETO llegan
#  por variables de entorno (ver backend/README.md). El mismo contenedor
#  sirve para la tarea programada cambiando el comando de inicio:
#     php backend/tareas/enviar_notificaciones.php
# =====================================================================
FROM php:8.2-apache

# Controlador de PostgreSQL para PDO (curl y mbstring ya vienen en la imagen).
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

# PHP de producción: sin mostrar errores ni la versión de PHP.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php = Off\ndate.timezone = America/La_Paz\n' > "$PHP_INI_DIR/conf.d/clinica.ini"

# Apache: .htaccess activo, https detrás del proxy de Railway e IP real del visitante.
COPY docker/apache-clinica.conf /etc/apache2/conf-available/clinica.conf
RUN a2enmod rewrite remoteip && a2enconf clinica

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/backend/storage

# Railway indica el puerto en la variable PORT; en otra máquina se usa el 80.
CMD ["sh", "-c", "sed -i \"s/^Listen .*/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:[0-9]*>/<VirtualHost *:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
