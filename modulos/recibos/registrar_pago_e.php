<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

// Validar que el usuario esté logueado
if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Validar que la petición sea por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

// 1. Sanitizar y validar los datos del formulario
$recibo_id = isset($_POST['recibo_id']) ? (int)$_POST['recibo_id'] : 0;
$monto = isset($_POST['monto']) ? (float)$_POST['monto'] : 0;
$fecha_pago = sanitize($_POST['fecha_pago'] ?? date('Y-m-d'));
$metodo = sanitize($_POST['metodo'] ?? '');
$referencia = sanitize($_POST['referencia'] ?? '');
$observaciones = sanitize($_POST['observaciones'] ?? '');
$usuario_id = $_SESSION['user_id'] ?? null;

// Validaciones básicas
if ($recibo_id <= 0 || $monto <= 0) {
  flash('mensaje', 'Datos inválidos para registrar el pago. El monto debe ser mayor a cero.', 'alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

// 2. Usar el servicio para registrar el pago
$service = new RecibosService();
$exito = $service->registrarPago(
    $recibo_id,
    $monto,
    $fecha_pago,
    $metodo,
    $referencia,
    $observaciones,
    $usuario_id
);

// 3. Redirigir con el mensaje correspondiente
if ($exito) {
  flash('mensaje', 'Pago registrado correctamente.');
} else {
  flash('mensaje', 'Error al registrar el pago.', 'alert alert-danger');
}

redirect(URL_ROOT . '/modulos/recibos/index.php');
exit;