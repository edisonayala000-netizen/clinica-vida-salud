-- =====================================================================
--  Archivo : 04_datos_iniciales.sql
--  Propósito: datos mínimos para que el sistema funcione:
--             roles, permisos, permisos por rol, especialidades y el
--             usuario administrador.
--  Contraseña inicial del administrador: Clinica2026*
--  (hash generado con password_hash() de PHP; cámbiela al ingresar)
-- =====================================================================

-- Zona horaria de Bolivia para todas las fechas de la base.
DO $$ BEGIN
    EXECUTE format('ALTER DATABASE %I SET timezone TO %L', current_database(), 'America/La_Paz');
END $$;
SET timezone TO 'America/La_Paz';

-- ---------------------------------------------------------------------
-- Roles del sistema (punto 3 del caso de estudio)
-- ---------------------------------------------------------------------
INSERT INTO rol (nombre, descripcion) VALUES
 ('administrador', 'Configura y supervisa el sistema: usuarios, permisos, médicos, especialidades, horarios y reportes.'),
 ('recepcionista', 'Gestiona pacientes y citas: programa, reprograma, cancela y registra asistencia.'),
 ('medico',        'Consulta su agenda de atención y marca citas como atendidas si tiene el permiso.'),
 ('paciente',      'Gestiona su perfil, reserva citas en línea y consulta su historial.');

-- ---------------------------------------------------------------------
-- Permisos básicos (RF01: el menú se muestra según los permisos)
-- ---------------------------------------------------------------------
INSERT INTO permiso (codigo, nombre, modulo) VALUES
 ('usuarios.gestionar',       'Gestionar usuarios y permisos',                 'seguridad'),
 ('bitacora.ver',             'Consultar la bitácora de acciones',             'seguridad'),
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
 ('reportes.ver',             'Generar reportes administrativos',              'reportes'),
 ('portal.perfil',            'Gestionar su perfil de paciente',               'portal'),
 ('portal.reservar',          'Reservar citas en línea',                       'portal'),
 ('portal.mis_citas',         'Ver y cancelar sus propias citas',              'portal');

-- Administrador: todo excepto el portal del paciente y la agenda propia.
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r CROSS JOIN permiso p
 WHERE r.nombre = 'administrador'
   AND p.modulo <> 'portal'
   AND p.codigo <> 'agenda.propia';

-- Recepcionista
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.codigo IN (
       'pacientes.ver','pacientes.gestionar','pacientes.historial','medicos.ver',
       'citas.ver','citas.programar','citas.reprogramar','citas.cancelar',
       'citas.confirmar','citas.atender','agenda.ver')
 WHERE r.nombre = 'recepcionista';

-- Médico: su agenda y (si el administrador lo mantiene) marcar atendidas.
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.codigo IN ('agenda.propia','citas.atender')
 WHERE r.nombre = 'medico';

-- Paciente: portal de autoservicio.
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
  FROM rol r JOIN permiso p ON p.modulo = 'portal'
 WHERE r.nombre = 'paciente';

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
-- Usuario administrador inicial
-- ---------------------------------------------------------------------
INSERT INTO usuario (nombre_usuario, correo, password_hash, nombre_completo, id_rol)
SELECT 'admin', 'admin@vidasalud.bo',
       '$2y$10$cqNvBIdFanPUtolihUHZueCzSMDyi2Y7L5tj1/C8WA9VqsJTSGlNS',
       'Administrador del Sistema', id_rol
  FROM rol WHERE nombre = 'administrador';
