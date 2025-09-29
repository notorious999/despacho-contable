<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$id = (int)sanitize($_GET['id'] ?? 0);
if ($id <= 0) { redirect(URL_ROOT.'/modulos/recibos/index.php'); exit; }

$db = new Database();
$db->query('SELECT monto_pagado, estatus FROM recibos WHERE id = :id');
$db->bind(':id', $id);
$r = $db->single();
if (!$r) {
  flash('mensaje','Recibo no encontrado.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}
if ($r->estatus === 'cancelado') {
  flash('mensaje','El recibo ya está cancelado.','alert alert-info');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}
if ((float)$r->monto_pagado > 0) {
  flash('mensaje','No se puede cancelar un recibo con pagos registrados.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

$uid = $_SESSION['user_id'] ?? null;

$db->query('UPDATE recibos SET estatus="cancelado", cancel_reason=:cr, cancelled_at=NOW(), cancelled_by=:uid WHERE id = :id');
$db->bind(':cr', 'Cancelado por usuario');
$db->bind(':uid', $uid);
$db->bind(':id', $id);
if ($db->execute()) {
  flash('mensaje','Recibo cancelado.','alert alert-success');
} else {
  flash('mensaje','No se pudo cancelar.','alert alert-danger');
}
redirect(URL_ROOT.'/modulos/recibos/index.php');