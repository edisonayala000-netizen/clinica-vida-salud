-- =====================================================================
--  Archivo : 05_datos_prueba.sql   (OPCIONAL)
--  Propósito: poblar la base con médicos, horarios, pacientes, usuarios
--             de demostración, citas de las últimas semanas y de las
--             próximas, y dos atenciones con signos vitales y receta,
--             para probar la agenda, el historial y los reportes.
--  Las fechas de las citas se calculan desde la fecha actual, así la
--  demostración siempre tiene citas pasadas y futuras.
--  Los correos de los pacientes usan el dominio reservado example.com
--  (no existe), para que el sistema nunca escriba a personas reales.
--  Contraseña de todos los usuarios de prueba: Clinica2026*
-- =====================================================================
SET timezone TO 'America/La_Paz';
-- La carga masiva no se audita ni programa correos.
SET app.sin_auditoria = 'on';
SET app.sin_notificaciones = 'on';

-- ---------------------------------------------------------------------
-- Médicos (RF03): primero la persona y luego su subtipo médico.
-- ---------------------------------------------------------------------
INSERT INTO persona (ci, nombres, apellidos, sexo, telefono, correo) VALUES
 ('3112233', 'Juan',          'Pérez Rojas',       'masculino', '70012345', 'juan.perez@vidasalud.bo'),
 ('3223344', 'Ana María',     'Gutiérrez Vaca',    'femenino',  '70123456', 'ana.gutierrez@vidasalud.bo'),
 ('3334455', 'Carla',         'Suárez Montaño',    'femenino',  '71234567', 'carla.suarez@vidasalud.bo'),
 ('3445566', 'Luis Fernando', 'Arteaga Justiniano','masculino', '72345678', 'luis.arteaga@vidasalud.bo'),
 ('3556677', 'Roberto',       'Mendoza Salazar',   'masculino', '73456789', 'roberto.mendoza@vidasalud.bo'),
 ('3667788', 'Patricia',      'Rojas Antelo',      'femenino',  '74567890', 'patricia.rojas@vidasalud.bo'),
 ('2778899', 'Jorge',         'Chávez Ribera',     'masculino', '75678901', 'jorge.chavez@vidasalud.bo');

INSERT INTO medico (id_medico, registro_profesional)
SELECT pe.id_persona, x.reg
  FROM (VALUES (1,'3112233','MP-10234'), (2,'3223344','MP-11872'), (3,'3334455','MP-12011'),
               (4,'3445566','MP-13550'), (5,'3556677','MP-14098'), (6,'3667788','MP-15421'),
               (7,'2778899','MP-09117')) AS x(n, ci, reg)
  JOIN persona pe ON pe.ci = x.ci
 ORDER BY x.n;

INSERT INTO medico_especialidad (id_medico, id_especialidad)
SELECT m.id_medico, e.id_especialidad
  FROM (VALUES ('MP-10234','Medicina general'),
               ('MP-11872','Pediatría'),
               ('MP-12011','Ginecología'),
               ('MP-13550','Odontología'),
               ('MP-14098','Cardiología'),
               ('MP-14098','Medicina general'),
               ('MP-15421','Medicina general'),
               ('MP-09117','Pediatría')) AS x(reg, esp)
  JOIN medico m       ON m.registro_profesional = x.reg
  JOIN especialidad e ON e.nombre = x.esp;

