<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$recibo_id = isset($_GET['recibo_id']) ? (int)$_GET['recibo_id'] : 0;
if ($recibo_id <= 0) {
  flash('mensaje','Recibo inválido.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/externos.php'); exit;
}

$db = new Database();
$db->query('SELECT * FROM recibos WHERE id = :id AND estatus="activo"');
$db->bind(':id', $recibo_id);
$recibo = $db->single();
if (!$recibo) {
  flash('mensaje','Recibo no encontrado.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/externos.php'); exit;
}
if (!empty($recibo->cliente_id)) {
  // Si no es externo, reenvía a la impresión general
  redirect(URL_ROOT.'/modulos/recibos/imprimir.php?recibo_id='.$recibo_id); exit;
}

// Ultimo folio de pago (como folio del recibo)
$db->query('SELECT folio FROM recibos_pagos WHERE recibo_id = :rid ORDER BY id DESC LIMIT 1');
$db->bind(':rid', $recibo_id);
$pago = $db->single();
$folio = $pago ? (int)$pago->folio : (int)$recibo_id;

// Datos básicos
$razon = $recibo->externo_nombre ?: '';
$rfc   = $recibo->externo_rfc ?: '';
$fecha = $recibo->periodo_inicio ?: date('Y-m-d');
$concepto = $recibo->concepto ?: 'Honorarios';
$monto = (float)$recibo->monto;

// Ruta imagen de plantilla y logo opcional
$plantilla = URL_ROOT . '/assets/plantillas/recibo_externo_base.png';
$logoPath = __DIR__ . '/../../assets/logo.png';
$logoUrl  = file_exists($logoPath) ? (URL_ROOT . '/assets/logo.png') : null;

/* ===== Helpers sin dependencias de intl/setlocale ===== */

// Convierte número a letras en español (0..999,999,999) y agrega "xx/100 M.N."
function numeroALetrasMX($numero): string {
  $numero = round((float)$numero, 2);
  $entero = (int)floor($numero);
  $centavos = (int)round(($numero - $entero) * 100);

  $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
               'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
  $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
  $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

  $toWords999 = function(int $n) use ($unidades, $decenas, $centenas): string {
    if ($n === 0) return 'CERO';
    if ($n === 100) return 'CIEN';
    $c = (int)floor($n / 100);
    $d = (int)floor(($n % 100) / 10);
    $u = $n % 10;
    $out = [];

    if ($c > 0) $out[] = $centenas[$c];

    $du = $n % 100;
    if ($du <= 20) {
      if ($du > 0) $out[] = $unidades[$du];
    } else {
      $textoDecena = $decenas[$d];
      if ($u === 0) {
        $out[] = $textoDecena;
      } else {
        $out[] = $textoDecena . ' Y ' . $unidades[$u];
      }
    }
    return trim(implode(' ', array_filter($out)));
  };

  $texto = '';
  if ($entero === 0) {
    $texto = 'CERO';
  } else {
    $millones = (int)floor($entero / 1000000);
    $miles    = (int)floor(($entero % 1000000) / 1000);
    $resto    = $entero % 1000;

    if ($millones > 0) {
      if ($millones === 1) $texto .= 'UN MILLON';
      else $texto .= $toWords999($millones) . ' MILLONES';
    }

    if ($miles > 0) {
      $texto .= ($texto ? ' ' : '');
      if ($miles === 1) $texto .= 'MIL';
      else $texto .= $toWords999($miles) . ' MIL';
    }

    if ($resto > 0) {
      $texto .= ($texto ? ' ' : '') . $toWords999($resto);
    }
  }

  $cent = str_pad((string)$centavos, 2, '0', STR_PAD_LEFT);
  return $texto . ' ' . $cent . '/100 M.N.';
}

// Fecha larga en español (sin setlocale)
function fechaLargaES($fechaYmd): string {
  $ts = strtotime($fechaYmd ?: 'now');
  $dias = [1=>'lunes','martes','miércoles','jueves','viernes','sábado','domingo'];
  $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $dow = (int)date('N', $ts);
  $dia = (int)date('j', $ts);
  $mes = (int)date('n', $ts);
  $anio = date('Y', $ts);
  return $dias[$dow] . ', ' . $dia . ' de ' . $meses[$mes] . ' de ' . $anio;
}

// Usa Intl si existe; si no, usa el helper
function numeroALetras($numero): string {
  if (class_exists('NumberFormatter')) {
    try {
      $fmt = new NumberFormatter('es', NumberFormatter::SPELLOUT);
      $numero = round((float)$numero, 2);
      $entero = (int)floor($numero);
      $centavos = (int)round(($numero - $entero) * 100);
      $letras = mb_strtoupper($fmt->format($entero), 'UTF-8');
      $cent = str_pad((string)$centavos, 2, '0', STR_PAD_LEFT);
      return $letras . ' ' . $cent . '/100 M.N.';
    } catch (Throwable $e) {
      // Fallback
      return numeroALetrasMX($numero);
    }
  }
  return numeroALetrasMX($numero);
}

