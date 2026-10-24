# Diseño de datos — normalización, mapeo y diccionario

Base de datos `clinica_vida_salud` (PostgreSQL), sección **6.3 Diseño de datos (lógico y físico)** del documento.
Contiene: el resumen del modelo, la comparación con el modelo anterior, las dependencias funcionales de cada tabla, el
análisis de las formas normales (1FN, 2FN, 3FN y FNBC), las decisiones de diseño, el mapeo de clases a tablas y el
diccionario de datos.

> El mapeo y el diccionario (al final) se generan del catálogo de PostgreSQL: coinciden siempre con la base instalada.

## 1. Resumen del modelo

24 tablas en 6 paquetes:

| Paquete | Tablas | Qué guarda |
|---|---|---|
| Personas | `persona`, `paciente`, `contacto_emergencia` | Datos comunes de toda persona; datos médicos del paciente y su contacto de emergencia |
| Médicos | `medico`, `especialidad`, `medico_especialidad`, `horario_atencion`, `dia_no_laborable` | Médicos, especialidades que atienden, horarios semanales y feriados o ausencias |
| Seguridad | `rol`, `permiso`, `rol_permiso`, `usuario`, `token_recuperacion`, `bitacora` | Cuentas, permisos por rol, recuperación de contraseña y bitácora con auditoría |
| Citas | `estado_cita`, `estado_cita_transicion`, `cita`, `cita_historial` | Citas, sus estados permitidos y la trazabilidad de cada cambio |
| Atención | `atencion`, `signo_vital`, `medicamento`, `receta`, `receta_detalle` | Registro clínico de la cita atendida, signos vitales y receta |
| Notificaciones | `notificacion` | Correos enviados y programados (recordatorios de citas y de medicamentos) |

**Generalización.** PERSONA es la superclase de PACIENTE y MEDICO, y toda cuenta de USUARIO pertenece a una persona.
Se implementa con una tabla por clase: `paciente.id_paciente` y `medico.id_medico` son a la vez llave primaria y llave
foránea hacia `persona.id_persona`. Una misma persona puede ser médico y paciente a la vez (por ejemplo, un médico que se
atiende en la clínica) sin repetir sus datos. La generalización es **incompleta y superpuesta**: hay personas que no son
ni paciente ni médico (el personal de recepción y el administrador) y una persona puede ser de los dos subtipos.

## 2. Del modelo anterior al nuevo

| Problema del modelo anterior (14 tablas) | Anomalía que provocaba | Solución en el modelo nuevo |
|---|---|---|
| `paciente` y `medico` repetían `nombres`, `apellidos`, `telefono` y `correo` | Si un médico también era paciente, sus datos se guardaban dos veces y podían quedar distintos (actualización) | Tabla `persona` con los datos comunes; los subtipos solo guardan lo propio |
| `usuario.nombre_completo` repetía el nombre de la persona | Al corregir el nombre de un médico había que corregirlo también en su cuenta (actualización) | Se eliminó: el nombre se obtiene de `persona` (vista `v_usuario`) |
| `usuario` tenía `id_medico` e `id_paciente`, con un `CHECK` para que no estén los dos | Dos columnas para un mismo concepto («de quién es la cuenta») | Una sola FK `usuario.id_persona` (única); el trigger `fn_tg_validar_usuario_rol` verifica el rol |
| El CI era único solo entre pacientes | El mismo CI podía pertenecer a un paciente y a un médico distintos | `UNIQUE (persona.ci)`: el CI identifica a la persona en todo el sistema |
| Los estados de la cita y sus cambios permitidos estaban escritos en un `CHECK` y en el código del trigger | Agregar o renombrar un estado obligaba a cambiar la estructura de la base | Catálogos `estado_cita` y `estado_cita_transicion`; `cita.estado` es una FK |
| La bitácora solo guardaba la acción, sin los datos | No se sabía qué valor tenía un dato antes de un cambio | Columnas de auditoría (`tabla`, `operacion`, `id_registro`, `datos_antes`, `datos_despues`) llenadas por un trigger |

## 3. Dependencias funcionales

Se indica la llave primaria (PK), las llaves candidatas (LC) y las dependencias funcionales (DF) no triviales. En todas
las tablas, **todo determinante es una llave candidata**.

