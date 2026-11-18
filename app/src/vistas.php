<?php
declare(strict_types=1);

function formulario(string $accion, ?int $ticket = null, bool $fotos = false): void
{
    echo '<form method="post" action="/acciones.php"' . ($fotos ? ' enctype="multipart/form-data"' : '') . '>';
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
    echo '<input type="hidden" name="accion" value="' . e($accion) . '">';
    if ($ticket !== null) {
        echo '<input type="hidden" name="id_ticket" value="' . $ticket . '">';
    }
}

function campo(string $nombre, string $etiqueta, string $tipo = 'text', int $maximo = 120, bool $requerido = true): void
{
    echo '<label class="form-label">' . e($etiqueta) . '<input class="form-control" name="' . e($nombre) . '" type="' . e($tipo) . '" maxlength="' . $maximo . '"' . ($tipo === 'number' ? ' min="1" max="10000"' : '') . ($requerido ? ' required' : '') . '></label>';
}

function area(string $nombre, string $etiqueta, int $maximo = 500, bool $requerido = true): void
{
    echo '<label class="form-label">' . e($etiqueta) . '<textarea class="form-control" name="' . e($nombre) . '" rows="3" maxlength="' . $maximo . '"' . ($requerido ? ' required' : '') . '></textarea></label>';
}

function seleccionar(string $nombre, string $etiqueta, array $opciones, mixed $seleccion = '', bool $requerido = true): void
{
    echo '<label class="form-label">' . e($etiqueta) . '<select class="form-select" name="' . e($nombre) . '"' . ($requerido ? ' required' : '') . '>';
    foreach ($opciones as $valor => $titulo) {
        echo '<option value="' . e($valor) . '"' . ((string) $valor === (string) $seleccion ? ' selected' : '') . '>' . e($titulo) . '</option>';
    }
    echo '</select></label>';
}

function boton(string $titulo = 'Guardar'): void
{
    echo '<button class="btn btn-primary" type="submit">' . e($titulo) . '</button></form>';
}

function campos_persona(): void
{
    campo('dni', 'DNI (8 dígitos)', 'text', 8);
    campo('nombres', 'Nombres', 'text', 80);
    campo('apellidos', 'Apellidos', 'text', 80);
    campo('correo', 'Correo', 'email');
}

function campos_equipo(): void
{
    $tipos = consulta('SELECT id_tipo_equipo,nombre FROM catalogo.tipo_equipo WHERE estado ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
    $marcas = consulta('SELECT id_marca,nombre FROM catalogo.marca WHERE estado ORDER BY nombre')->fetchAll(PDO::FETCH_KEY_PAIR);
    seleccionar('id_tipo_equipo', 'Tipo de equipo', $tipos);
    seleccionar('id_marca', 'Marca', $marcas);
    campo('modelo', 'Modelo (opcional)', 'text', 80, false);
    campo('serie', 'Número de serie', 'text', 80);
}

function tabla(array $registros): void
{
    if (!$registros) {
        echo '<p class="empty-note">Aún no hay registros para mostrar.</p>';
        return;
    }
    echo '<div class="table-responsive"><table class="table align-middle"><thead><tr>';
    foreach (array_keys($registros[0]) as $columna) {
        echo '<th scope="col">' . e(ucfirst(str_replace('_', ' ', $columna))) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($registros as $registro) {
        echo '<tr>';
        foreach ($registro as $valor) {
            echo '<td>' . e(is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

/** Estados legibles: el texto también distingue los estados, además del color. */
function etiqueta(string $valor, string $tipo = 'estado'): string
{
    $clases = [
        'ABIERTO' => 'status-open', 'ASIGNADO' => 'status-assigned',
        'EN_PROCESO' => 'status-progress', 'RESUELTO' => 'status-done',
        'CERRADO' => 'status-closed', 'ALTA' => 'priority-high',
        'MEDIA' => 'priority-medium', 'BAJA' => 'priority-low',
    ];
    return '<span class="status ' . ($clases[$valor] ?? 'status-closed') . ($tipo === 'prioridad' ? ' priority' : '') . '">' . e(ucfirst(strtolower(str_replace('_', ' ', $valor)))) . '</span>';
}

/** Iconos SVG locales: sin fuentes ni servicios externos. */
function icono(string $nombre): string
{
    $trazos = [
        'inicio' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'tickets' => 'M6 3h12v18H6z M9 7h6 M9 11h6 M9 15h4',
        'clientes' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
        'equipos' => 'M3 4h18v13H3z M8 21h8 M12 17v4',
        'equipo' => 'M3 4h18v13H3z M8 21h8 M12 17v4',
        'tecnicos' => 'm14 6 4 4 M3 21l8-8 M14 3a6 6 0 0 0-3 8L3 19l2 2 8-8a6 6 0 0 0 8-7l-4 4-3-3 4-4a6 6 0 0 0-4 0z',
        'catalogos' => 'M3 4h7v7H3z M14 4h7v7h-7z M3 15h7v6H3z M14 15h7v6h-7z',
        'auditoria' => 'M12 3 3 7v6c0 5 9 8 9 8s9-3 9-8V7z m-5 9 3 3 6-6',
        'actividad' => 'M3 12h4l3-8 4 16 3-8h4',
        'completado' => 'm5 12 4 4L20 5 M21 12a9 9 0 1 1-9-9',
        'menu' => 'M4 6h16 M4 12h16 M4 18h16',
    ];
    return '<svg class="ui-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . e($trazos[$nombre] ?? $trazos['tickets']) . '"/></svg>';
}
