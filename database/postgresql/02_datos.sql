-- Pocos datos, ficticios y fáciles de seguir en clase.
INSERT INTO catalogo.tipo_equipo(nombre) VALUES ('Laptop'),('PC de escritorio'),('Impresora');
INSERT INTO catalogo.marca(nombre) VALUES ('Lenovo'),('HP'),('Epson');
INSERT INTO personas.cliente(dni,nombres,apellidos,correo,telefono) VALUES
('10000001','Ana','Torres','ana@example.com','900000001'),
('10000002','Luis','Rojas','luis@example.com','900000002');
INSERT INTO personas.tecnico(dni,nombres,apellidos,especialidad,correo) VALUES
('20000001','Lucía','Pérez','Computadoras','lucia@example.com'),
('20000002','Mario','Díaz','Impresoras','mario@example.com');
INSERT INTO soporte.equipo(id_cliente,id_tipo_equipo,id_marca,modelo,serie) VALUES
(1,1,1,'ThinkPad','DEMO-LAP-001'),
(2,3,3,'EcoTank','DEMO-IMP-001');
INSERT INTO soporte.ticket(id_equipo,problema_reportado,prioridad) VALUES
(1,'La laptop no enciende al conectar el cargador.','ALTA'),
(2,'La impresora muestra líneas en las páginas.','MEDIA');
