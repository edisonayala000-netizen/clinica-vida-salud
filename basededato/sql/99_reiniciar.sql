-- =====================================================================
--  Borra TODO el contenido de la base para volver a instalarla.
--  Uso: psql -U postgres -d clinica_vida_salud -f 99_reiniciar.sql
--       y luego instalar.sql
-- =====================================================================
SET client_encoding = 'UTF8';
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public TO public;
