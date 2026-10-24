-- =====================================================================
--  Archivo : 06_diccionario.sql
--  Propósito: diccionario de datos dentro de la propia base. Describe
--             cada tabla y cada columna con COMMENT ON; pgAdmin y Enterprise
--             Architect muestran estas descripciones, y el diccionario del
--             documento (basededato/NORMALIZACION.md) se genera de aquí.
-- =====================================================================

-- ---------------------------------------------------------------- personas
COMMENT ON TABLE persona IS 'Generalización de paciente, médico y personal: datos comunes de toda persona del sistema.';
COMMENT ON COLUMN persona.id_persona       IS 'Identificador de la persona (también es la llave del paciente y del médico).';
COMMENT ON COLUMN persona.ci               IS 'Cédula de identidad, única entre todas las personas (puede llevar complemento).';
COMMENT ON COLUMN persona.nombres          IS 'Nombres.';
COMMENT ON COLUMN persona.apellidos        IS 'Apellidos.';
COMMENT ON COLUMN persona.fecha_nacimiento IS 'Fecha de nacimiento (obligatoria para el paciente).';
COMMENT ON COLUMN persona.sexo             IS 'femenino, masculino u otro (obligatorio para el paciente).';
COMMENT ON COLUMN persona.telefono         IS 'Teléfono de contacto (obligatorio para paciente y médico).';
COMMENT ON COLUMN persona.correo           IS 'Correo de contacto; a él llegan los recordatorios (obligatorio para el médico).';
COMMENT ON COLUMN persona.direccion        IS 'Dirección de domicilio.';
COMMENT ON COLUMN persona.creado_en        IS 'Fecha y hora de registro.';
COMMENT ON COLUMN persona.actualizado_en   IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE paciente IS 'Subtipo de persona: datos médicos y preferencias del paciente (RF02).';
COMMENT ON COLUMN paciente.id_paciente           IS 'Llave del paciente = id_persona de su persona.';
COMMENT ON COLUMN paciente.codigo                IS 'Código único PAC-0001 (secuencia).';
COMMENT ON COLUMN paciente.tipo_sangre           IS 'Grupo y factor sanguíneo (A+, O-, ...).';
COMMENT ON COLUMN paciente.alergias              IS 'Alergias conocidas.';
COMMENT ON COLUMN paciente.enfermedades_cronicas IS 'Enfermedades crónicas.';
COMMENT ON COLUMN paciente.medicamentos          IS 'Medicamentos de uso habitual.';
COMMENT ON COLUMN paciente.antecedentes          IS 'Antecedentes médicos y familiares.';
COMMENT ON COLUMN paciente.recordatorio_cita     IS 'Recordatorio de citas por correo: ninguno, 1h, 24h o 24h_1h antes.';
COMMENT ON COLUMN paciente.recordar_medicamentos IS 'Si recibe un correo a la hora de cada toma de sus medicamentos.';
COMMENT ON COLUMN paciente.compartir_info_medica IS 'Si el médico puede ver su información médica en la agenda.';
COMMENT ON COLUMN paciente.estado                IS 'activo o inactivo (el inactivo conserva su historial).';
COMMENT ON COLUMN paciente.creado_en             IS 'Fecha y hora de registro como paciente.';
COMMENT ON COLUMN paciente.actualizado_en        IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE contacto_emergencia IS 'Persona a la que se avisa en una emergencia del paciente.';
COMMENT ON COLUMN contacto_emergencia.id_contacto IS 'Identificador.';
COMMENT ON COLUMN contacto_emergencia.id_paciente IS 'Paciente al que pertenece.';
COMMENT ON COLUMN contacto_emergencia.nombre      IS 'Nombre completo del contacto.';
COMMENT ON COLUMN contacto_emergencia.parentesco  IS 'Relación con el paciente (madre, esposo, ...).';
COMMENT ON COLUMN contacto_emergencia.telefono    IS 'Teléfono del contacto.';

