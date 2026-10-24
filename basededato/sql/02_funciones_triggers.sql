-- =====================================================================
--  Archivo : 02_funciones_triggers.sql
--  Propósito: funciones y disparadores (triggers) que protegen la
--             integridad de los datos aunque alguien intente saltarse
--             la aplicación (segunda barrera de las reglas de negocio).
--  Nota     : la aplicación PHP valida primero (CU09, CU10, CU11, CU12);
--             estas funciones son la defensa final en el servidor de BD.
--
--  Variables de sesión que envía la aplicación (BaseDatos.php):
--    app.id_usuario  usuario que hace el cambio (historial y auditoría)
--    app.ip          IP desde la que se hace (auditoría)
--    app.detalle     detalle opcional para el historial de la cita
--  Variables solo para cargas masivas (05_datos_prueba.sql):
--    app.sin_auditoria = 'on'       no registra auditoría
--    app.sin_notificaciones = 'on'  no programa correos
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Actualiza la columna actualizado_en en cada UPDATE.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_actualizar_fecha()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.actualizado_en := now();
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_persona_actualizar BEFORE UPDATE ON persona
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_paciente_actualizar BEFORE UPDATE ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_medico_actualizar BEFORE UPDATE ON medico
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_especialidad_actualizar BEFORE UPDATE ON especialidad
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_horario_actualizar BEFORE UPDATE ON horario_atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_usuario_actualizar BEFORE UPDATE ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_cita_actualizar BEFORE UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_atencion_actualizar BEFORE UPDATE ON atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();

-- ---------------------------------------------------------------------
-- 2) Datos obligatorios según el subtipo de la persona (generalización).
--    Paciente: fecha de nacimiento, sexo y teléfono (RF02).
--    Médico:   teléfono y correo, y el correo no se repite entre médicos (RF03).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_validar_datos_persona(p_id INTEGER, p_es_paciente BOOLEAN, p_es_medico BOOLEAN)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE
    v persona%ROWTYPE;
BEGIN
    SELECT * INTO v FROM persona WHERE id_persona = p_id;
    IF NOT FOUND THEN
        RETURN;                      -- la FK hacia persona informa el error
    END IF;
    IF p_es_paciente AND (v.fecha_nacimiento IS NULL OR v.sexo IS NULL OR v.telefono IS NULL) THEN
        RAISE EXCEPTION 'RF02: un paciente debe tener fecha de nacimiento, sexo y teléfono.';
    END IF;
    IF p_es_medico THEN
        IF v.telefono IS NULL OR v.correo IS NULL THEN
            RAISE EXCEPTION 'RF03: un médico debe tener teléfono y correo.';
        END IF;
        IF EXISTS (SELECT 1 FROM medico m JOIN persona p ON p.id_persona = m.id_medico
                    WHERE lower(p.correo) = lower(v.correo) AND m.id_medico <> p_id) THEN
            RAISE EXCEPTION 'RF03: ya existe un médico con ese correo.';
        END IF;
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION fn_tg_validar_subtipo()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF TG_TABLE_NAME = 'paciente' THEN
        PERFORM fn_validar_datos_persona(NEW.id_paciente, TRUE,
                EXISTS (SELECT 1 FROM medico WHERE id_medico = NEW.id_paciente));
    ELSE
        PERFORM fn_validar_datos_persona(NEW.id_medico,
                EXISTS (SELECT 1 FROM paciente WHERE id_paciente = NEW.id_medico), TRUE);
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_paciente_validar_persona BEFORE INSERT ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_subtipo();
CREATE TRIGGER tg_medico_validar_persona BEFORE INSERT ON medico
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_subtipo();

-- Al modificar una persona se vuelve a comprobar lo que exige su subtipo.
CREATE OR REPLACE FUNCTION fn_tg_validar_persona()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    PERFORM fn_validar_datos_persona(NEW.id_persona,
            EXISTS (SELECT 1 FROM paciente WHERE id_paciente = NEW.id_persona),
            EXISTS (SELECT 1 FROM medico   WHERE id_medico   = NEW.id_persona));
    RETURN NULL;