$importeLetras = numeroALetras($monto);
$fechaLarga = fechaLargaES($fecha); // en minúsculas como tu ejemplo

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Impresión Recibo Externo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    @page { size: A4; margin: 10mm 10mm; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; }
    .sheet { position: relative; width: 190mm; margin: 0 auto; }
    .comprobante {
      position: relative;
      width: 100%;
      height: 135mm; /* mitad aprox de A4 con márgenes */
      background-image: url('<?php echo htmlspecialchars($plantilla, ENT_QUOTES, 'UTF-8'); ?>');
      background-repeat: no-repeat;
      background-size: contain;
      margin-bottom: 8mm; /* separación entre original y copia */
    }
    /* Área de logo (izquierda superior) */
    .logo {
      position: absolute; 
      left: 10mm; top: 8mm; 
      width: 35mm; height: 35mm;
      display: flex; align-items: center; justify-content: center;
    }
    .logo img { max-width: 100%; max-height: 100%; object-fit: contain; }

    /* Caja de FOLIO y FECHA (derecha superior) */
    .folio { position: absolute; right: 20mm; top: 20mm; font-size: 13px; }
    .folio .lbl { display: inline-block; width: 48px; font-weight: bold; }
    .folio .val { display: inline-block; min-width: 80px; text-align: left; }

    /* Datos del cliente en el bloque izquierdo medio */
    .dato { position: absolute; left: 40mm; font-size: 13px; }
    .dato.cliente { top: 55mm; width: 120mm; }
    .dato.rfc     { top: 63mm; width: 120mm; }
    .dato.dom     { top: 71mm; width: 120mm; } /* placeholder para domicilio */

    /* Tabla de detalle */
    .detalle { position: absolute; left: 15mm; right: 15mm; top: 90mm; font-size: 13px; }
    .detalle .desc { width: 90mm; display: inline-block; }
    .detalle .precio, .detalle .importe { width: 30mm; display: inline-block; text-align: right; }

    /* Cantidad con letras y totales */
    .letras { position: absolute; left: 40mm; top: 112mm; width: 100mm; font-size: 13px; }
    .totales { position: absolute; right: 15mm; top: 112mm; width: 45mm; font-size: 13px; }
    .totales .row { display: flex; justify-content: space-between; }

    /* Pie */
    .pie { position: absolute; left: 15mm; right: 15mm; bottom: 12mm; font-size: 12px; display: flex; justify-content: space-between; }
    .small { font-size: 11px; }
    .strong { font-weight: bold; }
  </style>
</head>
<body>
  <div class="sheet">
    <?php for ($i=0; $i<2; $i++): // Original y Copia ?>
    <div class="comprobante">
      <div class="logo">
        <?php if ($logoUrl): ?>
          <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
        <?php endif; ?>
      </div>

      <div class="folio">
        <div><span class="lbl">FOLIO</span> <span class="val strong"><?php echo htmlspecialchars((string)$folio, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div><span class="lbl">FECHA</span> <span class="val"><?php echo htmlspecialchars($fechaLarga, ENT_QUOTES, 'UTF-8'); ?></span></div>
      </div>

      <div class="dato cliente"><span class="strong">CLIENTE:</span> &nbsp; <?php echo htmlspecialchars($razon, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="dato rfc"><span class="strong">RFC:</span> &nbsp; <?php echo htmlspecialchars($rfc ?: 'N/D', ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="dato dom"><span class="strong">DOMICILIO:</span> &nbsp; <?php echo htmlspecialchars(' ', ENT_QUOTES, 'UTF-8'); ?></div>

      <div class="detalle">
        <span class="desc"><?php echo htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="precio">$ <?php echo number_format($monto, 2, '.', ','); ?></span>
        <span class="importe">$ <?php echo number_format($monto, 2, '.', ','); ?></span>
      </div>

      <div class="letras">
        <span class="strong">CANTIDAD CON LETRAS:</span> &nbsp; <?php echo htmlspecialchars($importeLetras, ENT_QUOTES, 'UTF-8'); ?>
      </div>

      <div class="totales">
        <div class="row"><span class="strong">SUBTOTAL</span> <span>$ <?php echo number_format($monto, 2, '.', ','); ?></span></div>
        <div class="row"><span class="strong">IVA</span> <span>$ 0.00</span></div>
        <div class="row"><span class="strong">TOTAL</span> <span>$ <?php echo number_format($monto, 2, '.', ','); ?></span></div>
      </div>

      <div class="pie small">
        <div>ORIGINAL / COPIA</div>
        <div>FECHA DE PAGO ____________________________</div>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</body>
</html>