-- ---------------------------------------------------------------------
-- Horarios de atención (RF05). 1 = lunes ... 7 = domingo
-- ---------------------------------------------------------------------
INSERT INTO horario_atencion (id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita)
SELECT m.id_medico, x.dia, x.ini::TIME, x.fin::TIME, x.dur
  FROM (VALUES
        ('MP-10234',1,'08:00','12:00',30), ('MP-10234',2,'08:00','12:00',30),
        ('MP-10234',3,'14:00','18:00',30), ('MP-10234',4,'08:00','12:00',30),
        ('MP-10234',5,'08:00','12:00',30), ('MP-10234',6,'08:00','12:00',30),
        ('MP-11872',1,'09:00','13:00',30), ('MP-11872',3,'09:00','13:00',30),
        ('MP-11872',5,'09:00','13:00',30), ('MP-11872',6,'09:00','12:00',30),
        ('MP-12011',2,'14:00','18:00',30), ('MP-12011',4,'14:00','18:00',30),
        ('MP-12011',6,'08:00','12:00',30),
        ('MP-13550',1,'15:00','19:00',45), ('MP-13550',2,'15:00','19:00',45),
        ('MP-13550',3,'15:00','19:00',45), ('MP-13550',4,'15:00','19:00',45),
        ('MP-13550',5,'15:00','19:00',45),
        ('MP-14098',2,'08:00','12:00',30), ('MP-14098',4,'08:00','12:00',30),
        ('MP-14098',6,'09:00','12:00',30),
        ('MP-15421',1,'14:00','18:00',20), ('MP-15421',2,'14:00','18:00',20),
        ('MP-15421',3,'08:00','12:00',20), ('MP-15421',4,'14:00','18:00',20),
        ('MP-15421',5,'14:00','18:00',20), ('MP-15421',6,'14:00','17:00',20),
        ('MP-09117',2,'09:00','12:00',30), ('MP-09117',4,'09:00','12:00',30)
       ) AS x(reg, dia, ini, fin, dur)
  JOIN medico m ON m.registro_profesional = x.reg;

-- Feriados nacionales de Bolivia (bloqueos de agenda para toda la clínica)
INSERT INTO dia_no_laborable (fecha, descripcion) VALUES
 (DATE '2026-11-02', 'Día de Todos los Santos'),
 (DATE '2026-12-25', 'Navidad'),
 (DATE '2027-01-01', 'Año Nuevo');

-- ---------------------------------------------------------------------
-- Pacientes (RF02): datos personales en PERSONA y médicos en PACIENTE.
-- ---------------------------------------------------------------------
INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion) VALUES
 ('6234567', 'María',     'López Rocha',        '1992-03-14','femenino', '76011223','maria.lopez@example.com',    'Av. Banzer 4to anillo, calle 3 #145'),
 ('7345678', 'Carlos',    'Vargas Méndez',      '1985-07-22','masculino','76122334','carlos.vargas@example.com',  'B/ Equipetrol, calle Los Pinos #12'),
 ('8456789', 'Lucía',     'Fernández Áñez',     '2001-11-05','femenino', '76233445','lucia.fa@example.com',       'Plan 3000, UV 160 Mz 12'),
 ('5567890', 'Pedro',     'Quiroga Salvatierra','1978-01-30','masculino','76344556',NULL,                         'Av. Santos Dumont 2do anillo'),
 ('9678901', 'Valeria',   'Cuéllar Justiniano', '2015-06-18','femenino', '76455667','mama.valeria@example.com',   'B/ Hamacas, calle 5 #30'),
 ('4789012', 'Jorge',     'Mamani Choque',      '1969-09-09','masculino','76566778','jorge.mamani@example.com',   'Villa 1ro de Mayo, calle 16'),
 ('6890123', 'Sofía',     'Ribera Paz',         '1998-12-01','femenino', '76677889','sofia.ribera@example.com',   'Av. Alemana 3er anillo'),
 ('7901234', 'Diego',     'Saavedra Vaca',      '2018-04-25','masculino','76788990','diego.papa@example.com',     'B/ Las Palmas, calle Ceibo #8'),
 ('5012345', 'Gabriela',  'Torrez Arias',       '1988-08-15','femenino', '76899001','gaby.torrez@example.com',    'Av. Cristo Redentor km 5'),
 ('8123456', 'Andrés',    'Montero Peña',       '1995-02-10','masculino','76900112','andres.montero@example.com', 'Calle Warnes #455'),
 ('6345670', 'Rosa',      'Céspedes Lima',      '1955-05-03','femenino', '77011223',NULL,                         'B/ Los Lotes, calle 7'),
 ('7456781', 'Fernando',  'Ortiz Gil',          '1982-10-28','masculino','77122334','fernando.ortiz@example.com', 'Av. Piraí 3er anillo'),
 ('9567892', 'Camila',    'Aguilera Soto',      '2010-01-19','femenino', '77233445','camila.mama@example.com',    'B/ Urbarí, calle Tajibos #20'),
 ('5678903', 'Miguel',    'Rivero Durán',       '1974-03-07','masculino','77344556','miguel.rivero@example.com',  'Av. Roca y Coronado 4to anillo'),
 ('6789014', 'Daniela',   'Pinto Herrera',      '1993-07-30','femenino', '77455667','daniela.pinto@example.com',  'Calle Sucre #210'),
 ('7890125', 'Ricardo',   'Velasco Rua',        '1965-11-11','masculino','77566778',NULL,                         'B/ Pampa de la Isla'),
 ('8901236', 'Natalia',   'Suárez Banegas',     '2003-09-02','femenino', '77677889','natalia.sb@example.com',     'Av. Grigotá 2do anillo'),
 ('9012347', 'Mateo',     'Guzmán Rojas',       '2020-12-12','masculino','77788990','mateo.papa@example.com',     'B/ Sirari, calle Los Gomeros'),
 ('5123458', 'Elena',     'Moreno Vaca',        '1990-04-04','femenino', '77899001','elena.moreno@example.com',   'Av. Busch 3er anillo'),
 ('6234569', 'Hugo',      'Paz Saucedo',        '1959-06-21','masculino','77900112',NULL,                         'Villa Primero de Mayo, UV 74');

