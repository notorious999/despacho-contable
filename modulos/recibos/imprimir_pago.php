<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// CONFIGURACIÓN DEL DESPACHO (ajusta a tu información real)
$EMPRESA_NOMBRE   = 'DESPACHO CONTABLE JLCHAN';
$EMPRESA_DOMICILIO= 'CALLE 21 68 D HOPELCHÉN, CAMPECHE 24600';
$EMPRESA_CELULAR  = '9811102199';
$EMPRESA_FIJO     = '(982) 82-203-21';
$EMPRESA_CORREO1  = 'jlchan@multietkmx.com';
$EMPRESA_CORREO2  = 'jlchandespacho@gmail.com';

$BANCO_NOMBRE     = 'BANCOMER';
$BANCO_CUENTA     = '29-45-13-10-57';
$BANCO_CLABE      = '0120-5502-9451-3105-70';
$BANCO_BENEF      = 'JORGE LUIS CHAN GONZALEZ';

$FIRMA_NOMBRE     = 'L.C. JORGE LUIS CHAN GONZALEZ';
$FIRMA_CEDULA     = 'CÉDULA PROFESIONAL 7224903';

// UTIL: número a letras (MXN)
function numeroALetras($number) {
  $UNIDADES = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
  $DECENAS = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
  $CENTENAS = ['', 'CIEN', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

  $number = number_format((float)$number, 2, '.', '');
  [$int, $dec] = explode('.', $number);
  $int = (int)$int; $dec = (int)$dec;

  $toWords = function($n) use (&$toWords, $UNIDADES, $DECENAS, $CENTENAS) {
    if ($n == 0) return 'CERO';
    if ($n <= 20) return $UNIDADES[$n];
    if ($n < 100) {
      $d = intdiv($n, 10); $u = $n % 10;
      if ($n <= 29) return 'VEINTI' . ($u ? strtolower($UNIDADES[$u]) : '');
      return $DECENAS[$d] . ($u ? ' Y ' . $UNIDADES[$u] : '');
    }
    if ($n < 1000) {
      $c = intdiv($n, 100); $r = $n % 100;
      if ($c == 1) return ($r==0) ? 'CIEN' : 'CIENTO ' . $toWords($r);
      return $CENTENAS[$c] . ($r ? ' ' . $toWords($r) : '');
    }
    if ($n < 1000000) {
      $m = intdiv($n, 1000); $r = $n % 1000;
      $mil = ($m==1) ? 'MIL' : $toWords($m) . ' MIL';
      return $mil . ($r ? ' ' . $toWords($r) : '');
    }
    if ($n < 1000000000000) {
      $mi = intdiv($n, 1000000); $r = $n % 1000000;
      $mill = ($mi==1) ? 'UN MILLÓN' : $toWords($mi) . ' MILLONES';
      return $mill . ($r ? ' ' . $toWords($r) : '');
    }
    return (string)$n; // fuera de rango
  };

  $entero = $toWords($int);
  return $entero . ' PESOS ' . str_pad((string)$dec, 2, '0', STR_PAD_LEFT) . '/100 MN';
}

// Obtener el pago
if (!isset($_GET['id'])) { http_response_code(400); echo 'Pago no especificado'; exit; }
$id = (int)$_GET['id'];

$db = new Database();
$db->query('SELECT p.*, r.concepto, r.monto AS monto_total, r.cliente_id,
                   c.razon_social, c.rfc, c.domicilio_fiscal, c.codigo_postal
            FROM recibos_pagos p
            JOIN recibos r ON r.id = p.recibo_id
            JOIN clientes c ON c.id = r.cliente_id
            WHERE p.id = :id');
$db->bind(':id', $id);
$pago = $db->single();
if (!$pago) { http_response_code(404); echo 'Pago no encontrado'; exit; }

$folio = (int)$pago->folio;
$fecha = date('l, j \d\e F \d\e Y', strtotime($pago->fecha_pago)); // en español si configuras locale
$cliente = (string)$pago->razon_social;
$rfc = (string)$pago->rfc;
$domicilio = trim((string)$pago->domicilio_fiscal) !== '' ? (string)$pago->domicilio_fiscal : (string)$pago->codigo_postal;
$concepto = (string)$pago->concepto;
$importe = (float)$pago->monto;
$importe_fmt = number_format($importe, 2, '.', ',');
$letras = numeroALetras($importe);

// IVA opcional (si tus honorarios incluyen IVA, ajusta aquí)
$subtotal = $importe; $iva = 0.00; $total = $importe;

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo de Honorarios - Folio <?php echo $folio; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @media print { .no-print { display: none; } }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
  .container { width: 900px; margin: 0 auto; }
  .row { display: flex; }
  .col { flex: 1; }
  .right { text-align: right; }
  .center { text-align: center; }
  .box { border: 2px solid #000; padding: 6px; }
  .line { border-bottom: 2px solid #000; height: 20px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 2px solid #000; padding: 6px; vertical-align: top; }
  .header { margin-top: 10px; margin-bottom: 10px; }
  .mb-2 { margin-bottom: 8px; }
  .mb-3 { margin-bottom: 12px; }
  .small { font-size: 11px; }
</style>
</head>
<body>
<div class="container">
  <div class="row header">
    <div class="col">
      <!-- Logo: puedes reemplazar por <img src="..."> -->
      <div style="font-weight:bold; font-size: 16px;"><?php echo htmlspecialchars($EMPRESA_NOMBRE, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="small">
        DOMICILIO: <?php echo htmlspecialchars($EMPRESA_DOMICILIO, ENT_QUOTES, 'UTF-8'); ?><br>
        CELULAR: <?php echo htmlspecialchars($EMPRESA_CELULAR, ENT_QUOTES, 'UTF-8'); ?>
        FIJO: <?php echo htmlspecialchars($EMPRESA_FIJO, ENT_QUOTES, 'UTF-8'); ?><br>
        CORREO ELECTRÓNICO (1) <?php echo htmlspecialchars($EMPRESA_CORREO1, ENT_QUOTES, 'UTF-8'); ?><br>
        CORREO ELECTRÓNICO (2) <?php echo htmlspecialchars($EMPRESA_CORREO2, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
    <div class="col">
      <div class="box">
        <div class="center" style="font-weight:bold;">RECIBO DE HONORARIOS</div>
        <table>
          <tr><td style="width: 120px;"><strong>FOLIO</strong></td><td class="right"><?php echo $folio; ?></td></tr>
          <tr><td><strong>FECHA</strong></td><td class="right"><?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?></td></tr>
        </table>
      </div>
      <div class="box" style="margin-top:8px;">
        <div class="small center" style="font-weight:bold;">ESTIMADO CLIENTE, AGRADECEMOS SU DEPÓSITO POR LOS SERVICIOS PRESTADOS A LAS SIGUIENTES CUENTAS:</div>
        <div class="small">
          BANCO: <?php echo htmlspecialchars($BANCO_NOMBRE, ENT_QUOTES, 'UTF-8'); ?><br>
          CUENTA: <?php echo htmlspecialchars($BANCO_CUENTA, ENT_QUOTES, 'UTF-8'); ?><br>
          CLABE INTERBANCARIA: <?php echo htmlspecialchars($BANCO_CLABE, ENT_QUOTES, 'UTF-8'); ?><br>
          BENEFICIARIO: <?php echo htmlspecialchars($BANCO_BENEF, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="box mb-3">
    <table>
      <tr>
        <td style="width:120px;"><strong>CLIENTE:</strong></td>
        <td><?php echo htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <td><strong>RFC:</strong></td>
        <td><?php echo htmlspecialchars($rfc, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <td><strong>DOMICILIO:</strong></td>
        <td><?php echo htmlspecialchars($domicilio, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </table>
  </div>

  <table class="mb-3">
    <thead>
      <tr>
        <th style="width:80px;">CANTIDAD</th>
        <th style="width:120px;">UNIDAD</th>
        <th>DESCRIPCIÓN</th>
        <th style="width:130px;">PRECIO SERV</th>
        <th style="width:130px;">IMPORTE</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="center">1</td>
        <td class="center">SERVICIO</td>
        <td><?php echo htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="right">$ <?php echo $importe_fmt; ?></td>
        <td class="right">$ <?php echo $importe_fmt; ?></td>
      </tr>
      <tr>
        <td colspan="2"><strong>CANTIDAD CON LETRAS:</strong></td>
        <td colspan="3"><?php echo htmlspecialchars($letras, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>

  <table class="mb-3">
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right" style="width:130px;"><strong>SUBTOTAL</strong></td>
      <td class="right" style="width:130px;">$ <?php echo number_format($subtotal, 2, '.', ','); ?></td>
    </tr>
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right"><strong>IVA</strong></td>
      <td class="right">$ <?php echo number_format($iva, 2, '.', ','); ?></td>
    </tr>
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right"><strong>TOTAL</strong></td>
      <td class="right">$ <?php echo number_format($total, 2, '.', ','); ?></td>
    </tr>
  </table>

  <div class="row mb-3">
    <div class="col center">
      <div class="line"></div>
      <div class="small">ORIGINAL</div>
    </div>
    <div class="col center">
      <div class="line"></div>
      <div class="small">FECHA DE PAGO</div>
    </div>
  </div>

  <div class="center mb-2">
    ATENTAMENTE
  </div>
  <div class="center mb-3">
    <div class="line" style="width: 60%; margin:0 auto;"></div>
    <div><?php echo htmlspecialchars($FIRMA_NOMBRE, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="small"><?php echo htmlspecialchars($FIRMA_CEDULA, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>
<div class="container">
  <div class="row header">
    <div class="col">
      <!-- Logo: puedes reemplazar por <img src="..."> -->
      <div style="font-weight:bold; font-size: 16px;"><?php echo htmlspecialchars($EMPRESA_NOMBRE, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="small">
        DOMICILIO: <?php echo htmlspecialchars($EMPRESA_DOMICILIO, ENT_QUOTES, 'UTF-8'); ?><br>
        CELULAR: <?php echo htmlspecialchars($EMPRESA_CELULAR, ENT_QUOTES, 'UTF-8'); ?>
        FIJO: <?php echo htmlspecialchars($EMPRESA_FIJO, ENT_QUOTES, 'UTF-8'); ?><br>
        CORREO ELECTRÓNICO (1) <?php echo htmlspecialchars($EMPRESA_CORREO1, ENT_QUOTES, 'UTF-8'); ?><br>
        CORREO ELECTRÓNICO (2) <?php echo htmlspecialchars($EMPRESA_CORREO2, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
    <div class="col">
      <div class="box">
        <div class="center" style="font-weight:bold;">RECIBO DE HONORARIOS</div>
        <table>
          <tr><td style="width: 120px;"><strong>FOLIO</strong></td><td class="right"><?php echo $folio; ?></td></tr>
          <tr><td><strong>FECHA</strong></td><td class="right"><?php echo htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?></td></tr>
        </table>
      </div>
      <div class="box" style="margin-top:8px;">
        <div class="small center" style="font-weight:bold;">ESTIMADO CLIENTE, AGRADECEMOS SU DEPÓSITO POR LOS SERVICIOS PRESTADOS A LAS SIGUIENTES CUENTAS:</div>
        <div class="small">
          BANCO: <?php echo htmlspecialchars($BANCO_NOMBRE, ENT_QUOTES, 'UTF-8'); ?><br>
          CUENTA: <?php echo htmlspecialchars($BANCO_CUENTA, ENT_QUOTES, 'UTF-8'); ?><br>
          CLABE INTERBANCARIA: <?php echo htmlspecialchars($BANCO_CLABE, ENT_QUOTES, 'UTF-8'); ?><br>
          BENEFICIARIO: <?php echo htmlspecialchars($BANCO_BENEF, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="box mb-3">
    <table>
      <tr>
        <td style="width:120px;"><strong>CLIENTE:</strong></td>
        <td><?php echo htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <td><strong>RFC:</strong></td>
        <td><?php echo htmlspecialchars($rfc, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
      <tr>
        <td><strong>DOMICILIO:</strong></td>
        <td><?php echo htmlspecialchars($domicilio, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </table>
  </div>

  <table class="mb-3">
    <thead>
      <tr>
        <th style="width:80px;">CANTIDAD</th>
        <th style="width:120px;">UNIDAD</th>
        <th>DESCRIPCIÓN</th>
        <th style="width:130px;">PRECIO SERV</th>
        <th style="width:130px;">IMPORTE</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="center">1</td>
        <td class="center">SERVICIO</td>
        <td><?php echo htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="right">$ <?php echo $importe_fmt; ?></td>
        <td class="right">$ <?php echo $importe_fmt; ?></td>
      </tr>
      <tr>
        <td colspan="2"><strong>CANTIDAD CON LETRAS:</strong></td>
        <td colspan="3"><?php echo htmlspecialchars($letras, ENT_QUOTES, 'UTF-8'); ?></td>
      </tr>
    </tbody>
  </table>

  <table class="mb-3">
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right" style="width:130px;"><strong>SUBTOTAL</strong></td>
      <td class="right" style="width:130px;">$ <?php echo number_format($subtotal, 2, '.', ','); ?></td>
    </tr>
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right"><strong>IVA</strong></td>
      <td class="right">$ <?php echo number_format($iva, 2, '.', ','); ?></td>
    </tr>
    <tr>
      <td style="border:none;"></td>
      <td style="border:none;"></td>
      <td class="right"><strong>TOTAL</strong></td>
      <td class="right">$ <?php echo number_format($total, 2, '.', ','); ?></td>
    </tr>
  </table>

  <div class="row mb-3">
    <div class="col center">
      <div class="line"></div>
      <div class="small">COPIA</div>
    </div>
    <div class="col center">
      <div class="line"></div>
      <div class="small">FECHA DE PAGO</div>
    </div>
  </div>

  <div class="center mb-2">
    ATENTAMENTE
  </div>
  <div class="center mb-3">
    <div class="line" style="width: 60%; margin:0 auto;"></div>
    <div><?php echo htmlspecialchars($FIRMA_NOMBRE, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="small"><?php echo htmlspecialchars($FIRMA_CEDULA, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>
  <div class="center no-print">
    <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
  </div>
</div>
</body>
</html>