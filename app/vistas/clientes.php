<h1>Clientes</h1>
<div class="columns">
<section class="panel"><h2>Registrar cliente y primer equipo</h2><p>Si el equipo no se puede guardar, el cliente tampoco se guarda.</p>
<?php
formulario('cliente');
campos_persona();
campo('telefono', 'Teléfono (opcional)', 'text', 15, false);
echo '<h3>Equipo inicial</h3>';
campos_equipo();
boton('Guardar cliente y equipo');
?>
</section>
<section class="panel"><h2>Clientes registrados</h2>
<?php tabla(consulta('SELECT c.id_cliente,c.nombres,c.apellidos,c.dni,c.correo,count(e.id_equipo) AS equipos FROM personas.cliente c LEFT JOIN soporte.equipo e USING(id_cliente) GROUP BY c.id_cliente ORDER BY c.id_cliente')->fetchAll()); ?>
<p><a href="<?= e(url('equipos')) ?>">Agregar otro equipo a un cliente</a></p>
</section></div>
