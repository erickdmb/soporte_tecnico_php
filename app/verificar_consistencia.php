<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';

try {
    $tickets = array_map('intval', consulta('SELECT id_ticket FROM soporte.ticket')->fetchAll(PDO::FETCH_COLUMN));
    $diagnosticos = consulta('SELECT id_diagnostico,id_ticket FROM soporte.diagnostico')->fetchAll(PDO::FETCH_KEY_PAIR);
    $historiales = documentos('historial_ticket');
    $fotos = documentos('evidencias', [], ['projection' => ['contenido' => 0]]);
    $documentales = array_column($historiales, 'ticket_id');
    $resultado = [
        'tickets_sin_historial' => array_values(array_diff($tickets, $documentales)),
        'historiales_sin_ticket' => array_values(array_diff($documentales, $tickets)),
        'actividades_sin_diagnostico' => [],
        'fotos_sin_diagnostico' => [],
        'referencias_sin_foto' => [],
        'fotos_sin_referencia' => [],
    ];
    $referencias = [];
    $idsFotos = [];
    foreach ($fotos as $foto) {
        $idsFotos[(string) $foto['_id']] = (int) $foto['ticket_id'];
        if ((int) ($diagnosticos[$foto['id_diagnostico']] ?? 0) !== (int) $foto['ticket_id']) {
            $resultado['fotos_sin_diagnostico'][] = (string) $foto['_id'];
        }
    }
    foreach ($historiales as $historial) {
        foreach ($historial['historial'] as $actividad) {
            if (isset($actividad['id_diagnostico']) && (int) ($diagnosticos[$actividad['id_diagnostico']] ?? 0) !== (int) $historial['ticket_id']) {
                $resultado['actividades_sin_diagnostico'][] = $historial['ticket_id'];
            }
            foreach ($actividad['fotos'] ?? [] as $foto) {
                $referencias[] = $foto;
                if (($idsFotos[$foto] ?? 0) !== (int) $historial['ticket_id']) {
                    $resultado['referencias_sin_foto'][] = $foto;
                }
            }
        }
    }
    $resultado['fotos_sin_referencia'] = array_values(array_diff(array_keys($idsFotos), $referencias));
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(array_filter($resultado) ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'No se pudo comprobar la consistencia: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
