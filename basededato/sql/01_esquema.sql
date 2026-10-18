-- =====================================================================
--  SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS — CLÍNICA VIDA SALUD
--  Archivo : 01_esquema.sql
--  Propósito: crear las tablas, llaves primarias/foráneas, restricciones
--             CHECK, UNIQUE, EXCLUDE e índices de la base de datos.
--  Motor    : PostgreSQL 14 o superior
--  Ejecutar : psql -U postgres -d clinica_vida_salud -f 01_esquema.sql
-- =====================================================================

SET client_encoding = 'UTF8';

-- btree_gist permite combinar "=" con "&&" (solapamiento de rangos)
-- en una restricción EXCLUDE. Con ella la BD impide citas superpuestas.
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- ---------------------------------------------------------------------
-- Función auxiliar: normaliza un texto (minúsculas, sin tildes y sin
-- espacios repetidos). Se usa para que no existan dos especialidades
-- con el mismo "nombre normalizado" (RF04).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_normalizar_texto(p_texto TEXT)
RETURNS TEXT
LANGUAGE sql
IMMUTABLE
AS $$
    SELECT lower(
             regexp_replace(
               btrim(translate(p_texto, 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun')),
               '\s+', ' ', 'g'))
$$;

-- =====================================================================
--  PAQUETE SEGURIDAD: rol, permiso, rol_permiso, usuario
-- =====================================================================

CREATE TABLE rol (
    id_rol        SMALLSERIAL  PRIMARY KEY,
    nombre        VARCHAR(30)  NOT NULL,
    descripcion   VARCHAR(150),
    CONSTRAINT uq_rol_nombre UNIQUE (nombre),
    CONSTRAINT ck_rol_nombre CHECK (nombre IN ('administrador','recepcionista','medico','paciente'))
);

CREATE TABLE permiso (
    id_permiso    SERIAL       PRIMARY KEY,
    codigo        VARCHAR(50)  NOT NULL,
    nombre        VARCHAR(120) NOT NULL,
    modulo        VARCHAR(30)  NOT NULL,
    CONSTRAINT uq_permiso_codigo UNIQUE (codigo)
);

-- Clase asociativa ROL *---* PERMISO (permisos básicos por rol)
CREATE TABLE rol_permiso (
    id_rol        SMALLINT     NOT NULL REFERENCES rol(id_rol)         ON DELETE CASCADE,
    id_permiso    INTEGER      NOT NULL REFERENCES permiso(id_permiso) ON DELETE CASCADE,
    PRIMARY KEY (id_rol, id_permiso)
);

-- =====================================================================
--  PAQUETE MÉDICOS: especialidad, medico, medico_especialidad,
--                   horario_atencion, dia_no_laborable
-- =====================================================================

CREATE TABLE especialidad (
    id_especialidad SERIAL       PRIMARY KEY,
    nombre          VARCHAR(80)  NOT NULL,
    descripcion     VARCHAR(250),
    estado          VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_especialidad_estado CHECK (estado IN ('activo','inactivo')),
    CONSTRAINT ck_especialidad_nombre CHECK (length(btrim(nombre)) >= 3)
);
-- No se permiten dos especialidades con el mismo nombre normalizado.
CREATE UNIQUE INDEX uq_especialidad_nombre_normalizado
    ON especialidad (fn_normalizar_texto(nombre));

CREATE SEQUENCE seq_codigo_medico START 1;

CREATE TABLE medico (
    id_medico             SERIAL       PRIMARY KEY,
    codigo                VARCHAR(10)  NOT NULL DEFAULT ('MED-' || lpad(nextval('seq_codigo_medico')::TEXT, 3, '0')),
    registro_profesional  VARCHAR(30)  NOT NULL,
    nombres               VARCHAR(60)  NOT NULL,
    apellidos             VARCHAR(60)  NOT NULL,
    telefono              VARCHAR(20)  NOT NULL,
    correo                VARCHAR(120) NOT NULL,
    estado                VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en             TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_medico_codigo   UNIQUE (codigo),
    CONSTRAINT uq_medico_registro UNIQUE (registro_profesional),
    CONSTRAINT uq_medico_correo   UNIQUE (correo),
    CONSTRAINT ck_medico_estado   CHECK (estado IN ('activo','inactivo')),
    CONSTRAINT ck_medico_correo   CHECK (correo ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    CONSTRAINT ck_medico_telefono CHECK (telefono ~ '^[0-9+\-\s]{7,20}$')
);

-- Clase asociativa MEDICO *---* ESPECIALIDAD
CREATE TABLE medico_especialidad (
    id_medico        INTEGER NOT NULL REFERENCES medico(id_medico)             ON DELETE RESTRICT,
    id_especialidad  INTEGER NOT NULL REFERENCES especialidad(id_especialidad) ON DELETE RESTRICT,
    PRIMARY KEY (id_medico, id_especialidad)
);

-- Horarios de atención semanales de cada médico (RF05).
-- dia_semana usa la norma ISO: 1 = lunes ... 7 = domingo.
CREATE TABLE horario_atencion (
    id_horario     SERIAL      PRIMARY KEY,
    id_medico      INTEGER     NOT NULL REFERENCES medico(id_medico) ON DELETE RESTRICT,
    dia_semana     SMALLINT    NOT NULL,
    hora_inicio    TIME        NOT NULL,
    hora_fin       TIME        NOT NULL,
    duracion_cita  SMALLINT    NOT NULL DEFAULT 30,
    estado         VARCHAR(10) NOT NULL DEFAULT 'activo',
    creado_en      TIMESTAMPTZ NOT NULL DEFAULT now(),
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ck_horario_dia      CHECK (dia_semana BETWEEN 1 AND 7),
    CONSTRAINT ck_horario_rango    CHECK (hora_fin > hora_inicio),
    CONSTRAINT ck_horario_duracion CHECK (duracion_cita IN (15, 20, 30, 45, 60)),
    CONSTRAINT ck_horario_estado   CHECK (estado IN ('activo','inactivo')),
    -- Un médico no puede tener dos bloques activos superpuestos el mismo día.
    CONSTRAINT ex_horario_sin_solape EXCLUDE USING gist (
        id_medico  WITH =,
        dia_semana WITH =,
        tsrange(DATE '2000-01-01' + hora_inicio, DATE '2000-01-01' + hora_fin) WITH &&
    ) WHERE (estado = 'activo')
);

-- Feriados o bloqueos de agenda (opcional del RF05).
-- id_medico NULL = bloqueo para toda la clínica.
CREATE TABLE dia_no_laborable (
    id_dia_no_laborable SERIAL       PRIMARY KEY,
    fecha               DATE         NOT NULL,
    id_medico           INTEGER      REFERENCES medico(id_medico) ON DELETE CASCADE,
    descripcion         VARCHAR(120) NOT NULL
);
CREATE UNIQUE INDEX uq_dia_no_laborable
    ON dia_no_laborable (fecha, COALESCE(id_medico, 0));

-- =====================================================================
--  PAQUETE PACIENTES: paciente, contacto_emergencia
-- =====================================================================

CREATE SEQUENCE seq_codigo_paciente START 1;

CREATE TABLE paciente (
    id_paciente            SERIAL       PRIMARY KEY,
    codigo                 VARCHAR(10)  NOT NULL DEFAULT ('PAC-' || lpad(nextval('seq_codigo_paciente')::TEXT, 4, '0')),
    ci                     VARCHAR(15),
    nombres                VARCHAR(60)  NOT NULL,
    apellidos              VARCHAR(60)  NOT NULL,
    fecha_nacimiento       DATE         NOT NULL,
    sexo                   VARCHAR(10)  NOT NULL,
    telefono               VARCHAR(20)  NOT NULL,
    correo                 VARCHAR(120),
    direccion              VARCHAR(200),
    -- Información médica del perfil del paciente (punto 3.4 del caso)
    tipo_sangre            VARCHAR(3),
    alergias               VARCHAR(250),
    enfermedades_cronicas  VARCHAR(250),
    medicamentos           VARCHAR(250),
    antecedentes           VARCHAR(500),
    -- Preferencias de la cuenta del paciente
    recibir_recordatorios  BOOLEAN      NOT NULL DEFAULT TRUE,
    compartir_info_medica  BOOLEAN      NOT NULL DEFAULT TRUE,
    estado                 VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en              TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en         TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_paciente_codigo UNIQUE (codigo),
    -- RF02 Regla: no se permiten dos pacientes con el mismo CI.
    CONSTRAINT uq_paciente_ci     UNIQUE (ci),
    CONSTRAINT ck_paciente_ci     CHECK (ci IS NULL OR ci ~ '^[0-9]{5,12}([-A-Z0-9]{1,3})?$'),
    CONSTRAINT ck_paciente_sexo   CHECK (sexo IN ('femenino','masculino','otro')),
    CONSTRAINT ck_paciente_fnac   CHECK (fecha_nacimiento BETWEEN DATE '1900-01-01' AND CURRENT_DATE),
    CONSTRAINT ck_paciente_correo CHECK (correo IS NULL OR correo ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    CONSTRAINT ck_paciente_tel    CHECK (telefono ~ '^[0-9+\-\s]{7,20}$'),
    CONSTRAINT ck_paciente_sangre CHECK (tipo_sangre IS NULL OR tipo_sangre IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')),
    CONSTRAINT ck_paciente_estado CHECK (estado IN ('activo','inactivo'))
);

CREATE TABLE contacto_emergencia (
    id_contacto  SERIAL       PRIMARY KEY,
    id_paciente  INTEGER      NOT NULL REFERENCES paciente(id_paciente) ON DELETE CASCADE,
    nombre       VARCHAR(120) NOT NULL,
    parentesco   VARCHAR(40)  NOT NULL,
    telefono     VARCHAR(20)  NOT NULL,
    CONSTRAINT ck_contacto_tel CHECK (telefono ~ '^[0-9+\-\s]{7,20}$')
);

-- =====================================================================
--  USUARIO (paquete seguridad). Se crea después de medico y paciente
--  porque un usuario puede estar vinculado a un médico o a un paciente.
-- =====================================================================

CREATE TABLE usuario (
    id_usuario         SERIAL       PRIMARY KEY,
    nombre_usuario     VARCHAR(40)  NOT NULL,
    correo             VARCHAR(120) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,       -- generado con password_hash() de PHP
    nombre_completo    VARCHAR(120) NOT NULL,
    id_rol             SMALLINT     NOT NULL REFERENCES rol(id_rol),
    id_medico          INTEGER      REFERENCES medico(id_medico)     ON DELETE RESTRICT,
    id_paciente        INTEGER      REFERENCES paciente(id_paciente) ON DELETE RESTRICT,
    estado             VARCHAR(10)  NOT NULL DEFAULT 'activo',
    intentos_fallidos  SMALLINT     NOT NULL DEFAULT 0,
    bloqueado_hasta    TIMESTAMPTZ,
    ultimo_acceso      TIMESTAMPTZ,
    creado_en          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_usuario_nombre   UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo   UNIQUE (correo),
    CONSTRAINT uq_usuario_medico   UNIQUE (id_medico),
    CONSTRAINT uq_usuario_paciente UNIQUE (id_paciente),
    CONSTRAINT ck_usuario_estado   CHECK (estado IN ('activo','inactivo','bloqueado')),
    CONSTRAINT ck_usuario_nombre   CHECK (nombre_usuario ~ '^[a-z0-9._]{4,40}$'),
    CONSTRAINT ck_usuario_vinculo  CHECK (NOT (id_medico IS NOT NULL AND id_paciente IS NOT NULL))
);

-- =====================================================================
--  PAQUETE CITAS: cita, cita_historial
-- =====================================================================

CREATE SEQUENCE seq_codigo_cita START 1;

CREATE TABLE cita (
    id_cita              SERIAL       PRIMARY KEY,
    codigo               VARCHAR(10)  NOT NULL DEFAULT ('CM-' || lpad(nextval('seq_codigo_cita')::TEXT, 4, '0')),
    id_paciente          INTEGER      NOT NULL REFERENCES paciente(id_paciente) ON DELETE RESTRICT,
    id_medico            INTEGER      NOT NULL,
    id_especialidad      INTEGER      NOT NULL,
    fecha                DATE         NOT NULL,
    hora_inicio          TIME         NOT NULL,
    hora_fin             TIME         NOT NULL,
    modalidad            VARCHAR(12)  NOT NULL DEFAULT 'presencial',
    motivo               VARCHAR(250),
    estado               VARCHAR(15)  NOT NULL DEFAULT 'programada',
    motivo_cancelacion   VARCHAR(250),
    id_usuario_registro  INTEGER      REFERENCES usuario(id_usuario),
    fecha_registro       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_cita_codigo     UNIQUE (codigo),
    -- RN03: la cita pertenece a un paciente y a un médico válidos; además el
    -- médico debe tener la especialidad elegida (FK compuesta).
    CONSTRAINT fk_cita_medico_especialidad FOREIGN KEY (id_medico, id_especialidad)
        REFERENCES medico_especialidad (id_medico, id_especialidad) ON DELETE RESTRICT,
    CONSTRAINT ck_cita_rango      CHECK (hora_fin > hora_inicio),
    CONSTRAINT ck_cita_modalidad  CHECK (modalidad IN ('presencial','virtual')),
    CONSTRAINT ck_cita_estado     CHECK (estado IN ('programada','confirmada','atendida','cancelada','inasistencia')),
    CONSTRAINT ck_cita_cancelada  CHECK (estado <> 'cancelada' OR motivo_cancelacion IS NOT NULL),
    -- RN07 / RF07: un médico no puede tener citas superpuestas.
    -- RN09: las citas canceladas no ocupan disponibilidad (cláusula WHERE).
    CONSTRAINT ex_cita_medico_sin_solape EXCLUDE USING gist (
        id_medico WITH =,
        tsrange(fecha + hora_inicio, fecha + hora_fin) WITH &&
    ) WHERE (estado <> 'cancelada'),
    -- Un paciente tampoco puede estar en dos citas al mismo tiempo.
    CONSTRAINT ex_cita_paciente_sin_solape EXCLUDE USING gist (
        id_paciente WITH =,
        tsrange(fecha + hora_inicio, fecha + hora_fin) WITH &&
    ) WHERE (estado <> 'cancelada')
);

-- Trazabilidad: cada registro, cambio de estado o reprogramación de una
-- cita queda guardado aquí (RF08, RF09, RF10, RF12).
CREATE TABLE cita_historial (
    id_historial     BIGSERIAL    PRIMARY KEY,
    id_cita          INTEGER      NOT NULL REFERENCES cita(id_cita) ON DELETE RESTRICT,
    accion           VARCHAR(20)  NOT NULL,
    estado_anterior  VARCHAR(15),
    estado_nuevo     VARCHAR(15),
    fecha_anterior   DATE,
    hora_anterior    TIME,
    fecha_nueva      DATE,
    hora_nueva       TIME,
    detalle          VARCHAR(250),
    id_usuario       INTEGER      REFERENCES usuario(id_usuario),
    fecha_hora       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_historial_accion CHECK (accion IN ('registro','cambio_estado','reprogramacion'))
);

-- Bitácora de acciones de los usuarios (CU02): inicios y cierres de sesión,
-- intentos fallidos, bloqueos y toda operación enviada por POST.
-- No se modifica ni se borra (trigger fn_tg_bitacora_inalterable).
CREATE TABLE bitacora (
    id_bitacora   BIGSERIAL    PRIMARY KEY,
    id_usuario    INTEGER      REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
    usuario       VARCHAR(120),                 -- nombre de usuario, o lo escrito en un login fallido
    accion        VARCHAR(40)  NOT NULL,
    modulo        VARCHAR(30)  NOT NULL,
    detalle       TEXT,
    ip            VARCHAR(45),
    fecha_hora    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- =====================================================================
--  ÍNDICES para las búsquedas más frecuentes
-- =====================================================================
CREATE INDEX ix_paciente_apellidos   ON paciente (lower(apellidos));
CREATE INDEX ix_paciente_nombres     ON paciente (lower(nombres));
CREATE INDEX ix_medico_apellidos     ON medico (lower(apellidos));
CREATE INDEX ix_horario_medico_dia   ON horario_atencion (id_medico, dia_semana);
CREATE INDEX ix_cita_medico_fecha    ON cita (id_medico, fecha);
CREATE INDEX ix_cita_paciente        ON cita (id_paciente);
CREATE INDEX ix_cita_fecha_estado    ON cita (fecha, estado);
CREATE INDEX ix_cita_especialidad    ON cita (id_especialidad);
CREATE INDEX ix_historial_cita       ON cita_historial (id_cita);
CREATE INDEX ix_usuario_rol          ON usuario (id_rol);
CREATE INDEX ix_bitacora_fecha_hora  ON bitacora (fecha_hora);
CREATE INDEX ix_bitacora_usuario     ON bitacora (id_usuario);
