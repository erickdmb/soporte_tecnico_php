<?php
declare(strict_types=1);

date_default_timezone_set('America/Lima');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mongo.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/fotos.php';
require_once __DIR__ . '/operaciones.php';

function iniciar_sesion(): void
{
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
