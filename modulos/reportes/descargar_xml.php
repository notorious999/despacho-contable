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
$tipo = sanitize($_GET['tipo'] ?? 'emitida'); // emitida o recibida

// Inicializar la base de datos
$database = new Database();

// Determinar tabla según el tipo
$tabla = ($tipo === 'recibida') ? 'CFDIs_Recibidas' : 'CFDIs_Emitidas';

// Obtener el XML de la factura
$database->query("SELECT folio_fiscal, xml_contenido, xml_filename FROM {$tabla} WHERE id = :id");
$database->bind(':id', $id);
$factura = $database->single();

// Verificar que la factura existe
if (!$factura) {
    flash('mensaje', 'Factura no encontrada', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

// Verificar que tiene contenido XML
if (empty($factura->xml_contenido)) {
    flash('mensaje', 'El XML de esta factura no está disponible', 'alert alert-warning');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

// Preparar el nombre del archivo
$filename = $factura->xml_filename ?: $factura->folio_fiscal . '.xml';

// Configurar headers para descarga
header('Content-Type: application/xml');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($factura->xml_contenido));

// Enviar el contenido XML
echo $factura->xml_contenido;