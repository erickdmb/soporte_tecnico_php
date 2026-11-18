<?php
declare(strict_types=1);

/** Una conexión PDO por solicitud. Todas las entradas usan parámetros. */
function pg(): PDO
{
    static $conexion;
    if (!$conexion) {
        $dsn = 'pgsql:host=' . getenv('POSTGRES_HOST') . ';dbname=' . getenv('POSTGRES_DB');
        $conexion = new PDO($dsn, getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $conexion;
}

function consulta(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = pg()->prepare($sql);
    $sentencia->execute($parametros);
    return $sentencia;
}

/** SQL revierte automáticamente si una operación del bloque falla. */
function transaccion(callable $operacion): mixed
{
    pg()->beginTransaction();
    try {
        $resultado = $operacion();
        pg()->commit();
        return $resultado;
    } catch (Throwable $error) {
        if (pg()->inTransaction()) {
            pg()->rollBack();
        }
        throw $error;
    }
}
