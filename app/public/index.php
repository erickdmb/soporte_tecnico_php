<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/vistas.php';
iniciar_sesion();
$paginas = [
    'inicio' => 'Resumen', 'clientes' => 'Clientes', 'equipos' => 'Equipos',
    'tickets' => 'Tickets', 'tecnicos' => 'Técnicos', 'catalogos' => 'Catálogos',
    'auditoria' => 'Auditoría', 'ticket' => 'Detalle del ticket',
];
$pagina = $_GET['pagina'] ?? 'inicio';
if (!is_string($pagina) || !isset($paginas[$pagina])) {
    http_response_code(404);
    exit('Página no encontrada.');
}
// Preparar el contenido antes de enviar HTML permite responder con código 503 si falla una base.
ob_start();
try {
    require __DIR__ . '/../vistas/' . $pagina . '.php';
    $contenido = ob_get_clean();
} catch (Throwable $error) {
    ob_end_clean();
    http_response_code($error instanceof InvalidArgumentException ? 404 : 503);
    $contenido = '<section class="panel"><h1>No se pudo mostrar la página</h1><p>' . e(mensaje_error($error)) . '</p></section>';
}
require __DIR__ . '/../vistas/layout.php';
