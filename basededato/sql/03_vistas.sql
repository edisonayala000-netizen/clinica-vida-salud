-- =====================================================================
--  Archivo : 03_vistas.sql
--  Propósito: vistas de consulta. Las tres primeras unen cada subtipo con
--             su PERSONA, así la aplicación lee un paciente, un médico o
--             un usuario completo en una sola fila. Las demás las usan la
--             agenda (CU13), el historial (CU05), los reportes (CU14) y
--             la atención médica.
-- =====================================================================

-- Paciente con sus datos de persona.
CREATE OR REPLACE VIEW v_paciente AS
SELECT pa.id_paciente,
       pa.codigo,
       pe.ci,
       pe.nombres,
       pe.apellidos,
       pe.fecha_nacimiento,
       pe.sexo,
       pe.telefono,
       pe.correo,
       pe.direccion,
       pa.tipo_sangre,
       pa.alergias,
       pa.enfermedades_cronicas,
       pa.medicamentos,
       pa.antecedentes,
       pa.recordatorio_cita,
       pa.recordar_medicamentos,
       pa.compartir_info_medica,
       pa.estado,
       pa.creado_en,
       greatest(pa.actualizado_en, pe.actualizado_en) AS actualizado_en
  FROM paciente pa
  JOIN persona  pe ON pe.id_persona = pa.id_paciente;

-- Médico con sus datos de persona.
CREATE OR REPLACE VIEW v_medico AS
SELECT m.id_medico,
       m.codigo,
       m.registro_profesional,
       pe.ci,
       pe.nombres,
       pe.apellidos,
       pe.sexo,
       pe.telefono,
       pe.correo,
       m.estado,
       m.creado_en,
       greatest(m.actualizado_en, pe.actualizado_en) AS actualizado_en
  FROM medico  m
  JOIN persona pe ON pe.id_persona = m.id_medico;

-- Usuario con su rol y el nombre de su persona. id_medico / id_paciente
-- indican a qué médico o paciente pertenece la cuenta según su rol.
CREATE OR REPLACE VIEW v_usuario AS
SELECT u.id_usuario,
       u.id_persona,
       u.id_rol,
       r.nombre                                         AS rol,
       u.nombre_usuario,
       u.correo,
       u.password_hash,
       u.estado,
       u.intentos_fallidos,
       u.bloqueado_hasta,
       u.ultimo_acceso,
       u.creado_en,
       u.actualizado_en,
       pe.nombres,
       pe.apellidos,
       pe.ci,
       pe.telefono,
       CASE WHEN r.nombre = 'medico' THEN 'Dr(a). ' ELSE '' END
           || pe.nombres || ' ' || pe.apellidos         AS nombre_completo,
       CASE WHEN r.nombre = 'medico'   THEN u.id_persona END AS id_medico,
       CASE WHEN r.nombre = 'paciente' THEN u.id_persona END AS id_paciente
  FROM usuario u
  JOIN rol     r  ON r.id_rol      = u.id_rol
  JOIN persona pe ON pe.id_persona = u.id_persona;

-- Cita con todos los datos legibles (paciente, médico, especialidad).
CREATE OR REPLACE VIEW v_cita_detalle AS
SELECT c.id_cita,
       c.codigo,
       c.fecha,
       c.hora_inicio,
       c.hora_fin,
       c.modalidad,
       c.motivo,
       c.estado,
       c.motivo_cancelacion,
       c.fecha_registro,
       c.id_paciente,
       pa.codigo                               AS codigo_paciente,
       pp.ci                                   AS ci_paciente,
       pp.nombres || ' ' || pp.apellidos       AS paciente,
       pp.telefono                             AS telefono_paciente,
       c.id_medico,
       m.codigo                                AS codigo_medico,
       'Dr(a). ' || pm.nombres || ' ' || pm.apellidos AS medico,
       c.id_especialidad,
       e.nombre                                AS especialidad,
       u.nombre_completo                       AS registrado_por
  FROM cita c
  JOIN paciente     pa ON pa.id_paciente     = c.id_paciente
  JOIN persona      pp ON pp.id_persona      = c.id_paciente
  JOIN medico       m  ON m.id_medico        = c.id_medico
  JOIN persona      pm ON pm.id_persona      = c.id_medico
  JOIN especialidad e  ON e.id_especialidad  = c.id_especialidad
  LEFT JOIN v_usuario u ON u.id_usuario      = c.id_usuario_registro;

