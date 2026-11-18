-- 1. Vistas: reúnen relaciones o muestran datos protegidos.
CREATE VIEW soporte.vw_ticket_operativo AS
SELECT t.*, t.fecha_apertura AS fecha_registro,
       c.nombres || ' ' || c.apellidos AS cliente,
       ti.nombre || ' - ' || m.nombre AS equipo,
       e.serie AS nro_serie, e.estado AS estado_equipo,
       COALESCE(te.nombres || ' ' || te.apellidos, 'Sin asignar') AS tecnico
FROM soporte.ticket t
JOIN soporte.equipo e USING (id_equipo)
JOIN personas.cliente c USING (id_cliente)
JOIN catalogo.tipo_equipo ti USING (id_tipo_equipo)
JOIN catalogo.marca m USING (id_marca)
LEFT JOIN LATERAL (
    SELECT a.id_tecnico
    FROM soporte.asignacion a
    WHERE a.id_ticket = t.id_ticket
    ORDER BY (a.estado = 'ACTIVA') DESC, a.id_asignacion DESC
    LIMIT 1
) a ON TRUE
LEFT JOIN personas.tecnico te USING (id_tecnico);

CREATE VIEW personas.vw_cliente_protegido AS
SELECT id_cliente, nombres, apellidos, '****' || right(dni, 4) AS dni,
       left(correo, 1) || '****' || substring(correo FROM position('@' IN correo)) AS correo
FROM personas.cliente;

-- 2. Funciones: devuelven un valor calculado.
CREATE FUNCTION soporte.fn_total_tickets() RETURNS INTEGER
LANGUAGE sql STABLE AS $$
    SELECT count(*)::INTEGER FROM soporte.ticket
$$;

CREATE FUNCTION soporte.fn_ticket_json(p_ticket INTEGER) RETURNS JSON
LANGUAGE sql STABLE AS $$
    SELECT row_to_json(v) FROM soporte.vw_ticket_operativo v WHERE id_ticket = p_ticket
$$;

CREATE FUNCTION soporte.fn_costo_ticket(p_ticket INTEGER) RETURNS NUMERIC
LANGUAGE sql STABLE AS $$
    -- Sumar por separado evita multiplicar la mano de obra por cada repuesto.
    SELECT COALESCE((
        SELECT sum(costo_mano_obra) FROM soporte.servicio_realizado WHERE id_ticket = p_ticket
    ), 0) + COALESCE((
        SELECT sum(r.cantidad * r.costo_unitario)
        FROM soporte.repuesto_utilizado r
        JOIN soporte.servicio_realizado s USING (id_servicio)
        WHERE s.id_ticket = p_ticket
    ), 0)
$$;

-- 3. Triggers: validan el cierre y auditan cambios reales de estado.
CREATE FUNCTION soporte.fn_validar_cierre() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.estado = 'CERRADO' AND OLD.estado NOT IN ('RESUELTO','CERRADO') THEN
        RAISE EXCEPTION 'Primero el ticket debe estar RESUELTO';
    END IF;
    IF NEW.estado = 'CERRADO' AND OLD.estado IS DISTINCT FROM NEW.estado THEN
        NEW.fecha_cierre = CURRENT_TIMESTAMP;
    ELSIF NEW.estado NOT IN ('CERRADO','RESUELTO') THEN
        NEW.fecha_cierre = NULL;
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER trg_validar_cierre BEFORE UPDATE OF estado ON soporte.ticket
FOR EACH ROW EXECUTE FUNCTION soporte.fn_validar_cierre();

CREATE FUNCTION auditoria.fn_auditar_ticket() RETURNS TRIGGER
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog AS $$
BEGIN
    IF OLD.estado IS DISTINCT FROM NEW.estado THEN
        INSERT INTO auditoria.auditoria_ticket(id_ticket,estado_anterior,estado_nuevo,usuario_bd)
        VALUES (NEW.id_ticket,OLD.estado,NEW.estado,session_user);
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER trg_auditar_ticket AFTER UPDATE OF estado ON soporte.ticket
FOR EACH ROW EXECUTE FUNCTION auditoria.fn_auditar_ticket();
REVOKE ALL ON FUNCTION auditoria.fn_auditar_ticket() FROM PUBLIC;

-- 4. Procedimientos: agrupan los cambios relacionados de una operación.
CREATE PROCEDURE soporte.sp_actualizar_disponibilidad()
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE personas.tecnico te
    SET disponible = NOT EXISTS (
        SELECT 1 FROM soporte.asignacion a
        JOIN soporte.ticket t USING (id_ticket)
        WHERE a.id_tecnico = te.id_tecnico AND a.estado = 'ACTIVA'
          AND t.estado NOT IN ('RESUELTO','CERRADO')
    );
