<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de recibo no especificado');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del recibo
$database->query('SELECT r.*, c.razon_social as cliente_nombre, c.rfc as cliente_rfc, 
                  c.domicilio_fiscal as cliente_domicilio, c.codigo_postal as cliente_cp,
                  u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                  FROM Recibos r 
                  LEFT JOIN Clientes c ON r.cliente_id = c.id 
                  LEFT JOIN Usuarios u ON r.usuario_id = u.id 
                  WHERE r.id = :id');
$database->bind(':id', $id);
$recibo = $database->single();

// Verificar que el recibo existe
if (!$recibo) {
    die('Recibo no encontrado');
}

// Convertir monto a texto
function numeroALetras($numero) {
    $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    $especiales = ['ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTIUNO', 'VEINTIDOS', 'VEINTITRES', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISEIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    
    $numero = number_format($numero, 2, '.', '');
    list($enteros, $decimales) = explode('.', $numero);
    
    $resultado = '';
    
    if ($enteros == 0) {
        $resultado = 'CERO';
    } else if ($enteros == 1) {
        $resultado = 'UNO';
    } else if ($enteros <= 29) {
        if (in_array($enteros, [11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 23, 24, 25, 26, 27, 28, 29])) {
            $resultado = $especiales[$enteros - 11];
        } else {
            $resultado = $decenas[floor($enteros / 10)] . (($enteros % 10 != 0) ? ' Y ' . $unidades[$enteros % 10] : '');
        }
    } else if ($enteros < 100) {
        $resultado = $decenas[floor($enteros / 10)] . (($enteros % 10 != 0) ? ' Y ' . $unidades[$enteros % 10] : '');
    } else if ($enteros < 1000) {
        if ($enteros == 100) {
            $resultado = 'CIEN';
        } else {
            $resultado = $centenas[floor($enteros / 100)] . ' ' . numeroALetras($enteros % 100);
        }
    } else if ($enteros < 1000000) {
        if ($enteros == 1000) {
            $resultado = 'MIL';
        } else if ($enteros < 2000) {
            $resultado = 'MIL ' . numeroALetras($enteros % 1000);
        } else {
            $resultado = numeroALetras(floor($enteros / 1000)) . ' MIL ' . numeroALetras($enteros % 1000);
        }
    }
    
    return trim($resultado) . ' PESOS ' . $decimales . '/100 M.N.';
}

$monto_texto = numeroALetras($recibo->monto);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo #<?php echo $recibo->id; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .recibo {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .header p {
            margin: 5px 0;
        }
        .info-cliente {
            margin-bottom: 20px;
        }
        .info-recibo {
            margin-bottom: 20px;
        }
        .monto {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border: 2px solid #007bff;
            border-radius: 5px;
        }
        .monto-texto {
            text-align: center;
            font-style: italic;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
        }
        .firma {
            margin-top: 60px;
            text-align: center;
        }
        .firma hr {
            width: 250px;
            margin: 10px auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            text-align: left;
            width: 30%;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-pagado {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }
        @media print {
            body {
                padding: 0;
            }
            .recibo {
                box-shadow: none;
                border: none;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="header">
            <h1>RECIBO DE PAGO</h1>
            <p>Despacho Contable - Servicios Profesionales</p>
            <p>RFC: XAXX010101000 | Tel: (123) 456-7890</p>
        </div>
        
        <div class="info-recibo">
            <table>
                <tr>
                    <th>Recibo No.:</th>
                    <td><strong><?php echo str_pad($recibo->id, 6, '0', STR_PAD_LEFT); ?></strong></td>
                </tr>
                <tr>
                    <th>Fecha:</th>
                    <td><?php echo formatDate($recibo->fecha_pago, 'd/m/Y'); ?></td>
                </tr>
                <tr>
                    <th>Estado:</th>
                    <td>
                        <span class="status <?php echo 'status-' . $recibo->estado; ?>">
                            <?php echo ucfirst($recibo->estado); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="info-cliente">
            <h3>Datos del Cliente</h3>
            <table>
                <tr>
                    <th>Cliente:</th>
                    <td><?php echo $recibo->cliente_nombre; ?></td>
                </tr>
                <tr>
                    <th>RFC:</th>
                    <td><?php echo $recibo->cliente_rfc; ?></td>
                </tr>
                <?php if (!empty($recibo->cliente_domicilio)): ?>
                <tr>
                    <th>Domicilio:</th>
                    <td><?php echo $recibo->cliente_domicilio; ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($recibo->cliente_cp)): ?>
                <tr>
                    <th>C.P.:</th>
                    <td><?php echo $recibo->cliente_cp; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="concepto">
            <h3>Concepto</h3>
            <p><?php echo $recibo->concepto; ?></p>
            
            <?php if (!empty($recibo->vencimiento)): ?>
            <p><strong>Vigencia:</strong> Del <?php echo formatDate($recibo->fecha_pago, 'd/m/Y'); ?> al <?php echo formatDate($recibo->vencimiento, 'd/m/Y'); ?> (<?php echo $recibo->duracion; ?>)</p>
            <?php endif; ?>
        </div>
        
        <div class="monto">
            <?php echo formatMoney($recibo->monto); ?>
        </div>
        
        <div class="monto-texto">
            <strong>Importe con letra:</strong> <?php echo $monto_texto; ?>
        </div>
        
        <?php if (!empty($recibo->observaciones)): ?>
        <div class="observaciones">
            <h3>Observaciones</h3>
            <p><?php echo nl2br($recibo->observaciones); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="firma">
            <hr>
            <p>Firma de Recibido</p>
        </div>
        
        <div class="footer">
            <p>Este documento es un comprobante de pago sin validez fiscal.</p>
            <p>Fecha de impresión: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print();" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Imprimir Recibo
        </button>
        <button onclick="window.close();" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Cerrar
        </button>
    </div>
</body>
</html>