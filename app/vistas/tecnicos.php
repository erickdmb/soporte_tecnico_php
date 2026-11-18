<h1>Técnicos</h1>
<div class="columns"><section class="panel"><h2>Registrar técnico</h2>
<?php formulario('tecnico'); campos_persona(); campo('especialidad', 'Especialidad', 'text', 100); boton(); ?>
</section><section class="panel"><h2>Equipo de atención</h2><p>Un técnico puede atender varios tickets. Su disponibilidad se recalcula al asignar y finalizar.</p>
<?php tabla(consulta("SELECT te.id_tecnico,te.nombres,te.apellidos,te.especialidad,te.disponible,count(a.id_asignacion) FILTER (WHERE a.estado='ACTIVA') AS pendientes FROM personas.tecnico te LEFT JOIN soporte.asignacion a USING(id_tecnico) GROUP BY te.id_tecnico ORDER BY te.id_tecnico")->fetchAll()); ?>
</section></div>