END
$$;

CREATE PROCEDURE soporte.sp_asignar_tecnico(p_ticket INTEGER, p_tecnico INTEGER)
LANGUAGE plpgsql AS $$
DECLARE
    v_estado VARCHAR;
BEGIN
    SELECT estado INTO v_estado FROM soporte.ticket WHERE id_ticket = p_ticket FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'El ticket no existe';
    END IF;
    IF v_estado IN ('RESUELTO','CERRADO') THEN
        RAISE EXCEPTION 'Reabra el ticket antes de asignarlo';
    END IF;
    PERFORM id_tecnico FROM personas.tecnico ORDER BY id_tecnico FOR UPDATE;
    IF NOT EXISTS (SELECT 1 FROM personas.tecnico WHERE id_tecnico = p_tecnico AND estado) THEN
        RAISE EXCEPTION 'Seleccione un técnico activo';
    END IF;
    IF EXISTS (SELECT 1 FROM soporte.asignacion WHERE id_ticket = p_ticket AND id_tecnico = p_tecnico AND estado = 'ACTIVA') THEN
        RETURN;
    END IF;
    UPDATE soporte.asignacion
    SET estado = 'FINALIZADA', fecha_finalizacion = CURRENT_TIMESTAMP
    WHERE id_ticket = p_ticket AND estado = 'ACTIVA';
    INSERT INTO soporte.asignacion(id_ticket,id_tecnico) VALUES (p_ticket,p_tecnico);
    IF v_estado = 'ABIERTO' THEN
        UPDATE soporte.ticket SET estado = 'ASIGNADO' WHERE id_ticket = p_ticket;
    END IF;
    UPDATE soporte.equipo SET estado = 'DIAGNOSTICO'
    WHERE id_equipo = (SELECT id_equipo FROM soporte.ticket WHERE id_ticket = p_ticket);
    CALL soporte.sp_actualizar_disponibilidad();
END
$$;

CREATE PROCEDURE soporte.sp_cambiar_estado(p_ticket INTEGER, p_estado VARCHAR)
LANGUAGE plpgsql AS $$
DECLARE
    v_equipo INTEGER;
BEGIN
    SELECT id_equipo INTO v_equipo FROM soporte.ticket WHERE id_ticket = p_ticket FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'El ticket no existe';
    END IF;
    -- Orden fijo para que dos asignaciones no recalculen disponibilidad a la vez.
    PERFORM id_tecnico FROM personas.tecnico ORDER BY id_tecnico FOR UPDATE;
    UPDATE soporte.ticket SET estado = p_estado WHERE id_ticket = p_ticket;
    IF p_estado IN ('RESUELTO','CERRADO') THEN
        UPDATE soporte.asignacion SET estado = 'FINALIZADA', fecha_finalizacion = CURRENT_TIMESTAMP
        WHERE id_ticket = p_ticket AND estado = 'ACTIVA';
    END IF;
    UPDATE soporte.equipo SET estado = CASE p_estado
        WHEN 'EN_PROCESO' THEN 'REPARACION'
        WHEN 'RESUELTO' THEN 'LISTO'
        WHEN 'CERRADO' THEN 'ENTREGADO'
        WHEN 'ABIERTO' THEN 'RECIBIDO'
        ELSE estado
    END WHERE id_equipo = v_equipo;
    CALL soporte.sp_actualizar_disponibilidad();
END
$$;

CREATE PROCEDURE soporte.sp_cambiar_prioridad(p_ticket INTEGER, p_prioridad VARCHAR)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE soporte.ticket SET prioridad = p_prioridad WHERE id_ticket = p_ticket;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'El ticket no existe';
    END IF;
END
$$;

-- 5. Roles de práctica; la cuenta web se configura en 04_permisos.sh.
CREATE ROLE rol_consulta NOLOGIN;
CREATE ROLE rol_tecnico NOLOGIN;
GRANT USAGE ON SCHEMA personas,catalogo,soporte,auditoria TO rol_consulta;
GRANT SELECT ON ALL TABLES IN SCHEMA personas,catalogo,soporte,auditoria TO rol_consulta;
GRANT USAGE ON SCHEMA soporte TO rol_tecnico;
GRANT SELECT ON soporte.ticket,soporte.equipo TO rol_tecnico;
GRANT SELECT,INSERT,UPDATE ON soporte.diagnostico,soporte.servicio_realizado TO rol_tecnico;
GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA soporte TO rol_tecnico;
