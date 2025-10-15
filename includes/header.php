<?php
// Cambiar la ruta relativa por una absoluta
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirigir si no está logueado
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="<?php echo URL_ROOT; ?>/uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/styles.css">
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo URL_ROOT; ?>">
                <img src="<?php echo URL_ROOT; ?>/uploads/logo.png" alt="Logo de <?php echo SITE_NAME; ?>" class="navbar-logo me-2">
                <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isLoggedIn()) : ?>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo URL_ROOT; ?>/index.php">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo URL_ROOT; ?>/modulos/clientes/index.php">Clientes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php">Reportes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo URL_ROOT; ?>/modulos/reportes/clientes_totales.php">
                                <i class=""></i> Totales
                            </a>
                        </li>
                        <li class="nav-item">
                                <a class="nav-link" href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php">Recibos</a>
                            </li>
                        <?php if (hasRole('administrador')) : ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo URL_ROOT; ?>/modulos/usuarios/index.php">Usuarios</a>
                            </li>
                            


                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name'] ?? 'Usuario'; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/usuarios/perfil.php">Perfil</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/usuarios/logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php flash('mensaje'); ?>