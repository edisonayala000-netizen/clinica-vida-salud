-- =====================================================================
--  Instala la base completa en orden. Ejecutar desde esta carpeta:
--    psql -U postgres -d clinica_vida_salud -f instalar.sql
-- =====================================================================
\set ON_ERROR_STOP on
-- Los scripts están en UTF-8 (en Windows, psql usa WIN1252 si no se indica).
SET client_encoding = 'UTF8';
\echo '1/6 Esquema (tablas y restricciones)...'
\i 01_esquema.sql
\echo '2/6 Funciones y triggers...'
\i 02_funciones_triggers.sql
\echo '3/6 Vistas...'
\i 03_vistas.sql
\echo '4/6 Datos iniciales (roles, permisos, estados, especialidades, medicamentos, admin)...'
\i 04_datos_iniciales.sql
\echo '5/6 Datos de prueba (médicos, pacientes, citas, atenciones)...'
\i 05_datos_prueba.sql
\echo '6/6 Diccionario de datos (descripción de tablas y columnas)...'
\i 06_diccionario.sql
\echo 'Base de datos instalada correctamente.'
