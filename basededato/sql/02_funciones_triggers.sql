-- =====================================================================
--  Archivo : 02_funciones_triggers.sql
--  Propósito: funciones y disparadores (triggers) que protegen la
--             integridad de los datos aunque alguien intente saltarse
--             la aplicación (segunda barrera de las reglas de negocio).
--  Nota     : la aplicación PHP valida primero (CU09, CU10, CU11, CU12);
--             estas funciones son la defensa final en el servidor de BD.
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

CREATE TRIGGER tg_especialidad_actualizar BEFORE UPDATE ON especialidad
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_medico_actualizar BEFORE UPDATE ON medico
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_horario_actualizar BEFORE UPDATE ON horario_atencion
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_paciente_actualizar BEFORE UPDATE ON paciente
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_usuario_actualizar BEFORE UPDATE ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();
CREATE TRIGGER tg_cita_actualizar BEFORE UPDATE ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_actualizar_fecha();

-- ---------------------------------------------------------------------
-- 2) Valida una cita al registrarla o reprogramarla:
--    RN04 médico activo · RN05 dentro del horario de atención ·
--    RN06 no en fechas pasadas · paciente activo · día no bloqueado ·
--    solo se reprograman citas 'programada' o 'confirmada' (RF08).
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

    IF TG_OP = 'UPDATE' AND OLD.estado NOT IN ('programada','confirmada') THEN
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
-- 3) Controla las transiciones de estado de una cita (RF10).
--    programada  -> confirmada | atendida | cancelada | inasistencia
--    confirmada  -> atendida   | cancelada | inasistencia
--    atendida, cancelada, inasistencia -> (estados finales)
--    Además: atendida / inasistencia solo el día de la cita o después.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_transicion_cita()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.estado = OLD.estado THEN
        RETURN NEW;
    END IF;

    IF NOT (
        (OLD.estado = 'programada' AND NEW.estado IN ('confirmada','atendida','cancelada','inasistencia')) OR
        (OLD.estado = 'confirmada' AND NEW.estado IN ('atendida','cancelada','inasistencia'))
    ) THEN
        RAISE EXCEPTION 'RF10: transición no permitida de "%" a "%".', OLD.estado, NEW.estado;
    END IF;

    IF NEW.estado IN ('atendida','inasistencia') AND NEW.fecha > CURRENT_DATE THEN
        RAISE EXCEPTION 'RF10: una cita futura no puede marcarse como %.', NEW.estado;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_cita_transicion
    BEFORE UPDATE OF estado ON cita
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_transicion_cita();

-- ---------------------------------------------------------------------
-- 4) Registra automáticamente el historial de la cita.
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
-- 5) Impide borrar físicamente citas e historial (RF09, RN10).
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

-- ---------------------------------------------------------------------
-- 6) Valida que el usuario tenga el vínculo correcto según su rol:
--    rol medico -> id_medico obligatorio; rol paciente -> id_paciente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_tg_validar_usuario_rol()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_rol VARCHAR(30);
BEGIN
    SELECT nombre INTO v_rol FROM rol WHERE id_rol = NEW.id_rol;
    IF v_rol = 'medico' AND NEW.id_medico IS NULL THEN
        RAISE EXCEPTION 'RN12: un usuario con rol médico debe estar vinculado a un médico.';
    ELSIF v_rol = 'paciente' AND NEW.id_paciente IS NULL THEN
        RAISE EXCEPTION 'RN12: un usuario con rol paciente debe estar vinculado a un paciente.';
    ELSIF v_rol NOT IN ('medico') AND NEW.id_medico IS NOT NULL THEN
        RAISE EXCEPTION 'RN12: solo un usuario con rol médico puede vincularse a un médico.';
    ELSIF v_rol NOT IN ('paciente') AND NEW.id_paciente IS NOT NULL THEN
        RAISE EXCEPTION 'RN12: solo un usuario con rol paciente puede vincularse a un paciente.';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER tg_usuario_validar_rol
    BEFORE INSERT OR UPDATE ON usuario
    FOR EACH ROW EXECUTE FUNCTION fn_tg_validar_usuario_rol();

-- ---------------------------------------------------------------------
-- 7) La bitácora de acciones (CU02) no se altera: impide modificar o
--    borrar sus filas. Solo se agregan registros nuevos.
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