-- ----------------------------------------------------------------- médicos
COMMENT ON TABLE medico IS 'Subtipo de persona: datos profesionales del médico (RF03).';
COMMENT ON COLUMN medico.id_medico            IS 'Llave del médico = id_persona de su persona.';
COMMENT ON COLUMN medico.codigo               IS 'Código único MED-001 (secuencia).';
COMMENT ON COLUMN medico.registro_profesional IS 'Matrícula profesional (única).';
COMMENT ON COLUMN medico.estado               IS 'activo o inactivo (el inactivo no recibe citas nuevas).';
COMMENT ON COLUMN medico.creado_en            IS 'Fecha y hora de registro como médico.';
COMMENT ON COLUMN medico.actualizado_en       IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE especialidad IS 'Especialidades que ofrece la clínica (RF04).';
COMMENT ON COLUMN especialidad.id_especialidad IS 'Identificador.';
COMMENT ON COLUMN especialidad.nombre          IS 'Nombre (único sin importar tildes ni mayúsculas).';
COMMENT ON COLUMN especialidad.descripcion     IS 'Descripción breve.';
COMMENT ON COLUMN especialidad.estado          IS 'activo o inactivo.';
COMMENT ON COLUMN especialidad.creado_en       IS 'Fecha y hora de registro.';
COMMENT ON COLUMN especialidad.actualizado_en  IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE medico_especialidad IS 'Clase asociativa: especialidades que atiende cada médico.';
COMMENT ON COLUMN medico_especialidad.id_medico       IS 'Médico.';
COMMENT ON COLUMN medico_especialidad.id_especialidad IS 'Especialidad que atiende.';

COMMENT ON TABLE horario_atencion IS 'Bloques semanales de atención de cada médico (RF05).';
COMMENT ON COLUMN horario_atencion.id_horario     IS 'Identificador.';
COMMENT ON COLUMN horario_atencion.id_medico      IS 'Médico.';
COMMENT ON COLUMN horario_atencion.dia_semana     IS 'Día ISO: 1 = lunes ... 7 = domingo.';
COMMENT ON COLUMN horario_atencion.hora_inicio    IS 'Hora de inicio del bloque.';
COMMENT ON COLUMN horario_atencion.hora_fin       IS 'Hora de fin del bloque (posterior al inicio).';
COMMENT ON COLUMN horario_atencion.duracion_cita  IS 'Minutos de cada cita: 15, 20, 30, 45 o 60.';
COMMENT ON COLUMN horario_atencion.estado         IS 'activo o inactivo.';
COMMENT ON COLUMN horario_atencion.creado_en      IS 'Fecha y hora de registro.';
COMMENT ON COLUMN horario_atencion.actualizado_en IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE dia_no_laborable IS 'Feriados y ausencias: fechas en que no se agendan citas.';
COMMENT ON COLUMN dia_no_laborable.id_dia_no_laborable IS 'Identificador.';
COMMENT ON COLUMN dia_no_laborable.fecha               IS 'Fecha bloqueada.';
COMMENT ON COLUMN dia_no_laborable.id_medico           IS 'Médico ausente; vacío = toda la clínica.';
COMMENT ON COLUMN dia_no_laborable.descripcion         IS 'Motivo (feriado, vacaciones, congreso...).';

-- --------------------------------------------------------------- seguridad
COMMENT ON TABLE rol IS 'Roles del sistema: administrador, recepcionista, médico y paciente.';
COMMENT ON COLUMN rol.id_rol      IS 'Identificador del rol.';
COMMENT ON COLUMN rol.nombre      IS 'administrador, recepcionista, medico o paciente.';
COMMENT ON COLUMN rol.descripcion IS 'Qué hace el rol.';

COMMENT ON TABLE permiso IS 'Permisos básicos; el menú y las operaciones se habilitan según ellos (RF01).';
COMMENT ON COLUMN permiso.id_permiso IS 'Identificador del permiso.';
COMMENT ON COLUMN permiso.codigo     IS 'Código que usa el sistema (p. ej. citas.programar).';
COMMENT ON COLUMN permiso.nombre     IS 'Descripción para el administrador.';
COMMENT ON COLUMN permiso.modulo     IS 'Paquete al que pertenece.';

COMMENT ON TABLE rol_permiso IS 'Clase asociativa: permisos asignados a cada rol.';
COMMENT ON COLUMN rol_permiso.id_rol     IS 'Rol que recibe el permiso.';
COMMENT ON COLUMN rol_permiso.id_permiso IS 'Permiso asignado.';

