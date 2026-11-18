<?php
$id = entero($_GET, 'id');
$registro = ticket($id);
$historial = documentos('historial_ticket', ['ticket_id' => $id])[0] ?? null;
$diagnosticos = consulta('SELECT * FROM soporte.diagnostico WHERE id_ticket = ? ORDER BY id_diagnostico DESC', [$id])->fetchAll();
$servicios = consulta('SELECT * FROM soporte.servicio_realizado WHERE id_ticket = ? ORDER BY id_servicio DESC', [$id])->fetchAll();
$repuestos = consulta('SELECT r.* FROM soporte.repuesto_utilizado r JOIN soporte.servicio_realizado s USING(id_servicio) WHERE s.id_ticket = ? ORDER BY r.id_repuesto_utilizado', [$id])->fetchAll();
$total = consulta('SELECT soporte.fn_costo_ticket(?)', [$id])->fetchColumn();
$tecnicos = consulta("SELECT id_tecnico,nombres || ' ' || apellidos AS nombre FROM personas.tecnico WHERE estado ORDER BY nombres")->fetchAll(PDO::FETCH_KEY_PAIR);
$asignaciones = consulta("SELECT te.nombres || ' ' || te.apellidos AS tecnico,a.estado,a.fecha_asignacion FROM soporte.asignacion a JOIN personas.tecnico te USING(id_tecnico) WHERE a.id_ticket = ? ORDER BY a.id_asignacion DESC", [$id])->fetchAll();
$fotos = documentos('evidencias', ['ticket_id' => $id], ['projection' => ['contenido' => 0], 'sort' => ['fecha' => -1]]);
?>
<a href="<?= e(url('tickets')) ?>">← Tickets</a>
<p class="eyebrow">TICKET #<?= $id ?> · <?= e($registro['estado']) ?></p>
<h1><?= e($registro['problema_reportado']) ?></h1>
<div class="columns">
<section class="panel"><h2>Atención</h2>
<dl>
<?php foreach (['cliente' => 'Cliente', 'equipo' => 'Equipo', 'nro_serie' => 'Serie', 'estado_equipo' => 'Estado del equipo', 'tecnico' => 'Responsable', 'prioridad' => 'Prioridad'] as $clave => $etiqueta): ?>
<dt><?= e($etiqueta) ?></dt><dd><?= e($registro[$clave]) ?></dd>
<?php endforeach ?>
<dt>Apertura</dt><dd><?= e(fecha($registro['fecha_registro'])) ?></dd><dt>Cierre</dt><dd><?= e(fecha($registro['fecha_cierre'])) ?></dd>
</dl>
<?php
if ($tecnicos && !in_array($registro['estado'], ['RESUELTO', 'CERRADO'], true)) {
    formulario('asignar', $id);
    seleccionar('id_tecnico', 'Asignar o cambiar técnico', $tecnicos);
    boton('Asignar responsable');
}
formulario('estado', $id);
seleccionar('estado', 'Cambiar estado', array_combine(ESTADOS, ESTADOS), $registro['estado']);
boton('Actualizar estado');
formulario('prioridad', $id);
seleccionar('prioridad', 'Cambiar prioridad', array_combine(PRIORIDADES, PRIORIDADES), $registro['prioridad']);
boton('Actualizar prioridad');
?>
<p class="hint">Para cerrar, primero cambie a RESUELTO. El trigger valida el cierre y registra la auditoría.</p>
<h3>Asignaciones anteriores</h3><?php tabla($asignaciones); ?>
</section>
<section class="panel"><h2>Diagnóstico y fotos</h2><p>El diagnóstico se guarda en PostgreSQL; la actividad y las fotos, en MongoDB.</p>
<?php
formulario('diagnostico', $id, true);
area('detalle', 'Diagnóstico');
area('recomendacion', 'Recomendación (opcional)', 500, false);
campo('usuario', 'Responsable');
?>
<label>Fotos (opcional)<input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple></label>
<small>Hasta cinco fotos de 5 MB cada una. JPG, PNG o WebP.</small>
<?php boton('Guardar diagnóstico'); ?>
<h3>Diagnósticos registrados</h3>
<?php foreach ($diagnosticos as $diagnostico): ?>
<article class="activity"><small><?= e(fecha($diagnostico['fecha'])) ?></small><p><?= e($diagnostico['detalle']) ?></p><p><?= e($diagnostico['recomendacion']) ?></p></article>
<?php endforeach ?>
</section>
</div>
<section class="panel"><h2>Servicios y repuestos</h2>
<p class="total">Costo total: S/ <?= e(number_format((float) $total, 2, '.', ',')) ?></p>
<p>PostgreSQL suma la mano de obra y los repuestos mediante <code>fn_costo_ticket</code>.</p>
<div class="columns"><div>
<?php
formulario('servicio', $id);
area('descripcion', 'Servicio realizado');
campo('costo_mano_obra', 'Costo de mano de obra (S/)', 'text', 11);
boton('Agregar servicio');
?>
</div><div>
<?php if ($servicios):
    $opciones = [];
    foreach ($servicios as $servicio) $opciones[$servicio['id_servicio']] = '#' . $servicio['id_servicio'] . ' · ' . $servicio['descripcion'];
    formulario('repuesto', $id);
    seleccionar('id_servicio', 'Servicio al que pertenece', $opciones);
    campo('nombre_repuesto', 'Nombre del repuesto');
    campo('cantidad', 'Cantidad', 'number', 5);
    campo('costo_unitario', 'Costo unitario (S/)', 'text', 11);
    boton('Agregar repuesto');
