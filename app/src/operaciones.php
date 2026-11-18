<?php
declare(strict_types=1);

function ticket(int $id): array
{
    $registro = consulta('SELECT * FROM soporte.vw_ticket_operativo WHERE id_ticket = ?', [$id])->fetch();
    if (!$registro) {
        throw new InvalidArgumentException('El ticket no existe.');
    }
    return $registro;
}

function insertar_equipo(array $datos, int $cliente): int
{
    $tipo = entero($datos, 'id_tipo_equipo');
    $marca = entero($datos, 'id_marca');
    if (!consulta('SELECT 1 FROM personas.cliente WHERE id_cliente = ? AND estado', [$cliente])->fetchColumn()
        || !consulta('SELECT 1 FROM catalogo.tipo_equipo WHERE id_tipo_equipo = ? AND estado', [$tipo])->fetchColumn()
        || !consulta('SELECT 1 FROM catalogo.marca WHERE id_marca = ? AND estado', [$marca])->fetchColumn()) {
        throw new InvalidArgumentException('Seleccione cliente, tipo y marca activos.');
    }
    return (int) consulta(
        'INSERT INTO soporte.equipo(id_cliente,id_tipo_equipo,id_marca,modelo,serie) VALUES (?,?,?,?,?) RETURNING id_equipo',
        [$cliente, $tipo, $marca, texto($datos, 'modelo', 0, 80), texto($datos, 'serie', 1, 80)]
    )->fetchColumn();
}

function registrar_cliente(array $datos): int
{
    $persona = persona($datos);
    $telefono = texto($datos, 'telefono', 0, 15);
    return transaccion(function () use ($datos, $persona, $telefono) {
        $id = (int) consulta(
            'INSERT INTO personas.cliente(dni,nombres,apellidos,correo,telefono) VALUES (?,?,?,?,?) RETURNING id_cliente',
            [...$persona, $telefono]
        )->fetchColumn();
        return insertar_equipo($datos, $id);
    });
}

function registrar_tecnico(array $datos): void
{
    [$dni, $nombres, $apellidos, $correo] = persona($datos);
    consulta('INSERT INTO personas.tecnico(dni,nombres,apellidos,correo,especialidad) VALUES (?,?,?,?,?)', [
        $dni, $nombres, $apellidos, $correo, texto($datos, 'especialidad', 1, 100),
    ]);
}

function crear_ticket(array $datos): int
{
    $equipo = entero($datos, 'id_equipo');
    $problema = texto($datos, 'problema_reportado', 5, 300);
    $prioridad = opcion($datos, 'prioridad', PRIORIDADES);
    $usuario = texto($datos, 'usuario', 3, 120);
    if (!consulta('SELECT 1 FROM soporte.equipo e JOIN personas.cliente c USING(id_cliente) WHERE e.id_equipo = ? AND c.estado', [$equipo])->fetchColumn()) {
        throw new InvalidArgumentException('Seleccione el equipo de un cliente activo.');
    }
    return transaccion(function () use ($equipo, $problema, $prioridad, $usuario) {
        $id = (int) consulta('INSERT INTO soporte.ticket(id_equipo,problema_reportado,prioridad) VALUES (?,?,?) RETURNING id_ticket', [$equipo, $problema, $prioridad])->fetchColumn();
        insertar_documento('historial_ticket', [
            'ticket_id' => $id,
            'historial' => [actividad('REGISTRO', $problema, $usuario)],
            'observaciones' => [],
        ]);
        // El COMMIT pertenece a PostgreSQL; MongoDB ya confirmó su escritura.
        return $id;
    });
}

function registrar_diagnostico(int $id, array $datos, array $archivos): void
{
    ticket($id);
    $detalle = texto($datos, 'detalle', 5, 500);
    $recomendacion = texto($datos, 'recomendacion', 0, 500);
    $usuario = texto($datos, 'usuario', 3, 120);
    $fotos = preparar_fotos($archivos);
    transaccion(function () use ($id, $detalle, $recomendacion, $usuario, $fotos) {
        $diagnostico = (int) consulta('INSERT INTO soporte.diagnostico(id_ticket,detalle,recomendacion) VALUES (?,?,?) RETURNING id_diagnostico', [$id, $detalle, $recomendacion])->fetchColumn();
        $registro = actividad('DIAGNOSTICO', $detalle, $usuario);
        $registro['id_diagnostico'] = $diagnostico;
        $registro['fotos'] = [];
        foreach ($fotos as $foto) {
            $archivo = insertar_documento('evidencias', [
                'ticket_id' => $id, 'id_diagnostico' => $diagnostico,
                'nombre' => $foto['nombre'], 'mime' => $foto['mime'],
                'fecha' => $registro['fecha'], 'usuario' => $usuario,
                'contenido' => new MongoDB\BSON\Binary($foto['contenido']),
            ]);
            $registro['fotos'][] = (string) $archivo;
        }
        agregar_actividad($id, $registro);
    });
}

function registrar_servicio(int $id, array $datos): void
{
    ticket($id);
    consulta('INSERT INTO soporte.servicio_realizado(id_ticket,descripcion,costo_mano_obra) VALUES (?,?,?)', [
        $id, texto($datos, 'descripcion', 5, 500), importe($datos, 'costo_mano_obra'),
    ]);
}

function registrar_repuesto(int $id, array $datos): void
{
    ticket($id);
    $servicio = entero($datos, 'id_servicio');
    $cantidad = entero($datos, 'cantidad');
    if ($cantidad > 10000 || !consulta('SELECT 1 FROM soporte.servicio_realizado WHERE id_servicio = ? AND id_ticket = ?', [$servicio, $id])->fetchColumn()) {
        throw new InvalidArgumentException('Seleccione un servicio de este ticket y una cantidad entre 1 y 10000.');
    }
    consulta('INSERT INTO soporte.repuesto_utilizado(id_servicio,nombre_repuesto,cantidad,costo_unitario) VALUES (?,?,?,?)', [
        $servicio, texto($datos, 'nombre_repuesto', 1, 120), $cantidad, importe($datos, 'costo_unitario'),
    ]);
}