COMMENT ON TABLE usuario IS 'Cuenta de acceso; toda cuenta pertenece a una persona.';
COMMENT ON COLUMN usuario.id_usuario        IS 'Identificador de la cuenta.';
COMMENT ON COLUMN usuario.id_persona        IS 'Persona dueña de la cuenta (una cuenta por persona).';
COMMENT ON COLUMN usuario.id_rol            IS 'Rol de la cuenta.';
COMMENT ON COLUMN usuario.nombre_usuario    IS 'Usuario para iniciar sesión (único).';
COMMENT ON COLUMN usuario.correo            IS 'Correo de acceso y de recuperación de la contraseña (único).';
COMMENT ON COLUMN usuario.password_hash     IS 'Contraseña cifrada con password_hash() (bcrypt).';
COMMENT ON COLUMN usuario.estado            IS 'activo, inactivo o bloqueado.';
COMMENT ON COLUMN usuario.intentos_fallidos IS 'Intentos fallidos seguidos de inicio de sesión.';
COMMENT ON COLUMN usuario.bloqueado_hasta   IS 'Hasta cuándo está bloqueada tras varios intentos fallidos.';
COMMENT ON COLUMN usuario.ultimo_acceso     IS 'Último inicio de sesión correcto.';
COMMENT ON COLUMN usuario.creado_en         IS 'Fecha y hora de registro.';
COMMENT ON COLUMN usuario.actualizado_en    IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE token_recuperacion IS 'Enlaces de recuperación de la contraseña enviados por correo.';
COMMENT ON COLUMN token_recuperacion.id_token   IS 'Identificador.';
COMMENT ON COLUMN token_recuperacion.id_usuario IS 'Cuenta que pidió recuperar su contraseña.';
COMMENT ON COLUMN token_recuperacion.token_hash IS 'Hash SHA-256 del token enviado (el token no se guarda).';
COMMENT ON COLUMN token_recuperacion.creado_en  IS 'Cuándo se pidió.';
COMMENT ON COLUMN token_recuperacion.expira_en  IS 'Vencimiento (30 minutos después).';
COMMENT ON COLUMN token_recuperacion.usado_en   IS 'Cuándo se usó (un solo uso).';
COMMENT ON COLUMN token_recuperacion.ip         IS 'IP desde la que se pidió.';

COMMENT ON TABLE bitacora IS 'Acciones de los usuarios y auditoría de cambios de datos; no se modifica ni se borra.';
COMMENT ON COLUMN bitacora.id_bitacora   IS 'Identificador.';
COMMENT ON COLUMN bitacora.id_usuario    IS 'Usuario que hizo la acción (vacío si no inició sesión).';
COMMENT ON COLUMN bitacora.usuario       IS 'Nombre de usuario, o lo escrito en un inicio de sesión fallido.';
COMMENT ON COLUMN bitacora.accion        IS 'inicio_sesion, inicio_fallido, operacion, auditoria, ...';
COMMENT ON COLUMN bitacora.modulo        IS 'Paquete del sistema.';
COMMENT ON COLUMN bitacora.detalle       IS 'Descripción de lo que se hizo y su resultado.';
COMMENT ON COLUMN bitacora.ip            IS 'Dirección IP.';
COMMENT ON COLUMN bitacora.tabla         IS 'Auditoría: tabla modificada.';
COMMENT ON COLUMN bitacora.operacion     IS 'Auditoría: INSERT, UPDATE o DELETE.';
COMMENT ON COLUMN bitacora.id_registro   IS 'Auditoría: llave del registro modificado.';
COMMENT ON COLUMN bitacora.datos_antes   IS 'Auditoría: valores anteriores (solo las columnas que cambiaron).';
COMMENT ON COLUMN bitacora.datos_despues IS 'Auditoría: valores nuevos.';
COMMENT ON COLUMN bitacora.fecha_hora    IS 'Fecha y hora de la acción.';

-- ------------------------------------------------------------------- citas
COMMENT ON TABLE estado_cita IS 'Catálogo de estados de la cita (RF10).';
COMMENT ON COLUMN estado_cita.codigo      IS 'Código del estado (programada, confirmada, atendida, cancelada, inasistencia).';
COMMENT ON COLUMN estado_cita.nombre      IS 'Nombre que se muestra.';
COMMENT ON COLUMN estado_cita.descripcion IS 'Qué significa el estado.';
COMMENT ON COLUMN estado_cita.orden       IS 'Orden para listados y reportes.';
COMMENT ON COLUMN estado_cita.es_final    IS 'Si es un estado final (la cita ya no cambia).';

COMMENT ON TABLE estado_cita_transicion IS 'Cambios de estado permitidos (máquina de estados de la cita).';
COMMENT ON COLUMN estado_cita_transicion.estado_origen    IS 'Estado actual.';
COMMENT ON COLUMN estado_cita_transicion.estado_destino   IS 'Estado al que puede pasar.';
COMMENT ON COLUMN estado_cita_transicion.solo_desde_fecha IS 'Solo desde el día de la cita (atendida, inasistencia).';

