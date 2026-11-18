<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#173c35">
    <title><?= e($paginas[$pagina]) ?> · Soporte PHP</title>
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/styles.css?v=3">
    <script src="/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script src="/interfaz.js" defer></script>
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="menu-principal" aria-labelledby="titulo-menu">
    <div class="offcanvas-header d-lg-none">
        <h2 class="offcanvas-title fs-5" id="titulo-menu">Menú principal</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#menu-principal" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <a class="brand" href="/"><span class="brand-mark"><?= icono('equipo') ?></span><span>Soporte<span class="brand-subtitle">Gestión de atención técnica</span></span></a>
        <p class="nav-label">ESPACIO DE TRABAJO</p>
        <nav aria-label="Principal">
            <?php foreach ($paginas as $clave => $titulo): if ($clave === 'ticket') continue;
                $activa = $clave === $pagina || ($pagina === 'ticket' && $clave === 'tickets'); ?>
                <a href="<?= e(url($clave)) ?>" <?= $activa ? 'aria-current="page"' : '' ?>><?= icono($clave) ?><span><?= e($titulo) ?></span></a>
            <?php endforeach ?>
        </nav>
        <div class="sidebar-note"><span class="sidebar-note-label">SEGUIMIENTO</span><p>Consulta los cambios de estado de las atenciones.</p><a href="<?= e(url('auditoria')) ?>">Ver auditoría <span aria-hidden="true">→</span></a></div>
        <div class="sidebar-footer">Proyecto académico <span>PHP · PostgreSQL · MongoDB</span></div>
    </div>
</aside>
<div class="app-shell">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-light d-lg-none menu-toggle" data-bs-toggle="offcanvas" data-bs-target="#menu-principal" aria-controls="menu-principal" aria-label="Abrir menú"><?= icono('menu') ?></button>
            <div><span class="topbar-label">Centro de soporte</span><p class="topbar-location"><?= e($paginas[$pagina]) ?></p></div>
        </div>
        <div class="d-flex align-items-center gap-3"><span class="topbar-date d-none d-md-inline"><?= e(date('d/m/Y')) ?></span><a class="btn btn-primary new-ticket" href="<?= e(url('tickets', ['crear' => 1])) ?>"><span aria-hidden="true">＋</span> Nuevo ticket</a></div>
    </header>
    <main id="contenido" tabindex="-1">
        <?php if (isset($_SESSION['mensaje'])): [$tipo, $mensaje] = $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
            <div class="alert <?= $tipo === 'ok' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show" role="status"><?= e($mensaje) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button></div>
        <?php endif ?>
        <?= $contenido ?>
    </main>
    <footer class="app-footer"><span>Soporte técnico · Aprender con un caso práctico</span><a href="/salud.php">Comprobar conexiones <span aria-hidden="true">↗</span></a></footer>
</div>
</body>
</html>
