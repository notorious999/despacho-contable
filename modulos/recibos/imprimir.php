<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$recibo_id = isset($_GET['recibo_id']) ? (int)$_GET['recibo_id'] : 0;
if ($recibo_id <= 0) {
  flash('mensaje','Recibo inválido.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

$db = new Database();
$db->query('SELECT r.*,
                   c.razon_social AS cli_razon, c.rfc AS cli_rfc, c.domicilio_fiscal AS cli_dom
            FROM recibos r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id = :id');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

if (!$recibo) {
  flash('mensaje','Recibo no encontrado.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

// Datos para impresión
$razon = $recibo->cliente_id ? ($recibo->cli_razon ?: '') : ($recibo->externo_nombre ?: '');
$rfc   = $recibo->cliente_id ? ($recibo->cli_rfc ?: '')   : ($recibo->externo_rfc ?: '');
$fecha = $recibo->periodo_inicio ?: date('Y-m-d'); // para externo usamos la "fecha" que guardamos como periodo_inicio
$concepto = $recibo->concepto ?: 'Honorarios';
$monto = (float)$recibo->monto;

// Si quieres imprimir un comprobante de pago ligado a un pago específico (folio), aquí podrías consultar el último pago
// y mostrar su folio. Para simplicidad, mostramos solo datos del recibo.

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Impresión de Recibo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?php echo URL_ROOT; ?>/assets/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { padding: 0; margin: 0; }
      .card { border: none; }
    }
    body { background: #fff; }
    .header { border-bottom: 2px solid #000; margin-bottom: 1rem; padding-bottom: .5rem; }
    .totals { font-size: 1.1rem; }
  </style>
</head>
<body>
<div class="container my-3">
  <div class="d-flex justify-content-between align-items-center header">
    <div>
      <h4 class="mb-0">Despacho Contable</h4>
      <div class="small text-muted">Comprobante de Recibo</div>
    </div>
    <div class="text-end">
      <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
      <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-sm btn-outline-secondary no-print">Volver</a>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-md-7">
      <div><strong>Cliente:</strong> <?php echo htmlspecialchars($razon ?: 'N/D', ENT_QUOTES, 'UTF-8'); ?></div>
      <div><strong>RFC:</strong> <?php echo htmlspecialchars($rfc ?: 'N/D', ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <div class="col-md-5">
      <div><strong>Fecha:</strong> <?php echo formatDate($fecha); ?></div>
      <div><strong>Estado:</strong> <?php echo ucfirst($recibo->estado); ?></div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="mb-3"><strong>Concepto:</strong><br><?php echo nl2br(htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8')); ?></div>
      <div class="totals d-flex justify-content-between">
        <div><strong>Monto:</strong></div>
        <div><strong><?php echo formatMoney($monto); ?></strong></div>
      </div>
    </div>
  </div>

  <div class="mt-4 small text-muted">
    Este documento es una representación impresa del recibo registrado en el sistema.
  </div>
</div>

<script src="<?php echo URL_ROOT; ?>/assets/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</body>
</html>