<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';


if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$id_pago = isset($_GET['pago_id']) ? (int)$_GET['pago_id'] : 0;

if ($id_pago <= 0) {
    die('<div class="alert alert-danger">El ID del pago no es válido.</div>');
}

$db = new Database();


$db->query('
        SELECT 
            p.id AS id_pago,
            p.folio,
            p.fecha_pago,
            r.concepto,
            r.monto,
            r.monto_pagado,
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

$folio = $data->folio;
$fecha = date("d/m/Y", strtotime($data->fecha_pago));
$cliente = $data->cliente ?? "_________________________";
$rfc = $data->rfc ?? "_________________________";
$domicilio = $data->domicilio ?? "_________________________";
$descripcion = $data->concepto ?? "_________________________";
$precio_servicio = number_format($data->monto, 2);
$importe_pago = number_format($data->monto_pagado, 2);
$saldo = number_format($data->monto - $data->monto_pagado, 2);
$estado = strtoupper($data->estado);
$fecha_pago = $fecha;

// Función convertir a letras
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
                    <!--<b>Comprobante:</b> Pago de honorarios<br>!-->
                    <b>Folio:</b> <?= $folio ?><br>
                    <!--<b>Fecha:</b> <?= $fecha ?><br>!-->
                    <b>Cliente:</b> <?= $cliente ?><br>
                    <b>RFC:</b> <?= $rfc ?><br>
                    <!--<b>Domicilio:</b> <?= $domicilio ?> !-->
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
                        <?php if ($saldo > 0): ?> <td><b>Saldo:</b>$<?= $saldo ?></td>
                    </tr><?php endif; ?>
                <tr>
                    <th>Cantidad en letras</th>
                    <td><?= $cantidad_letras ?></td>
                </tr>
                <!--<tr><th>Saldo</th><td>$<?= $saldo ?></td></tr>!-->
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