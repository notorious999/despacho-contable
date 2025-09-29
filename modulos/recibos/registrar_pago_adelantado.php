<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) { redirect(URL_ROOT . '/modulos/usuarios/login.php'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

$clienteId = (int)sanitize($_POST['cliente_id'] ?? 0);
$meses = $_POST['meses'] ?? [];
$meses = is_array($meses) ? array_values(array_unique(array_filter($meses, fn($x)=>preg_match('/^\d{4}\-\d{2}$/',$x)) )) : [];
$fv = sanitize($_POST['fecha_vencimiento'] ?? '') ?: null;
$metodo = sanitize($_POST['metodo'] ?? '');
$referencia = sanitize($_POST['referencia'] ?? '');
$obs = sanitize($_POST['observaciones'] ?? '');
$usuarioId = $_SESSION['user_id'] ?? null;

if ($clienteId <= 0 || empty($meses)) {
  flash('mensaje','Selecciona cliente y al menos un mes.','alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

// Traer datos de cliente para mostrar en el comprobante
$db = new Database();
$db->query('SELECT id, razon_social, rfc, domicilio_fiscal FROM clientes WHERE id = :id AND estatus="activo"');
$db->bind(':id', $clienteId);
$cli = $db->single();
if (!$cli) {
  flash('mensaje', 'Cliente no encontrado o inactivo.', 'alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

$service = new RecibosService();
$res = $service->pagarMesesAdelantados($clienteId, $meses, $fv, $metodo, $referencia, $obs, $usuarioId);

if (!$res['ok']) {
  flash('mensaje', 'No se pudo registrar: ' . $res['msg'], 'alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

// Construir token temporal de impresión para este “lote”
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$token = bin2hex(random_bytes(8));
$_SESSION['pa_print'] = $_SESSION['pa_print'] ?? [];
$_SESSION['pa_print'][$token] = [
  'cliente' => [
    'id' => (int)$cli->id,
    'razon_social' => (string)($cli->razon_social ?? ''),
    'rfc' => (string)($cli->rfc ?? ''),
    'domicilio' => (string)($cli->domicilio_fiscal ?? ''),
  ],
  'metodo' => $metodo,
  'referencia' => $referencia,
  'observaciones' => $obs,
  'usuario_id' => $usuarioId,
  'fecha' => date('Y-m-d'),
  // IDs y periodos resultantes
  'recibos' => array_map(function($r){ return ['id'=>(int)$r['id'], 'periodo_inicio'=>$r['periodo_inicio'], 'periodo_fin'=>$r['periodo_fin']]; }, $res['recibos'] ?? []),
  'created_at' => time()
];

// Redirigir directamente a la impresión del pago adelantado
redirect(URL_ROOT . '/modulos/recibos/imprimir_pago_adelantado.php?token=' . urlencode($token));
exit;