END;
$$;

CREATE TRIGGER tg_persona_validar AFTER UPDATE ON persona
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_persona();

-- ---------------------------------------------------------------------
-- 3) Valida una cita al registrarla o reprogramarla:
--    RN04 médico activo · RN05 dentro del horario de atención ·
--    RN06 no en fechas pasadas · paciente activo · día no bloqueado ·
--    solo se reprograman citas en un estado que no es final (RF08).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_cita()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_estado_medico   VARCHAR(10);
    v_estado_paciente VARCHAR(10);
    v_estado_esp      VARCHAR(10);
    v_horario_valido  BOOLEAN;
BEGIN
    -- Si solo cambia el estado (cancelar, atender, etc.) no se revalida la agenda.
    IF TG_OP = 'UPDATE'
       AND NEW.fecha = OLD.fecha
       AND NEW.hora_inicio = OLD.hora_inicio
       AND NEW.hora_fin = OLD.hora_fin
       AND NEW.id_medico = OLD.id_medico THEN
        RETURN NEW;
    END IF;

    IF TG_OP = 'INSERT' AND NEW.estado <> 'programada' THEN
        RAISE EXCEPTION 'RF10: toda cita nueva se registra en estado programada.';
    END IF;

    IF TG_OP = 'UPDATE' AND (SELECT es_final FROM estado_cita WHERE codigo = OLD.estado) THEN
        RAISE EXCEPTION 'RF08: una cita % no puede reprogramarse; registre una nueva cita.', OLD.estado;
    END IF;

    IF (NEW.fecha + NEW.hora_inicio) < localtimestamp THEN
        RAISE EXCEPTION 'RN06: no se pueden registrar citas en fechas u horas pasadas.';
    END IF;

    SELECT estado INTO v_estado_medico FROM medico WHERE id_medico = NEW.id_medico;
    IF v_estado_medico IS DISTINCT FROM 'activo' THEN
        RAISE EXCEPTION 'RN04: el médico está inactivo y no puede recibir nuevas citas.';
    END IF;

    SELECT estado INTO v_estado_paciente FROM paciente WHERE id_paciente = NEW.id_paciente;
    IF v_estado_paciente IS DISTINCT FROM 'activo' THEN
        RAISE EXCEPTION 'RN03: el paciente está inactivo.';
    END IF;

    SELECT estado INTO v_estado_esp FROM especialidad WHERE id_especialidad = NEW.id_especialidad;
    IF v_estado_esp IS DISTINCT FROM 'activo' THEN
        RAISE EXCEPTION 'RN03: la especialidad está inactiva.';
    END IF;

    SELECT EXISTS (
        SELECT 1
          FROM horario_atencion h
         WHERE h.id_medico   = NEW.id_medico
           AND h.estado      = 'activo'
           AND h.dia_semana  = EXTRACT(ISODOW FROM NEW.fecha)
           AND NEW.hora_inicio >= h.hora_inicio
           AND NEW.hora_fin    <= h.hora_fin
    ) INTO v_horario_valido;

    IF NOT v_horario_valido THEN
        RAISE EXCEPTION 'RN05: la cita está fuera del horario de atención del médico.';
    END IF;

    IF EXISTS (SELECT 1 FROM dia_no_laborable d
                WHERE d.fecha = NEW.fecha
                  AND (d.id_medico IS NULL OR d.id_medico = NEW.id_medico)) THEN
        RAISE EXCEPTION 'RF05: la fecha elegida está bloqueada (feriado o ausencia del médico).';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_validar
    BEFORE INSERT OR UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_cita();

