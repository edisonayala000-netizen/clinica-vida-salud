-- =====================================================================
--  Archivo : 04_datos_iniciales.sql
--  Propósito: datos mínimos para que el sistema funcione:
--             roles, permisos, permisos por rol, estados de la cita,
--             especialidades, catálogo de medicamentos y el usuario
--             administrador.
--  Contraseña inicial del administrador: Clinica2026*
--  (hash generado con password_hash() de PHP; cámbiela al ingresar)
-- =====================================================================

-- Zona horaria de Bolivia para todas las fechas de la base.
DO $$ BEGIN
    EXECUTE format('ALTER DATABASE %I SET timezone TO %L', current_database(), 'America/La_Paz');
END $$;
SET timezone TO 'America/La_Paz';
-- La carga inicial no se registra en la auditoría de la bitácora.
SET app.sin_auditoria = 'on';

-- ---------------------------------------------------------------------
-- Roles del sistema (punto 3 del caso de estudio)
-- ---------------------------------------------------------------------
INSERT INTO rol (nombre, descripcion) VALUES
 ('administrador', 'Configura y supervisa el sistema: usuarios, permisos, médicos, especialidades, horarios y reportes.'),
 ('recepcionista', 'Gestiona pacientes y citas: programa, reprograma, cancela y registra asistencia.'),
 ('medico',        'Consulta su agenda, atiende a sus pacientes y emite recetas.'),
 ('paciente',      'Gestiona su perfil, reserva citas en línea y consulta su historial y sus recetas.');

-- ---------------------------------------------------------------------
-- Permisos básicos (RF01: el menú se muestra según los permisos)
-- ---------------------------------------------------------------------
INSERT INTO permiso (codigo, nombre, modulo) VALUES
 ('usuarios.gestionar',       'Gestionar usuarios y permisos',                 'seguridad'),
 ('bitacora.ver',             'Consultar la bitácora de acciones',             'seguridad'),
 ('notificaciones.ver',       'Consultar los correos enviados por el sistema', 'seguridad'),
 ('pacientes.ver',            'Consultar pacientes',                           'pacientes'),
 ('pacientes.gestionar',      'Registrar, modificar y desactivar pacientes',   'pacientes'),
 ('pacientes.historial',      'Consultar el historial de citas de un paciente','pacientes'),
 ('especialidades.gestionar', 'Gestionar especialidades médicas',              'medicos'),
 ('medicos.ver',              'Consultar médicos',                             'medicos'),
 ('medicos.gestionar',        'Registrar, modificar y desactivar médicos',     'medicos'),
 ('horarios.gestionar',       'Gestionar horarios de atención y feriados',     'medicos'),
 ('citas.ver',                'Consultar citas por fecha y estado',            'citas'),
 ('citas.programar',          'Programar citas',                               'citas'),
 ('citas.reprogramar',        'Reprogramar citas',                             'citas'),
 ('citas.cancelar',           'Cancelar citas',                                'citas'),
 ('citas.confirmar',          'Confirmar citas y registrar inasistencias',     'citas'),
 ('citas.atender',            'Marcar citas como atendidas',                   'citas'),
 ('agenda.ver',               'Consultar la agenda de cualquier médico',       'citas'),
 ('agenda.propia',            'Consultar su propia agenda (médico)',           'citas'),
 ('atencion.registrar',       'Registrar la atención, signos vitales y receta','atencion'),
 ('atencion.ver',             'Consultar atenciones y recetas de los pacientes','atencion'),
 ('medicamentos.gestionar',   'Gestionar el catálogo de medicamentos',         'atencion'),
 ('reportes.ver',             'Generar reportes administrativos',              'reportes'),
 ('portal.perfil',            'Gestionar su perfil de paciente',               'portal'),
 ('portal.reservar',          'Reservar citas en línea',                       'portal'),
 ('portal.mis_citas',         'Ver y cancelar sus propias citas',              'portal'),
 ('portal.recetas',           'Ver sus recetas y recordatorios de medicamentos','portal');

-- Administrador: todo excepto el portal del paciente, la agenda propia y
-- los actos clínicos (registrar la atención es del médico).
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r CROSS JOIN permiso p
 WHERE r.nombre = 'administrador'
   AND p.modulo <> 'portal'
   AND p.codigo NOT IN ('agenda.propia', 'atencion.registrar');

-- Recepcionista
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.codigo IN (
       'pacientes.ver','pacientes.gestionar','pacientes.historial','medicos.ver',
       'citas.ver','citas.programar','citas.reprogramar','citas.cancelar',
       'citas.confirmar','citas.atender','agenda.ver')
 WHERE r.nombre = 'recepcionista';

-- Médico: su agenda, marcar atendidas y registrar la atención con su receta.
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.codigo IN ('agenda.propia','citas.atender','atencion.registrar','atencion.ver')
 WHERE r.nombre = 'medico';

-- Paciente: portal de autoservicio.
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.modulo = 'portal'
 WHERE r.nombre = 'paciente';