INSERT INTO paciente (id_paciente, tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes)
SELECT pe.id_persona, x.sangre, x.alergias, x.cronicas, x.meds, x.antecedentes
  FROM (VALUES
        ( 1,'6234567','O+', 'Penicilina',NULL,                   NULL,                  'Cesárea en 2019'),
        ( 2,'7345678','A+', NULL,        'Hipertensión arterial','Losartán 50 mg',      'Padre con infarto a los 60 años'),
        ( 3,'8456789','B+', NULL,        NULL,                   NULL,                  NULL),
        ( 4,'5567890','O-', 'Sulfas',    'Diabetes tipo 2',      'Metformina 850 mg',   NULL),
        ( 5,'9678901','A-', 'Lactosa',   NULL,                   NULL,                  'Nacimiento prematuro (36 semanas)'),
        ( 6,'4789012','O+', NULL,        'Arritmia',             'Atenolol 25 mg',      NULL),
        ( 7,'6890123','AB+',NULL,        NULL,                   NULL,                  NULL),
        ( 8,'7901234','B-', 'Polen',     NULL,                   NULL,                  NULL),
        ( 9,'5012345','A+', NULL,        'Asma',                 'Salbutamol inhalador',NULL),
        (10,'8123456','O+', NULL,        NULL,                   NULL,                  NULL),
        (11,'6345670','O+', 'Ibuprofeno','Artrosis',             'Paracetamol 500 mg',  'Cirugía de cadera 2021'),
        (12,'7456781','A+', NULL,        NULL,                   NULL,                  NULL),
        (13,'9567892','B+', NULL,        NULL,                   NULL,                  NULL),
        (14,'5678903','O-', NULL,        'Colesterol alto',      'Atorvastatina 20 mg', NULL),
        (15,'6789014','A-', NULL,        NULL,                   NULL,                  'Embarazo en curso'),
        (16,'7890125','O+', NULL,        'Hipertensión arterial','Enalapril 10 mg',     NULL),
        (17,'8901236','B+', NULL,        NULL,                   NULL,                  NULL),
        (18,'9012347',NULL, NULL,        NULL,                   NULL,                  NULL),
        (19,'5123458','AB-',NULL,        NULL,                   NULL,                  NULL),
        (20,'6234569','A+', NULL,        'EPOC',                 'Tiotropio',           NULL)
       ) AS x(n, ci, sangre, alergias, cronicas, meds, antecedentes)
  JOIN persona pe ON pe.ci = x.ci
 ORDER BY x.n;