-- ---------------------------------------------------------------------
-- 4) Controla las transiciones de estado de una cita (RF10).
--    Los cambios permitidos están en la tabla estado_cita_transicion;
--    si solo_desde_fecha es verdadero, el cambio (atendida, inasistencia)
--    solo se registra el día de la cita o después.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_transicion_cita()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_solo_desde_fecha BOOLEAN;
BEGIN
    IF NEW.estado = OLD.estado THEN
        RETURN NEW;
    END IF;

    SELECT t.solo_desde_fecha INTO v_solo_desde_fecha
      FROM estado_cita_transicion t
     WHERE t.estado_origen = OLD.estado AND t.estado_destino = NEW.estado;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'RF10: transición no permitida de "%" a "%".', OLD.estado, NEW.estado;
    END IF;

    IF v_solo_desde_fecha AND NEW.fecha > CURRENT_DATE THEN
        RAISE EXCEPTION 'RF10: una cita futura no puede marcarse como %.', NEW.estado;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_transicion
    BEFORE UPDATE OF estado ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_transicion_cita();

-- ---------------------------------------------------------------------
-- 5) Registra automáticamente el historial de la cita.
--    La aplicación indica el usuario con:  SET LOCAL app.id_usuario = 'N'
--    y un detalle opcional con:            SET LOCAL app.detalle = '...'
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_historial_cita()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_usuario INTEGER := NULLIF(current_setting('app.id_usuario', TRUE), '')::INTEGER;
    v_detalle VARCHAR(250) := NULLIF(current_setting('app.detalle', TRUE), '');
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO cita_historial (id_cita, accion, estado_nuevo, fecha_nueva, hora_nueva, detalle, id_usuario)
        VALUES (NEW.id_cita, 'registro', NEW.estado, NEW.fecha, NEW.hora_inicio,
                COALESCE(v_detalle, 'Cita registrada'), v_usuario);
        RETURN NEW;
    END IF;

    IF NEW.fecha <> OLD.fecha OR NEW.hora_inicio <> OLD.hora_inicio THEN
        INSERT INTO cita_historial (id_cita, accion, estado_anterior, estado_nuevo,
                                    fecha_anterior, hora_anterior, fecha_nueva, hora_nueva, detalle, id_usuario)
        VALUES (NEW.id_cita, 'reprogramacion', OLD.estado, NEW.estado,
                OLD.fecha, OLD.hora_inicio, NEW.fecha, NEW.hora_inicio,
                COALESCE(v_detalle, 'Cita reprogramada'), v_usuario);
    END IF;

    IF NEW.estado <> OLD.estado THEN
        INSERT INTO cita_historial (id_cita, accion, estado_anterior, estado_nuevo, detalle, id_usuario)
        VALUES (NEW.id_cita, 'cambio_estado', OLD.estado, NEW.estado,
                COALESCE(v_detalle, NEW.motivo_cancelacion, 'Cambio de estado'), v_usuario);
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_historial
    AFTER INSERT OR UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_historial_cita();

-- ---------------------------------------------------------------------
-- 6) Impide borrar físicamente citas, su historial y los registros
--    clínicos (RF09, RN10).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_impedir_eliminacion()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'RN10: los registros de % no se eliminan; cambie su estado.', TG_TABLE_NAME;
END;
$$;

CREATE TRIGGER tg_cita_no_eliminar
    BEFORE DELETE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_impedir_eliminacion();
CREATE TRIGGER tg_historial_no_eliminar
    BEFORE DELETE ON cita_historial
    FOR EACH ROW EXECUTE FUNCTION fn_tg_impedir_eliminacion();
CREATE TRIGGER tg_atencion_no_eliminar
    BEFORE DELETE ON atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_impedir_eliminacion();
CREATE TRIGGER tg_receta_no_eliminar
    BEFORE DELETE ON receta
    FOR EACH ROW EXECUTE FUNCTION fn_tg_impedir_eliminacion();

