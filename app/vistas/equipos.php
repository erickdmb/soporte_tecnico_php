<h1>Equipos</h1>
<div class="columns"><section class="panel"><h2>Agregar equipo</h2>
<?php
$clientes = consulta("SELECT id_cliente,nombres || ' ' || apellidos AS nombre FROM personas.cliente WHERE estado ORDER BY nombres")->fetchAll(PDO::FETCH_KEY_PAIR);
if ($clientes) {
    formulario('equipo');
    seleccionar('id_cliente', 'Cliente', $clientes);
    campos_equipo();
    boton();
} else {
    echo '<p>Registre primero un cliente.</p>';
}
?>
</section><section class="panel"><h2>Equipos registrados</h2>
<?php tabla(consulta("SELECT e.id_equipo,c.nombres || ' ' || c.apellidos AS cliente,t.nombre AS tipo,m.nombre AS marca,e.modelo,e.serie,e.estado FROM soporte.equipo e JOIN personas.cliente c USING(id_cliente) JOIN catalogo.tipo_equipo t USING(id_tipo_equipo) JOIN catalogo.marca m USING(id_marca) ORDER BY e.id_equipo")->fetchAll()); ?>
</section></div>
