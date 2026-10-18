-- =====================================================================
--  PRUEBAS DE LA BASE DE DATOS (restricciones y triggers)
--  Verifica que PostgreSQL rechace los datos inválidos aunque alguien
--  intente saltarse la aplicación PHP. No deja cambios: todo se revierte.
--  Uso: psql -U postgres -d clinica_vida_salud -f basededato/sql/pruebas/pruebas_bd.sql
-- =====================================================================
SET client_encoding = 'UTF8';
\set ON_ERROR_STOP on
SET client_min_messages TO notice;
BEGIN;

CREATE TEMP TABLE resultado (n INT, prueba TEXT, ok BOOLEAN, detalle TEXT);

-- Ejecuta una sentencia que DEBE fallar y registra el mensaje obtenido.
CREATE OR REPLACE FUNCTION pg_temp.debe_fallar(p_n INT, p_prueba TEXT, p_sql TEXT)
RETURNS VOID LANGUAGE plpgsql AS $$
BEGIN
    BEGIN
        EXECUTE p_sql;
        INSERT INTO resultado VALUES (p_n, p_prueba, FALSE, 'La BD aceptó el dato inválido');
    EXCEPTION WHEN OTHERS THEN
        INSERT INTO resultado VALUES (p_n, p_prueba, TRUE, SQLERRM);
    END;
END $$;

-- Datos de apoyo: una cita futura en un horario válido del Dr. Juan Pérez (MED-001).
CREATE TEMP TABLE apoyo AS
SELECT d::DATE AS fecha
  FROM generate_series(CURRENT_DATE + 1, CURRENT_DATE + 14, INTERVAL '1 day') d
 WHERE EXTRACT(ISODOW FROM d) = 1                      -- un lunes (atiende 08:00-12:00)
   AND NOT EXISTS (SELECT 1 FROM dia_no_laborable n WHERE n.fecha = d::DATE)
 LIMIT 1;

-- Se liberan las 11:00 y 11:30 de ese lunes y se crea la cita base.
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Prueba'
 WHERE id_medico = 1 AND fecha = (SELECT fecha FROM apoyo) AND hora_inicio IN ('11:00','11:30') AND estado <> 'cancelada';
INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, motivo)
SELECT 3, 1, 1, fecha, '11:30', '12:00', 'Cita base de la prueba' FROM apoyo;

SELECT pg_temp.debe_fallar(1, 'CI duplicado (UNIQUE uq_paciente_ci)',
  $q$INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono)
     VALUES ('6234567','Copia','Duplicada','1990-01-01','femenino','70000000')$q$);

SELECT pg_temp.debe_fallar(2, 'Cita superpuesta del mismo médico (EXCLUDE ex_cita_medico_sin_solape)',
  format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
            VALUES (4, 1, 1, %L, '11:30', '12:00')$q$, (SELECT fecha FROM apoyo)));

SELECT pg_temp.debe_fallar(3, 'Cita fuera del horario de atención (trigger RN05)',
  format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
            VALUES (4, 1, 1, %L, '19:00', '19:30')$q$, (SELECT fecha FROM apoyo)));

SELECT pg_temp.debe_fallar(4, 'Cita en fecha pasada (trigger RN06)',
  $q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
     VALUES (4, 1, 1, CURRENT_DATE - 7, '08:00', '08:30')$q$);

SELECT pg_temp.debe_fallar(5, 'Cita con médico inactivo (trigger RN04)',
  $q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
     SELECT 4, m.id_medico, 2, CURRENT_DATE + 3, '09:00', '09:30' FROM medico m WHERE m.estado = 'inactivo' LIMIT 1$q$);

SELECT pg_temp.debe_fallar(6, 'Médico con especialidad que no atiende (FK compuesta)',
  format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
            VALUES (4, 1, 4, %L, '11:00', '11:30')$q$, (SELECT fecha FROM apoyo)));

SELECT pg_temp.debe_fallar(7, 'Cita cancelada marcada como atendida (trigger RF10)',
  $q$UPDATE cita SET estado = 'atendida' WHERE id_cita = (SELECT id_cita FROM cita WHERE estado = 'cancelada' LIMIT 1)$q$);

SELECT pg_temp.debe_fallar(8, 'Borrado físico de una cita (trigger RN10)',
  $q$DELETE FROM cita WHERE id_cita = (SELECT min(id_cita) FROM cita)$q$);

SELECT pg_temp.debe_fallar(9, 'Especialidad con el mismo nombre normalizado',
  $q$INSERT INTO especialidad (nombre) VALUES ('  PEDIATRIA ')$q$);

SELECT pg_temp.debe_fallar(10, 'Horario con hora final anterior a la inicial (CHECK)',
  $q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (1, 7, '12:00', '08:00')$q$);

SELECT pg_temp.debe_fallar(11, 'Horario superpuesto del mismo médico (EXCLUDE)',
  $q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (1, 1, '10:00', '13:00')$q$);

SELECT pg_temp.debe_fallar(12, 'Eliminar un paciente con citas (FK RESTRICT, RN11)',
  $q$DELETE FROM paciente WHERE id_paciente = 3$q$);

SELECT pg_temp.debe_fallar(13, 'Usuario con rol médico sin vincular a un médico (trigger RN12)',
  $q$INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol)
     SELECT 'medico.sin.vinculo', 'x@x.com', 'x', 'Prueba', id_rol FROM rol WHERE nombre = 'medico'$q$);

-- Prueba positiva: cancelar libera el horario y queda en el historial.
DO $$
DECLARE v_id INT; v_hist INT;
BEGIN
    SELECT id_cita INTO v_id FROM cita WHERE motivo = 'Cita base de la prueba';
    UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Prueba de cancelación' WHERE id_cita = v_id;
    INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
    SELECT 4, 1, 1, fecha, '11:30', '12:00' FROM cita WHERE id_cita = v_id;
    SELECT count(*) INTO v_hist FROM cita_historial WHERE id_cita = v_id;
    INSERT INTO resultado VALUES (14, 'Cancelar libera el horario y conserva el historial (RN09)', v_hist >= 2,
                                  'Horario reutilizado; la cita cancelada tiene ' || v_hist || ' registros de historial');
END $$;

-- Bitácora (CU02): se agrega un registro de apoyo y se intenta alterarlo.
INSERT INTO bitacora (usuario, accion, modulo, detalle, ip)
VALUES ('prueba', 'operacion', 'seguridad', 'Registro de apoyo de la prueba', '127.0.0.1');

SELECT pg_temp.debe_fallar(15, 'Borrar una fila de la bitácora (trigger fn_tg_bitacora_inalterable)',
  $q$DELETE FROM bitacora WHERE detalle = 'Registro de apoyo de la prueba'$q$);

SELECT pg_temp.debe_fallar(16, 'Modificar una fila de la bitácora (trigger fn_tg_bitacora_inalterable)',
  $q$UPDATE bitacora SET detalle = 'alterado' WHERE detalle = 'Registro de apoyo de la prueba'$q$);

\echo ''
\echo '===================== RESULTADOS ====================='
SELECT n AS "N.º", prueba AS "Prueba", CASE WHEN ok THEN 'APROBADA' ELSE 'FALLIDA' END AS "Estado",
       left(detalle, 90) AS "Mensaje de PostgreSQL"
  FROM resultado ORDER BY n;
SELECT count(*) FILTER (WHERE ok) || ' de ' || count(*) || ' pruebas aprobadas' AS "Resumen" FROM resultado;

ROLLBACK;