| Tabla | Llaves | Dependencias funcionales |
|---|---|---|
| PERSONA | PK `id_persona`; LC `ci` | `id_persona` → ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion, creado_en, actualizado_en · `ci` → id_persona |
| PACIENTE | PK `id_paciente`; LC `codigo` | `id_paciente` → codigo, tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes, recibir_recordatorios, compartir_info_medica, estado, creado_en, actualizado_en · `codigo` → id_paciente |
| CONTACTO_EMERGENCIA | PK `id_contacto` | `id_contacto` → id_paciente, nombre, parentesco, telefono |
| MEDICO | PK `id_medico`; LC `codigo`, `registro_profesional` | `id_medico` → codigo, registro_profesional, estado, creado_en, actualizado_en · `codigo` → id_medico · `registro_profesional` → id_medico |
| ESPECIALIDAD | PK `id_especialidad`; LC nombre normalizado | `id_especialidad` → nombre, descripcion, estado, creado_en, actualizado_en |
| MEDICO_ESPECIALIDAD | PK (`id_medico`, `id_especialidad`) | Solo la llave (relación de muchos a muchos) |
| HORARIO_ATENCION | PK `id_horario` | `id_horario` → id_medico, dia_semana, hora_inicio, hora_fin, duracion_cita, estado, creado_en, actualizado_en |
| DIA_NO_LABORABLE | PK `id_dia_no_laborable`; LC (`fecha`, `id_medico`) | `id_dia_no_laborable` → fecha, id_medico, descripcion · (fecha, id_medico) → descripcion |
| ROL | PK `id_rol`; LC `nombre` | `id_rol` → nombre, descripcion |
| PERMISO | PK `id_permiso`; LC `codigo` | `id_permiso` → codigo, nombre, modulo |
| ROL_PERMISO | PK (`id_rol`, `id_permiso`) | Solo la llave |
| USUARIO | PK `id_usuario`; LC `id_persona`, `nombre_usuario`, `correo` | `id_usuario` → id_persona, id_rol, nombre_usuario, correo, password_hash, estado, intentos_fallidos, bloqueado_hasta, ultimo_acceso, creado_en, actualizado_en |
| TOKEN_RECUPERACION | PK `id_token`; LC `token_hash` | `id_token` → id_usuario, token_hash, creado_en, expira_en, usado_en, ip |
| BITACORA | PK `id_bitacora` | `id_bitacora` → todas las demás columnas (cada fila es un hecho único e inalterable) |
| ESTADO_CITA | PK `codigo`; LC `orden` | `codigo` → nombre, descripcion, orden, es_final |
| ESTADO_CITA_TRANSICION | PK (`estado_origen`, `estado_destino`) | (estado_origen, estado_destino) → solo_desde_fecha |
| CITA | PK `id_cita`; LC `codigo` | `id_cita` → codigo, id_paciente, id_medico, id_especialidad, fecha, hora_inicio, hora_fin, modalidad, motivo, estado, motivo_cancelacion, id_usuario_registro, fecha_registro, actualizado_en |
| CITA_HISTORIAL | PK `id_historial` | `id_historial` → id_cita, accion, estado_anterior, estado_nuevo, fecha_anterior, hora_anterior, fecha_nueva, hora_nueva, detalle, id_usuario, fecha_hora |
| ATENCION | PK `id_atencion`; LC `id_cita` | `id_atencion` → id_cita, diagnostico, tratamiento, observaciones, id_usuario_registro, fecha_registro, actualizado_en |
| SIGNO_VITAL | PK `id_signo_vital` | `id_signo_vital` → id_atencion, fecha_hora, peso_kg, talla_cm, presion_sistolica, presion_diastolica, temperatura_c, frecuencia_cardiaca, saturacion_o2 |
| MEDICAMENTO | PK `id_medicamento`; LC (nombre normalizado, presentacion, concentracion) | `id_medicamento` → nombre, presentacion, concentracion, estado |
| RECETA | PK `id_receta`; LC `codigo`, `id_atencion` | `id_receta` → codigo, id_atencion, fecha_emision, indicaciones, enviada_en |
| RECETA_DETALLE | PK `id_detalle`; LC (`id_receta`, `id_medicamento`) | `id_detalle` → id_receta, id_medicamento, dosis, via, frecuencia_horas, duracion_dias, fecha_inicio, hora_primera_toma, indicaciones, recordar |
| NOTIFICACION | PK `id_notificacion` | `id_notificacion` → tipo, id_persona, correo_destino, asunto, id_cita, id_receta, id_detalle, programada_para, estado, intentos, enviada_en, id_externo, error, creado_en |

## 4. Formas normales

