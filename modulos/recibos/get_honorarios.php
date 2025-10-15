<?php
// Iniciar la sesión para poder verificar si el usuario está logueado.
session_start(); 
header('Content-Type: application/json');

// Requerir los archivos de configuración y funciones.
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo continuar si el usuario está logueado y se recibió un ID de cliente.
if (!isLoggedIn() || !isset($_GET['id'])) {
    // Devolver un error claro si no se cumplen las condiciones.
    echo json_encode(['error' => 'Acceso no autorizado o ID de cliente no proporcionado.']);
    exit;
}

$db = new Database();
$cliente_id = (int)$_GET['id'];

$db->query('SELECT honorarios FROM clientes WHERE id = :id');
$db->bind(':id', $cliente_id);
$cliente = $db->single();

if ($cliente) {
    // Si se encuentra el cliente, devolver sus honorarios.
    echo json_encode(['honorarios' => $cliente->honorarios]);
} else {
    // Si no se encuentra, devolver un valor de 0.
    echo json_encode(['honorarios' => 0]);
}