COMMENT ON TABLE cita IS 'Cita médica de un paciente con un médico en una especialidad (RF06-RF10).';
COMMENT ON COLUMN cita.id_cita             IS 'Identificador interno.';
COMMENT ON COLUMN cita.codigo              IS 'Código único CM-0001 (secuencia).';
COMMENT ON COLUMN cita.id_paciente         IS 'Paciente.';
COMMENT ON COLUMN cita.id_medico           IS 'Médico (FK compuesta con la especialidad).';
COMMENT ON COLUMN cita.id_especialidad     IS 'Especialidad que el médico atiende.';
COMMENT ON COLUMN cita.fecha               IS 'Fecha de la cita.';
COMMENT ON COLUMN cita.hora_inicio         IS 'Hora de inicio.';
COMMENT ON COLUMN cita.hora_fin            IS 'Hora de fin (posterior al inicio).';
COMMENT ON COLUMN cita.modalidad           IS 'presencial o virtual.';
COMMENT ON COLUMN cita.motivo              IS 'Motivo de la consulta.';
COMMENT ON COLUMN cita.estado              IS 'Estado actual (catálogo estado_cita).';
COMMENT ON COLUMN cita.motivo_cancelacion  IS 'Obligatorio si la cita se cancela.';
COMMENT ON COLUMN cita.id_usuario_registro IS 'Usuario que registró la cita.';
COMMENT ON COLUMN cita.fecha_registro      IS 'Cuándo se registró.';
COMMENT ON COLUMN cita.actualizado_en      IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE cita_historial IS 'Trazabilidad de cada cita: registro, cambios de estado y reprogramaciones.';
COMMENT ON COLUMN cita_historial.id_historial    IS 'Identificador.';
COMMENT ON COLUMN cita_historial.id_cita         IS 'Cita.';
COMMENT ON COLUMN cita_historial.accion          IS 'registro, cambio_estado o reprogramacion.';
COMMENT ON COLUMN cita_historial.estado_anterior IS 'Estado antes del cambio.';
COMMENT ON COLUMN cita_historial.estado_nuevo    IS 'Estado después del cambio.';
COMMENT ON COLUMN cita_historial.fecha_anterior  IS 'Fecha antes de reprogramar.';
COMMENT ON COLUMN cita_historial.hora_anterior   IS 'Hora antes de reprogramar.';
COMMENT ON COLUMN cita_historial.fecha_nueva     IS 'Fecha nueva.';
COMMENT ON COLUMN cita_historial.hora_nueva      IS 'Hora nueva.';
COMMENT ON COLUMN cita_historial.detalle         IS 'Motivo o descripción del cambio.';
COMMENT ON COLUMN cita_historial.id_usuario      IS 'Usuario que hizo el cambio.';
COMMENT ON COLUMN cita_historial.fecha_hora      IS 'Cuándo ocurrió.';

-- ---------------------------------------------------------------- atención
COMMENT ON TABLE atencion IS 'Registro clínico de una cita atendida (una atención por cita).';
COMMENT ON COLUMN atencion.id_atencion         IS 'Identificador.';
COMMENT ON COLUMN atencion.id_cita             IS 'Cita atendida (única).';
COMMENT ON COLUMN atencion.diagnostico         IS 'Diagnóstico del médico.';
COMMENT ON COLUMN atencion.tratamiento         IS 'Tratamiento indicado.';
COMMENT ON COLUMN atencion.observaciones       IS 'Observaciones de la consulta.';
COMMENT ON COLUMN atencion.id_usuario_registro IS 'Usuario (médico) que registró la atención.';
COMMENT ON COLUMN atencion.fecha_registro      IS 'Cuándo se registró.';
COMMENT ON COLUMN atencion.actualizado_en      IS 'Fecha y hora de la última modificación.';

COMMENT ON TABLE signo_vital IS 'Signos vitales tomados durante una atención (el IMC se calcula en v_signo_vital).';
COMMENT ON COLUMN signo_vital.id_signo_vital      IS 'Identificador.';
COMMENT ON COLUMN signo_vital.id_atencion         IS 'Atención en la que se tomaron.';
COMMENT ON COLUMN signo_vital.fecha_hora          IS 'Cuándo se tomaron.';
COMMENT ON COLUMN signo_vital.peso_kg             IS 'Peso en kilogramos (0,5 a 400).';
COMMENT ON COLUMN signo_vital.talla_cm            IS 'Talla en centímetros (30 a 250).';
COMMENT ON COLUMN signo_vital.presion_sistolica   IS 'Presión sistólica en mmHg (50 a 260).';
COMMENT ON COLUMN signo_vital.presion_diastolica  IS 'Presión diastólica en mmHg (30 a 160; menor que la sistólica).';
COMMENT ON COLUMN signo_vital.temperatura_c       IS 'Temperatura en °C (30 a 45).';
COMMENT ON COLUMN signo_vital.frecuencia_cardiaca IS 'Latidos por minuto (20 a 250).';
COMMENT ON COLUMN signo_vital.saturacion_o2       IS 'Saturación de oxígeno en % (50 a 100).';