| Forma normal | Condición | Cómo se cumple |
|---|---|---|
| **1FN** | Valores atómicos, sin grupos repetitivos, toda tabla con llave primaria | Nombres y apellidos están separados; la presión arterial se guarda en dos columnas (`presion_sistolica`, `presion_diastolica`) y no como el texto «120/80»; los medicamentos de una receta no se guardan en una lista dentro de la receta sino como filas de `receta_detalle`; los teléfonos y correos son un solo valor por columna. Las 24 tablas tienen PK. |
| **2FN** | 1FN y ningún atributo depende de solo una parte de una llave compuesta | Las tablas con PK compuesta (`medico_especialidad`, `rol_permiso`, `estado_cita_transicion`) no tienen atributos o tienen solo uno que depende de toda la llave (`solo_desde_fecha` depende del par origen–destino). En `receta_detalle` la PK es simple (`id_detalle`) y la dosis, la frecuencia y la duración dependen del medicamento **en esa receta**, no solo del medicamento. |
| **3FN** | 2FN y sin dependencias transitivas (un atributo no clave no depende de otro no clave) | `atencion` no repite el médico ni el paciente: se obtienen de su cita. `cita` no guarda el nombre del médico ni del paciente (vista `v_cita_detalle`). El nombre de la especialidad, del estado y del medicamento están solo en su catálogo. Los datos derivados no se guardan: el IMC (`v_signo_vital`), el número de tomas de un medicamento (`v_receta_detalle`), la edad del paciente y el nombre completo (`v_usuario`). |
| **FNBC** | Todo determinante es una llave candidata | En la tabla de dependencias funcionales (sección 3) cada determinante es la PK o una llave candidata declarada con `UNIQUE` (`ci`, `codigo`, `registro_profesional`, `nombre_usuario`, `token_hash`, `id_cita` en `atencion`, `id_atencion` en `receta`...). No hay dependencias entre atributos que no sean llaves. |

### Casos que se revisaron en detalle

| Caso | Decisión | Por qué cumple |
|---|---|---|
| `cita.hora_fin` podría calcularse con la duración del horario | Se guarda | La duración de las citas puede cambiar después; la hora de fin es un hecho de esa cita en el momento de reservarla, no un dato derivado del horario actual. |
| `cita` tiene `id_medico` e `id_especialidad` | FK compuesta hacia `medico_especialidad` | La pareja garantiza que el médico atiende esa especialidad; ninguno de los dos depende del otro. |
| `cita_historial.fecha_anterior`, `estado_anterior`... | Se guardan | Son el registro histórico de un cambio (un hecho distinto por fila), no una copia del estado actual de la cita. |
| `notificacion.correo_destino` y `asunto` | Se guardan | Registran a qué dirección y con qué asunto **se envió** el correo; si la persona cambia de correo, el historial de envíos no debe cambiar. |
| `bitacora.usuario` | Se guarda | En un inicio de sesión fallido es lo que se escribió (puede no existir la cuenta); en los demás casos es una constancia inalterable. |
| `bitacora.datos_antes` / `datos_despues` (JSONB) | Se aceptan | Es un registro de auditoría de solo lectura: guarda una «foto» de las columnas que cambiaron en cualquier tabla. El sistema no busca ni actualiza valores dentro de esos datos, así que no hay anomalías. |
| `usuario.correo` y `persona.correo` | Son atributos distintos | El primero es el correo de acceso y de recuperación de la cuenta (único entre cuentas); el segundo es el correo de contacto de la persona. Pueden ser diferentes (por ejemplo, el correo del padre para un paciente menor). |
| `paciente.alergias`, `enfermedades_cronicas`, `medicamentos` | Texto libre | Son notas clínicas que se registran y se leen completas; el sistema no consulta alergias individuales. Si en el futuro se necesita (por ejemplo, alertar una alergia al recetar), se pasarían a una tabla `paciente_alergia`. |
| `contacto_emergencia` no es una `persona` | Se deja como tabla dependiente del paciente | El contacto no es un actor del sistema ni necesita CI o cuenta; solo se guarda cómo ubicarlo. |
| Columnas `codigo` (PAC-0001, MED-001, CM-0001, REC-0001) | Llaves alternativas | Son identificadores legibles para el usuario, únicos y generados por secuencia; no se repiten en otras tablas. |

## 5. Integridad que garantiza la base

Además de las llaves, la base hace cumplir las reglas aunque alguien se salte la aplicación: 48 restricciones `CHECK`,
16 `UNIQUE`, 3 `EXCLUDE` (citas y horarios que no se cruzan), 33 llaves foráneas y 34 triggers (validación de citas,
transiciones del catálogo, datos obligatorios por subtipo, historial, auditoría, token de un solo uso y recordatorios).
El detalle está en [sql/LEEME.md](sql/LEEME.md) y se verifica con las 25 pruebas de `sql/pruebas/pruebas_bd.sql`.

## Mapeo de clases a tablas

Cada clase persistente del diagrama de clases es una tabla. Las asociaciones se convierten en llaves foráneas; las de muchos a muchos, en tablas asociativas; y la generalización PERSONA → PACIENTE/MEDICO, en una tabla por clase donde el subtipo usa la llave de su persona como PK y FK.

