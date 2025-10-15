<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$recibo_id = isset($_GET['recibo_id']) ? (int)$_GET['recibo_id'] : 0;
if ($recibo_id <= 0) die('ID de recibo no válido.');

$db = new Database();

// Datos del recibo y cliente (ya incluye estado y monto_pagado)
$db->query('SELECT r.*, c.razon_social, c.rfc, c.domicilio_fiscal FROM recibos r JOIN clientes c ON c.id = r.cliente_id WHERE r.id = :id');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

// Servicios del recibo
$db->query('SELECT * FROM recibo_servicios WHERE recibo_id = :id');
$db->bind(':id', $recibo_id);
$servicios = $db->resultSet();

$cantidad_servicios = count($servicios);
$descripciones = array_map(function($s) { return $s->descripcion; }, $servicios);
$servicios_string = implode(', ', $descripciones);

// --- NUEVO CÓDIGO PARA DATOS ADICIONALES ---
$saldo_pendiente = max(((float)$recibo->monto - (float)$recibo->monto_pagado), 0.0);
$estado_recibo = ucfirst($recibo->estado); // Pone la primera letra en mayúscula
// --- FIN DEL NUEVO CÓDIGO ---

$logo_path = __DIR__ . '/../../uploads/logo.png';
$logo_url = file_exists($logo_path) ? '../../uploads/logo.png' : null;

function numeroALetras($numero) {
    // (La función numeroALetras se mantiene igual)
    $UNIDADES = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
    $DECENAS = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $CENTENAS = ['', 'CIEN', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    $numero = number_format($numero, 2, '.', '');
    list($entero, $decimal) = explode('.', $numero);

    $toWords = function ($n) use ($UNIDADES, $DECENAS, $CENTENAS, &$toWords) {
        if ($n == 0) return 'CERO';
        if ($n <= 20) return $UNIDADES[$n];
        if ($n < 100) {
            $d = intval($n / 10);
            $u = $n % 10;
            return $DECENAS[$d] . ($u ? ' Y ' . $UNIDADES[$u] : '');
        }
        if ($n < 1000) {
            $c = intval($n / 100);
            $r = $n % 100;
            if ($c == 1 && $r == 0) return 'CIEN';
            return $CENTENAS[$c] . ($r ? ' ' . $toWords($r) : '');
        }
        if ($n < 1000000) {
            $m = intval($n / 1000);
            $r = $n % 1000;
            $mil = $m == 1 ? 'MIL' : $toWords($m) . ' MIL';
            return $mil . ($r ? ' ' . $toWords($r) : '');
        }
        return 'CANTIDAD FUERA DE RANGO';
    };

    return $toWords(intval($entero)) . ' PESOS ' . str_pad($decimal, 2, '0', STR_PAD_LEFT) . '/100 M.N.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Servicios</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .recibo { border: 1px solid #000; padding: 15px; width: 800px; margin: auto; }
        .header { display: flex; align-items: center; border-bottom: 1px solid #000; padding-bottom: 10px; }
        .logo { margin-right: 20px; }
        .empresa { text-align: center; flex-grow: 1; }
        .info-cliente { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        td.cantidad { text-align: center; }
        .text-end { text-align: right; }
        .total { font-weight: bold; }
        .firma { margin-top: 50px; text-align: center; }
        .estado { font-size: 1.2em; font-weight: bold; text-align: center; padding: 5px; margin-top: 10px; border-radius: 5px; }
        .pagado { background-color: #d4edda; color: #155724; }
        .pendiente { background-color: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="header">
            <div class="logo"><?php if ($logo_url): ?><img src="<?= $logo_url ?>" alt="Logo" width="100"><?php else: ?>LOGO<?php endif; ?></div>
            <div class="empresa">
                <h2>DESPACHO CONTABLE JLCHAN</h2>
                <p>CALLE 21 #68 D, HOPELCHÉN, CAMPECHE 24600<br>CEL: 981-110-2199 / FIJO: 982-822-0321</p>
            </div>
        </div>
        <div class="info-cliente">
            <p><strong>Fecha:</strong> <?php echo formatDate($recibo->fecha_pago); ?></p>
            <p><strong>Cliente:</strong> <?php echo $recibo->razon_social; ?></p>
            <p><strong>RFC:</strong> <?php echo $recibo->rfc; ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80%;">Servicios</th>
                    <th style="width: 20%; text-align: center;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($servicios_string, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="cantidad"><?php echo $cantidad_servicios; ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-end">Monto Total:</td>
                    <td class="text-end"><?php echo formatMoney($recibo->monto); ?></td>
                </tr>
                <tr>
                    <td class="text-end">Monto Pagado:</td>
                    <td class="text-end"><?php echo formatMoney($recibo->monto_pagado); ?></td>
                </tr>
                <tr>
                    <td class="text-end total">Saldo Pendiente:</td>
                    <td class="text-end total"><?php echo formatMoney($saldo_pendiente); ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="estado <?php echo strtolower($estado_recibo); ?>">
            Estado: <?php echo $estado_recibo; ?>
        </div>

        <p><strong>Cantidad en letra (del monto total):</strong> <?php echo numeroALetras($recibo->monto); ?></p>

        <div class="firma">
            <p>_________________________</p>
            <p><strong>L.C. JORGE LUIS CHAN GONZÁLEZ</strong></p>
        </div>
    </div>
    <script>window.print();</script>
</body>
</html>