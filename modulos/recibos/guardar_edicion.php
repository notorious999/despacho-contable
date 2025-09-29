<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

$id = (int)sanitize($_POST['id'] ?? 0);
$concepto = sanitize($_POST['concepto'] ?? '');
$pi = sanitize($_POST['periodo_inicio'] ?? '');
$pf = sanitize($_POST['periodo_fin'] ?? '');
$monto = (float)sanitize($_POST['monto'] ?? 0);
$fv = sanitize($_POST['fecha_vencimiento'] ?? '') ?: null;
$obs = sanitize($_POST['observaciones'] ?? '');

$db = new Database();
$db->query('SELECT * FROM recibos WHERE id = :id');
$db->bind(':id', $id);
$r = $db->single();
if (!$r) {
  flash('mensaje', 'Recibo no encontrado.', 'alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

if ($r->estatus === 'cancelado') {
  flash('mensaje', 'No se puede editar un recibo cancelado.', 'alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/editar.php?id='.$id); exit;
}

if ($monto < (float)$r->monto_pagado) {
  flash('mensaje', 'El monto no puede ser menor a lo ya pagado ('.number_format((float)$r->monto_pagado,2,'.',',').').', 'alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/editar.php?id='.$id); exit;
}

$db->query('UPDATE recibos SET
            concepto = :concepto,
            periodo_inicio = :pi,
            periodo_fin = :pf,
            monto = :monto,
            fecha_vencimiento = :fv,
            observaciones = :obs
            WHERE id = :id');
$db->bind(':concepto', $concepto);
$db->bind(':pi', $pi);
$db->bind(':pf', $pf);
$db->bind(':monto', $monto);
$db->bind(':fv', $fv);
$db->bind(':obs', $obs);
$db->bind(':id', $id);

if (!$db->execute()) {
  flash('mensaje','No se pudo guardar.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/editar.php?id='.$id); exit;
}

// Recalcular estado por si el monto cambió
$service = new RecibosService();
$service->recalcularEstado($id);

flash('mensaje', 'Cambios guardados.', 'alert alert-success');
redirect(URL_ROOT.'/modulos/recibos/editar.php?id='.$id);