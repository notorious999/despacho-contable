<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

$recibo_id = (int)sanitize($_POST['recibo_id'] ?? 0);
$fecha_vencimiento = trim((string)sanitize($_POST['fecha_vencimiento'] ?? ''));

// Permitir vacío para quitar vencimiento
if ($fecha_vencimiento === '') $fecha_vencimiento = null;

if ($recibo_id <= 0) {
    flash('mensaje', 'Recibo inválido.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

$db = new Database();
$db->query('UPDATE recibos SET fecha_vencimiento = :fv, vencimiento = :fv WHERE id = :id');
$db->bind(':fv', $fecha_vencimiento);
$db->bind(':id', $recibo_id);

if (!$db->execute()) {
    flash('mensaje', 'No se pudo actualizar el vencimiento.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

// Recalcular estado tras cambiar el vencimiento
$service = new RecibosService();
$service->recalcularEstado($recibo_id);

flash('mensaje', 'Vencimiento actualizado correctamente.', 'alert alert-success');
$back = $_SERVER['HTTP_REFERER'] ?? (URL_ROOT . '/modulos/recibos/index.php');
redirect($back);