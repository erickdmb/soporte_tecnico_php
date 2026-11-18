<?php
declare(strict_types=1);

const ESTADOS = ['ABIERTO', 'ASIGNADO', 'EN_PROCESO', 'RESUELTO', 'CERRADO'];
const PRIORIDADES = ['BAJA', 'MEDIA', 'ALTA'];

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function texto(array $datos, string $campo, int $minimo = 1, int $maximo = 500): string
{
    $valor = $datos[$campo] ?? '';
    if (!is_string($valor)) {
        throw new InvalidArgumentException("Revise el campo $campo.");
    }
    $valor = trim($valor);
    $longitud = preg_match_all('/./us', $valor);
    if ($longitud === false || $longitud < $minimo || $longitud > $maximo) {
        throw new InvalidArgumentException("El campo $campo admite entre $minimo y $maximo caracteres.");
    }
    return $valor;
}

function entero(array $datos, string $campo): int
{
    $valor = filter_var($datos[$campo] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    if ($valor === false) {
        throw new InvalidArgumentException("Seleccione un registro válido: $campo.");
    }
    return $valor;
}

function opcion(array $datos, string $campo, array $permitidos): string
{
    $valor = $datos[$campo] ?? '';
    if (!in_array($valor, $permitidos, true)) {
        throw new InvalidArgumentException("Valor no permitido en $campo.");
    }
    return $valor;
}

/** Conserva el importe como texto decimal: evita cálculos monetarios con float. */
function importe(array $datos, string $campo): string
{
    $valor = texto($datos, $campo, 1, 11);
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $valor)) {
        throw new InvalidArgumentException('Ingrese un costo entre 0 y 99999999.99, con hasta dos decimales.');
    }
    return $valor;
}

function persona(array $datos): array
{
    $dni = texto($datos, 'dni', 8, 8);
    if (!preg_match('/^[0-9]{8}$/D', $dni)) {
        throw new InvalidArgumentException('El DNI debe tener ocho dígitos.');
    }
    $correo = texto($datos, 'correo', 3, 120);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ingrese un correo válido.');
    }
    return [$dni, texto($datos, 'nombres', 1, 80), texto($datos, 'apellidos', 1, 80), $correo];
}

function fecha(mixed $valor): string
{
    if (!$valor) {
        return '—';
    }
    $fecha = $valor instanceof MongoDB\BSON\UTCDateTime ? $valor->toDateTime() : new DateTimeImmutable((string) $valor);
    return $fecha->setTimezone(new DateTimeZone('America/Lima'))->format('d/m/Y H:i');
}

function url(string $pagina, array $parametros = []): string
{
    return '/index.php?' . http_build_query(['pagina' => $pagina] + $parametros);
}

function redirigir(string $pagina, array $parametros = []): never
{
    header('Location: ' . url($pagina, $parametros), true, 303);
    exit;
}

function mensaje_error(Throwable $error): string
{
    if ($error instanceof InvalidArgumentException) {
        return $error->getMessage();
    }
    error_log((string) $error);
    if ($error instanceof PDOException) {
        return match ($error->getCode()) {
            '23505' => 'Ya existe un registro con ese DNI, correo, serie o nombre.',
            'P0001' => 'Operación rechazada. Para cerrar, primero resuelva el ticket; para asignar, use un ticket activo y un técnico válido.',
            '23503', '23514' => 'Revise los registros relacionados y los valores permitidos.',
            default => 'No se pudo completar la operación SQL. Consulte los registros del servicio web.',
        };
    }
    return 'No se pudo completar la operación. Revise los servicios y ejecute el verificador de consistencia si hubo escrituras en ambos motores.';
}
