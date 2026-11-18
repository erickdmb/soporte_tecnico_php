<h1>Catálogos</h1>
<div class="columns">
<?php foreach (['tipo_equipo' => 'Tipos de equipo', 'marca' => 'Marcas'] as $catalogo => $titulo): ?>
<section class="panel"><h2><?= e($titulo) ?></h2>
<?php
formulario('catalogo');
echo '<input type="hidden" name="catalogo" value="' . e($catalogo) . '">';
campo('nombre', 'Nombre', 'text', 60);
boton('Agregar');
tabla(consulta("SELECT nombre FROM catalogo.$catalogo WHERE estado ORDER BY nombre")->fetchAll());
?>
</section>
<?php endforeach ?>
</div>