-- ---------------------------------------------------------------------
-- 7) Valida que la cuenta corresponda al rol (RN12):
--    rol medico   -> la persona debe ser un médico registrado;
--    rol paciente -> la persona debe ser un paciente registrado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_usuario_rol()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_rol VARCHAR(30);
BEGIN
    SELECT nombre INTO v_rol FROM rol WHERE id_rol = NEW.id_rol;
    IF v_rol = 'medico' AND NOT EXISTS (SELECT 1 FROM medico WHERE id_medico = NEW.id_persona) THEN
        RAISE EXCEPTION 'RN12: un usuario con rol médico debe pertenecer a un médico registrado.';
    ELSIF v_rol = 'paciente' AND NOT EXISTS (SELECT 1 FROM paciente WHERE id_paciente = NEW.id_persona) THEN
        RAISE EXCEPTION 'RN12: un usuario con rol paciente debe pertenecer a un paciente registrado.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_usuario_validar_rol
    BEFORE INSERT OR UPDATE OF id_rol, id_persona ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_usuario_rol();

-- ---------------------------------------------------------------------
-- 8) La bitácora (CU02) no se altera: impide modificar o borrar sus
--    filas. Solo se agregan registros nuevos.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_bitacora_inalterable()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'La bitácora no se modifica ni se borra: solo se agregan registros.';
END;
$$;

CREATE TRIGGER tg_bitacora_inalterable
    BEFORE UPDATE OR DELETE ON bitacora
    FOR EACH ROW EXECUTE FUNCTION fn_tg_bitacora_inalterable();

-- ---------------------------------------------------------------------
-- 9) Auditoría de datos: guarda en la bitácora cada INSERT, UPDATE o
--    DELETE con los valores de antes y de después. En un UPDATE solo se
--    guardan las columnas que cambiaron. Nunca se guarda la contraseña.
--    Argumentos del trigger: (columna de la llave primaria, módulo).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_auditoria()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    -- Columnas que no se auditan: contraseña y datos técnicos del inicio de sesión.
    v_ignorar  TEXT[] := ARRAY['password_hash','actualizado_en','intentos_fallidos','bloqueado_hasta','ultimo_acceso'];
    v_antes    JSONB;
    v_despues  JSONB;
    v_usuario  INTEGER := NULLIF(current_setting('app.id_usuario', TRUE), '')::INTEGER;
    v_id       TEXT;
    v_columnas TEXT;
BEGIN
    IF current_setting('app.sin_auditoria', TRUE) = 'on' THEN
        RETURN NULL;
    END IF;

    IF TG_OP IN ('UPDATE','DELETE') THEN
        v_antes := to_jsonb(OLD) - v_ignorar;
    END IF;
    IF TG_OP IN ('INSERT','UPDATE') THEN
        v_despues := to_jsonb(NEW) - v_ignorar;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        SELECT jsonb_object_agg(a.key, a.value) INTO v_antes
          FROM jsonb_each(to_jsonb(OLD) - v_ignorar) a
         WHERE v_despues -> a.key IS DISTINCT FROM a.value;
        IF v_antes IS NULL THEN
            RETURN NULL;             -- no cambió ningún dato que se audite
        END IF;
        SELECT jsonb_object_agg(d.key, d.value) INTO v_despues
          FROM jsonb_each(to_jsonb(NEW) - v_ignorar) d
         WHERE v_antes ? d.key;
        SELECT string_agg(k, ', ' ORDER BY k) INTO v_columnas FROM jsonb_object_keys(v_antes) k;
    END IF;

    v_id := COALESCE(to_jsonb(NEW), to_jsonb(OLD)) ->> TG_ARGV[0];

    INSERT INTO bitacora (id_usuario, usuario, accion, modulo, detalle, ip,
                          tabla, operacion, id_registro, datos_antes, datos_despues)
    VALUES (v_usuario,
            (SELECT nombre_usuario FROM usuario WHERE id_usuario = v_usuario),
            'auditoria', TG_ARGV[1],
            CASE TG_OP WHEN 'INSERT' THEN format('Registró %s #%s', TG_TABLE_NAME, v_id)
                       WHEN 'UPDATE' THEN format('Modificó %s #%s: %s', TG_TABLE_NAME, v_id, v_columnas)
                       ELSE format('Eliminó %s #%s', TG_TABLE_NAME, v_id) END,
            NULLIF(current_setting('app.ip', TRUE), ''),
            TG_TABLE_NAME, TG_OP, v_id, v_antes, v_despues);
    RETURN NULL;
