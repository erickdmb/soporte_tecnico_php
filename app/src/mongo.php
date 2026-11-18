<?php
declare(strict_types=1);

use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;

/** Se usa directamente la extensión mongodb, sin Composer ni framework. */
function mongo(): Manager
{
    static $conexion;
    if (!$conexion) {
        $usuario = rawurlencode(getenv('MONGO_APP_USER'));
        $clave = rawurlencode(getenv('MONGO_APP_PASSWORD'));
        $base = rawurlencode(getenv('MONGO_DB'));
        $host = getenv('MONGO_HOST');
        $conexion = new Manager("mongodb://$usuario:$clave@$host:27017/$base?authSource=$base", [
            'serverSelectionTimeoutMS' => 5000,
        ]);
    }
    return $conexion;
}

function documentos(string $coleccion, array $filtro = [], array $opciones = []): array
{
    $cursor = mongo()->executeQuery(getenv('MONGO_DB') . '.' . $coleccion, new Query($filtro, $opciones));
    $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
    return $cursor->toArray();
}

function insertar_documento(string $coleccion, array $documento): mixed
{
    $escritura = new BulkWrite();
    $id = $escritura->insert($documento);
    mongo()->executeBulkWrite(getenv('MONGO_DB') . '.' . $coleccion, $escritura);
    return $id;
}

function actualizar_documento(string $coleccion, array $filtro, array $cambio): void
{
    $escritura = new BulkWrite();
    $escritura->update($filtro, $cambio, ['multi' => false, 'upsert' => false]);
    $resultado = mongo()->executeBulkWrite(getenv('MONGO_DB') . '.' . $coleccion, $escritura);
    if ($resultado->getMatchedCount() === 0) {
        throw new RuntimeException('Falta el historial del ticket. Ejecute la comprobación de consistencia.');
    }
}

function actividad(string $accion, string $detalle, string $usuario): array
{
    return ['fecha' => new UTCDateTime(), 'accion' => $accion, 'detalle' => $detalle, 'usuario' => $usuario];
}

function agregar_actividad(int $ticket, array $actividad): void
{
    actualizar_documento('historial_ticket', ['ticket_id' => $ticket], ['$push' => ['historial' => $actividad]]);
}
