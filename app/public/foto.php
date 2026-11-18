<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    $ticket = entero($_GET, 'ticket');
    ticket($ticket);
    $id = texto($_GET, 'id', 24, 24);
    if (!ctype_xdigit($id)) {
        throw new InvalidArgumentException('Foto no encontrada.');
    }
    $fotos = documentos('evidencias', ['_id' => new MongoDB\BSON\ObjectId($id), 'ticket_id' => $ticket]);
    $foto = $fotos[0] ?? null;
    if (!$foto || !in_array($foto['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new InvalidArgumentException('Foto no encontrada.');
    }
    header('Content-Type: ' . $foto['mime']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0');
    echo $foto['contenido']->getData();
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 404 : 503);
    echo e(mensaje_error($error));
}
