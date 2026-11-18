<?php
declare(strict_types=1);

/** Valida TODOS los archivos antes de escribir en cualquiera de las bases. */
function preparar_fotos(array $archivos): array
{
    $nombres = $archivos['name'] ?? [];
    if (!is_array($nombres)) {
        throw new InvalidArgumentException('Formato de carga de fotos inválido.');
    }
    $fotos = [];
    $formatos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach ($nombres as $indice => $nombre) {
        $error = $archivos['error'][$indice] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo cargar una foto. Máximo 5 MB por archivo.');
        }
        if (count($fotos) >= 5) {
            throw new InvalidArgumentException('Seleccione como máximo cinco fotos.');
        }
        $ruta = $archivos['tmp_name'][$indice];
        if (!is_uploaded_file($ruta) || filesize($ruta) > 5 * 1024 * 1024 || filesize($ruta) === 0) {
            throw new InvalidArgumentException('Cada foto debe pesar entre 1 byte y 5 MB.');
        }
        $imagen = @getimagesize($ruta);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
        if (!$imagen || !isset($formatos[$mime]) || $imagen['mime'] !== $mime || $imagen[0] * $imagen[1] > 20000000) {
            throw new InvalidArgumentException('Solo se admiten fotos JPG, PNG o WebP de hasta 20 millones de píxeles.');
        }
        // Cada documento pesa menos de 5 MB; no hace falta GridFS en este ejemplo.
        $fotos[] = ['nombre' => 'foto-' . ($indice + 1) . '.' . $formatos[$mime], 'mime' => $mime, 'contenido' => file_get_contents($ruta)];
    }
    return $fotos;
}
