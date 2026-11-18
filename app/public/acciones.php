<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
iniciar_sesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Utilice un formulario.');
}
if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Formulario vencido. Recargue la página y vuelva a intentarlo.');
}
$pagina = 'inicio';
$parametros = [];
try {
    $accion = texto($_POST, 'accion', 1, 30);
    // El destino se determina aquí, nunca con una URL enviada por el visitante.
    $pagina = match ($accion) {
        'cliente' => 'clientes', 'equipo' => 'equipos', 'tecnico' => 'tecnicos',
        'catalogo' => 'catalogos', 'ticket' => 'tickets',
        default => 'ticket',
    };
    if ($pagina === 'ticket') {
        $id = entero($_POST, 'id_ticket');
        ticket($id);
        $parametros = ['id' => $id];
    }
    switch ($accion) {
        case 'cliente':
            $equipo = registrar_cliente($_POST);
            $pagina = 'tickets';
            $parametros = ['equipo' => $equipo];
            break;
        case 'equipo':
            $equipo = insertar_equipo($_POST, entero($_POST, 'id_cliente'));
            $pagina = 'tickets';
            $parametros = ['equipo' => $equipo];
            break;
        case 'tecnico':
            registrar_tecnico($_POST);
            break;
        case 'catalogo':
            $tabla = opcion($_POST, 'catalogo', ['tipo_equipo', 'marca']);
            // Identificador de una lista cerrada; el nombre se envía como parámetro.
            consulta("INSERT INTO catalogo.$tabla(nombre) VALUES (?)", [texto($_POST, 'nombre', 1, 60)]);
            break;
        case 'ticket':
            $parametros = ['id' => crear_ticket($_POST)];
            $pagina = 'ticket';
            break;
        case 'asignar':
            consulta('CALL soporte.sp_asignar_tecnico(?,?)', [$id, entero($_POST, 'id_tecnico')]);
            break;
        case 'estado':
            consulta('CALL soporte.sp_cambiar_estado(?,?)', [$id, opcion($_POST, 'estado', ESTADOS)]);
            break;
        case 'prioridad':
            consulta('CALL soporte.sp_cambiar_prioridad(?,?)', [$id, opcion($_POST, 'prioridad', PRIORIDADES)]);
            break;
        case 'diagnostico':
            registrar_diagnostico($id, $_POST, $_FILES['fotos'] ?? []);
            break;
        case 'servicio':
            registrar_servicio($id, $_POST);
            break;
        case 'repuesto':
            registrar_repuesto($id, $_POST);
            break;
        case 'actividad':
            agregar_actividad($id, actividad(
                opcion($_POST, 'tipo', ['SEGUIMIENTO', 'SOLUCION']),
                texto($_POST, 'detalle', 5, 2000), texto($_POST, 'usuario', 3, 120)
            ));
            break;
        case 'observacion':
            $operador = opcion($_POST, 'operador', ['$addToSet', '$pull']);
            actualizar_documento('historial_ticket', ['ticket_id' => $id], [$operador => ['observaciones' => texto($_POST, 'observacion', 1, 500)]]);
            break;
        default:
            throw new InvalidArgumentException('Operación no permitida.');
    }
    $_SESSION['mensaje'] = ['ok', 'Operación completada.'];
} catch (Throwable $error) {
    $_SESSION['mensaje'] = ['error', mensaje_error($error)];
}
redirigir($pagina, $parametros);
