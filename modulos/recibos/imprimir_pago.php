<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$id_pago = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pago <= 0) {
    die('<div class="alert alert-danger">El ID del pago no es válido.</div>');
}

$db = new Database();

// 1. MODIFICACIÓN: Se añaden r.id y r.tipo a la consulta principal
$db->query('
    SELECT 
        p.id AS id_pago,
        p.folio,
        p.monto,
        p.fecha_pago,
        r.id AS recibo_id, -- ID del recibo para buscar servicios si es necesario
        r.tipo AS recibo_tipo, -- Tipo de recibo para la lógica condicional
        r.concepto,
        r.monto AS precio_servicio,
        r.estado,
        c.razon_social AS cliente,
        c.rfc,
        c.domicilio_fiscal AS domicilio
    FROM recibos_pagos p
    LEFT JOIN recibos r ON r.id = p.recibo_id
    LEFT JOIN clientes c ON c.id = r.cliente_id
    WHERE p.id = :id_pago
    LIMIT 1
');

$db->bind(':id_pago', $id_pago);
$data = $db->single();

if (!$data) {
    die('<div class="alert alert-danger">Pago no encontrado.</div>');
}

// 2. LÓGICA CONDICIONAL: Se prepara la descripción según el tipo de recibo
$descripcion = $data->concepto ?? "_________________________"; // Descripción por defecto

if ($data->recibo_tipo === 'servicio') {
    // Si es un recibo de servicio, buscamos los detalles
    $db->query('SELECT descripcion FROM recibo_servicios WHERE recibo_id = :recibo_id');
    $db->bind(':recibo_id', $data->recibo_id);
    $servicios = $db->resultSet();
    
    if ($servicios) {
        $cantidad_servicios = count($servicios);
        $descripciones = array_map(function($s) { return $s->descripcion; }, $servicios);
        // Se crea el string con el formato "Servicio1, Servicio2 (Cantidad: 2)"
        $descripcion = implode(', ', $descripciones) . " (Cantidad: " . $cantidad_servicios . ")";
    } else {
        $descripcion = "Recibo de servicios sin detalles.";
    }
}

// El resto de las variables se asignan como antes
$folio = $data->folio;
$fecha = date("d/m/Y", strtotime($data->fecha_pago));
$cliente = $data->cliente ?? "_________________________";
$rfc = $data->rfc ?? "_________________________";
$domicilio = $data->domicilio ?? "_________________________";
$precio_servicio = number_format($data->precio_servicio, 2);
$importe_pago = number_format($data->monto, 2);
$saldo = number_format($data->precio_servicio - $data->monto, 2);
$estado = strtoupper($data->estado);
$fecha_pago = $fecha;

// (El resto del archivo, incluyendo la función numeroALetras y el HTML, se mantiene exactamente igual)
function numeroALetras($numero)
{
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

$cantidad_letras = numeroALetras($data->monto);

// Logo dinámico
$logo_path = __DIR__ . '/../../uploads/logo.png';
$logo_url = file_exists($logo_path) ? '../../uploads/logo.png' : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de Pago</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        .page {
            width: 210mm;
            height: 297mm;
            padding: 6mm 10mm;
            margin: auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .recibo {
            width: 100%;
            border: 1px solid #aaa;
            height: 130mm;
            /* Mitad exacta */
            padding: 5mm 8mm;
            position: relative;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .copy-label {
            position: absolute;
            top: 4px;
            right: 10px;
            font-weight: bold;
            font-size: 11px;
        }

        .header {
            display: flex;
            align-items: center;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .logo {
            width: 70px;
            height: 65px;
            border: 1px solid #ccc;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 10px;
        }

        .logo img {
            max-width: 65px;
            max-height: 60px;
            object-fit: contain;
        }

        .empresa {
            flex: 1;
            text-align: center;
            font-size: 11px;
        }

        .empresa h2 {
            margin: 0;
            font-size: 15px;
        }

        .info {
            line-height: 1.3;
            margin-bottom: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 3px 5px;
        }

        .mensaje {
            background: #f4f8ff;
            border-left: 3px solid #4a91e9;
            padding: 4px 7px;
            line-height: 1.3;
            font-size: 11.5px;
        }

        .firma {
            text-align: center;
            margin-top: 6px;
        }

        .firma hr {
            width: 45%;
            border: 1px solid #000;
            margin: 3px auto;
        }

        .divisor {
            border-top: 1px dashed #aaa;
            margin: 2mm 0;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            body,
            html {
                margin: 0;
                padding: 0;
            }

            .page {
                height: 287mm;
            }

            .recibo {
                height: 130mm;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <?php
        $tipos = ['ORIGINAL', 'COPIA'];
        foreach ($tipos as $index => $tipo): ?>
            <div class="recibo">
                <span class="copy-label"><?= $tipo ?></span>
                <div class="header">
                    <div class="logo">
                        <?php if ($logo_url): ?>
                            <img src="<?= $logo_url ?>" alt="Logo">
                        <?php else: ?>
                            LOGO
                        <?php endif; ?>
                    </div>
                    <div class="empresa">
                        <h2>DESPACHO CONTABLE JLCHAN</h2>
                        CALLE 21 #68 D, HOPELCHÉN, CAMPECHE 24600<br>
                        CEL: 981-110-2199 / FIJO: 982-822-0321<br>
                        Correo: jlchan@multitekmx.com / jlchandespacho@gmail.com
                    </div>
                </div>
                <div class="info">
                    <b>Folio:</b> <?= $folio ?><br>
                    <b>Cliente:</b> <?= $cliente ?><br>
                    <b>RFC:</b> <?= $rfc ?><br>
                </div>
                <table>
                    <tr>
                        <th>Descripción</th>
                        <td><?= $descripcion ?></td>
                    </tr>
                    <tr>
                        <th>Precio Servicio</th>
                        <td>$<?= $precio_servicio ?></td>
                    </tr>
                    <tr>
                        <th>Importe Pago</th>
                        <td>$<?= $importe_pago ?></td>
                        
                    </tr>
                <tr>
                    <th>Cantidad en letras</th>
                    <td><?= $cantidad_letras ?></td>
                </tr>
                <tr>
                    <th>Estado</th>
                    <td><?= $estado ?></td>
                </tr>
                <tr>
                    <th>Fecha Pago</th>
                    <td><?= $fecha_pago ?></td>
                </tr>
                </table>
                <div class="mensaje">
                    Estimado cliente, agradecemos su depósito por los servicios prestados a las siguientes cuentas:<br>
                    <b>Banco:</b> BANCOMER<br>
                    <b>Cuenta:</b> 29-45-13-10-57<br>
                    <b>CLABE:</b> 0120-5502-9451-3105-70<br>
                    <b>Beneficiario:</b> JORGE LUIS CHAN GONZÁLEZ
                </div>
                <div class="firma">
                    <p>Atentamente,</p>
                    <hr>
                    <p><b>L.C. JORGE LUIS CHAN GONZÁLEZ</b><br>Cédula Profesional 7224903</p>
                </div>
            </div>
            <?php if ($index === 0): ?>
                <div class="divisor"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <script>
        window.print();
    </script>
</body>
</html>