else: ?><p>Agregue un servicio para registrar sus repuestos.</p><?php endif ?>
</div></div>
<h3>Servicios registrados</h3><?php tabla($servicios); ?>
<h3>Repuestos registrados</h3><?php tabla($repuestos); ?>
</section>
<div class="columns">
<section class="panel"><h2>Historial documental</h2>
<?php if (!$historial): ?><p class="message error">Falta el historial de este ticket. Ejecute el verificador de consistencia.</p><?php endif ?>
<?php
formulario('actividad', $id);
seleccionar('tipo', 'Tipo de actividad', ['SEGUIMIENTO' => 'Seguimiento', 'SOLUCION' => 'Solución']);
area('detalle', 'Actividad realizada', 2000);
campo('usuario', 'Responsable');
boton('Agregar actividad');
?>
<?php foreach (array_reverse($historial['historial'] ?? []) as $actividad): ?>
<article class="activity"><span class="tag"><?= e($actividad['accion']) ?></span><small><?= e(fecha($actividad['fecha'])) ?> · <?= e($actividad['usuario']) ?></small><p><?= e($actividad['detalle']) ?></p></article>
<?php endforeach ?>
</section>
<section class="panel"><h2>Observaciones</h2><p>Agregar utiliza <code>$addToSet</code>, que evita duplicados. Quitar utiliza <code>$pull</code> sobre el texto exacto.</p>
<?php
formulario('observacion', $id);
campo('observacion', 'Texto de la observación', 'text', 500);
seleccionar('operador', 'Operación', ['$addToSet' => 'Agregar', '$pull' => 'Quitar']);
boton('Aplicar');
?>
<ul><?php foreach ($historial['observaciones'] ?? [] as $observacion): ?><li><?= e($observacion) ?></li><?php endforeach ?></ul>
</section></div>
<section class="panel"><h2>Evidencias del equipo</h2>
<div class="photos">
<?php foreach ($fotos as $foto): $enlace = '/foto.php?' . http_build_query(['ticket' => $id, 'id' => (string) $foto['_id']]); ?>
<figure><a href="<?= e($enlace) ?>" target="_blank" rel="noopener"><img src="<?= e($enlace) ?>" alt="<?= e($foto['nombre']) ?>" loading="lazy"></a><figcaption>Diagnóstico #<?= e($foto['id_diagnostico']) ?><small><?= e(fecha($foto['fecha'])) ?> · <?= e($foto['usuario']) ?></small></figcaption></figure>
<?php endforeach ?>
</div><?php if (!$fotos): ?><p>Aún no hay fotos.</p><?php endif ?>
</section>