| Paquete | Clase | Tabla | PK | FK | Demás columnas |
|---|---|---|---|---|---|
| Personas | Persona | PERSONA | id_persona | — | ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo, direccion, creado_en, actualizado_en |
| Personas | Paciente | PACIENTE | id_paciente | id_paciente → persona | codigo, tipo_sangre, alergias, enfermedades_cronicas, medicamentos, antecedentes, recibir_recordatorios, compartir_info_medica, estado, creado_en, actualizado_en |
| Personas | ContactoEmergencia | CONTACTO_EMERGENCIA | id_contacto | id_paciente → paciente | nombre, parentesco, telefono |
| Médicos | Medico | MEDICO | id_medico | id_medico → persona | codigo, registro_profesional, estado, creado_en, actualizado_en |
| Médicos | Especialidad | ESPECIALIDAD | id_especialidad | — | nombre, descripcion, estado, creado_en, actualizado_en |
| Médicos | MedicoEspecialidad | MEDICO_ESPECIALIDAD | id_medico, id_especialidad | id_medico → medico, id_especialidad → especialidad | — |
| Médicos | HorarioAtencion | HORARIO_ATENCION | id_horario | id_medico → medico | dia_semana, hora_inicio, hora_fin, duracion_cita, estado, creado_en, actualizado_en |
| Médicos | DiaNoLaborable | DIA_NO_LABORABLE | id_dia_no_laborable | id_medico → medico | fecha, descripcion |
| Seguridad | Rol | ROL | id_rol | — | nombre, descripcion |
| Seguridad | Permiso | PERMISO | id_permiso | — | codigo, nombre, modulo |
| Seguridad | RolPermiso | ROL_PERMISO | id_rol, id_permiso | id_rol → rol, id_permiso → permiso | — |
| Seguridad | Usuario | USUARIO | id_usuario | id_persona → persona, id_rol → rol | nombre_usuario, correo, password_hash, estado, intentos_fallidos, bloqueado_hasta, ultimo_acceso, creado_en, actualizado_en |
| Seguridad | TokenRecuperacion | TOKEN_RECUPERACION | id_token | id_usuario → usuario | token_hash, creado_en, expira_en, usado_en, ip |
| Seguridad | Bitacora | BITACORA | id_bitacora | id_usuario → usuario | usuario, accion, modulo, detalle, ip, tabla, operacion, id_registro, datos_antes, datos_despues, fecha_hora |
| Citas | EstadoCita | ESTADO_CITA | codigo | — | nombre, descripcion, orden, es_final |
| Citas | EstadoCitaTransicion | ESTADO_CITA_TRANSICION | estado_origen, estado_destino | estado_origen → estado_cita, estado_destino → estado_cita | solo_desde_fecha |
| Citas | Cita | CITA | id_cita | id_paciente → paciente, id_medico → medico_especialidad, id_especialidad → medico_especialidad, estado → estado_cita, id_usuario_registro → usuario | codigo, fecha, hora_inicio, hora_fin, modalidad, motivo, motivo_cancelacion, fecha_registro, actualizado_en |
| Citas | CitaHistorial | CITA_HISTORIAL | id_historial | id_cita → cita, estado_anterior → estado_cita, estado_nuevo → estado_cita, id_usuario → usuario | accion, fecha_anterior, hora_anterior, fecha_nueva, hora_nueva, detalle, fecha_hora |
| Atención | Atencion | ATENCION | id_atencion | id_cita → cita, id_usuario_registro → usuario | diagnostico, tratamiento, observaciones, fecha_registro, actualizado_en |
| Atención | SignoVital | SIGNO_VITAL | id_signo_vital | id_atencion → atencion | fecha_hora, peso_kg, talla_cm, presion_sistolica, presion_diastolica, temperatura_c, frecuencia_cardiaca, saturacion_o2 |
| Atención | Medicamento | MEDICAMENTO | id_medicamento | — | nombre, presentacion, concentracion, estado |
| Atención | Receta | RECETA | id_receta | id_atencion → atencion | codigo, fecha_emision, indicaciones, enviada_en |
| Atención | RecetaDetalle | RECETA_DETALLE | id_detalle | id_receta → receta, id_medicamento → medicamento | dosis, via, frecuencia_horas, duracion_dias, fecha_inicio, hora_primera_toma, indicaciones, recordar |
| Notificaciones | Notificacion | NOTIFICACION | id_notificacion | id_persona → persona, id_cita → cita, id_receta → receta, id_detalle → receta_detalle | tipo, correo_destino, asunto, programada_para, estado, intentos, enviada_en, id_externo, error, creado_en |

