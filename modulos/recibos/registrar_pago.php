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
}

$db = new Database();

$recibo_id   = (int)sanitize($_POST['recibo_id'] ?? 0);
$fecha_pago  = sanitize($_POST['fecha_pago'] ?? date('Y-m-d'));
$monto       = (float)sanitize($_POST['monto'] ?? 0);
$metodo      = sanitize($_POST['metodo'] ?? '');
$referencia  = sanitize($_POST['referencia'] ?? '');
$obs         = sanitize($_POST['observaciones'] ?? '');
$usuario_id  =  ($_SESSION['user_id'] ?? null);

if ($recibo_id <= 0 || $monto <= 0) {
    flash('mensaje', 'Datos de pago inválidos.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

// Obtener saldo
$db->query('SELECT monto, monto_pagado FROM recibos WHERE id = :id');
$db->bind(':id', $recibo_id);
$rec = $db->single();
if (!$rec) {
    flash('mensaje', 'Recibo no encontrado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

$total = (float)$rec->monto;
$pagado= (float)$rec->monto_pagado;
$saldo = max($total - $pagado, 0.0);

// Clamp en servidor para evitar sobrepago
if ($monto > $saldo) {
    $monto = $saldo;
    $ajustado = true;
}
// Si prefieres RECHAZAR en lugar de ajustar, usa esto en vez de lo anterior:
// if ($monto > $saldo + 0.00001) { flash('mensaje', 'El monto excede el saldo del recibo.', 'alert alert-danger'); redirect(URL_ROOT.'/modulos/recibos/index.php'); exit; }

if ($monto <= 0) {
    flash('mensaje', 'No hay saldo por cobrar en este recibo.', 'alert alert-info');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

// Asignar folio (MAX(folio)+1). Para baja concurrencia es suficiente.
$db->query('SELECT COALESCE(MAX(folio),0) AS next_folio FROM recibos_pagos');
$next = $db->single();
$folio = ((int)($next->next_folio ?? 0)) + 1;

// Insert pago
$db->query('INSERT INTO recibos_pagos (recibo_id, fecha_pago, monto, metodo, referencia, observaciones, usuario_id, folio)
            VALUES (:rid, :fp, :m, :mt, :ref, :obs, :uid, :folio)');
$db->bind(':rid', $recibo_id);
$db->bind(':fp', $fecha_pago);
$db->bind(':m', $monto);
$db->bind(':mt', $metodo);
$db->bind(':ref', $referencia);
$db->bind(':obs', $obs);
$db->bind(':uid', $usuario_id);
$db->bind(':folio', $folio);

if (!$db->execute()) {
    flash('mensaje', 'No se pudo registrar el pago.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

// Recalcular estado
$service = new RecibosService();
$service->recalcularEstado($recibo_id);

$msg = 'Pago registrado correctamente. Folio: ' . $folio;
if (!empty($ajustado)) { $msg .= ' (el monto se ajustó al saldo disponible)'; }
flash('mensaje', $msg, 'alert alert-success');

// Redirigir a imprimir ese pago directamente (opcional):
// redirect(URL_ROOT . '/modulos/recibos/pagos.php?recibo_id=' . $recibo_id);
$back = $_SERVER['HTTP_REFERER'] ?? (URL_ROOT . '/modulos/recibos/index.php');
redirect($back);