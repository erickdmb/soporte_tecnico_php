<?php
declare(strict_types=1);
// Ejecutar solo en el proyecto Docker de pruebas: crea registros PRUEBA-*.
require '/var/www/html/src/bootstrap.php';
if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
    exit("Utilice scripts/test.ps1 o scripts/test.sh.\n");
}
$cantidad = 0;
function comprobar(bool $condicion, string $descripcion): void
{
    global $cantidad;
    if (!$condicion) throw new RuntimeException('FALLÓ: ' . $descripcion);
    $cantidad++;
    echo 'OK · ' . $descripcion . PHP_EOL;
}

function rechaza(callable $operacion, string $descripcion): void
{
    try {
        $operacion();
    } catch (InvalidArgumentException | PDOException $error) {
        comprobar(true, $descripcion);
        return;
    }
    comprobar(false, $descripcion);
}

$cookie = '';
function http(string $ruta, ?array $datos = null, ?string $cuerpo = null, ?string $tipo = null): array
{
    global $cookie;
    $headers = ['Cookie: ' . $cookie];
    if ($datos !== null || $cuerpo !== null) $headers[] = 'Content-Type: ' . ($tipo ?? 'application/x-www-form-urlencoded');
    $contexto = stream_context_create(['http' => [
        'method' => ($datos !== null || $cuerpo !== null) ? 'POST' : 'GET',
        'header' => implode("\r\n", $headers),
        'content' => $cuerpo ?? ($datos !== null ? http_build_query($datos) : ''),
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20,
    ]]);
    $contenido = file_get_contents('http://localhost' . $ruta, false, $contexto);
    $respuesta = $http_response_header ?? [];
    foreach ($respuesta as $header) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $header, $coincide)) $cookie = $coincide[1];
    }
    return ['estado' => (int) substr($respuesta[0] ?? '', 9, 3), 'cuerpo' => (string) $contenido, 'headers' => $respuesta];
}

