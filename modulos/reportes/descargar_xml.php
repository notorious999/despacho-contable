<?php
require_once '../../config/config.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener el XML de la factura
$database->query('SELECT folio_fiscal, xml_contenido, xml_filename FROM CFDIs WHERE id = :id');
$database->bind(':i