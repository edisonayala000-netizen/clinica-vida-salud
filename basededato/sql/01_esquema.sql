-- =====================================================================
--  SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS — CLÍNICA VIDA SALUD
--  Archivo : 01_esquema.sql
--  Propósito: crear las tablas, llaves primarias/foráneas, restricciones
--             CHECK, UNIQUE, EXCLUDE e índices de la base de datos.
--  Motor    : PostgreSQL 14 o superior
--  Ejecutar : psql -U postgres -d clinica_vida_salud -f 01_esquema.sql
--
--  24 tablas en 6 paquetes:
--    personas       persona, paciente, contacto_emergencia
--    médicos        medico, especialidad, medico_especialidad,
--                   horario_atencion, dia_no_laborable
--    seguridad      rol, permiso, rol_permiso, usuario,
--                   token_recuperacion, bitacora
--    citas          estado_cita, estado_cita_transicion, cita, cita_historial
--    atención       atencion, signo_vital, medicamento, receta, receta_detalle
--    notificaciones notificacion
--
--  PERSONA es la generalización de PACIENTE y MEDICO (y de todo usuario):
--  cada subtipo usa como llave primaria la misma llave de la persona
--  (id_paciente = id_persona, id_medico = id_persona). Así los datos
--  comunes (CI, nombres, teléfono, correo...) se guardan una sola vez.
-- =====================================================================

SET client_encoding = 'UTF8';

-- btree_gist permite combinar "=" con "&&" (solapamiento de rangos)
-- en una restricción EXCLUDE. Con ella la BD impide citas superpuestas.
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- ---------------------------------------------------------------------
-- Función auxiliar: normaliza un texto (minúsculas, sin tildes y sin
-- espacios repetidos). Se usa para que no existan dos especialidades
-- (ni dos medicamentos) con el mismo "nombre normalizado" (RF04).
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
--  PAQUETE PERSONAS: persona, paciente, contacto_emergencia
-- =====================================================================

