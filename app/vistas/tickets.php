<?php
$filtro = $_GET['estado'] ?? '';
if (!is_string($filtro)) throw new InvalidArgumentException('Estado inválido.');
$tickets = $filtro === ''
    ? consulta('SELECT * FROM soporte.vw_ticket_operativo ORDER BY id_ticket DESC')->fetchAll()
    : consulta('SELECT * FROM soporte.vw_ticket_operativo WHERE estado = ? ORDER BY id_ticket DESC', [$filtro])->fetchAll();
$equipos = consulta("SELECT e.id_equipo,c.nombres || ' ' || c.apellidos || ' · ' || e.serie AS titulo FROM soporte.equipo e JOIN personas.cliente c USING(id_cliente) WHERE c.estado ORDER BY e.id_equipo")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="page-heading"><p class="eyebrow">GESTIÓN DE ATENCIONES</p><h1>Tickets</h1><p class="intro">Busca una atención, revisa su estado y continúa el trabajo.</p></div>
<details class="panel create-ticket" id="nuevo-ticket" <?= isset($_GET['equipo']) || isset($_GET['crear']) ? 'open' : '' ?>><summary><span><?= icono('tickets') ?> Crear un nuevo ticket</span><span class="summary-toggle" aria-hidden="true">＋</span></summary><div class="details-content">
<p>Selecciona el equipo, describe el problema e indica quién lo recibe.</p>
<?php if ($equipos):
    formulario('ticket');
    seleccionar('id_equipo', 'Equipo del cliente', $equipos, $_GET['equipo'] ?? '');
    area('problema_reportado', 'Problema reportado', 300);
    seleccionar('prioridad', 'Prioridad', array_combine(PRIORIDADES, PRIORIDADES), 'MEDIA');
    campo('usuario', 'Registrado por');
    boton('Crear ticket e historial');
else: ?><p>Registre primero un cliente y su equipo.</p><a class="btn btn-primary" href="<?= e(url('clientes')) ?>">Registrar cliente</a><?php endif ?>
</div></details>
<section class="panel ticket-list"><div class="section-heading"><h2>Atenciones registradas</h2><span class="record-count" data-search-count aria-live="polite"><?= count($tickets) ?> tickets</span></div>
<div class="list-tools"><label class="search-field" for="buscar-ticket">Buscar en esta lista<input class="form-control" type="search" id="buscar-ticket" placeholder="Ticket, cliente, problema o serie…" data-search-table="tabla-tickets" aria-controls="tabla-tickets"></label>
<form class="filter" method="get"><input type="hidden" name="pagina" value="tickets">
<?php seleccionar('estado', 'Estado', ['' => 'Todos los estados'] + array_combine(ESTADOS, ESTADOS), $filtro, false); ?>
<button class="btn btn-outline-primary" type="submit">Filtrar</button></form></div>
<div class="table-responsive"><table class="table align-middle" id="tabla-tickets"><thead><tr><th scope="col">Ticket y problema</th><th scope="col">Cliente y equipo</th><th scope="col">Prioridad</th><th scope="col">Estado</th><th scope="col">Responsable</th></tr></thead><tbody>
<?php foreach ($tickets as $registro): ?>
<tr data-search-row><td><a class="ticket-title" href="<?= e(url('ticket', ['id' => $registro['id_ticket']])) ?>"><span class="ticket-number">#<?= e($registro['id_ticket']) ?></span><?= e($registro['problema_reportado']) ?></a></td>
<td><?= e($registro['cliente']) ?><small><?= e($registro['equipo']) ?> · <?= e($registro['nro_serie']) ?></small></td>
<td><?= etiqueta($registro['prioridad'], 'prioridad') ?></td><td><?= etiqueta($registro['estado']) ?></td><td><?= e($registro['tecnico']) ?></td></tr>
<?php endforeach ?>
<tr data-empty-row hidden><td colspan="5"><div class="empty-state compact"><h3>No hay coincidencias</h3><p>Prueba con otro nombre, número de ticket o serie.</p></div></td></tr>
</tbody></table></div>
<?php if (!$tickets): ?><div class="empty-state"><?= icono('tickets') ?><h3>No hay tickets<?= $filtro !== '' ? ' en este estado' : ' todavía' ?></h3><p><?= $filtro !== '' ? 'Selecciona otro estado para continuar.' : 'Crea una atención para empezar a registrar el trabajo.' ?></p><a class="btn btn-outline-primary" href="<?= e($filtro !== '' ? url('tickets') : url('tickets', ['crear' => 1])) ?>"><?= $filtro !== '' ? 'Ver todos los tickets' : 'Crear el primer ticket' ?></a></div><?php endif ?>
</section>
