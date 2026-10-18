-- =====================================================================
--  Archivo : 05_datos_prueba.sql   (OPCIONAL)
--  Propósito: poblar la base con médicos, horarios, pacientes, usuarios
--             de demostración y citas de las últimas semanas y de las
--             próximas, para probar la agenda, el historial y los reportes.
--  Las fechas de las citas se calculan desde la fecha actual, así la
--  demostración siempre tiene citas pasadas y futuras.
--  Contraseña de todos los usuarios de prueba: Clinica2026*
-- =====================================================================
SET timezone TO 'America/La_Paz';

-- ---------------------------------------------------------------------
-- Médicos (RF03)
-- ---------------------------------------------------------------------
INSERT INTO medico (registro_profesional, nombres, apellidos, telefono, correo, estado) VALUES
 ('MP-10234', 'Juan',          'Pérez Rojas',       '70012345', 'juan.perez@vidasalud.bo',      'activo'),
 ('MP-11872', 'Ana María',     'Gutiérrez Vaca',    '70123456', 'ana.gutierrez@vidasalud.bo',   'activo'),
 ('MP-12011', 'Carla',         'Suárez Montaño',    '71234567', 'carla.suarez@vidasalud.bo',    'activo'),
 ('MP-13550', 'Luis Fernando', 'Arteaga Justiniano','72345678', 'luis.arteaga@vidasalud.bo',    'activo'),
 ('MP-14098', 'Roberto',       'Mendoza Salazar',   '73456789', 'roberto.mendoza@vidasalud.bo', 'activo'),
 ('MP-15421', 'Patricia',      'Rojas Antelo',      '74567890', 'patricia.rojas@vidasalud.bo',  'activo'),
 ('MP-09117', 'Jorge',         'Chávez Ribera',     '75678901', 'jorge.chavez@vidasalud.bo',    'activo');

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
-- Pacientes (RF02)
-- ---------------------------------------------------------------------
INSERT INTO paciente (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion,
                      tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes) VALUES
 ('6234567', 'María',     'López Rocha',      '1992-03-14','femenino', '76011223','maria.lopez@gmail.com',   'Av. Banzer 4to anillo, calle 3 #145', 'O+','Penicilina','Ninguna','Ninguno','Cesárea en 2019'),
 ('7345678', 'Carlos',    'Vargas Méndez',    '1985-07-22','masculino','76122334','carlos.vargas@gmail.com', 'B/ Equipetrol, calle Los Pinos #12',  'A+',NULL,'Hipertensión arterial','Losartán 50 mg','Padre con infarto a los 60 años'),
 ('8456789', 'Lucía',     'Fernández Áñez',   '2001-11-05','femenino', '76233445','lucia.fa@gmail.com',      'Plan 3000, UV 160 Mz 12',              'B+',NULL,NULL,NULL,NULL),
 ('5567890', 'Pedro',     'Quiroga Salvatierra','1978-01-30','masculino','76344556',NULL,                     'Av. Santos Dumont 2do anillo',         'O-','Sulfas','Diabetes tipo 2','Metformina 850 mg',NULL),
 ('9678901', 'Valeria',   'Cuéllar Justiniano','2015-06-18','femenino', '76455667','mama.valeria@gmail.com',  'B/ Hamacas, calle 5 #30',              'A-','Lactosa',NULL,NULL,'Nacimiento prematuro (36 semanas)'),
 ('4789012', 'Jorge',     'Mamani Choque',    '1969-09-09','masculino','76566778','jorge.mamani@hotmail.com','Villa 1ro de Mayo, calle 16',          'O+',NULL,'Arritmia','Atenolol 25 mg',NULL),
 ('6890123', 'Sofía',     'Ribera Paz',       '1998-12-01','femenino', '76677889','sofia.ribera@gmail.com',  'Av. Alemana 3er anillo',               'AB+',NULL,NULL,NULL,NULL),
 ('7901234', 'Diego',     'Saavedra Vaca',    '2018-04-25','masculino','76788990','diego.papa@gmail.com',    'B/ Las Palmas, calle Ceibo #8',        'B-','Polen',NULL,NULL,NULL),
 ('5012345', 'Gabriela',  'Torrez Arias',     '1988-08-15','femenino', '76899001','gaby.torrez@gmail.com',   'Av. Cristo Redentor km 5',             'A+',NULL,'Asma','Salbutamol inhalador',NULL),
 ('8123456', 'Andrés',    'Montero Peña',     '1995-02-10','masculino','76900112','andres.montero@gmail.com','Calle Warnes #455',                    'O+',NULL,NULL,NULL,NULL),
 ('6345670', 'Rosa',      'Céspedes Lima',    '1955-05-03','femenino', '77011223',NULL,                     'B/ Los Lotes, calle 7',                'O+','Ibuprofeno','Artrosis','Paracetamol 500 mg','Cirugía de cadera 2021'),
 ('7456781', 'Fernando',  'Ortiz Gil',        '1982-10-28','masculino','77122334','fernando.ortiz@gmail.com','Av. Piraí 3er anillo',                 'A+',NULL,NULL,NULL,NULL),
 ('9567892', 'Camila',    'Aguilera Soto',    '2010-01-19','femenino', '77233445','camila.mama@gmail.com',   'B/ Urbarí, calle Tajibos #20',         'B+',NULL,NULL,NULL,NULL),
 ('5678903', 'Miguel',    'Rivero Durán',     '1974-03-07','masculino','77344556','miguel.rivero@gmail.com', 'Av. Roca y Coronado 4to anillo',       'O-',NULL,'Colesterol alto','Atorvastatina 20 mg',NULL),
 ('6789014', 'Daniela',   'Pinto Herrera',    '1993-07-30','femenino', '77455667','daniela.pinto@gmail.com', 'Calle Sucre #210',                     'A-',NULL,NULL,NULL,'Embarazo en curso'),
 ('7890125', 'Ricardo',   'Velasco Rua',      '1965-11-11','masculino','77566778',NULL,                     'B/ Pampa de la Isla',                  'O+',NULL,'Hipertensión arterial','Enalapril 10 mg',NULL),
 ('8901236', 'Natalia',   'Suárez Banegas',   '2003-09-02','femenino', '77677889','natalia.sb@gmail.com',    'Av. Grigotá 2do anillo',               'B+',NULL,NULL,NULL,NULL),
 ('9012347', 'Mateo',     'Guzmán Rojas',     '2020-12-12','masculino','77788990','mateo.papa@gmail.com',    'B/ Sirari, calle Los Gomeros',         NULL,NULL,NULL,NULL,NULL),
 ('5123458', 'Elena',     'Moreno Vaca',      '1990-04-04','femenino', '77899001','elena.moreno@gmail.com',  'Av. Busch 3er anillo',                 'AB-',NULL,NULL,NULL,NULL),
 ('6234569', 'Hugo',      'Paz Saucedo',      '1959-06-21','masculino','77900112',NULL,                     'Villa Primero de Mayo, UV 74',          'A+',NULL,'EPOC','Tiotropio',NULL);

