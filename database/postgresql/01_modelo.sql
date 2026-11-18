-- Instalación nueva: sin compatibilidad ni migraciones de la versión Python.
CREATE SCHEMA personas;
CREATE SCHEMA catalogo;
CREATE SCHEMA soporte;
CREATE SCHEMA auditoria;

CREATE TABLE personas.cliente (
    id_cliente SERIAL PRIMARY KEY,
    dni VARCHAR(8) NOT NULL UNIQUE CHECK (dni ~ '^[0-9]{8}$'),
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    correo VARCHAR(120) NOT NULL,
    telefono VARCHAR(15) NOT NULL DEFAULT '',
    estado BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE personas.tecnico (
    id_tecnico SERIAL PRIMARY KEY,
    dni VARCHAR(8) NOT NULL UNIQUE CHECK (dni ~ '^[0-9]{8}$'),
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    especialidad VARCHAR(100) NOT NULL,
    correo VARCHAR(120) NOT NULL UNIQUE,
    disponible BOOLEAN NOT NULL DEFAULT TRUE,
    estado BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE catalogo.tipo_equipo (
    id_tipo_equipo SERIAL PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    estado BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE catalogo.marca (
    id_marca SERIAL PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    estado BOOLEAN NOT NULL DEFAULT TRUE
);
CREATE TABLE soporte.equipo (
    id_equipo SERIAL PRIMARY KEY,
    id_cliente INTEGER NOT NULL REFERENCES personas.cliente,
    id_tipo_equipo INTEGER NOT NULL REFERENCES catalogo.tipo_equipo,
    id_marca INTEGER NOT NULL REFERENCES catalogo.marca,
    modelo VARCHAR(80) NOT NULL DEFAULT '',
    serie VARCHAR(80) NOT NULL UNIQUE,
    estado VARCHAR(20) NOT NULL DEFAULT 'RECIBIDO'
        CHECK (estado IN ('RECIBIDO','DIAGNOSTICO','REPARACION','LISTO','ENTREGADO'))
);
CREATE TABLE soporte.ticket (
    id_ticket SERIAL PRIMARY KEY,
    id_equipo INTEGER NOT NULL REFERENCES soporte.equipo,
    problema_reportado VARCHAR(300) NOT NULL,
    prioridad VARCHAR(10) NOT NULL DEFAULT 'MEDIA' CHECK (prioridad IN ('BAJA','MEDIA','ALTA')),
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTO'
        CHECK (estado IN ('ABIERTO','ASIGNADO','EN_PROCESO','RESUELTO','CERRADO')),
    fecha_apertura TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre TIMESTAMPTZ
);
CREATE TABLE soporte.asignacion (
    id_asignacion SERIAL PRIMARY KEY,
    id_ticket INTEGER NOT NULL REFERENCES soporte.ticket,
    id_tecnico INTEGER NOT NULL REFERENCES personas.tecnico,
    estado VARCHAR(15) NOT NULL DEFAULT 'ACTIVA' CHECK (estado IN ('ACTIVA','FINALIZADA')),
    fecha_asignacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_finalizacion TIMESTAMPTZ
);
CREATE TABLE soporte.diagnostico (
    id_diagnostico SERIAL PRIMARY KEY,
    id_ticket INTEGER NOT NULL REFERENCES soporte.ticket,
    detalle VARCHAR(500) NOT NULL,
    recomendacion VARCHAR(500) NOT NULL DEFAULT '',
    fecha TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE soporte.servicio_realizado (
    id_servicio SERIAL PRIMARY KEY,
    id_ticket INTEGER NOT NULL REFERENCES soporte.ticket,
    descripcion VARCHAR(500) NOT NULL,
    costo_mano_obra NUMERIC(10,2) NOT NULL CHECK (costo_mano_obra >= 0),
    fecha TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE soporte.repuesto_utilizado (
    id_repuesto_utilizado SERIAL PRIMARY KEY,
    id_servicio INTEGER NOT NULL REFERENCES soporte.servicio_realizado,
    nombre_repuesto VARCHAR(120) NOT NULL,
    cantidad INTEGER NOT NULL CHECK (cantidad BETWEEN 1 AND 10000),
    costo_unitario NUMERIC(10,2) NOT NULL CHECK (costo_unitario >= 0)
);
CREATE TABLE auditoria.auditoria_ticket (
    id_auditoria SERIAL PRIMARY KEY,
    id_ticket INTEGER NOT NULL REFERENCES soporte.ticket,
    estado_anterior VARCHAR(20) NOT NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    usuario_bd VARCHAR(100) NOT NULL,
    fecha TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX uq_asignacion_activa ON soporte.asignacion(id_ticket) WHERE estado='ACTIVA';
CREATE INDEX idx_ticket_estado ON soporte.ticket(estado);
CREATE INDEX idx_equipo_cliente ON soporte.equipo(id_cliente);
CREATE INDEX idx_diagnostico_ticket ON soporte.diagnostico(id_ticket);
CREATE INDEX idx_servicio_ticket ON soporte.servicio_realizado(id_ticket);
CREATE INDEX idx_repuesto_servicio ON soporte.repuesto_utilizado(id_servicio);
CREATE EXTENSION pgcrypto;
