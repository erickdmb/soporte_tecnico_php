<h1>Auditoría de estados</h1>
<p>Estos registros los escribe el trigger de PostgreSQL. La cuenta web no puede insertarlos ni borrarlos directamente.</p>
<section class="panel">
<?php tabla(consulta('SELECT id_ticket,estado_anterior,estado_nuevo,usuario_bd,fecha FROM auditoria.auditoria_ticket ORDER BY id_auditoria DESC LIMIT 100')->fetchAll()); ?>
</section>
