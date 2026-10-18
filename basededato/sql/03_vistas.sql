-- =====================================================================
--  Archivo : 03_vistas.sql
--  Propósito: vistas de consulta usadas por la agenda (CU13), el
--             historial (CU05) y los reportes (CU14).
-- =====================================================================

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
       p.codigo                               AS codigo_paciente,
       p.ci                                   AS ci_paciente,
       p.nombres || ' ' || p.apellidos        AS paciente,
       p.telefono                             AS telefono_paciente,
       c.id_medico,
       m.codigo                               AS codigo_medico,
       'Dr(a). ' || m.nombres || ' ' || m.apellidos AS medico,
       c.id_especialidad,
       e.nombre                               AS especialidad,
       u.nombre_completo                      AS registrado_por
  FROM cita c
  JOIN paciente     p ON p.id_paciente     = c.id_paciente
  JOIN medico       m ON m.id_medico       = c.id_medico
  JOIN especialidad e ON e.id_especialidad = c.id_especialidad
  LEFT JOIN usuario u ON u.id_usuario      = c.id_usuario_registro;

-- Médico con la lista de sus especialidades en una sola columna.
CREATE OR REPLACE VIEW v_medico_especialidades AS
SELECT m.id_medico,
       m.codigo,
       m.registro_profesional,
       m.nombres,
       m.apellidos,
       m.telefono,
       m.correo,
       m.estado,
       COALESCE(string_agg(e.nombre, ', ' ORDER BY e.nombre), '') AS especialidades
  FROM medico m
  LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
  LEFT JOIN especialidad e         ON e.id_especialidad = me.id_especialidad
 GROUP BY m.id_medico;

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