-- ---------------------------------------------------------------------
-- Estados de la cita y cambios permitidos (RF10)
--   programada  -> confirmada | atendida | cancelada | inasistencia
--   confirmada  -> atendida   | cancelada | inasistencia
--   atendida, cancelada, inasistencia -> (estados finales)
-- ---------------------------------------------------------------------
INSERT INTO estado_cita (codigo, nombre, descripcion, orden, es_final) VALUES
 ('programada',   'Programada',   'La cita fue registrada y espera la fecha de atención.',       1, FALSE),
 ('confirmada',   'Confirmada',   'El paciente confirmó que asistirá.',                           2, FALSE),
 ('atendida',     'Atendida',     'El médico atendió al paciente.',                               3, TRUE),
 ('cancelada',    'Cancelada',    'La cita se anuló; su horario queda libre.',                    4, TRUE),
 ('inasistencia', 'Inasistencia', 'El paciente no se presentó a la cita.',                        5, TRUE);

INSERT INTO estado_cita_transicion (estado_origen, estado_destino, solo_desde_fecha) VALUES
 ('programada', 'confirmada',   FALSE),
 ('programada', 'atendida',     TRUE),
 ('programada', 'cancelada',    FALSE),
 ('programada', 'inasistencia', TRUE),
 ('confirmada', 'atendida',     TRUE),
 ('confirmada', 'cancelada',    FALSE),
 ('confirmada', 'inasistencia', TRUE);

-- ---------------------------------------------------------------------
-- Especialidades que ofrece la clínica (RF04)
-- ---------------------------------------------------------------------
INSERT INTO especialidad (nombre, descripcion) VALUES
 ('Medicina general', 'Atención primaria, chequeos y derivación a especialistas.'),
 ('Pediatría',        'Atención de niños y adolescentes hasta los 17 años.'),
 ('Ginecología',      'Salud de la mujer, control prenatal y planificación familiar.'),
 ('Odontología',      'Prevención, diagnóstico y tratamiento dental.'),
 ('Cardiología',      'Diagnóstico y tratamiento de enfermedades del corazón.');

-- ---------------------------------------------------------------------
-- Catálogo de medicamentos de uso frecuente
-- ---------------------------------------------------------------------
INSERT INTO medicamento (nombre, presentacion, concentracion) VALUES
 ('Paracetamol',                         'tableta',    '500 mg'),
 ('Paracetamol',                         'jarabe',     '120 mg/5 ml'),
 ('Ibuprofeno',                          'tableta',    '400 mg'),
 ('Ibuprofeno',                          'suspensión', '100 mg/5 ml'),
 ('Diclofenaco',                         'tableta',    '50 mg'),
 ('Ketorolaco',                          'tableta',    '10 mg'),
 ('Amoxicilina',                         'cápsula',    '500 mg'),
 ('Amoxicilina',                         'suspensión', '250 mg/5 ml'),
 ('Amoxicilina + ácido clavulánico',     'tableta',    '875/125 mg'),
 ('Azitromicina',                        'tableta',    '500 mg'),
 ('Cefalexina',                          'cápsula',    '500 mg'),
 ('Ciprofloxacino',                      'tableta',    '500 mg'),
 ('Metronidazol',                        'tableta',    '500 mg'),
 ('Fluconazol',                          'cápsula',    '150 mg'),
 ('Clotrimazol',                         'crema',      '1 %'),
 ('Omeprazol',                           'cápsula',    '20 mg'),
 ('Loratadina',                          'tableta',    '10 mg'),
 ('Cetirizina',                          'tableta',    '10 mg'),
 ('Clorfenamina',                        'tableta',    '4 mg'),
 ('Ambroxol',                            'jarabe',     '30 mg/5 ml'),
 ('Salbutamol',                          'inhalador',  '100 mcg/dosis'),
 ('Losartán',                            'tableta',    '50 mg'),
 ('Enalapril',                           'tableta',    '10 mg'),
 ('Amlodipino',                          'tableta',    '5 mg'),
 ('Atenolol',                            'tableta',    '50 mg'),
 ('Metformina',                          'tableta',    '850 mg'),
 ('Atorvastatina',                       'tableta',    '20 mg'),
 ('Ácido acetilsalicílico',              'tableta',    '100 mg'),
 ('Ácido fólico',                        'tableta',    '5 mg'),
 ('Sulfato ferroso',                     'tableta',    '300 mg'),
 ('Sales de rehidratación oral',         'sobre',      '27,9 g'),
 ('Dexametasona',                        'inyectable', '4 mg/ml');

-- ---------------------------------------------------------------------
-- Usuario administrador inicial (también es una persona)
-- ---------------------------------------------------------------------
WITH admin AS (
    INSERT INTO persona (nombres, apellidos, correo)
    VALUES ('Administrador', 'del Sistema', 'admin@vidasalud.bo')
    RETURNING id_persona
)
INSERT INTO usuario (id_persona, id_rol, nombre_usuario, correo, password_hash)
SELECT admin.id_persona, r.id_rol, 'admin', 'admin@vidasalud.bo',
       '$2y$10$cqNvBIdFanPUtolihUHZueCzSMDyi2Y7L5tj1/C8WA9VqsJTSGlNS'
  FROM admin, rol r WHERE r.nombre = 'administrador';

RESET app.sin_auditoria;