COMMENT ON TABLE medicamento IS 'Catálogo de medicamentos que se pueden recetar.';
COMMENT ON COLUMN medicamento.id_medicamento IS 'Identificador.';
COMMENT ON COLUMN medicamento.nombre         IS 'Nombre genérico.';
COMMENT ON COLUMN medicamento.presentacion   IS 'tableta, cápsula, jarabe, suspensión, gotas, inyectable, crema, ...';
COMMENT ON COLUMN medicamento.concentracion  IS 'Concentración (500 mg, 120 mg/5 ml, ...).';
COMMENT ON COLUMN medicamento.estado         IS 'activo o inactivo.';

COMMENT ON TABLE receta IS 'Receta emitida en una atención (una por atención).';
COMMENT ON COLUMN receta.id_receta     IS 'Identificador.';
COMMENT ON COLUMN receta.codigo        IS 'Código único REC-0001 (secuencia).';
COMMENT ON COLUMN receta.id_atencion   IS 'Atención en la que se emitió (única).';
COMMENT ON COLUMN receta.fecha_emision IS 'Cuándo se emitió.';
COMMENT ON COLUMN receta.indicaciones  IS 'Indicaciones generales.';
COMMENT ON COLUMN receta.enviada_en    IS 'Cuándo se envió al correo del paciente.';

COMMENT ON TABLE receta_detalle IS 'Cada medicamento de la receta con su posología.';
COMMENT ON COLUMN receta_detalle.id_detalle        IS 'Identificador.';
COMMENT ON COLUMN receta_detalle.id_receta         IS 'Receta.';
COMMENT ON COLUMN receta_detalle.id_medicamento    IS 'Medicamento recetado (una vez por receta).';
COMMENT ON COLUMN receta_detalle.dosis             IS 'Cantidad por toma (1 tableta, 5 ml, ...).';
COMMENT ON COLUMN receta_detalle.via               IS 'Vía de administración (oral, tópica, ...).';
COMMENT ON COLUMN receta_detalle.frecuencia_horas  IS 'Cada cuántas horas (1 a 168).';
COMMENT ON COLUMN receta_detalle.duracion_dias     IS 'Durante cuántos días (1 a 365).';
COMMENT ON COLUMN receta_detalle.fecha_inicio      IS 'Desde qué día se toma.';
COMMENT ON COLUMN receta_detalle.hora_primera_toma IS 'Hora de la primera toma.';
COMMENT ON COLUMN receta_detalle.indicaciones      IS 'Indicaciones del medicamento.';
COMMENT ON COLUMN receta_detalle.recordar          IS 'Si se envían recordatorios por correo de cada toma.';

-- ---------------------------------------------------------- notificaciones
COMMENT ON TABLE notificacion IS 'Cola de correos del sistema (Brevo): recuperación, citas, recetas y recordatorios.';
COMMENT ON COLUMN notificacion.id_notificacion IS 'Identificador.';
COMMENT ON COLUMN notificacion.tipo            IS 'recuperar_clave, cita_registrada, recordatorio_cita (24 h), recordatorio_cita_1h, receta, toma_medicamento, ...';
COMMENT ON COLUMN notificacion.id_persona      IS 'Persona destinataria.';
COMMENT ON COLUMN notificacion.correo_destino  IS 'Dirección a la que se envió (dato histórico).';
COMMENT ON COLUMN notificacion.asunto          IS 'Asunto del correo.';
COMMENT ON COLUMN notificacion.id_cita         IS 'Cita relacionada (correos de citas).';
COMMENT ON COLUMN notificacion.id_receta       IS 'Receta relacionada (envío de la receta).';
COMMENT ON COLUMN notificacion.id_detalle      IS 'Medicamento relacionado (recordatorio de toma).';
COMMENT ON COLUMN notificacion.programada_para IS 'Cuándo debe enviarse.';
COMMENT ON COLUMN notificacion.estado          IS 'pendiente, enviada, error o anulada.';
COMMENT ON COLUMN notificacion.intentos        IS 'Intentos de envío realizados.';
COMMENT ON COLUMN notificacion.enviada_en      IS 'Cuándo se envió.';
COMMENT ON COLUMN notificacion.id_externo      IS 'Identificador del mensaje en Brevo.';
COMMENT ON COLUMN notificacion.error           IS 'Último error de envío.';
COMMENT ON COLUMN notificacion.creado_en       IS 'Cuándo se programó.';
