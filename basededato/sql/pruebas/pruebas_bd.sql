-- =====================================================================
--  PRUEBAS DE LA BASE DE DATOS (restricciones y triggers)
--  Verifica que PostgreSQL rechace los datos inválidos aunque alguien
--  intente saltarse la aplicación PHP, y que los triggers automáticos
--  (historial, auditoría, recordatorios) funcionen.
--  No deja cambios: todo se revierte al final.
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

-- Registros de apoyo (se buscan por código o CI: no dependen de los ids).
CREATE TEMP TABLE ref AS
SELECT (SELECT id_medico FROM medico WHERE codigo = 'MED-001')                         AS med,      -- Dr. Juan Pérez
       (SELECT id_persona FROM persona WHERE ci = '8456789')                           AS pac_a,    -- Lucía Fernández (con correo)
       (SELECT id_persona FROM persona WHERE ci = '5567890')                           AS pac_b,    -- Pedro Quiroga
       (SELECT id_especialidad FROM especialidad WHERE nombre = 'Medicina general')    AS esp_mg,
       (SELECT id_especialidad FROM especialidad WHERE nombre = 'Odontología')         AS esp_odo,
       (SELECT id_persona FROM usuario WHERE nombre_usuario = 'admin')                 AS persona_admin,
       (SELECT d::DATE FROM generate_series(CURRENT_DATE + 1, CURRENT_DATE + 14, INTERVAL '1 day') d
         WHERE EXTRACT(ISODOW FROM d) = 1                     -- un lunes (atiende 08:00-12:00)
           AND NOT EXISTS (SELECT 1 FROM dia_no_laborable n WHERE n.fecha = d::DATE)
         LIMIT 1)                                                                      AS fecha;

-- Se liberan las 11:00 y 11:30 de ese lunes (médico y pacientes de apoyo) y se crea la cita base.
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Prueba'
 WHERE fecha = (SELECT fecha FROM ref) AND hora_inicio >= '11:00' AND hora_inicio < '12:00' AND estado <> 'cancelada'
   AND (id_medico = (SELECT med FROM ref) OR id_paciente IN ((SELECT pac_a FROM ref), (SELECT pac_b FROM ref)));
INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, motivo)
SELECT pac_a, med, esp_mg, fecha, '11:30', '12:00', 'Cita base de la prueba' FROM ref;

SELECT pg_temp.debe_fallar(1, 'CI duplicado (UNIQUE uq_persona_ci)',
  $q$INSERT INTO persona (ci, nombres, apellidos) VALUES ('6234567','Copia','Duplicada')$q$);

SELECT pg_temp.debe_fallar(2, 'Cita superpuesta del mismo médico (EXCLUDE ex_cita_medico_sin_solape)',
  (SELECT format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
                    VALUES (%s, %s, %s, %L, '11:30', '12:00')$q$, pac_b, med, esp_mg, fecha) FROM ref));

SELECT pg_temp.debe_fallar(3, 'Cita fuera del horario de atención (trigger RN05)',
  (SELECT format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
                    VALUES (%s, %s, %s, %L, '19:00', '19:30')$q$, pac_b, med, esp_mg, fecha) FROM ref));

SELECT pg_temp.debe_fallar(4, 'Cita en fecha pasada (trigger RN06)',
  (SELECT format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
                    VALUES (%s, %s, %s, CURRENT_DATE - 7, '08:00', '08:30')$q$, pac_b, med, esp_mg) FROM ref));

SELECT pg_temp.debe_fallar(5, 'Cita con médico inactivo (trigger RN04)',
  (SELECT format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
                    SELECT %s, m.id_medico, me.id_especialidad, CURRENT_DATE + 3, '09:00', '09:30'
                      FROM medico m JOIN medico_especialidad me ON me.id_medico = m.id_medico
                     WHERE m.estado = 'inactivo' LIMIT 1$q$, pac_b) FROM ref));

SELECT pg_temp.debe_fallar(6, 'Médico con especialidad que no atiende (FK compuesta)',
  (SELECT format($q$INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
                    VALUES (%s, %s, %s, %L, '11:00', '11:30')$q$, pac_b, med, esp_odo, fecha) FROM ref));