try {
    foreach (['-1', 'NaN', '1.999', '100000000', '1e3'] as $valor) {
        rechaza(fn() => importe(['costo' => $valor], 'costo'), 'Costo inválido: ' . $valor);
    }
    comprobar(importe(['costo' => '12.30'], 'costo') === '12.30', 'Importes conservan dos decimales sin usar float');
    comprobar(e('<script>') === '&lt;script&gt;', 'Escape HTML');
    rechaza(fn() => persona(['dni' => '1234567x']), 'DNI inválido');
    comprobar((int) consulta("SELECT count(*) FROM information_schema.tables WHERE table_schema IN ('personas','catalogo','soporte','auditoria') AND table_type='BASE TABLE'")->fetchColumn() === 11, 'Modelo de once tablas');
    comprobar(consulta('SELECT current_user')->fetchColumn() === 'soporte_web', 'La aplicación utiliza una cuenta limitada');
    rechaza(fn() => consulta('DELETE FROM soporte.ticket WHERE FALSE'), 'La web no tiene DELETE');
    rechaza(fn() => consulta("INSERT INTO auditoria.auditoria_ticket(id_ticket,estado_anterior,estado_nuevo,usuario_bd) VALUES (1,'ABIERTO','CERRADO','falso')"), 'La web no escribe auditoría directamente');

    $sufijo = bin2hex(random_bytes(5));
    $dni = (string) random_int(30000000, 89999999);
    $datos = ['dni' => $dni, 'nombres' => 'PRUEBA', 'apellidos' => $sufijo, 'correo' => $sufijo . '@example.com',
        'telefono' => '', 'id_tipo_equipo' => '1', 'id_marca' => '1', 'modelo' => 'Académico', 'serie' => 'PRUEBA-' . $sufijo];
    $equipo = registrar_cliente($datos);
    $cliente = consulta('SELECT id_cliente FROM soporte.equipo WHERE id_equipo = ?', [$equipo])->fetchColumn();
    comprobar((bool) $cliente, 'Cliente y equipo en una transacción');
    $duplicado = $datos;
    $duplicado['dni'] = (string) ((int) $dni + 1);
    rechaza(fn() => registrar_cliente($duplicado), 'Serie duplicada rechazada');
    comprobar(!consulta('SELECT 1 FROM personas.cliente WHERE dni = ?', [$duplicado['dni']])->fetchColumn(), 'ROLLBACK evita un cliente parcial');

    $id = crear_ticket(['id_equipo' => $equipo, 'problema_reportado' => 'PRUEBA: el equipo no enciende.', 'prioridad' => 'ALTA', 'usuario' => 'Prueba automática']);
    comprobar(count(documentos('historial_ticket', ['ticket_id' => $id])) === 1, 'Ticket con historial único MongoDB');
    consulta('CALL soporte.sp_asignar_tecnico(?,?)', [$id, 1]);
    comprobar(ticket($id)['estado'] === 'ASIGNADO', 'Procedimiento asigna técnico y cambia estado');
    consulta('CALL soporte.sp_asignar_tecnico(?,?)', [$id, 2]);
    comprobar((int) consulta("SELECT count(*) FROM soporte.asignacion WHERE id_ticket = ? AND estado='ACTIVA'", [$id])->fetchColumn() === 1, 'Reasignación mantiene una sola asignación activa');
    rechaza(fn() => consulta('CALL soporte.sp_cambiar_estado(?,?)', [$id, 'CERRADO']), 'Trigger impide cerrar antes de resolver');
    comprobar(ticket($id)['estado'] === 'ASIGNADO', 'Cierre rechazado conserva estado');

    registrar_servicio($id, ['descripcion' => 'Servicio de prueba', 'costo_mano_obra' => '25.50']);
    $servicio = (int) consulta('SELECT max(id_servicio) FROM soporte.servicio_realizado WHERE id_ticket = ?', [$id])->fetchColumn();
    registrar_repuesto($id, ['id_servicio' => $servicio, 'nombre_repuesto' => 'Cable', 'cantidad' => 2, 'costo_unitario' => '10.25']);
    comprobar(consulta('SELECT soporte.fn_costo_ticket(?)', [$id])->fetchColumn() === '46.00', 'Costo total: mano de obra + cantidad × precio');
    $otro = crear_ticket(['id_equipo' => $equipo, 'problema_reportado' => 'PRUEBA: segunda atención del equipo.', 'prioridad' => 'BAJA', 'usuario' => 'Prueba automática']);
    rechaza(fn() => registrar_repuesto($otro, ['id_servicio' => $servicio, 'nombre_repuesto' => 'Cable', 'cantidad' => 1, 'costo_unitario' => '1']), 'No se aceptan repuestos de otro ticket');

    actualizar_documento('historial_ticket', ['ticket_id' => $id], ['$addToSet' => ['observaciones' => 'Revisar cargador']]);
    actualizar_documento('historial_ticket', ['ticket_id' => $id], ['$addToSet' => ['observaciones' => 'Revisar cargador']]);
    comprobar(count(documentos('historial_ticket', ['ticket_id' => $id])[0]['observaciones']) === 1, '$addToSet evita duplicados');
    actualizar_documento('historial_ticket', ['ticket_id' => $id], ['$pull' => ['observaciones' => 'Revisar cargador']]);
    comprobar(documentos('historial_ticket', ['ticket_id' => $id])[0]['observaciones'] === [], '$pull elimina una observación');

    // Pruebas por HTTP: sesiones, formularios, fotos reales y recorrido de estado.
    $respuesta = http('/index.php?pagina=ticket&id=' . $id);
    comprobar($respuesta['estado'] === 200 && preg_match('/name="csrf" value="([a-f0-9]+)"/', $respuesta['cuerpo'], $coincide) === 1, 'Detalle HTTP y token CSRF');
    $csrf = $coincide[1];
    comprobar(http('/acciones.php', ['accion' => 'estado', 'id_ticket' => $id, 'estado' => 'CERRADO'])['estado'] === 403, 'POST sin CSRF rechazado');
    comprobar(http('/acciones.php')['estado'] === 405, 'Acciones solo admiten POST');
    foreach (['inicio','clientes','equipos','tickets','tecnicos','catalogos','auditoria'] as $pagina) {
        comprobar(http('/index.php?pagina=' . $pagina)['estado'] === 200, 'Página HTTP: ' . $pagina);
    }
    comprobar(http('/index.php?pagina=../../etc/passwd')['estado'] === 404, 'Solo se incluyen páginas de la lista permitida');
    comprobar(http('/salud.php')['estado'] === 200, 'Salud de ambos motores');

    $limite = 'Prueba' . $sufijo;
    $cuerpo = '';
    foreach (['csrf' => $csrf, 'accion' => 'diagnostico', 'id_ticket' => $id, 'detalle' => 'Diagnóstico con foto real.', 'recomendacion' => 'Cambiar cable.', 'usuario' => 'Prueba HTTP'] as $campo => $valor) {
        $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"$campo\"\r\n\r\n$valor\r\n";
    }
    $imagen = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j7woAAAAASUVORK5CYII=');
    $cuerpo .= "--$limite\r\nContent-Disposition: form-data; name=\"fotos[]\"; filename=\"foto.png\"\r\nContent-Type: image/png\r\n\r\n$imagen\r\n--$limite--\r\n";
    comprobar(http('/acciones.php', null, $cuerpo, 'multipart/form-data; boundary=' . $limite)['estado'] === 303, 'Envío multipart de diagnóstico');
    $fotos = documentos('evidencias', ['ticket_id' => $id]);
    comprobar(count($fotos) === 1, 'Foto almacenada como BSON binario');
    $foto = (string) $fotos[0]['_id'];
    $respuestaFoto = http('/foto.php?ticket=' . $id . '&id=' . $foto);
    comprobar($respuestaFoto['estado'] === 200 && $respuestaFoto['cuerpo'] === $imagen, 'Foto recuperada sin alterar sus bytes');
    comprobar(http('/foto.php?ticket=' . $otro . '&id=' . $foto)['estado'] === 404, 'Una foto solo se consulta desde su ticket');
    $falso = str_replace($imagen, '<script>archivo falso</script>', $cuerpo);
    http('/acciones.php', null, $falso, 'multipart/form-data; boundary=' . $limite);
    comprobar(count(documentos('evidencias', ['ticket_id' => $id])) === 1, 'Archivo disfrazado no se almacena');
    comprobar((int) consulta('SELECT count(*) FROM soporte.diagnostico WHERE id_ticket = ?', [$id])->fetchColumn() === 1, 'Archivo inválido no deja diagnóstico parcial');

    foreach (['EN_PROCESO','RESUELTO','CERRADO'] as $estado) {
        http('/acciones.php', ['csrf' => $csrf, 'accion' => 'estado', 'id_ticket' => $id, 'estado' => $estado]);
        comprobar(ticket($id)['estado'] === $estado, 'Formulario de estado: ' . $estado);
    }
    comprobar(ticket($id)['fecha_cierre'] !== null, 'Trigger completa la fecha de cierre');
    comprobar((int) consulta("SELECT count(*) FROM soporte.asignacion WHERE id_ticket = ? AND estado='ACTIVA'", [$id])->fetchColumn() === 0, 'Cierre finaliza asignaciones');
    comprobar((int) consulta("SELECT count(*) FROM auditoria.auditoria_ticket WHERE id_ticket = ? AND usuario_bd='soporte_web'", [$id])->fetchColumn() >= 4, 'Auditoría identifica la cuenta conectada');
    echo "\n$cantidad comprobaciones aprobadas.\n";
} catch (Throwable $error) {
    fwrite(STDERR, (string) $error . PHP_EOL);
    exit(1);
}