END;
$$;

CREATE TRIGGER tg_persona_auditoria AFTER INSERT OR UPDATE OR DELETE ON persona
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_persona', 'personas');
CREATE TRIGGER tg_paciente_auditoria AFTER INSERT OR UPDATE OR DELETE ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_paciente', 'pacientes');
CREATE TRIGGER tg_medico_auditoria AFTER INSERT OR UPDATE OR DELETE ON medico
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_medico', 'medicos');
CREATE TRIGGER tg_especialidad_auditoria AFTER INSERT OR UPDATE OR DELETE ON especialidad
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_especialidad', 'medicos');
CREATE TRIGGER tg_horario_auditoria AFTER INSERT OR UPDATE OR DELETE ON horario_atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_horario', 'medicos');
CREATE TRIGGER tg_usuario_auditoria AFTER INSERT OR UPDATE OR DELETE ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_usuario', 'seguridad');
CREATE TRIGGER tg_cita_auditoria AFTER INSERT OR UPDATE OR DELETE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_cita', 'citas');
CREATE TRIGGER tg_atencion_auditoria AFTER INSERT OR UPDATE OR DELETE ON atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_atencion', 'atencion');
CREATE TRIGGER tg_receta_auditoria AFTER INSERT OR UPDATE OR DELETE ON receta
    FOR EACH ROW EXECUTE FUNCTION fn_tg_auditoria('id_receta', 'atencion');

-- ---------------------------------------------------------------------
-- 10) Un enlace de recuperación de contraseña se usa una sola vez y solo
--     mientras está vigente. El token no se puede cambiar después.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_token_un_uso()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.token_hash <> OLD.token_hash OR NEW.id_usuario <> OLD.id_usuario
       OR NEW.creado_en <> OLD.creado_en OR NEW.expira_en <> OLD.expira_en THEN
        RAISE EXCEPTION 'El token de recuperación no se puede modificar.';
    END IF;
    IF OLD.usado_en IS NOT NULL THEN
        RAISE EXCEPTION 'Este enlace de recuperación ya fue usado. Solicite uno nuevo.';
    END IF;
    IF NEW.usado_en IS NOT NULL AND OLD.expira_en < now() THEN
        RAISE EXCEPTION 'Este enlace de recuperación venció. Solicite uno nuevo.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_token_un_uso
    BEFORE UPDATE ON token_recuperacion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_token_un_uso();

-- ---------------------------------------------------------------------
-- 11) La atención (registro clínico) solo se registra para una cita
--     atendida.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_atencion()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF (SELECT estado FROM cita WHERE id_cita = NEW.id_cita) IS DISTINCT FROM 'atendida' THEN
        RAISE EXCEPTION 'RF12: solo se registra la atención de una cita atendida.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_atencion_validar
    BEFORE INSERT OR UPDATE OF id_cita ON atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_atencion();

-- ---------------------------------------------------------------------
-- 12) Recordatorios de la cita por correo, según la preferencia del
--     paciente (recordatorio_cita): ninguno, 1h, 24h o 24h_1h antes.
--     · 24 h antes: con los botones Confirmar / Cancelar; solo si faltan
--       más de 2 horas (antes de eso ya no se puede cancelar en línea).
--     · 1 h antes: solo un aviso.
--     fn_programar_recordatorios_cita anula los pendientes de la cita y los
--     vuelve a programar; la usan el trigger de la cita y el cambio de
--     preferencia del paciente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_programar_recordatorios_cita(p_id_cita INTEGER)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE
    v_cita    cita%ROWTYPE;
    v_correo  VARCHAR(120);
    v_pref    VARCHAR(8);
    v_inicio  TIMESTAMPTZ;
