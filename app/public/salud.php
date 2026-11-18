<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    consulta('SELECT soporte.fn_total_tickets()');
    mongo()->executeCommand(getenv('MONGO_DB'), new MongoDB\Driver\Command(['ping' => 1]));
    echo json_encode(['estado' => 'ok', 'postgresql' => 'conectado', 'mongodb' => 'conectado']);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    echo json_encode(['estado' => 'error', 'detalle' => 'Revise los servicios Docker.']);
}