-- Un paciente desactivado: conserva su historial pero no recibe citas nuevas.
UPDATE paciente SET estado = 'inactivo'
 WHERE id_paciente = (SELECT id_persona FROM persona WHERE ci = '6234569');

INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono)
SELECT pe.id_persona, x.nombre, x.par, x.tel
  FROM (VALUES ('6234567','José López Rocha','Hermano','70911223'),
               ('7345678','Mónica Ríos de Vargas','Esposa','70922334'),
               ('9678901','Andrea Justiniano','Madre','70933445'),
               ('7901234','Raúl Saavedra','Padre','70944556'),
               ('6345670','Carmen Céspedes','Hija','70955667'),
               ('9567892','Lorena Soto','Madre','70966778'),
               ('9012347','Iván Guzmán','Padre','70977889')) AS x(ci, nombre, par, tel)
  JOIN persona pe ON pe.ci = x.ci;

-- ---------------------------------------------------------------------
-- Personal de recepción (personas sin subtipo médico ni paciente)
-- ---------------------------------------------------------------------
INSERT INTO persona (ci, nombres, apellidos, sexo, telefono, correo) VALUES
 ('4011223', 'Lucía',   'Fernández Vaca', 'femenino',  '70500001', 'recepcion@vidasalud.bo'),
 ('4022334', 'Marcelo', 'Antelo Suárez',  'masculino', '70500002', 'recepcion2@vidasalud.bo');