## Diccionario de datos

Generado del catálogo de PostgreSQL (`06_diccionario.sql`): tipo, llave primaria (PK), llave foránea (FK con la tabla a la que apunta), obligatoriedad y descripción de cada columna.

### Paquete Personas

#### PERSONA

Generalización de paciente, médico y personal: datos comunes de toda persona del sistema.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_persona | serial | Sí | No | Sí | Identificador de la persona (también es la llave del paciente y del médico). |
| ci | varchar(15) | No | No | No | Cédula de identidad, única entre todas las personas (puede llevar complemento). |
| nombres | varchar(60) | No | No | Sí | Nombres. |
| apellidos | varchar(60) | No | No | Sí | Apellidos. |
| fecha_nacimiento | date | No | No | No | Fecha de nacimiento (obligatoria para el paciente). |
| sexo | varchar(10) | No | No | No | femenino, masculino u otro (obligatorio para el paciente). |
| telefono | varchar(20) | No | No | No | Teléfono de contacto (obligatorio para paciente y médico). |
| correo | varchar(120) | No | No | No | Correo de contacto; a él llegan los recordatorios (obligatorio para el médico). |
| direccion | varchar(200) | No | No | No | Dirección de domicilio. |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### PACIENTE

Subtipo de persona: datos médicos y preferencias del paciente (RF02).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_paciente | integer | Sí | Sí → persona | Sí | Llave del paciente = id_persona de su persona. |
| codigo | varchar(10) | No | No | Sí | Código único PAC-0001 (secuencia). |
| tipo_sangre | varchar(3) | No | No | No | Grupo y factor sanguíneo (A+, O-, ...). |
| alergias | varchar(250) | No | No | No | Alergias conocidas. |
| enfermedades_cronicas | varchar(250) | No | No | No | Enfermedades crónicas. |
| medicamentos | varchar(250) | No | No | No | Medicamentos de uso habitual. |
| antecedentes | varchar(500) | No | No | No | Antecedentes médicos y familiares. |
| recibir_recordatorios | boolean | No | No | Sí | Si acepta recordatorios por correo (citas y medicamentos). |
| compartir_info_medica | boolean | No | No | Sí | Si el médico puede ver su información médica en la agenda. |
| estado | varchar(10) | No | No | Sí | activo o inactivo (el inactivo conserva su historial). |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro como paciente. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### CONTACTO_EMERGENCIA

Persona a la que se avisa en una emergencia del paciente.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_contacto | serial | Sí | No | Sí | Identificador. |
| id_paciente | integer | No | Sí → paciente | Sí | Paciente al que pertenece. |
| nombre | varchar(120) | No | No | Sí | Nombre completo del contacto. |
| parentesco | varchar(40) | No | No | Sí | Relación con el paciente (madre, esposo, ...). |
| telefono | varchar(20) | No | No | Sí | Teléfono del contacto. |

### Paquete Médicos

#### MEDICO

Subtipo de persona: datos profesionales del médico (RF03).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_medico | integer | Sí | Sí → persona | Sí | Llave del médico = id_persona de su persona. |
| codigo | varchar(10) | No | No | Sí | Código único MED-001 (secuencia). |
| registro_profesional | varchar(30) | No | No | Sí | Matrícula profesional (única). |
| estado | varchar(10) | No | No | Sí | activo o inactivo (el inactivo no recibe citas nuevas). |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro como médico. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### ESPECIALIDAD

Especialidades que ofrece la clínica (RF04).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_especialidad | serial | Sí | No | Sí | Identificador. |
| nombre | varchar(80) | No | No | Sí | Nombre (único sin importar tildes ni mayúsculas). |
| descripcion | varchar(250) | No | No | No | Descripción breve. |
| estado | varchar(10) | No | No | Sí | activo o inactivo. |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### MEDICO_ESPECIALIDAD

Clase asociativa: especialidades que atiende cada médico.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_medico | integer | Sí | Sí → medico | Sí | Médico. |
| id_especialidad | integer | Sí | Sí → especialidad | Sí | Especialidad que atiende. |

#### HORARIO_ATENCION

Bloques semanales de atención de cada médico (RF05).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_horario | serial | Sí | No | Sí | Identificador. |
| id_medico | integer | No | Sí → medico | Sí | Médico. |
| dia_semana | smallint | No | No | Sí | Día ISO: 1 = lunes ... 7 = domingo. |
| hora_inicio | time | No | No | Sí | Hora de inicio del bloque. |
| hora_fin | time | No | No | Sí | Hora de fin del bloque (posterior al inicio). |
| duracion_cita | smallint | No | No | Sí | Minutos de cada cita: 15, 20, 30, 45 o 60. |
| estado | varchar(10) | No | No | Sí | activo o inactivo. |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### DIA_NO_LABORABLE

