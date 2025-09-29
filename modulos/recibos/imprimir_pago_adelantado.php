<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($token === '' || empty($_SESSION['pa_print'][$token])) {
  flash('mensaje','No hay información de pago adelantado para imprimir (token inválido o expirado).','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

$info = $_SESSION['pa_print'][$token];
// Limpieza opcional: si quieres que solo se imprima una vez, descomenta siguiente línea
// unset($_SESSION['pa_print'][$token]);

$cliente = $info['cliente'] ?? [];
$recibosToken = $info['recibos'] ?? [];
$ids = array_map(fn($r)=> (int)$r['id'], $recibosToken);
$ids = array_values(array_filter($ids, fn($x)=>$x>0));

// Seguridad simple ante lista vacía
if (empty($ids)) {
  flash('mensaje','No hay recibos asociados a este pago adelantado.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

// Traer recibos desde BD (para montos y conceptos finales)
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$db = new Database();
$db->query("SELECT id, cliente_id, concepto, monto, monto_pagado, periodo_inicio, periodo_fin
            FROM recibos
            WHERE id IN ($placeholders) AND estatus='activo'");
foreach ($ids as $i=>$v) { $db->bind(($i+1), $v); }
$rows = $db->resultSet();

// Map rápido por ID
$byId = [];
foreach ($rows as $r) { $byId[(int)$r->id] = $r; }

// Construir lista de partidas (periodo + monto)
$partidas = [];
$total = 0.0;
foreach ($recibosToken as $r) {
  $id = (int)$r['id'];
  if (!isset($byId[$id])) continue;
  $row = $byId[$id];
  $pi = $row->periodo_inicio;
  $pf = $row->periodo_fin;
  $periodo = ($pi === $pf) ? formatDate($pi) : (formatDate($pi) . ' — ' . formatDate($pf));
  $monto = (float)$row->monto;
  $total += $monto;
  $partidas[] = [
    'periodo'=>$periodo,
    'concepto'=>$row->concepto ?? 'Honorarios',
    'monto'=>$monto
  ];
}

// Helpers sin intl para número a letras y fecha en español
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

    if ($millones > 0) $texto .= ($millones === 1) ? 'UN MILLON' : ($toWords999($millones) . ' MILLONES');
    if ($miles > 0)   $texto .= ($texto ? ' ' : '') . ($miles === 1 ? 'MIL' : $toWords999($miles) . ' MIL');
    if ($resto > 0)   $texto .= ($texto ? ' ' : '') . $toWords999($resto);
  }

  $cent = str_pad((string)$centavos, 2, '0', STR_PAD_LEFT);
  return $texto . ' ' . $cent . '/100 M.N.';
}

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

$totalLetras = numeroALetrasMX($total);
$fechaDoc = fechaLargaES($info['fecha'] ?? date('Y-m-d'));

// Intentar folio: usa el mayor folio de pagos de esos recibos (si existen)
$db->query("SELECT MAX(folio) AS fol FROM recibos_pagos WHERE recibo_id IN ($placeholders)");
foreach ($ids as $i=>$v) { $db->bind(($i+1), $v); }
$mx = $db->single();
$folio = $mx && isset($mx->fol) ? (int)$mx->fol : 0;

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
    .no-print { margin: 8px 0 0; }
    .btn { display: inline-block; padding: 6px 10px; border: 1px solid #666; background: #fafafa; color: #000; text-decoration: none; border-radius: 4px; }
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
        <div class="col"><strong>Cliente:</strong> <?php echo htmlspecialchars($cliente['razon_social'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="col"><strong>RFC:</strong> <?php echo htmlspecialchars($cliente['rfc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (!empty($cliente['domicilio'])): ?>
          <div class="col" style="flex-basis:100%"><strong>Domicilio:</strong> <?php echo htmlspecialchars($cliente['domicilio'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>
      <div class="row" style="margin-top:6px;">
        <?php if (!empty($info['metodo'])): ?>
          <div class="col"><strong>Método:</strong> <?php echo htmlspecialchars($info['metodo'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($info['referencia'])): ?>
          <div class="col"><strong>Referencia:</strong> <?php echo htmlspecialchars($info['referencia'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($info['observaciones'])): ?>
          <div class="col" style="flex-basis:100%"><strong>Observaciones:</strong> <?php echo htmlspecialchars($info['observaciones'], ENT_QUOTES, 'UTF-8'); ?></div>
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
            <td class="text-end">$ <?php echo number_format($p['monto'], 2, '.', ','); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="text-end">Total</th>
            <th class="text-end">$ <?php echo number_format($total, 2, '.', ','); ?></th>
          </tr>
        </tfoot>
      </table>
      <div class="tot"><strong>Cantidad con letras:</strong> <?php echo htmlspecialchars($totalLetras, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="muted">Este documento lista los recibos cubiertos en el pago adelantado. Para imprimir un recibo individual, usa la opción "Imprimir" desde el listado de recibos.</div>

    <div class="no-print">
      <a href="#" class="btn" onclick="window.print();return false;">Imprimir</a>
      <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn">Volver</a>
    </div>
  </div>
</body>
</html>