-- ---------------------------------------------------------------------
-- Usuarios de demostración (contraseña: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (id_persona, id_rol, nombre_usuario, correo, password_hash)
SELECT pe.id_persona, r.id_rol, x.usr, x.mail, '$2y$10$cqNvBIdFanPUtolihUHZueCzSMDyi2Y7L5tj1/C8WA9VqsJTSGlNS'
  FROM (VALUES
        (1, 'recepcion',      'recepcion@vidasalud.bo',       '4011223', 'recepcionista'),
        (2, 'recepcion2',     'recepcion2@vidasalud.bo',      '4022334', 'recepcionista'),
        (3, 'dr.perez',       'juan.perez@vidasalud.bo',      '3112233', 'medico'),
        (4, 'dra.gutierrez',  'ana.gutierrez@vidasalud.bo',   '3223344', 'medico'),
        (5, 'dra.suarez',     'carla.suarez@vidasalud.bo',    '3334455', 'medico'),
        (6, 'dr.mendoza',     'roberto.mendoza@vidasalud.bo', '3556677', 'medico'),
        (7, 'maria.lopez',    'maria.lopez@example.com',      '6234567', 'paciente'),
        (8, 'carlos.vargas',  'carlos.vargas@example.com',    '7345678', 'paciente')
       ) AS x(n, usr, mail, ci, rol)
  JOIN persona pe ON pe.ci = x.ci
  JOIN rol r      ON r.nombre = x.rol
 ORDER BY x.n;

-- ---------------------------------------------------------------------
-- Citas de demostración: desde hace 6 semanas hasta dentro de 3 semanas.
-- Para cargar citas PASADAS (atendidas, canceladas, inasistencias) se
-- desactiva temporalmente la validación de agenda; en el uso normal del
-- sistema esa validación siempre está activa.
-- ---------------------------------------------------------------------
ALTER TABLE cita DISABLE TRIGGER tg_cita_validar;

DO $$
DECLARE
    v_dia        DATE;
    v_h          RECORD;
    v_slot       TIME;
    v_paciente   INTEGER;
    v_esp        INTEGER;
    v_estado     VARCHAR(15);
    v_motivo     VARCHAR(250);
    v_recep      INTEGER := (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'recepcion');
    v_pacientes  INTEGER[] := ARRAY(SELECT id_paciente FROM paciente ORDER BY id_paciente);
    v_motivos    TEXT[];
    v_esp_nombre TEXT;
    v_r          DOUBLE PRECISION;
BEGIN
    PERFORM setseed(0.42);
    FOR v_dia IN SELECT d::DATE FROM generate_series(CURRENT_DATE - 42, CURRENT_DATE + 21, INTERVAL '1 day') d LOOP
        CONTINUE WHEN EXISTS (SELECT 1 FROM dia_no_laborable WHERE fecha = v_dia);
        FOR v_h IN
            SELECT h.*, m.estado AS estado_medico
              FROM horario_atencion h JOIN medico m ON m.id_medico = h.id_medico
             WHERE h.dia_semana = EXTRACT(ISODOW FROM v_dia)
             ORDER BY h.id_medico, h.hora_inicio
        LOOP
            v_slot := v_h.hora_inicio;
            WHILE v_slot + make_interval(mins => v_h.duracion_cita) <= v_h.hora_fin LOOP
                v_r := random();
                -- Ocupación: ~55 % en el pasado, ~35 % en el futuro
                IF ((v_dia + v_slot) <  localtimestamp AND v_r < 0.55) OR
                   ((v_dia + v_slot) >= localtimestamp AND v_r < 0.35) THEN
                    v_paciente := v_pacientes[1 + floor(random() * (array_length(v_pacientes,1) - 1))::INT];
                    SELECT id_especialidad INTO v_esp FROM medico_especialidad
                     WHERE id_medico = v_h.id_medico ORDER BY id_especialidad LIMIT 1;
                    SELECT nombre INTO v_esp_nombre FROM especialidad WHERE id_especialidad = v_esp;
                    v_motivos := CASE v_esp_nombre
                        WHEN 'Pediatría'    THEN ARRAY['Control de niño sano','Fiebre y tos','Vacunación','Dolor de estómago','Control de peso y talla']
                        WHEN 'Ginecología'  THEN ARRAY['Control prenatal','Control anual','Ecografía de control','Planificación familiar','Revisión de resultados de PAP']
                        WHEN 'Odontología'  THEN ARRAY['Limpieza dental','Dolor de muela','Control de ortodoncia','Curación de caries','Revisión general']
                        WHEN 'Cardiología'  THEN ARRAY['Chequeo cardiológico','Control de presión arterial','Dolor en el pecho al esfuerzo','Electrocardiograma de control','Palpitaciones']
                        ELSE ARRAY['Consulta general','Control de rutina','Dolor de cabeza persistente','Fiebre y malestar general','Revisión de exámenes de laboratorio','Tos y resfrío','Renovación de receta']
                    END;
                    v_motivo := v_motivos[1 + floor(random() * array_length(v_motivos,1))::INT];
                    v_r := random();
                    IF (v_dia + v_slot) < localtimestamp THEN
                        v_estado := CASE WHEN v_r < 0.72 THEN 'atendida'
                                         WHEN v_r < 0.87 THEN 'cancelada'
                                         ELSE 'inasistencia' END;
                    ELSE
                        v_estado := CASE WHEN v_r < 0.65 THEN 'programada'
                                         WHEN v_r < 0.90 THEN 'confirmada'
                                         ELSE 'cancelada' END;
                    END IF;
                    BEGIN
                        INSERT INTO cita (id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin,
                                          modalidad, motivo, estado, motivo_cancelacion,
                                          id_usuario_registro, fecha_registro)
                        VALUES (v_paciente, v_h.id_medico, v_esp, v_dia, v_slot,
                                v_slot + make_interval(mins => v_h.duracion_cita),
                                CASE WHEN random() < 0.12 THEN 'virtual' ELSE 'presencial' END,
                                v_motivo, v_estado,
                                CASE WHEN v_estado = 'cancelada' THEN
                                     (ARRAY['El paciente no puede asistir','Viaje imprevisto',
                                            'Solicitud del paciente por teléfono','El médico tuvo una emergencia'])[1 + floor(random()*4)::INT]
                                END,
                                v_recep,
                                (v_dia - (1 + floor(random() * 10))::INT) + TIME '10:00');
                    EXCEPTION WHEN exclusion_violation THEN
                        NULL; -- el paciente ya tiene otra cita a esa hora: se omite
                    END;
                END IF;
                v_slot := v_slot + make_interval(mins => v_h.duracion_cita);
            END LOOP;
        END LOOP;
    END LOOP;
END $$;

-- Historial coherente con las fechas de las citas de demostración:
-- la cita nace "programada" el día en que se registró y luego cambia de estado.
UPDATE cita_historial h
   SET fecha_hora = c.fecha_registro, estado_nuevo = 'programada', id_usuario = c.id_usuario_registro
  FROM cita c
 WHERE h.id_cita = c.id_cita AND h.accion = 'registro';

INSERT INTO cita_historial (id_cita, accion, estado_anterior, estado_nuevo, detalle, id_usuario, fecha_hora)
SELECT c.id_cita, 'cambio_estado', 'programada', c.estado,
       CASE c.estado WHEN 'atendida'     THEN 'Consulta realizada'
                     WHEN 'inasistencia' THEN 'El paciente no se presentó'
                     WHEN 'confirmada'   THEN 'El paciente confirmó su asistencia'
                     ELSE c.motivo_cancelacion END,
       c.id_usuario_registro,
       CASE WHEN c.estado IN ('atendida','inasistencia') THEN (c.fecha + c.hora_fin)::TIMESTAMPTZ
            ELSE LEAST(c.fecha_registro + INTERVAL '1 day', (c.fecha + c.hora_inicio)::TIMESTAMPTZ - INTERVAL '3 hours') END
  FROM cita c
 WHERE c.estado <> 'programada';

-- El Dr. Jorge Chávez deja la clínica: queda inactivo y sus citas futuras
-- se cancelan; su historial se conserva (RF03, RN11).
SET app.detalle = 'Médico desactivado: baja de la clínica';
UPDATE cita SET estado = 'cancelada', motivo_cancelacion = 'El médico dejó de atender en la clínica'
 WHERE id_medico = (SELECT id_medico FROM medico WHERE registro_profesional = 'MP-09117')
   AND fecha >= CURRENT_DATE AND estado IN ('programada','confirmada');
RESET app.detalle;
UPDATE medico SET estado = 'inactivo' WHERE registro_profesional = 'MP-09117';

ALTER TABLE cita ENABLE TRIGGER tg_cita_validar;

-- ---------------------------------------------------------------------
-- Atenciones de demostración: la última cita atendida de María López y
-- de Carlos Vargas, con sus signos vitales y su receta.
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_cita     RECORD;
    v_atencion INTEGER;
    v_receta   INTEGER;
    v_usuario  INTEGER;
BEGIN
    -- María López: faringitis (es alérgica a la penicilina: se receta azitromicina).
    SELECT c.* INTO v_cita FROM cita c JOIN persona p ON p.id_persona = c.id_paciente
     WHERE p.ci = '6234567' AND c.estado = 'atendida' ORDER BY c.fecha DESC, c.hora_inicio DESC LIMIT 1;
    IF FOUND THEN
        SELECT id_usuario INTO v_usuario FROM usuario WHERE id_persona = v_cita.id_medico;
        INSERT INTO atencion (id_cita, diagnostico, tratamiento, observaciones, id_usuario_registro, fecha_registro)
        VALUES (v_cita.id_cita, 'Faringoamigdalitis aguda',
                'Antibiótico por 3 días, analgésico si hay fiebre, abundantes líquidos y reposo.',
                'Paciente alérgica a la penicilina: se evita la amoxicilina.',
                v_usuario, (v_cita.fecha + v_cita.hora_fin)::TIMESTAMPTZ)
        RETURNING id_atencion INTO v_atencion;
        INSERT INTO signo_vital (id_atencion, fecha_hora, peso_kg, talla_cm, presion_sistolica, presion_diastolica,
                                 temperatura_c, frecuencia_cardiaca, saturacion_o2)
        VALUES (v_atencion, (v_cita.fecha + v_cita.hora_inicio)::TIMESTAMPTZ, 62, 160, 115, 75, 38.2, 92, 97);
        INSERT INTO receta (id_atencion, fecha_emision, indicaciones)
        VALUES (v_atencion, (v_cita.fecha + v_cita.hora_fin)::TIMESTAMPTZ, 'Volver a control si la fiebre dura más de 3 días.')
        RETURNING id_receta INTO v_receta;
        INSERT INTO receta_detalle (id_receta, id_medicamento, dosis, via, frecuencia_horas, duracion_dias,
                                    fecha_inicio, hora_primera_toma, indicaciones)
        SELECT v_receta, m.id_medicamento, x.dosis, 'oral', x.cada, x.dias, v_cita.fecha, x.hora::TIME, x.ind
          FROM (VALUES ('Azitromicina', '500 mg', '1 tableta', 24, 3, '20:00', 'Tomar una hora antes de comer.'),
                       ('Paracetamol',  '500 mg', '1 tableta',  8, 3, '20:00', 'Solo si tiene fiebre o dolor.')
               ) AS x(nom, conc, dosis, cada, dias, hora, ind)
          JOIN medicamento m ON m.nombre = x.nom AND m.concentracion = x.conc;
    END IF;

    -- Carlos Vargas: control de hipertensión.
    SELECT c.* INTO v_cita FROM cita c JOIN persona p ON p.id_persona = c.id_paciente
     WHERE p.ci = '7345678' AND c.estado = 'atendida' ORDER BY c.fecha DESC, c.hora_inicio DESC LIMIT 1;
    IF FOUND THEN
        SELECT id_usuario INTO v_usuario FROM usuario WHERE id_persona = v_cita.id_medico;
        INSERT INTO atencion (id_cita, diagnostico, tratamiento, observaciones, id_usuario_registro, fecha_registro)
        VALUES (v_cita.id_cita, 'Hipertensión arterial esencial en control',
                'Continuar con losartán, agregar ácido acetilsalicílico; dieta con poca sal y caminata diaria.',
                'Presión ligeramente elevada. Control en 30 días.',
                v_usuario, (v_cita.fecha + v_cita.hora_fin)::TIMESTAMPTZ)
        RETURNING id_atencion INTO v_atencion;
        INSERT INTO signo_vital (id_atencion, fecha_hora, peso_kg, talla_cm, presion_sistolica, presion_diastolica,
                                 temperatura_c, frecuencia_cardiaca, saturacion_o2)
        VALUES (v_atencion, (v_cita.fecha + v_cita.hora_inicio)::TIMESTAMPTZ, 82, 172, 138, 88, 36.6, 76, 98);
        INSERT INTO receta (id_atencion, fecha_emision, indicaciones)
        VALUES (v_atencion, (v_cita.fecha + v_cita.hora_fin)::TIMESTAMPTZ, 'Controlar la presión en casa cada mañana.')
        RETURNING id_receta INTO v_receta;
        INSERT INTO receta_detalle (id_receta, id_medicamento, dosis, via, frecuencia_horas, duracion_dias,
                                    fecha_inicio, hora_primera_toma, indicaciones)
        SELECT v_receta, m.id_medicamento, x.dosis, 'oral', x.cada, x.dias, v_cita.fecha, x.hora::TIME, x.ind
          FROM (VALUES ('Losartán',               '50 mg',  '1 tableta', 24, 30, '08:00', 'En ayunas.'),
                       ('Ácido acetilsalicílico', '100 mg', '1 tableta', 24, 30, '13:00', 'Después del almuerzo.')
               ) AS x(nom, conc, dosis, cada, dias, hora, ind)
          JOIN medicamento m ON m.nombre = x.nom AND m.concentracion = x.conc;
    END IF;
END $$;

RESET app.sin_auditoria;
RESET app.sin_notificaciones;