BEGIN
    UPDATE notificacion SET estado = 'anulada'
     WHERE id_cita = p_id_cita AND tipo IN ('recordatorio_cita','recordatorio_cita_1h') AND estado = 'pendiente';

    IF current_setting('app.sin_notificaciones', TRUE) = 'on' THEN
        RETURN;
    END IF;

    SELECT * INTO v_cita FROM cita WHERE id_cita = p_id_cita;
    IF NOT FOUND OR (SELECT es_final FROM estado_cita WHERE codigo = v_cita.estado) THEN
        RETURN;                      -- cancelada o cerrada: no se recuerda
    END IF;

    SELECT pa.recordatorio_cita, pe.correo INTO v_pref, v_correo
      FROM paciente pa JOIN persona pe ON pe.id_persona = pa.id_paciente
     WHERE pa.id_paciente = v_cita.id_paciente;
    IF v_pref = 'ninguno' OR v_correo IS NULL THEN
        RETURN;
    END IF;

    v_inicio := (v_cita.fecha + v_cita.hora_inicio)::TIMESTAMPTZ;

    IF v_pref IN ('24h','24h_1h') AND v_inicio - now() > INTERVAL '2 hours' THEN
        INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_cita, programada_para)
        VALUES ('recordatorio_cita', v_cita.id_paciente, v_correo,
                format('Recordatorio: su cita %s es el %s a las %s', v_cita.codigo,
                       to_char(v_cita.fecha, 'DD/MM/YYYY'), to_char(v_cita.hora_inicio, 'HH24:MI')),
                v_cita.id_cita, greatest(now(), v_inicio - INTERVAL '24 hours'));
    END IF;

    IF v_pref IN ('1h','24h_1h') AND v_inicio - now() > INTERVAL '1 hour' THEN
        INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_cita, programada_para)
        VALUES ('recordatorio_cita_1h', v_cita.id_paciente, v_correo,
                format('Su cita %s es en 1 hora (%s)', v_cita.codigo, to_char(v_cita.hora_inicio, 'HH24:MI')),
                v_cita.id_cita, v_inicio - INTERVAL '1 hour');
    END IF;
END;
$$;

CREATE OR REPLACE FUNCTION fn_tg_recordatorio_cita()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    -- Un cambio de estado que no cierra la cita (p. ej. confirmarla) no mueve los recordatorios.
    IF TG_OP = 'UPDATE' AND NEW.fecha = OLD.fecha AND NEW.hora_inicio = OLD.hora_inicio
       AND NOT (SELECT es_final FROM estado_cita WHERE codigo = NEW.estado) THEN
        RETURN NULL;
    END IF;
    PERFORM fn_programar_recordatorios_cita(NEW.id_cita);
    RETURN NULL;
END;
$$;

CREATE TRIGGER tg_cita_recordatorio
    AFTER INSERT OR UPDATE OF fecha, hora_inicio, estado ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_recordatorio_cita();

-- ---------------------------------------------------------------------
-- 13) Recordatorios de tomar el medicamento: un correo por cada toma
--     futura (como máximo 90 por medicamento), si el medicamento tiene
--     «recordar» y el paciente acepta recordatorios de medicamentos.
--     fn_programar_tomas anula las tomas pendientes del medicamento y las
--     vuelve a programar; la usan el trigger de la receta y el cambio de
--     preferencia del paciente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_programar_tomas(p_id_detalle INTEGER)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE
    v_detalle     receta_detalle%ROWTYPE;
    v_persona     INTEGER;
    v_correo      VARCHAR(120);
    v_medicamento TEXT;
    v_tomas       INTEGER;