Feriados y ausencias: fechas en que no se agendan citas.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_dia_no_laborable | serial | Sí | No | Sí | Identificador. |
| fecha | date | No | No | Sí | Fecha bloqueada. |
| id_medico | integer | No | Sí → medico | No | Médico ausente; vacío = toda la clínica. |
| descripcion | varchar(120) | No | No | Sí | Motivo (feriado, vacaciones, congreso...). |

### Paquete Seguridad

#### ROL

Roles del sistema: administrador, recepcionista, médico y paciente.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_rol | smallserial | Sí | No | Sí | Identificador del rol. |
| nombre | varchar(30) | No | No | Sí | administrador, recepcionista, medico o paciente. |
| descripcion | varchar(150) | No | No | No | Qué hace el rol. |

#### PERMISO

Permisos básicos; el menú y las operaciones se habilitan según ellos (RF01).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_permiso | serial | Sí | No | Sí | Identificador del permiso. |
| codigo | varchar(50) | No | No | Sí | Código que usa el sistema (p. ej. citas.programar). |
| nombre | varchar(120) | No | No | Sí | Descripción para el administrador. |
| modulo | varchar(30) | No | No | Sí | Paquete al que pertenece. |

#### ROL_PERMISO

Clase asociativa: permisos asignados a cada rol.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_rol | smallint | Sí | Sí → rol | Sí | Rol que recibe el permiso. |
| id_permiso | integer | Sí | Sí → permiso | Sí | Permiso asignado. |

#### USUARIO

Cuenta de acceso; toda cuenta pertenece a una persona.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_usuario | serial | Sí | No | Sí | Identificador de la cuenta. |
| id_persona | integer | No | Sí → persona | Sí | Persona dueña de la cuenta (una cuenta por persona). |
| id_rol | smallint | No | Sí → rol | Sí | Rol de la cuenta. |
| nombre_usuario | varchar(40) | No | No | Sí | Usuario para iniciar sesión (único). |
| correo | varchar(120) | No | No | Sí | Correo de acceso y de recuperación de la contraseña (único). |
| password_hash | varchar(255) | No | No | Sí | Contraseña cifrada con password_hash() (bcrypt). |
| estado | varchar(10) | No | No | Sí | activo, inactivo o bloqueado. |
| intentos_fallidos | smallint | No | No | Sí | Intentos fallidos seguidos de inicio de sesión. |
| bloqueado_hasta | timestamptz | No | No | No | Hasta cuándo está bloqueada tras varios intentos fallidos. |
| ultimo_acceso | timestamptz | No | No | No | Último inicio de sesión correcto. |
| creado_en | timestamptz | No | No | Sí | Fecha y hora de registro. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### TOKEN_RECUPERACION

Enlaces de recuperación de la contraseña enviados por correo.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_token | serial | Sí | No | Sí | Identificador. |
| id_usuario | integer | No | Sí → usuario | Sí | Cuenta que pidió recuperar su contraseña. |
| token_hash | char(64) | No | No | Sí | Hash SHA-256 del token enviado (el token no se guarda). |
| creado_en | timestamptz | No | No | Sí | Cuándo se pidió. |
| expira_en | timestamptz | No | No | Sí | Vencimiento (30 minutos después). |
| usado_en | timestamptz | No | No | No | Cuándo se usó (un solo uso). |
| ip | varchar(45) | No | No | No | IP desde la que se pidió. |

#### BITACORA

Acciones de los usuarios y auditoría de cambios de datos; no se modifica ni se borra.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_bitacora | bigserial | Sí | No | Sí | Identificador. |
| id_usuario | integer | No | Sí → usuario | No | Usuario que hizo la acción (vacío si no inició sesión). |
| usuario | varchar(120) | No | No | No | Nombre de usuario, o lo escrito en un inicio de sesión fallido. |
| accion | varchar(40) | No | No | Sí | inicio_sesion, inicio_fallido, operacion, auditoria, ... |
| modulo | varchar(30) | No | No | Sí | Paquete del sistema. |
| detalle | text | No | No | No | Descripción de lo que se hizo y su resultado. |
| ip | varchar(45) | No | No | No | Dirección IP. |
| tabla | varchar(40) | No | No | No | Auditoría: tabla modificada. |
| operacion | varchar(10) | No | No | No | Auditoría: INSERT, UPDATE o DELETE. |
| id_registro | varchar(40) | No | No | No | Auditoría: llave del registro modificado. |
| datos_antes | jsonb | No | No | No | Auditoría: valores anteriores (solo las columnas que cambiaron). |
| datos_despues | jsonb | No | No | No | Auditoría: valores nuevos. |
| fecha_hora | timestamptz | No | No | Sí | Fecha y hora de la acción. |

