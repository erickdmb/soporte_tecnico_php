<?php
$resumen = consulta("SELECT count(*) AS total, count(*) FILTER (WHERE estado NOT IN ('RESUELTO','CERRADO')) AS activos, count(*) FILTER (WHERE estado IN ('RESUELTO','CERRADO')) AS atendidos FROM soporte.ticket")->fetch();
$clientes = consulta('SELECT count(*) FROM personas.cliente')->fetchColumn();
$recientes = consulta('SELECT id_ticket,problema_reportado,cliente,equipo,prioridad,estado FROM soporte.vw_ticket_operativo ORDER BY id_ticket DESC LIMIT 5')->fetchAll();
?>
<div class="welcome-heading"><div><p class="eyebrow">VISTA GENERAL</p><h1>Resumen de atenciones</h1><p class="intro">Organiza los equipos, acompaña cada atención y encuentra el siguiente paso.</p></div><a class="btn btn-outline-primary" href="<?= e(url('clientes')) ?>"><?= icono('clientes') ?> Registrar cliente</a></div>
<section class="stats" aria-label="Resumen de atenciones">
    <?php foreach ([['total', 'Tickets registrados', 'tickets', 'Todas las atenciones'], ['activos', 'En atención', 'actividad', 'Abiertos, asignados y en proceso'], ['atendidos', 'Atendidos', 'completado', 'Resueltos o cerrados'], ['clientes', 'Clientes', 'clientes', 'Personas registradas']] as [$clave, $titulo, $icono, $ayuda]): ?>
        <article class="stat-card stat-<?= e($clave) ?>"><div class="stat-heading"><span><?= e($titulo) ?></span><span class="stat-icon"><?= icono($icono) ?></span></div><strong><?= e($clave === 'clientes' ? $clientes : $resumen[$clave]) ?></strong><small><?= e($ayuda) ?></small></article>
    <?php endforeach ?>
</section>
<section class="panel recent-tickets"><div class="section-heading"><div><p class="eyebrow">SEGUIMIENTO</p><h2>Últimos tickets</h2></div><a class="text-link" href="<?= e(url('tickets')) ?>">Ver todos <span aria-hidden="true">→</span></a></div>
<?php if ($recientes): ?>
<div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">Atención</th><th scope="col">Cliente</th><th scope="col">Prioridad</th><th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Detalle</span></th></tr></thead><tbody>
<?php foreach ($recientes as $registro): ?>
<tr><td><a class="ticket-title" href="<?= e(url('ticket', ['id' => $registro['id_ticket']])) ?>"><span class="ticket-number">#<?= e($registro['id_ticket']) ?></span><?= e($registro['problema_reportado']) ?></a></td><td><?= e($registro['cliente']) ?><small><?= e($registro['equipo']) ?></small></td><td><?= etiqueta($registro['prioridad'], 'prioridad') ?></td><td><?= etiqueta($registro['estado']) ?></td><td><a class="detail-link" href="<?= e(url('ticket', ['id' => $registro['id_ticket']])) ?>" aria-label="Abrir ticket <?= e($registro['id_ticket']) ?>">→</a></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php else: ?><div class="empty-state"><?= icono('tickets') ?><h3>El primer ticket empieza aquí</h3><p>Registra un cliente y su equipo para iniciar una atención.</p><a class="btn btn-primary" href="<?= e(url('clientes')) ?>">Registrar cliente</a></div><?php endif ?>
</section>
<div class="section-heading"><div><p class="eyebrow">PASO A PASO</p><h2>¿Qué necesitas hacer?</h2></div></div>
<div class="quick-grid">
    <section class="panel action-card"><span class="action-icon"><?= icono('clientes') ?></span><span class="step-label">01 · RECEPCIÓN</span><h2>Recibir un equipo</h2><p>Registra al cliente y su equipo en una sola operación.</p><a class="text-link" href="<?= e(url('clientes')) ?>">Registrar cliente <span aria-hidden="true">→</span></a></section>
    <section class="panel action-card"><span class="action-icon"><?= icono('tickets') ?></span><span class="step-label">02 · ATENCIÓN</span><h2>Continuar un ticket</h2><p>Asigna al responsable, registra el diagnóstico y sigue la solución.</p><a class="text-link" href="<?= e(url('tickets')) ?>">Consultar tickets <span aria-hidden="true">→</span></a></section>
    <section class="panel action-card"><span class="action-icon"><?= icono('auditoria') ?></span><span class="step-label">03 · CONTROL</span><h2>Revisar los cambios</h2><p>Consulta quién cambió el estado de cada ticket y cuándo lo hizo.</p><a class="text-link" href="<?= e(url('auditoria')) ?>">Consultar auditoría <span aria-hidden="true">→</span></a></section>
</div>