SELECT pg_temp.debe_fallar(7, 'Cita cancelada marcada como atendida (catálogo estado_cita_transicion)',
  $q$UPDATE cita SET estado = 'atendida' WHERE id_cita = (SELECT id_cita FROM cita WHERE estado = 'cancelada' LIMIT 1)$q$);

SELECT pg_temp.debe_fallar(8, 'Borrado físico de una cita (trigger RN10)',
  $q$DELETE FROM cita WHERE id_cita = (SELECT min(id_cita) FROM cita)$q$);

SELECT pg_temp.debe_fallar(9, 'Especialidad con el mismo nombre normalizado',
  $q$INSERT INTO especialidad (nombre) VALUES ('  PEDIATRIA ')$q$);

SELECT pg_temp.debe_fallar(10, 'Horario con hora final anterior a la inicial (CHECK)',
  (SELECT format($q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (%s, 7, '12:00', '08:00')$q$, med) FROM ref));

SELECT pg_temp.debe_fallar(11, 'Horario superpuesto del mismo médico (EXCLUDE)',
  (SELECT format($q$INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin) VALUES (%s, 1, '10:00', '13:00')$q$, med) FROM ref));

SELECT pg_temp.debe_fallar(12, 'Eliminar un paciente con citas (FK RESTRICT, RN11)',
  (SELECT format($q$DELETE FROM paciente WHERE id_paciente = %s$q$, pac_a) FROM ref));

SELECT pg_temp.debe_fallar(13, 'Usuario con rol médico que no es médico (trigger RN12)',
  (SELECT format($q$UPDATE usuario SET id_rol = (SELECT id_rol FROM rol WHERE nombre = 'medico')
                    WHERE id_persona = %s$q$, persona_admin) FROM ref));

-- Prueba positiva: cancelar libera el horario y queda en el historial.
DO $$
DECLARE v_id INT; v_hist INT;
BEGIN
    SELECT id_cita INTO v_id FROM cita WHERE motivo = 'Cita base de la prueba';
    UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'Prueba de cancelación' WHERE id_cita = v_id;
    INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin)
    SELECT r.pac_b, c.id_medico, c.id_especialidad, c.fecha, '11:30', '12:00' FROM cita c, ref r WHERE c.id_cita = v_id;
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

-- ---------------------------------------------------------------------
-- Pruebas nuevas: persona, catálogo de estados, auditoría, token,
-- atención médica y recordatorios por correo.
-- ---------------------------------------------------------------------
SELECT pg_temp.debe_fallar(17, 'Paciente sin fecha de nacimiento ni sexo (trigger fn_validar_datos_persona)',
  $q$WITH p AS (INSERT INTO persona (nombres, apellidos, telefono) VALUES ('Sin','Datos','70000009') RETURNING id_persona)
     INSERT INTO paciente (id_paciente) SELECT id_persona FROM p$q$);

SELECT pg_temp.debe_fallar(18, 'Dos médicos con el mismo correo (trigger fn_validar_datos_persona)',
  $q$WITH p AS (INSERT INTO persona (nombres, apellidos, telefono, correo)
                VALUES ('Otro','Médico','70000010','juan.perez@vidasalud.bo') RETURNING id_persona)
     INSERT INTO medico (id_medico, registro_profesional) SELECT id_persona, 'MP-99999' FROM p$q$);

SELECT pg_temp.debe_fallar(19, 'Historial con un estado que no existe en el catálogo (FK estado_cita)',
  $q$INSERT INTO cita_historial (id_cita, accion, estado_anterior, estado_nuevo)
     VALUES ((SELECT max(id_cita) FROM cita), 'cambio_estado', 'programada', 'perdida')$q$);

