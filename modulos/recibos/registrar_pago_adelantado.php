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

// --- Sanitización de datos (sin cambios) ---
$clienteId = (int)sanitize($_POST['cliente_id'] ?? 0);
$meses = $_POST['meses'] ?? [];
$meses = is_array($meses) ? array_values(array_unique(array_filter($meses, fn($x)=>preg_match('/^\d{4}\-\d{2}$/',$x)) )) : [];
$fv = sanitize($_POST['fecha_vencimiento'] ?? '') ?: null;
$metodo = sanitize($_POST['metodo'] ?? '');
$referencia = sanitize($_POST['referencia'] ?? '');
$obs = sanitize($_POST['observaciones'] ?? '');
$usuarioId = $_SESSION['user_id'] ?? null;
$fechaPago = date('Y-m-d'); // Usamos la fecha actual para el pago

if ($clienteId <= 0 || empty($meses)) {
  flash('mensaje','Selecciona cliente y al menos un mes.','alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/pago_adelantado.php');
  exit;
}

$db = new Database();
$db->query('SELECT id, razon_social, rfc, domicilio_fiscal FROM clientes WHERE id = :id AND estatus="activo"');
$db->bind(':id', $clienteId);
$cli = $db->single();
if (!$cli) {
  flash('mensaje', 'Cliente no encontrado o inactivo.', 'alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/index.php');
  exit;
}

// --- NUEVA LÓGICA ---
$service = new RecibosService();
$db->beginTransaction(); // Iniciar transacción para asegurar consistencia

try {
  // 1. Generar o encontrar los recibos para los meses seleccionados
  $resRecibos = $service->generarRecibosPorMeses($clienteId, $meses, $fv, $usuarioId);
  if (!$resRecibos['ok']) {
    throw new Exception($resRecibos['msg'] ?? 'Error generando los recibos.');
  }
  $recibos = $resRecibos['recibos'];
  $montoTotal = array_reduce($recibos, fn($sum, $r) => $sum + (float)$r['monto'], 0.0);

  // 2. Crear el registro del "lote" de pago adelantado
  $db->query('INSERT INTO pagos_adelantados_lotes (cliente_id, fecha_pago, monto_total, metodo, referencia, observaciones, usuario_id)
              VALUES (:cid, :fp, :mt, :met, :ref, :obs, :uid)');
  $db->bind(':cid', $clienteId);
  $db->bind(':fp', $fechaPago);
  $db->bind(':mt', $montoTotal);
  $db->bind(':met', $metodo);
  $db->bind(':ref', $referencia);
  $db->bind(':obs', $obs);
  $db->bind(':uid', $usuarioId);
  $db->execute();
  $loteId = $db->lastInsertId(); // Obtenemos el ID del lote que acabamos de crear

  // 3. Registrar un pago individual para cada recibo, vinculándolo al lote
  foreach ($recibos as $recibo) {
    $reciboId = $recibo['id'];
    $montoRecibo = (float)$recibo['monto'];
    
    if ($montoRecibo > 0) { // Solo registrar pago si el recibo tiene monto
        $pagoExitoso = $service->registrarPago($reciboId, $montoRecibo, $fechaPago, $metodo, $referencia, $obs, $usuarioId, $loteId);
        if (!$pagoExitoso) {
            throw new Exception("No se pudo registrar el pago para el recibo ID: $reciboId.");
        }
    }
  }

  $db->commit(); // Si todo salió bien, confirmamos los cambios

  // Redirigir directamente a la impresión del lote de pago adelantado
  redirect(URL_ROOT . '/modulos/recibos/imprimir_pago_adelantado.php?lote_id=' . urlencode($loteId));
  exit;

} catch (Exception $e) {
  $db->rollBack(); // Si algo falla, revertimos todo
  flash('mensaje', 'No se pudo registrar el pago adelantado: ' . $e->getMessage(), 'alert alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/pago_adelantado.php');
  exit;
}


