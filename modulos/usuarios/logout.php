<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Destruir todas las variables de sesión
session_unset();

// Destruir la sesión
session_destroy();

// Redirigir a la página de login con mensaje
flash('mensaje', 'Has cerrado sesión correctamente', 'alert alert-success');
redirect(URL_ROOT . '/modulos/usuarios/login.php');
?>