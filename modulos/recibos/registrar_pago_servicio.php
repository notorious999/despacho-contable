<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(URL_ROOT . '/modulos/recibos/servicios.php');
}

$service = new RecibosService();
$recibo_id = isset($_POST['recibo_id']) ? (int)$_POST['recibo_id'] : 0;
$monto = isset($_POST['monto']) ? (float)$_POST['monto'] : 0;
$fecha_pago = sanitize($_POST['fecha_pago']);
$metodo = sanitize($_POST['metodo']);
$referencia = sanitize($_POST['referencia']);
$observaciones = sanitize($_POST['observaciones']);

if ($recibo_id > 0 && $monto > 0) {
    // CORRECCIÓN: Se ha intercambiado el orden de $monto y $fecha_pago para que coincida con la definición de la función.
    $pago_id = $service->registrarPago($recibo_id, $fecha_pago, $monto, $metodo, $referencia, $observaciones, $_SESSION['user_id']);
    
    if ($pago_id) {
        flash('mensaje', 'Pago registrado correctamente.');
    } else {
        flash('mensaje', 'Error al registrar el pago.', 'alert alert-danger');
    }
} else {
    flash('mensaje', 'Datos de pago inválidos.', 'alert alert-danger');
}

redirect(URL_ROOT . '/modulos/recibos/servicios.php');
?>