-- Datos comunes de toda persona del sistema: pacientes, médicos y personal.
-- Los datos que un subtipo exige (p. ej. la fecha de nacimiento del
-- paciente) los controla el trigger fn_tg_validar_datos_persona.
CREATE TABLE persona (
    id_persona        SERIAL       PRIMARY KEY,
    ci                VARCHAR(15),
    nombres           VARCHAR(60)  NOT NULL,
    apellidos         VARCHAR(60)  NOT NULL,
    fecha_nacimiento  DATE,
    sexo              VARCHAR(10),
    telefono          VARCHAR(20),
    correo            VARCHAR(120),
    direccion         VARCHAR(200),
    creado_en         TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    -- RF02 Regla: no se permiten dos personas con el mismo CI.
    CONSTRAINT uq_persona_ci     UNIQUE (ci),
    CONSTRAINT ck_persona_ci     CHECK (ci IS NULL OR ci ~ '^[0-9]{5,12}([-A-Z0-9]{1,3})?$'),
    CONSTRAINT ck_persona_sexo   CHECK (sexo IS NULL OR sexo IN ('femenino','masculino','otro')),
    CONSTRAINT ck_persona_fnac   CHECK (fecha_nacimiento IS NULL OR fecha_nacimiento BETWEEN DATE '1900-01-01' AND CURRENT_DATE),
    CONSTRAINT ck_persona_correo CHECK (correo IS NULL OR correo ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    CONSTRAINT ck_persona_tel    CHECK (telefono IS NULL OR telefono ~ '^[0-9+\-\s]{7,20}$')
);

CREATE SEQUENCE seq_codigo_paciente START 1;

-- Subtipo PACIENTE: solo los datos propios del paciente.
CREATE TABLE paciente (
    id_paciente            INTEGER      PRIMARY KEY REFERENCES persona(id_persona) ON DELETE RESTRICT,
    codigo                 VARCHAR(10)  NOT NULL DEFAULT ('PAC-' || lpad(nextval('seq_codigo_paciente')::TEXT, 4, '0')),
    -- Información médica del perfil del paciente (punto 3.4 del caso)
    tipo_sangre            VARCHAR(3),
    alergias               VARCHAR(250),
    enfermedades_cronicas  VARCHAR(250),
    medicamentos           VARCHAR(250),     -- medicamentos de uso habitual (texto libre)
    antecedentes           VARCHAR(500),
    -- Preferencias de la cuenta del paciente
    recordatorio_cita      VARCHAR(8)   NOT NULL DEFAULT '24h',   -- ninguno, 1h, 24h o 24h_1h antes de la cita
    recordar_medicamentos  BOOLEAN      NOT NULL DEFAULT TRUE,    -- aviso por correo en cada toma
    compartir_info_medica  BOOLEAN      NOT NULL DEFAULT TRUE,
    estado                 VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en              TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en         TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_paciente_codigo UNIQUE (codigo),
    CONSTRAINT ck_paciente_sangre CHECK (tipo_sangre IS NULL OR tipo_sangre IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')),
    CONSTRAINT ck_paciente_recordatorio CHECK (recordatorio_cita IN ('ninguno','1h','24h','24h_1h')),
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
--  PAQUETE MÉDICOS: medico, especialidad, medico_especialidad,
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

-- Subtipo MEDICO: solo los datos propios del médico.
CREATE TABLE medico (
    id_medico             INTEGER      PRIMARY KEY REFERENCES persona(id_persona) ON DELETE RESTRICT,
    codigo                VARCHAR(10)  NOT NULL DEFAULT ('MED-' || lpad(nextval('seq_codigo_medico')::TEXT, 3, '0')),
    registro_profesional  VARCHAR(30)  NOT NULL,
    estado                VARCHAR(10)  NOT NULL DEFAULT 'activo',
    creado_en             TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_medico_codigo   UNIQUE (codigo),
    CONSTRAINT uq_medico_registro UNIQUE (registro_profesional),
    CONSTRAINT ck_medico_estado   CHECK (estado IN ('activo','inactivo'))
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
--  PAQUETE SEGURIDAD: rol, permiso, rol_permiso, usuario,
--                     token_recuperacion, bitacora
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

-- Cuenta de acceso. Toda cuenta pertenece a una persona (0..1 cuenta por
-- persona). El nombre que se muestra sale de PERSONA (vista v_usuario).
-- El trigger fn_tg_validar_usuario_rol exige que un usuario con rol
-- "medico" sea un médico y uno con rol "paciente" sea un paciente.
CREATE TABLE usuario (
    id_usuario         SERIAL       PRIMARY KEY,
    id_persona         INTEGER      NOT NULL REFERENCES persona(id_persona) ON DELETE RESTRICT,
    id_rol             SMALLINT     NOT NULL REFERENCES rol(id_rol),
    nombre_usuario     VARCHAR(40)  NOT NULL,
    correo             VARCHAR(120) NOT NULL,       -- correo de acceso y de recuperación de la contraseña
    password_hash      VARCHAR(255) NOT NULL,       -- generado con password_hash() de PHP
    estado             VARCHAR(10)  NOT NULL DEFAULT 'activo',
    intentos_fallidos  SMALLINT     NOT NULL DEFAULT 0,
    bloqueado_hasta    TIMESTAMPTZ,
    ultimo_acceso      TIMESTAMPTZ,
    creado_en          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    actualizado_en     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT uq_usuario_persona  UNIQUE (id_persona),
    CONSTRAINT uq_usuario_nombre   UNIQUE (nombre_usuario),
    CONSTRAINT uq_usuario_correo   UNIQUE (correo),
    CONSTRAINT ck_usuario_estado   CHECK (estado IN ('activo','inactivo','bloqueado')),
    CONSTRAINT ck_usuario_nombre   CHECK (nombre_usuario ~ '^[a-z0-9._]{4,40}$'),
    CONSTRAINT ck_usuario_correo   CHECK (correo ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$')
);

-- Recuperación de la contraseña por correo (CU01). Solo se guarda el hash
-- SHA-256 del token: quien lea la base no puede usar el enlace enviado.
-- Un token vence a los 30 minutos y se usa una sola vez (trigger).
CREATE TABLE token_recuperacion (
    id_token     SERIAL       PRIMARY KEY,
    id_usuario   INTEGER      NOT NULL REFERENCES usuario(id_usuario) ON DELETE CASCADE,
    token_hash   CHAR(64)     NOT NULL,
    creado_en    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    expira_en    TIMESTAMPTZ  NOT NULL DEFAULT now() + INTERVAL '30 minutes',
    usado_en     TIMESTAMPTZ,
    ip           VARCHAR(45),
    CONSTRAINT uq_token_hash        UNIQUE (token_hash),
    CONSTRAINT ck_token_vigencia    CHECK (expira_en > creado_en),
    CONSTRAINT ck_token_uso         CHECK (usado_en IS NULL OR usado_en >= creado_en)
);

-- Bitácora (CU02). Guarda dos tipos de registro:
--  1) acciones de la aplicación: inicios y cierres de sesión, intentos
--     fallidos, bloqueos y toda operación enviada por POST;
--  2) auditoría de datos: cada INSERT/UPDATE/DELETE de las tablas
--     principales, con los valores de antes y de después (trigger
--     fn_tg_auditoria). Nunca guarda contraseñas.
-- No se modifica ni se borra (trigger fn_tg_bitacora_inalterable).
CREATE TABLE bitacora (
    id_bitacora   BIGSERIAL    PRIMARY KEY,
    id_usuario    INTEGER      REFERENCES usuario(id_usuario) ON DELETE RESTRICT,
    usuario       VARCHAR(120),                 -- nombre de usuario, o lo escrito en un login fallido
    accion        VARCHAR(40)  NOT NULL,
    modulo        VARCHAR(30)  NOT NULL,
    detalle       TEXT,
    ip            VARCHAR(45),
    -- Auditoría de datos (vacíos en las acciones de la aplicación)
    tabla         VARCHAR(40),
    operacion     VARCHAR(10),
    id_registro   VARCHAR(40),
    datos_antes   JSONB,
    datos_despues JSONB,
    fecha_hora    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_bitacora_operacion CHECK (operacion IS NULL OR operacion IN ('INSERT','UPDATE','DELETE')),
    CONSTRAINT ck_bitacora_auditoria CHECK ((tabla IS NULL) = (operacion IS NULL))
);

-- =====================================================================
--  PAQUETE CITAS: estado_cita, estado_cita_transicion, cita,
--                 cita_historial
-- =====================================================================

-- Catálogo de estados de la cita (RF10).
CREATE TABLE estado_cita (
    codigo       VARCHAR(15)  PRIMARY KEY,
    nombre       VARCHAR(30)  NOT NULL,
    descripcion  VARCHAR(150) NOT NULL,
    orden        SMALLINT     NOT NULL,
    es_final     BOOLEAN      NOT NULL DEFAULT FALSE,
    CONSTRAINT uq_estado_cita_orden UNIQUE (orden)
);

-- Cambios de estado permitidos (máquina de estados de la cita).
-- solo_desde_fecha = el cambio solo se registra el día de la cita o después.
CREATE TABLE estado_cita_transicion (
    estado_origen    VARCHAR(15) NOT NULL REFERENCES estado_cita(codigo) ON DELETE CASCADE,
    estado_destino   VARCHAR(15) NOT NULL REFERENCES estado_cita(codigo) ON DELETE CASCADE,
    solo_desde_fecha BOOLEAN     NOT NULL DEFAULT FALSE,
    PRIMARY KEY (estado_origen, estado_destino),
    CONSTRAINT ck_transicion_distinta CHECK (estado_origen <> estado_destino)
);

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
    estado               VARCHAR(15)  NOT NULL DEFAULT 'programada' REFERENCES estado_cita(codigo),
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
    estado_anterior  VARCHAR(15)  REFERENCES estado_cita(codigo),
    estado_nuevo     VARCHAR(15)  REFERENCES estado_cita(codigo),
    fecha_anterior   DATE,
    hora_anterior    TIME,
    fecha_nueva      DATE,
    hora_nueva       TIME,
    detalle          VARCHAR(250),
    id_usuario       INTEGER      REFERENCES usuario(id_usuario),
    fecha_hora       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_historial_accion CHECK (accion IN ('registro','cambio_estado','reprogramacion'))
);

-- =====================================================================
--  PAQUETE ATENCIÓN: atencion, signo_vital, medicamento, receta,
--                    receta_detalle
-- =====================================================================

-- Registro clínico de una cita atendida (una atención por cita).
-- El médico y el paciente se obtienen de la cita (no se repiten aquí).
CREATE TABLE atencion (
    id_atencion          SERIAL        PRIMARY KEY,
    id_cita              INTEGER       NOT NULL REFERENCES cita(id_cita) ON DELETE RESTRICT,
    diagnostico          VARCHAR(500)  NOT NULL,
    tratamiento          VARCHAR(1000),
    observaciones        VARCHAR(1000),
    id_usuario_registro  INTEGER       REFERENCES usuario(id_usuario),
    fecha_registro       TIMESTAMPTZ   NOT NULL DEFAULT now(),
    actualizado_en       TIMESTAMPTZ   NOT NULL DEFAULT now(),
    CONSTRAINT uq_atencion_cita        UNIQUE (id_cita),
    CONSTRAINT ck_atencion_diagnostico CHECK (length(btrim(diagnostico)) >= 3)
);

-- Signos vitales tomados durante una atención (0..* por atención).
-- La presión se guarda en dos columnas (valores atómicos, 1FN) y el IMC
-- no se guarda: se calcula en la vista v_signo_vital (3FN).
CREATE TABLE signo_vital (
    id_signo_vital      SERIAL       PRIMARY KEY,
    id_atencion         INTEGER      NOT NULL REFERENCES atencion(id_atencion) ON DELETE RESTRICT,
    fecha_hora          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    peso_kg             NUMERIC(5,2),
    talla_cm            NUMERIC(4,1),
    presion_sistolica   SMALLINT,
    presion_diastolica  SMALLINT,
    temperatura_c       NUMERIC(3,1),
    frecuencia_cardiaca SMALLINT,
    saturacion_o2       SMALLINT,
    CONSTRAINT ck_signo_peso        CHECK (peso_kg IS NULL OR peso_kg BETWEEN 0.5 AND 400),
    CONSTRAINT ck_signo_talla       CHECK (talla_cm IS NULL OR talla_cm BETWEEN 30 AND 250),
    CONSTRAINT ck_signo_sistolica   CHECK (presion_sistolica IS NULL OR presion_sistolica BETWEEN 50 AND 260),
    CONSTRAINT ck_signo_diastolica  CHECK (presion_diastolica IS NULL OR presion_diastolica BETWEEN 30 AND 160),
    CONSTRAINT ck_signo_presion     CHECK ((presion_sistolica IS NULL) = (presion_diastolica IS NULL)
                                           AND (presion_sistolica IS NULL OR presion_sistolica > presion_diastolica)),
    CONSTRAINT ck_signo_temperatura CHECK (temperatura_c IS NULL OR temperatura_c BETWEEN 30 AND 45),
    CONSTRAINT ck_signo_frecuencia  CHECK (frecuencia_cardiaca IS NULL OR frecuencia_cardiaca BETWEEN 20 AND 250),
    CONSTRAINT ck_signo_saturacion  CHECK (saturacion_o2 IS NULL OR saturacion_o2 BETWEEN 50 AND 100),
    CONSTRAINT ck_signo_alguno      CHECK (num_nonnulls(peso_kg, talla_cm, presion_sistolica, temperatura_c,
                                                        frecuencia_cardiaca, saturacion_o2) > 0)
);

-- Catálogo de medicamentos que se pueden recetar.
CREATE TABLE medicamento (
    id_medicamento  SERIAL       PRIMARY KEY,
    nombre          VARCHAR(80)  NOT NULL,      -- nombre genérico
    presentacion    VARCHAR(30)  NOT NULL,
    concentracion   VARCHAR(40),
    estado          VARCHAR(10)  NOT NULL DEFAULT 'activo',
    CONSTRAINT ck_medicamento_presentacion CHECK (presentacion IN
        ('tableta','cápsula','jarabe','suspensión','gotas','inyectable','crema','pomada','inhalador','óvulo','sobre','solución')),
    CONSTRAINT ck_medicamento_estado CHECK (estado IN ('activo','inactivo'))
);
CREATE UNIQUE INDEX uq_medicamento
    ON medicamento (fn_normalizar_texto(nombre), presentacion, COALESCE(concentracion, ''));

CREATE SEQUENCE seq_codigo_receta START 1;

-- Receta emitida en una atención (0..1 por atención).
CREATE TABLE receta (
    id_receta      SERIAL       PRIMARY KEY,
    codigo         VARCHAR(10)  NOT NULL DEFAULT ('REC-' || lpad(nextval('seq_codigo_receta')::TEXT, 4, '0')),
    id_atencion    INTEGER      NOT NULL REFERENCES atencion(id_atencion) ON DELETE RESTRICT,
    fecha_emision  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    indicaciones   VARCHAR(500),
    enviada_en     TIMESTAMPTZ,                -- cuándo se envió al correo del paciente
    CONSTRAINT uq_receta_codigo   UNIQUE (codigo),
    CONSTRAINT uq_receta_atencion UNIQUE (id_atencion)
);

-- Cada medicamento de la receta con su posología. El número de tomas no
-- se guarda: se calcula (duracion_dias * 24 / frecuencia_horas).
CREATE TABLE receta_detalle (
    id_detalle         SERIAL       PRIMARY KEY,
    id_receta          INTEGER      NOT NULL REFERENCES receta(id_receta) ON DELETE CASCADE,
    id_medicamento     INTEGER      NOT NULL REFERENCES medicamento(id_medicamento) ON DELETE RESTRICT,
    dosis              VARCHAR(40)  NOT NULL,          -- p. ej. "1 tableta", "5 ml"
    via                VARCHAR(15)  NOT NULL DEFAULT 'oral',
    frecuencia_horas   SMALLINT     NOT NULL,
    duracion_dias      SMALLINT     NOT NULL,
    fecha_inicio       DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora_primera_toma  TIME         NOT NULL DEFAULT '08:00',
    indicaciones       VARCHAR(250),
    recordar           BOOLEAN      NOT NULL DEFAULT TRUE,   -- enviar recordatorios de cada toma
    CONSTRAINT uq_receta_medicamento UNIQUE (id_receta, id_medicamento),
    CONSTRAINT ck_detalle_via        CHECK (via IN ('oral','sublingual','tópica','inhalatoria','intramuscular',
                                                    'intravenosa','subcutánea','oftálmica','ótica','nasal','rectal','vaginal')),
    CONSTRAINT ck_detalle_frecuencia CHECK (frecuencia_horas BETWEEN 1 AND 168),
    CONSTRAINT ck_detalle_duracion   CHECK (duracion_dias BETWEEN 1 AND 365)
);

-- =====================================================================
--  PAQUETE NOTIFICACIONES: notificacion
-- =====================================================================

-- Cola de correos que envía el sistema (Brevo). Las de recordatorio se
-- crean solas con triggers; una tarea programada envía las pendientes
-- cuyo momento ya llegó. correo_destino guarda la dirección a la que se
-- envió (dato histórico: no cambia si la persona cambia su correo).
CREATE TABLE notificacion (
    id_notificacion  BIGSERIAL    PRIMARY KEY,
    tipo             VARCHAR(25)  NOT NULL,
    id_persona       INTEGER      NOT NULL REFERENCES persona(id_persona) ON DELETE RESTRICT,
    correo_destino   VARCHAR(120) NOT NULL,
    asunto           VARCHAR(150) NOT NULL,
    id_cita          INTEGER      REFERENCES cita(id_cita) ON DELETE RESTRICT,
    id_receta        INTEGER      REFERENCES receta(id_receta) ON DELETE RESTRICT,
    id_detalle       INTEGER      REFERENCES receta_detalle(id_detalle) ON DELETE CASCADE,
    programada_para  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    estado           VARCHAR(10)  NOT NULL DEFAULT 'pendiente',
    intentos         SMALLINT     NOT NULL DEFAULT 0,
    enviada_en       TIMESTAMPTZ,
    id_externo       VARCHAR(100),             -- identificador del mensaje en Brevo
    error            VARCHAR(250),
    creado_en        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_notificacion_tipo   CHECK (tipo IN ('recuperar_clave','cita_registrada','cita_reprogramada',
                                                      'cita_cancelada','recordatorio_cita','recordatorio_cita_1h',
                                                      'receta','toma_medicamento')),
    CONSTRAINT ck_notificacion_estado CHECK (estado IN ('pendiente','enviada','error','anulada')),
    CONSTRAINT ck_notificacion_correo CHECK (correo_destino ~* '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    CONSTRAINT ck_notificacion_enviada CHECK ((estado = 'enviada') = (enviada_en IS NOT NULL)),
    -- Cada tipo lleva la referencia que necesita su correo.
    CONSTRAINT ck_notificacion_referencia CHECK (
        (tipo NOT IN ('cita_registrada','cita_reprogramada','cita_cancelada','recordatorio_cita','recordatorio_cita_1h')
         OR id_cita IS NOT NULL)
        AND (tipo <> 'receta' OR id_receta IS NOT NULL)
        AND (tipo <> 'toma_medicamento' OR id_detalle IS NOT NULL))
);
-- Una toma de medicamento y un recordatorio de cita no se programan dos veces
-- (las anuladas no cuentan: se pueden volver a programar).
CREATE UNIQUE INDEX uq_notificacion_toma
    ON notificacion (id_detalle, programada_para) WHERE tipo = 'toma_medicamento' AND estado <> 'anulada';
CREATE UNIQUE INDEX uq_notificacion_recordatorio_cita
    ON notificacion (id_cita, tipo) WHERE tipo IN ('recordatorio_cita','recordatorio_cita_1h') AND estado <> 'anulada';

-- =====================================================================
--  ÍNDICES para las búsquedas más frecuentes
-- =====================================================================
CREATE INDEX ix_persona_apellidos      ON persona (lower(apellidos));
CREATE INDEX ix_persona_nombres        ON persona (lower(nombres));
CREATE INDEX ix_horario_medico_dia     ON horario_atencion (id_medico, dia_semana);
CREATE INDEX ix_cita_medico_fecha      ON cita (id_medico, fecha);
CREATE INDEX ix_cita_paciente          ON cita (id_paciente);
CREATE INDEX ix_cita_fecha_estado      ON cita (fecha, estado);
CREATE INDEX ix_cita_especialidad      ON cita (id_especialidad);
CREATE INDEX ix_historial_cita         ON cita_historial (id_cita);
CREATE INDEX ix_usuario_rol            ON usuario (id_rol);
CREATE INDEX ix_token_usuario          ON token_recuperacion (id_usuario, creado_en);
CREATE INDEX ix_bitacora_fecha_hora    ON bitacora (fecha_hora);
CREATE INDEX ix_bitacora_usuario       ON bitacora (id_usuario);
CREATE INDEX ix_bitacora_tabla         ON bitacora (tabla, id_registro);
CREATE INDEX ix_signo_atencion         ON signo_vital (id_atencion);
CREATE INDEX ix_detalle_receta         ON receta_detalle (id_receta);
CREATE INDEX ix_notificacion_pendiente ON notificacion (programada_para) WHERE estado = 'pendiente';
CREATE INDEX ix_notificacion_persona   ON notificacion (id_persona);