-- Médico con la lista de sus especialidades en una sola columna.
CREATE OR REPLACE VIEW v_medico_especialidades AS
SELECT m.id_medico,
       m.codigo,
       m.registro_profesional,
       m.ci,
       m.nombres,
       m.apellidos,
       m.telefono,
       m.correo,
       m.estado,
       COALESCE(string_agg(e.nombre, ', ' ORDER BY e.nombre), '') AS especialidades
  FROM v_medico m
  LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
  LEFT JOIN especialidad e         ON e.id_especialidad = me.id_especialidad
 GROUP BY m.id_medico, m.codigo, m.registro_profesional, m.ci, m.nombres, m.apellidos,
          m.telefono, m.correo, m.estado;

-- Resumen de citas por fecha y estado (base de los reportes).
CREATE OR REPLACE VIEW v_resumen_citas_dia AS
SELECT fecha,
       count(*)                                         AS total,
       count(*) FILTER (WHERE estado = 'programada')    AS programadas,
       count(*) FILTER (WHERE estado = 'confirmada')    AS confirmadas,
       count(*) FILTER (WHERE estado = 'atendida')      AS atendidas,
       count(*) FILTER (WHERE estado = 'cancelada')     AS canceladas,
       count(*) FILTER (WHERE estado = 'inasistencia')  AS inasistencias
  FROM cita
 GROUP BY fecha;

-- Signos vitales con el IMC calculado (no se guarda: 3FN).
CREATE OR REPLACE VIEW v_signo_vital AS
SELECT s.*,
       CASE WHEN s.presion_sistolica IS NOT NULL
            THEN s.presion_sistolica || '/' || s.presion_diastolica END            AS presion_arterial,
       CASE WHEN s.peso_kg IS NOT NULL AND s.talla_cm IS NOT NULL
            THEN round(s.peso_kg / ((s.talla_cm / 100.0) ^ 2), 1) END             AS imc
  FROM signo_vital s;

-- Cada medicamento recetado con los datos de la receta, la cita y el paciente.
CREATE OR REPLACE VIEW v_receta_detalle AS
SELECT d.id_detalle,
       r.id_receta,
       r.codigo                                     AS codigo_receta,
       r.fecha_emision,
       r.indicaciones                               AS indicaciones_generales,
       r.enviada_en,
       a.id_atencion,
       a.diagnostico,
       c.id_cita,
       c.codigo                                     AS codigo_cita,
       c.id_paciente,
       pp.nombres || ' ' || pp.apellidos            AS paciente,
       pp.correo                                    AS correo_paciente,
       c.id_medico,
       'Dr(a). ' || pm.nombres || ' ' || pm.apellidos AS medico,
       m.id_medicamento,
       m.nombre                                     AS medicamento,
       m.presentacion,
       m.concentracion,
       d.dosis,
       d.via,
       d.frecuencia_horas,
       d.duracion_dias,
       d.duracion_dias * 24 / d.frecuencia_horas    AS total_tomas,
       d.fecha_inicio,
       d.hora_primera_toma,
       d.indicaciones,
       d.recordar
  FROM receta_detalle d
  JOIN receta      r  ON r.id_receta      = d.id_receta
  JOIN atencion    a  ON a.id_atencion    = r.id_atencion
  JOIN cita        c  ON c.id_cita        = a.id_cita
  JOIN persona     pp ON pp.id_persona    = c.id_paciente
  JOIN persona     pm ON pm.id_persona    = c.id_medico
  JOIN medicamento m  ON m.id_medicamento = d.id_medicamento;