-- Un paciente desactivado: conserva su historial pero no recibe citas nuevas.
UPDATE paciente SET estado = 'inactivo' WHERE ci = '6234569';

INSERT INTO contacto_emergencia (id_paciente, nombre, parentesco, telefono)
SELECT p.id_paciente, x.nombre, x.par, x.tel
  FROM (VALUES ('6234567','José López Rocha','Hermano','70911223'),
               ('7345678','Mónica Ríos de Vargas','Esposa','70922334'),
               ('9678901','Andrea Justiniano','Madre','70933445'),
               ('7901234','Raúl Saavedra','Padre','70944556'),
               ('6345670','Carmen Céspedes','Hija','70955667'),
               ('9567892','Lorena Soto','Madre','70966778'),
               ('9012347','Iván Guzmán','Padre','70977889')) AS x(ci, nombre, par, tel)
  JOIN paciente p ON p.ci = x.ci;

-- ---------------------------------------------------------------------
-- Usuarios de demostración (contraseña: Clinica2026*)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol, id_medico, id_paciente)
SELECT x.usr, x.mail, '$2y$10$cqNvBIdFanPUtolihUHZueCzSMDyi2Y7L5tj1/C8WA9VqsJTSGlNS', x.nom,
       r.id_rol,
       (SELECT id_medico   FROM medico   WHERE registro_profesional = x.reg),
       (SELECT id_paciente FROM paciente WHERE ci = x.ci)
  FROM (VALUES
        ('recepcion',     'recepcion@vidasalud.bo',    'Lucía Fernández Vaca',        'recepcionista', NULL,       NULL),
        ('recepcion2',    'recepcion2@vidasalud.bo',   'Marcelo Antelo Suárez',       'recepcionista', NULL,       NULL),
        ('dr.perez',      'juan.perez@vidasalud.bo',   'Dr. Juan Pérez Rojas',        'medico',        'MP-10234', NULL),
        ('dra.gutierrez', 'ana.gutierrez@vidasalud.bo','Dra. Ana María Gutiérrez Vaca','medico',       'MP-11872', NULL),
        ('dra.suarez',    'carla.suarez@vidasalud.bo', 'Dra. Carla Suárez Montaño',   'medico',        'MP-12011', NULL),
        ('dr.mendoza',    'roberto.mendoza@vidasalud.bo','Dr. Roberto Mendoza Salazar','medico',       'MP-14098', NULL),
        ('maria.lopez',   'maria.lopez@gmail.com',     'María López Rocha',           'paciente',      NULL,       '6234567'),
        ('carlos.vargas', 'carlos.vargas@gmail.com',   'Carlos Vargas Méndez',        'paciente',      NULL,       '7345678')
       ) AS x(usr, mail, nom, rol, reg, ci)
  JOIN rol r ON r.nombre = x.rol;

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