### Paquete Citas

#### ESTADO_CITA

Catálogo de estados de la cita (RF10).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| codigo | varchar(15) | Sí | No | Sí | Código del estado (programada, confirmada, atendida, cancelada, inasistencia). |
| nombre | varchar(30) | No | No | Sí | Nombre que se muestra. |
| descripcion | varchar(150) | No | No | Sí | Qué significa el estado. |
| orden | smallint | No | No | Sí | Orden para listados y reportes. |
| es_final | boolean | No | No | Sí | Si es un estado final (la cita ya no cambia). |

#### ESTADO_CITA_TRANSICION

Cambios de estado permitidos (máquina de estados de la cita).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| estado_origen | varchar(15) | Sí | Sí → estado_cita | Sí | Estado actual. |
| estado_destino | varchar(15) | Sí | Sí → estado_cita | Sí | Estado al que puede pasar. |
| solo_desde_fecha | boolean | No | No | Sí | Solo desde el día de la cita (atendida, inasistencia). |

#### CITA

Cita médica de un paciente con un médico en una especialidad (RF06-RF10).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_cita | serial | Sí | No | Sí | Identificador interno. |
| codigo | varchar(10) | No | No | Sí | Código único CM-0001 (secuencia). |
| id_paciente | integer | No | Sí → paciente | Sí | Paciente. |
| id_medico | integer | No | Sí → medico_especialidad | Sí | Médico (FK compuesta con la especialidad). |
| id_especialidad | integer | No | Sí → medico_especialidad | Sí | Especialidad que el médico atiende. |
| fecha | date | No | No | Sí | Fecha de la cita. |
| hora_inicio | time | No | No | Sí | Hora de inicio. |
| hora_fin | time | No | No | Sí | Hora de fin (posterior al inicio). |
| modalidad | varchar(12) | No | No | Sí | presencial o virtual. |
| motivo | varchar(250) | No | No | No | Motivo de la consulta. |
| estado | varchar(15) | No | Sí → estado_cita | Sí | Estado actual (catálogo estado_cita). |
| motivo_cancelacion | varchar(250) | No | No | No | Obligatorio si la cita se cancela. |
| id_usuario_registro | integer | No | Sí → usuario | No | Usuario que registró la cita. |
| fecha_registro | timestamptz | No | No | Sí | Cuándo se registró. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### CITA_HISTORIAL

Trazabilidad de cada cita: registro, cambios de estado y reprogramaciones.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_historial | bigserial | Sí | No | Sí | Identificador. |
| id_cita | integer | No | Sí → cita | Sí | Cita. |
| accion | varchar(20) | No | No | Sí | registro, cambio_estado o reprogramacion. |
| estado_anterior | varchar(15) | No | Sí → estado_cita | No | Estado antes del cambio. |
| estado_nuevo | varchar(15) | No | Sí → estado_cita | No | Estado después del cambio. |
| fecha_anterior | date | No | No | No | Fecha antes de reprogramar. |
| hora_anterior | time | No | No | No | Hora antes de reprogramar. |
| fecha_nueva | date | No | No | No | Fecha nueva. |
| hora_nueva | time | No | No | No | Hora nueva. |
| detalle | varchar(250) | No | No | No | Motivo o descripción del cambio. |
| id_usuario | integer | No | Sí → usuario | No | Usuario que hizo el cambio. |
| fecha_hora | timestamptz | No | No | Sí | Cuándo ocurrió. |

### Paquete Atención

#### ATENCION

Registro clínico de una cita atendida (una atención por cita).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_atencion | serial | Sí | No | Sí | Identificador. |
| id_cita | integer | No | Sí → cita | Sí | Cita atendida (única). |
| diagnostico | varchar(500) | No | No | Sí | Diagnóstico del médico. |
| tratamiento | varchar(1000) | No | No | No | Tratamiento indicado. |
| observaciones | varchar(1000) | No | No | No | Observaciones de la consulta. |
| id_usuario_registro | integer | No | Sí → usuario | No | Usuario (médico) que registró la atención. |
| fecha_registro | timestamptz | No | No | Sí | Cuándo se registró. |
| actualizado_en | timestamptz | No | No | Sí | Fecha y hora de la última modificación. |

#### SIGNO_VITAL