-- Prueba positiva: la auditoría guarda lo de antes y lo de después, y nunca la contraseña.
DO $$
DECLARE v_antes TEXT; v_ok BOOLEAN; v_b RECORD; v_u RECORD;
BEGIN
    PERFORM set_config('app.id_usuario', (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'recepcion')::TEXT, TRUE);
    SELECT telefono INTO v_antes FROM persona WHERE id_persona = (SELECT pac_a FROM ref);
    UPDATE persona SET telefono = '79999999' WHERE id_persona = (SELECT pac_a FROM ref);
    SELECT * INTO v_b FROM bitacora
     WHERE tabla = 'persona' AND operacion = 'UPDATE' AND id_registro = (SELECT pac_a FROM ref)::TEXT
     ORDER BY id_bitacora DESC LIMIT 1;
    UPDATE usuario SET password_hash = 'otro-hash', correo = 'recepcion2.nuevo@vidasalud.bo' WHERE nombre_usuario = 'recepcion2';
    SELECT * INTO v_u FROM bitacora WHERE tabla = 'usuario' AND operacion = 'UPDATE' ORDER BY id_bitacora DESC LIMIT 1;
    v_ok := v_b.datos_antes ->> 'telefono' = v_antes AND v_b.datos_despues ->> 'telefono' = '79999999'
            AND v_b.usuario = 'recepcion'
            AND v_u.datos_despues ? 'correo' AND NOT (v_u.datos_despues ? 'password_hash');
    INSERT INTO resultado VALUES (20, 'Auditoría: guarda antes/después y omite la contraseña (fn_tg_auditoria)', COALESCE(v_ok, FALSE),
           format('%s · antes %s → después %s', v_b.detalle, v_b.datos_antes, v_b.datos_despues));
END $$;

-- Token de recuperación: se usa una sola vez.
INSERT INTO token_recuperacion (id_usuario, token_hash)
SELECT id_usuario, repeat('a', 64) FROM usuario WHERE nombre_usuario = 'admin';
UPDATE token_recuperacion SET usado_en = now() WHERE token_hash = repeat('a', 64);
SELECT pg_temp.debe_fallar(21, 'Usar dos veces el mismo enlace de recuperación (trigger fn_tg_token_un_uso)',
  $q$UPDATE token_recuperacion SET usado_en = now() WHERE token_hash = repeat('a', 64)$q$);

SELECT pg_temp.debe_fallar(22, 'Registrar la atención de una cita no atendida (trigger RF12)',
  $q$INSERT INTO atencion (id_cita, diagnostico)
     SELECT id_cita, 'Diagnóstico de prueba' FROM cita WHERE estado = 'programada' LIMIT 1$q$);

SELECT pg_temp.debe_fallar(23, 'Presión sistólica menor que la diastólica (CHECK ck_signo_presion)',
  $q$INSERT INTO signo_vital (id_atencion, presion_sistolica, presion_diastolica)
     VALUES ((SELECT min(id_atencion) FROM atencion), 70, 90)$q$);

-- Prueba positiva: una receta programa un correo por cada toma futura.
DO $$
DECLARE v_cita INT; v_atencion INT; v_receta INT; v_detalle INT; v_tomas INT;
BEGIN
    SELECT c.id_cita INTO v_cita
      FROM cita c JOIN paciente pa ON pa.id_paciente = c.id_paciente JOIN persona pe ON pe.id_persona = c.id_paciente
     WHERE c.estado = 'atendida' AND pe.correo IS NOT NULL AND pa.recordar_medicamentos
       AND NOT EXISTS (SELECT 1 FROM atencion a WHERE a.id_cita = c.id_cita)
     LIMIT 1;
    INSERT INTO atencion (id_cita, diagnostico) VALUES (v_cita, 'Diagnóstico de prueba') RETURNING id_atencion INTO v_atencion;
    INSERT INTO receta (id_atencion) VALUES (v_atencion) RETURNING id_receta INTO v_receta;
    INSERT INTO receta_detalle (id_receta, id_medicamento, dosis, frecuencia_horas, duracion_dias, fecha_inicio, hora_primera_toma)
    SELECT v_receta, id_medicamento, '1 tableta', 8, 2, CURRENT_DATE + 1, '08:00'
      FROM medicamento WHERE nombre = 'Paracetamol' AND presentacion = 'tableta'
    RETURNING id_detalle INTO v_detalle;
    SELECT count(*) INTO v_tomas FROM notificacion
     WHERE id_detalle = v_detalle AND tipo = 'toma_medicamento' AND estado = 'pendiente';
    INSERT INTO resultado VALUES (24, 'Receta cada 8 h por 2 días programa 6 recordatorios (trigger fn_tg_programar_tomas)',
                                  v_tomas = 6, v_tomas || ' correos de toma programados');
END $$;

