-- =====================================================================
--  Instala la base completa en orden. Ejecutar desde esta carpeta:
--    psql -U postgres -d clinica_vida_salud -f instalar.sql
-- =====================================================================
\set ON_ERROR_STOP on
-- Los scripts están en UTF-8 (en Windows, psql usa WIN1252 si no se indica).
SET client_encoding = 'UTF8';
\echo '1/5 Esquema (tablas y restricciones)...'
\i 01_esquema.sql
\echo '2/5 Funciones y triggers...'
\i 02_funciones_triggers.sql
\echo '3/5 Vistas...'
\i 03_vistas.sql
\echo '4/5 Datos iniciales (roles, permisos, especialidades, admin)...'
\i 04_datos_iniciales.sql
\echo '5/5 Datos de prueba (médicos, pacientes, citas)...'
\i 05_datos_prueba.sql
\echo 'Base de datos instalada correctamente.'