Signos vitales tomados durante una atención (el IMC se calcula en v_signo_vital).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_signo_vital | serial | Sí | No | Sí | Identificador. |
| id_atencion | integer | No | Sí → atencion | Sí | Atención en la que se tomaron. |
| fecha_hora | timestamptz | No | No | Sí | Cuándo se tomaron. |
| peso_kg | numeric(5,2) | No | No | No | Peso en kilogramos (0,5 a 400). |
| talla_cm | numeric(4,1) | No | No | No | Talla en centímetros (30 a 250). |
| presion_sistolica | smallint | No | No | No | Presión sistólica en mmHg (50 a 260). |
| presion_diastolica | smallint | No | No | No | Presión diastólica en mmHg (30 a 160; menor que la sistólica). |
| temperatura_c | numeric(3,1) | No | No | No | Temperatura en °C (30 a 45). |
| frecuencia_cardiaca | smallint | No | No | No | Latidos por minuto (20 a 250). |
| saturacion_o2 | smallint | No | No | No | Saturación de oxígeno en % (50 a 100). |

#### MEDICAMENTO

Catálogo de medicamentos que se pueden recetar.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_medicamento | serial | Sí | No | Sí | Identificador. |
| nombre | varchar(80) | No | No | Sí | Nombre genérico. |
| presentacion | varchar(30) | No | No | Sí | tableta, cápsula, jarabe, suspensión, gotas, inyectable, crema, ... |
| concentracion | varchar(40) | No | No | No | Concentración (500 mg, 120 mg/5 ml, ...). |
| estado | varchar(10) | No | No | Sí | activo o inactivo. |

#### RECETA

Receta emitida en una atención (una por atención).

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_receta | serial | Sí | No | Sí | Identificador. |
| codigo | varchar(10) | No | No | Sí | Código único REC-0001 (secuencia). |
| id_atencion | integer | No | Sí → atencion | Sí | Atención en la que se emitió (única). |
| fecha_emision | timestamptz | No | No | Sí | Cuándo se emitió. |
| indicaciones | varchar(500) | No | No | No | Indicaciones generales. |
| enviada_en | timestamptz | No | No | No | Cuándo se envió al correo del paciente. |

#### RECETA_DETALLE

Cada medicamento de la receta con su posología.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_detalle | serial | Sí | No | Sí | Identificador. |
| id_receta | integer | No | Sí → receta | Sí | Receta. |
| id_medicamento | integer | No | Sí → medicamento | Sí | Medicamento recetado (una vez por receta). |
| dosis | varchar(40) | No | No | Sí | Cantidad por toma (1 tableta, 5 ml, ...). |
| via | varchar(15) | No | No | Sí | Vía de administración (oral, tópica, ...). |
| frecuencia_horas | smallint | No | No | Sí | Cada cuántas horas (1 a 168). |
| duracion_dias | smallint | No | No | Sí | Durante cuántos días (1 a 365). |
| fecha_inicio | date | No | No | Sí | Desde qué día se toma. |
| hora_primera_toma | time | No | No | Sí | Hora de la primera toma. |
| indicaciones | varchar(250) | No | No | No | Indicaciones del medicamento. |
| recordar | boolean | No | No | Sí | Si se envían recordatorios por correo de cada toma. |

### Paquete Notificaciones

#### NOTIFICACION

Cola de correos del sistema (Brevo): recuperación, citas, recetas y recordatorios.

| Atributo | Tipo de dato | PK | FK | Not Null | Descripción |
|---|---|---|---|---|---|
| id_notificacion | bigserial | Sí | No | Sí | Identificador. |
| tipo | varchar(25) | No | No | Sí | recuperar_clave, cita_registrada, recordatorio_cita, receta, toma_medicamento, ... |
| id_persona | integer | No | Sí → persona | Sí | Persona destinataria. |
| correo_destino | varchar(120) | No | No | Sí | Dirección a la que se envió (dato histórico). |
| asunto | varchar(150) | No | No | Sí | Asunto del correo. |
| id_cita | integer | No | Sí → cita | No | Cita relacionada (correos de citas). |
| id_receta | integer | No | Sí → receta | No | Receta relacionada (envío de la receta). |
| id_detalle | integer | No | Sí → receta_detalle | No | Medicamento relacionado (recordatorio de toma). |
| programada_para | timestamptz | No | No | Sí | Cuándo debe enviarse. |
| estado | varchar(10) | No | No | Sí | pendiente, enviada, error o anulada. |
| intentos | smallint | No | No | Sí | Intentos de envío realizados. |
| enviada_en | timestamptz | No | No | No | Cuándo se envió. |
| id_externo | varchar(100) | No | No | No | Identificador del mensaje en Brevo. |
| error | varchar(250) | No | No | No | Último error de envío. |
| creado_en | timestamptz | No | No | Sí | Cuándo se programó. |