-- Prueba positiva: cancelar la cita base anuló su recordatorio por correo.
INSERT INTO resultado
SELECT 25, 'Cancelar una cita anula su recordatorio (trigger fn_tg_recordatorio_cita)',
       count(*) FILTER (WHERE n.estado = 'anulada') = 1 AND count(*) FILTER (WHERE n.estado = 'pendiente') = 0,
       count(*) || ' recordatorio(s): ' || COALESCE(string_agg(n.estado, ', '), 'ninguno')
  FROM notificacion n
 WHERE n.tipo = 'recordatorio_cita'
   AND n.id_cita = (SELECT id_cita FROM cita WHERE motivo = 'Cita base de la prueba');

-- Prueba positiva: la preferencia del paciente decide qué recordatorios de cita se programan.
DO $$
DECLARE v_cita INT; v_ambos TEXT; v_solo1h TEXT; v_ninguno INT;
BEGIN
    UPDATE paciente SET recordatorio_cita = '24h_1h' WHERE id_paciente = (SELECT pac_a FROM ref);
    INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, motivo)
    SELECT pac_a, med, esp_mg, fecha, '11:00', '11:30', 'Cita de la prueba de preferencias' FROM ref
    RETURNING id_cita INTO v_cita;
    SELECT string_agg(tipo, ', ' ORDER BY tipo) INTO v_ambos FROM notificacion WHERE id_cita = v_cita AND estado = 'pendiente';
    UPDATE paciente SET recordatorio_cita = '1h' WHERE id_paciente = (SELECT pac_a FROM ref);
    SELECT string_agg(tipo, ', ' ORDER BY tipo) INTO v_solo1h FROM notificacion WHERE id_cita = v_cita AND estado = 'pendiente';
    UPDATE paciente SET recordatorio_cita = 'ninguno' WHERE id_paciente = (SELECT pac_a FROM ref);
    SELECT count(*) INTO v_ninguno FROM notificacion WHERE id_cita = v_cita AND estado = 'pendiente';
    INSERT INTO resultado VALUES (26, 'Preferencia de recordatorio de citas: 24h y 1h → solo 1h → ninguno (fn_tg_preferencia_recordatorios)',
        v_ambos = 'recordatorio_cita, recordatorio_cita_1h' AND v_solo1h = 'recordatorio_cita_1h' AND v_ninguno = 0,
        format('24h_1h: %s · 1h: %s · ninguno: %s pendientes', v_ambos, v_solo1h, v_ninguno));
END $$;

-- Prueba positiva: desactivar los recordatorios de medicamentos anula las tomas; reactivarlos las vuelve a programar.
DO $$
DECLARE v_detalle INT; v_paciente INT; v_antes INT; v_apagado INT; v_encendido INT;
BEGIN
    SELECT n.id_detalle, n.id_persona INTO v_detalle, v_paciente FROM notificacion n
     WHERE n.tipo = 'toma_medicamento' AND n.estado = 'pendiente' ORDER BY n.id_notificacion DESC LIMIT 1;
    SELECT count(*) INTO v_antes FROM notificacion WHERE id_detalle = v_detalle AND estado = 'pendiente';
    UPDATE paciente SET recordar_medicamentos = FALSE WHERE id_paciente = v_paciente;
    SELECT count(*) INTO v_apagado FROM notificacion WHERE id_detalle = v_detalle AND estado = 'pendiente';
    UPDATE paciente SET recordar_medicamentos = TRUE WHERE id_paciente = v_paciente;
    SELECT count(*) INTO v_encendido FROM notificacion WHERE id_detalle = v_detalle AND estado = 'pendiente';
    INSERT INTO resultado VALUES (27, 'Desactivar y reactivar los recordatorios de medicamentos (fn_programar_tomas)',
        v_antes > 0 AND v_apagado = 0 AND v_encendido = v_antes,
        format('Tomas pendientes: %s → al desactivar %s → al reactivar %s', v_antes, v_apagado, v_encendido));
END $$;

\echo ''
\echo '===================== RESULTADOS ====================='
SELECT n AS "N.º", prueba AS "Prueba", CASE WHEN ok THEN 'APROBADA' ELSE 'FALLIDA' END AS "Estado",
       left(detalle, 90) AS "Mensaje de PostgreSQL"
  FROM resultado ORDER BY n;
SELECT count(*) FILTER (WHERE ok) || ' de ' || count(*) || ' pruebas aprobadas' AS "Resumen" FROM resultado;

ROLLBACK;
