<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$loteId = isset($_GET['lote_id']) ? (int)$_GET['lote_id'] : 0;

if ($loteId <= 0) {
  flash('mensaje','Token de pago adelantado inválido.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

$db = new Database();

// 1. Traer información del lote de pago y del cliente
$db->query('SELECT l.*, c.razon_social, c.rfc, c.domicilio_fiscal
            FROM pagos_adelantados_lotes l
            JOIN clientes c ON c.id = l.cliente_id
            WHERE l.id = :lote_id');
$db->bind(':lote_id', $loteId);
$info = $db->single();

if (!$info) {
    flash('mensaje','No se encontró el comprobante de pago adelantado.','alert alert-danger');
    redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

// 2. Traer los recibos individuales que fueron pagados con este lote
$db->query("SELECT r.concepto, r.monto, r.periodo_inicio, r.periodo_fin
            FROM recibos r
            JOIN recibos_pagos p ON p.recibo_id = r.id
            WHERE p.lote_id = :lote_id
            ORDER BY r.periodo_inicio ASC");
$db->bind(':lote_id', $loteId);
$recibosPagados = $db->resultSet();

// 3. Procesar datos para la vista
$cliente = [
    'razon_social' => $info->razon_social ?? '',
    'rfc' => $info->rfc ?? '',
    'domicilio' => $info->domicilio_fiscal ?? ''
];
$total = (float)($info->monto_total ?? 0.0);

$partidas = [];
foreach ($recibosPagados as $recibo) {
    $pi = $recibo->periodo_inicio;
    $pf = $recibo->periodo_fin;
    $periodo = ($pi === $pf || empty($pf)) ? formatDate($pi) : (formatDate($pi) . ' — ' . formatDate($pf));
    $partidas[] = [
        'periodo' => $periodo,
        'concepto' => $recibo->concepto ?? 'Honorarios',
        'monto' => (float)$recibo->monto
    ];
}

$totalLetras = numeroALetrasMX($total); // <- Ahora funciona
$fechaDoc = fechaLargaES($info->fecha_pago ?? date('Y-m-d')); // <- Ahora funciona
$folio = $info->id;

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Comprobante de Pago Adelantado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    @page { size: A4; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; }
    .wrap { max-width: 800px; margin: 0 auto; }
    .hdr { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .hdr .left h2 { margin: 0 0 4px; font-size: 18px; }
    .hdr .right { text-align: right; font-size: 12px; }
    .box { border: 1px solid #000; padding: 10px; margin-bottom: 10px; }
    .row { display: flex; flex-wrap: wrap; gap: 10px; font-size: 13px; }
    .col { flex: 1 1 220px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 6px; }
    th, td { border: 1px solid #000; padding: 6px; }
    th { background: #f2f2f2; }
    .text-end { text-align: right; }
    .tot { font-size: 14px; margin-top: 8px; }
    .muted { color: #555; font-size: 12px; }
    .no-print { margin: 12px 0 0; }
    .btn { display: inline-block; padding: 6px 10px; border: 1px solid #666; background: #fafafa; color: #000; text-decoration: none; border-radius: 4px; }
    @media print {
        .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">
      <div class="left">
        <h2>Comprobante de pago adelantado</h2>
        <div class="muted">Listado de periodos pagados en una sola operación</div>
      </div>
      <div class="right">
        <div><strong>Folio:</strong> <?php echo htmlspecialchars($folio ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong>Fecha:</strong> <?php echo htmlspecialchars($fechaDoc, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>
    </div>

    <div class="box">
      <div class="row">
        <div class="col"><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['razon_social'], ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="col"><strong>RFC:</strong> <?php echo htmlspecialchars($cliente['rfc'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (!empty($cliente['domicilio'])): ?>
          <div class="col" style="flex-basis:100%"><strong>Domicilio:</strong> <?php echo htmlspecialchars($cliente['domicilio'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div class="row" style="margin-top:6px;">
        <?php if (!empty($info->metodo)): ?>
          <div class="col"><strong>Método:</strong> <?php echo htmlspecialchars($info->metodo, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($info->referencia)): ?>
          <div class="col"><strong>Referencia:</strong> <?php echo htmlspecialchars($info->referencia, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($info->observaciones)): ?>
          <div class="col" style="flex-basis:100%"><strong>Observaciones:</strong> <?php echo htmlspecialchars($info->observaciones, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="box">
      <table>
        <thead>
          <tr>
            <th style="width: 35%;">Periodo</th>
            <th style="width: 45%;">Concepto</th>
            <th class="text-end" style="width: 20%;">Importe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($partidas as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars($p['periodo'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($p['concepto'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end">$<?php echo number_format($p['monto'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="text-end">Total</th>
            <th class="text-end">$<?php echo number_format($total, 2); ?></th>
          </tr>
        </tfoot>
      </table>
      <div class="tot"><strong>Cantidad con letras:</strong> <?php echo htmlspecialchars($totalLetras, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="muted">Este documento es un comprobante del pago total. Para imprimir un recibo individual, use la opción desde el listado de recibos.</div>

    <div class="no-print">
      <a href="#" class="btn" onclick="window.print();return false;">Imprimir</a>
      <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn">Volver al Listado</a>
    </div>
  </div>
</body>
</html>