BEGIN
    UPDATE notificacion SET estado = 'anulada'
     WHERE id_detalle = p_id_detalle AND tipo = 'toma_medicamento' AND estado = 'pendiente';

    IF current_setting('app.sin_notificaciones', TRUE) = 'on' THEN
        RETURN;
    END IF;

    SELECT * INTO v_detalle FROM receta_detalle WHERE id_detalle = p_id_detalle;
    IF NOT FOUND OR NOT v_detalle.recordar THEN
        RETURN;
    END IF;

    SELECT pe.id_persona, pe.correo INTO v_persona, v_correo
      FROM receta r
      JOIN atencion a  ON a.id_atencion = r.id_atencion
      JOIN cita c      ON c.id_cita = a.id_cita
      JOIN paciente pa ON pa.id_paciente = c.id_paciente
      JOIN persona pe  ON pe.id_persona = pa.id_paciente
     WHERE r.id_receta = v_detalle.id_receta AND pa.recordar_medicamentos AND pe.correo IS NOT NULL;
    IF NOT FOUND THEN
        RETURN;
    END IF;

    SELECT m.nombre || COALESCE(' ' || m.concentracion, '') INTO v_medicamento
      FROM medicamento m WHERE m.id_medicamento = v_detalle.id_medicamento;
    v_tomas := least(90, v_detalle.duracion_dias * 24 / v_detalle.frecuencia_horas);

    INSERT INTO notificacion (tipo, id_persona, correo_destino, asunto, id_receta, id_detalle, programada_para)
    SELECT 'toma_medicamento', v_persona, v_correo,
           format('Hora de su medicamento: %s (%s)', v_medicamento, v_detalle.dosis),
           v_detalle.id_receta, v_detalle.id_detalle, t.momento
      FROM (SELECT (v_detalle.fecha_inicio + v_detalle.hora_primera_toma)::TIMESTAMPTZ
                   + make_interval(hours => k * v_detalle.frecuencia_horas) AS momento
              FROM generate_series(0, v_tomas - 1) k) t
     WHERE t.momento > now();
END;
$$;

CREATE OR REPLACE FUNCTION fn_tg_programar_tomas()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    PERFORM fn_programar_tomas(NEW.id_detalle);
    RETURN NULL;
END;
$$;

CREATE TRIGGER tg_detalle_programar_tomas
    AFTER INSERT OR UPDATE OF recordar, frecuencia_horas, duracion_dias, fecha_inicio, hora_primera_toma
    ON receta_detalle
    FOR EACH ROW EXECUTE FUNCTION fn_tg_programar_tomas();

-- ---------------------------------------------------------------------
-- 14) Cuando el paciente cambia sus preferencias de recordatorios, se
--     reprograman los recordatorios de sus citas pendientes y de sus
--     medicamentos vigentes (o se anulan si los desactivó).
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_preferencia_recordatorios()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.recordatorio_cita IS DISTINCT FROM OLD.recordatorio_cita THEN
        PERFORM fn_programar_recordatorios_cita(c.id_cita)
           FROM cita c JOIN estado_cita e ON e.codigo = c.estado
          WHERE c.id_paciente = NEW.id_paciente AND NOT e.es_final
            AND (c.fecha + c.hora_inicio) > localtimestamp;
    END IF;
    IF NEW.recordar_medicamentos IS DISTINCT FROM OLD.recordar_medicamentos THEN
        PERFORM fn_programar_tomas(d.id_detalle)
           FROM receta_detalle d
           JOIN receta r   ON r.id_receta = d.id_receta
           JOIN atencion a ON a.id_atencion = r.id_atencion
           JOIN cita c     ON c.id_cita = a.id_cita
          WHERE c.id_paciente = NEW.id_paciente AND d.fecha_inicio + d.duracion_dias >= CURRENT_DATE;
    END IF;
    RETURN NULL;
END;
$$;

CREATE TRIGGER tg_paciente_preferencia_recordatorios
    AFTER UPDATE OF recordatorio_cita, recordar_medicamentos ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_preferencia_recordatorios();
