<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();
$data = null;

// --- LÓGICA DE UNIFICACIÓN ---
// El archivo ahora puede recibir 'pago_id' o 'recibo_id'
$pago_id = isset($_GET['pago_id']) ? (int)$_GET['pago_id'] : 0;
$recibo_id = isset($_GET['recibo_id']) ? (int)$_GET['recibo_id'] : 0;

if ($pago_id > 0) {
    // Si viene un pago_id, obtenemos los datos a través del pago (comportamiento original)
    $db->query('
        SELECT 
            p.id AS id_pago, p.folio, p.fecha_pago,
            r.id AS recibo_id, r.tipo AS recibo_tipo, r.concepto, r.monto, r.monto_pagado, r.estado, r.fecha_pago AS fecha_recibo,
            c.razon_social AS cliente, c.rfc, c.domicilio_fiscal AS domicilio
        FROM recibos_pagos p
        LEFT JOIN recibos r ON r.id = p.recibo_id
        LEFT JOIN clientes c ON c.id = r.cliente_id
        WHERE p.id = :id_pago LIMIT 1
    ');
    $db->bind(':id_pago', $pago_id);
    $data = $db->single();
} elseif ($recibo_id > 0) {
    // Si viene un recibo_id, obtenemos los datos directamente del recibo (comportamiento de imprimir_servicio)
    $db->query('
        SELECT 
            NULL AS id_pago, NULL AS folio, r.fecha_pago,
            r.id AS recibo_id, r.tipo AS recibo_tipo, r.concepto, r.monto, r.monto_pagado, r.estado, r.fecha_pago AS fecha_recibo,
            c.razon_social AS cliente, c.rfc, c.domicilio_fiscal AS domicilio
        FROM recibos r
        LEFT JOIN clientes c ON c.id = r.cliente_id
        WHERE r.id = :recibo_id LIMIT 1
    ');
    $db->bind(':recibo_id', $recibo_id);
    $data = $db->single();
} else {
    die('<div class="alert alert-danger">No se especificó un ID de pago o recibo válido.</div>');
}

if (!$data) {
    die('<div class="alert alert-danger">No se encontraron datos para imprimir.</div>');
}

// Variables para la plantilla
$folio = $data->folio ?? 'N/A';
$fecha = date("d/m/Y", strtotime($data->fecha_pago));
$cliente = $data->cliente ?? "_________________________";
$rfc = $data->rfc ?? "_________________________";
$domicilio = $data->domicilio ?? "_________________________";
$estado = strtoupper($data->estado);
$cantidad_letras = numeroALetras($data->monto);

// Logo dinámico
$logo_path = __DIR__ . '/../../uploads/logo.png';
$logo_url = file_exists($logo_path) ? '../../uploads/logo.png' : null;

// Función para convertir número a letras (sin cambios)
function numeroALetras($numero) {
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
    <meta charset="utf-8">
    <title>Recibo de Pago</title>
    <style>
        /* (Los estilos se mantienen igual, con los añadidos para el estado) */
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 12px; margin: 0; }
        .page { width: 210mm; height: 297mm; padding: 6mm 10mm; margin: auto; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .recibo { width: 100%; border: 1px solid #aaa; height: 130mm; padding: 5mm 8mm; position: relative; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; }
        .header { display: flex; align-items: center; border-bottom: 1px solid #000; padding-bottom: 3px; margin-bottom: 5px; }
        .logo { width: 70px; height: 65px; border: 1px solid #ccc; display: flex; justify-content: center; align-items: center; margin-right: 10px; }
        .logo img { max-width: 65px; max-height: 60px; object-fit: contain; }
        .empresa { flex: 1; text-align: center; font-size: 11px; }
        .empresa h2 { margin: 0; font-size: 15px; }
        .info { line-height: 1.3; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; }
        .text-end { text-align: right; }
        .total { font-weight: bold; }
        .estado { font-size: 1.1em; font-weight: bold; text-align: center; padding: 4px; border-radius: 5px; border: 1px solid; }
        .pagado { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .pendiente { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .firma hr { width: 45%; border: 1px solid #000; margin: 3px auto; }
        .firma { text-align: center; margin-top: 6px; }
        .divisor { border-top: 1px dashed #aaa; margin: 2mm 0; }
        @media print { @page { size: A4 portrait; margin: 5mm; } body, html { margin: 0; padding: 0; } .page { height: 287mm; } .recibo { height: 130mm; page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="page">
        <?php foreach (['ORIGINAL', 'COPIA'] as $index => $tipo): ?>
            <div class="recibo">
                <div class="header">
                    <div class="logo"><?php if ($logo_url): ?><img src="<?= $logo_url ?>" alt="Logo"><?php else: ?>LOGO<?php endif; ?></div>
                    <div class="empresa">
                        <h2>DESPACHO CONTABLE JLCHAN</h2>
                        CALLE 21 #68 D, HOPELCHÉN, CAMPECHE 24600<br>
                        CEL: 981-110-2199 / FIJO: 982-822-0321<br>
                        Correo: jlchan@multitekmx.com / jlchandespacho@gmail.com
                    </div>
                </div>

                <div class="info">
                    <b>Folio:</b> <?= $folio ?><br>
                    <!--<b>Fecha:</b> <?= $fecha ?><br>!-->
                    <b>Cliente:</b> <?= $cliente ?><br>
                    <b>RFC:</b> <?= $rfc ?><br>
                </div>

                <?php if ($data->recibo_tipo === 'servicio'):
                    // --- Bloque para Recibos de Servicio ---
                    $db->query('SELECT descripcion FROM recibo_servicios WHERE recibo_id = :recibo_id');
                    $db->bind(':recibo_id', $data->recibo_id);
                    $servicios = $db->resultSet();
                    $cantidad_servicios = count($servicios);
                    $descripciones = array_map(function($s) { return $s->descripcion; }, $servicios);
                    $servicios_string = implode(', ', $descripciones);
                    $saldo_pendiente = max(((float)$data->monto - (float)$data->monto_pagado), 0.0);
                ?>
                    <table>
                        <thead><tr><th style="width: 80%;">Servicios</th><th style="width: 20%; text-align: center;">Cantidad</th></tr></thead>
                        <tbody><tr><td><?php echo htmlspecialchars($servicios_string, ENT_QUOTES, 'UTF-8'); ?></td><td style="text-align: center;"><?php echo $cantidad_servicios; ?></td></tr></tbody>
                        <tfoot>
                            <tr><td class="text-end">Monto Total:</td><td class="text-end"><?php echo formatMoney($data->monto); ?></td></tr>
                            <tr><td class="text-end">Monto Pagado:</td><td class="text-end"><?php echo formatMoney($data->monto_pagado); ?></td></tr>
                            <tr><td class="text-end total">Saldo Pendiente:</td><td class="text-end total"><?php echo formatMoney($saldo_pendiente); ?></td></tr>
                        </tfoot>
                    </table>
                    <div class="estado <?php echo strtolower($estado); ?>">Estado: <?php echo $estado; ?></div>

                <?php else:
                    // --- Bloque para Recibos de Honorarios (Original) ---
                    $saldo = max(((float)$data->monto - (float)$data->monto_pagado), 0.0);
                ?>
                    <table>
                        <tr><th>Descripción</th><td><?= htmlspecialchars($data->concepto ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
                        <tr><th>Precio Servicio</th><td>$<?= number_format($data->monto, 2) ?></td></tr>
                        <tr><th>Importe Pago</th><td>$<?= number_format($data->monto_pagado, 2) ?></td></tr>
                        <?php if ($saldo > 0): ?><tr><th>Saldo</th><td>$<?= number_format($saldo, 2) ?></td></tr><?php endif; ?>
                        <tr><th>Cantidad en letras</th><td><?= $cantidad_letras ?></td></tr>
                        <tr><th>Estado</th><td><?= $estado ?></td></tr>
                        <tr><th>Fecha Pago</th><td><?= $fecha ?></td></tr>
                    </table>
                <?php endif; ?>
                <div class="firma">
                    <p>Atentamente,</p><hr><p><b>L.C. JORGE LUIS CHAN GONZÁLEZ</b><br>Cédula Profesional 7224903</p>
                </div>
            </div>

            <?php if ($index === 0): ?><div class="divisor"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
    <script>window.print();</script>
</body>
</html>