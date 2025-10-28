<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php'; // Necesario para numeroALetras, formatMoney, etc.

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$recibo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($recibo_id <= 0) die('<div class="alert alert-danger">ID de recibo no válido.</div>');

$db = new Database();

// --- OBTENER DATOS DEL RECIBO EXTERNO ---
$db->query('SELECT * FROM recibos WHERE id = :id AND cliente_id IS NULL AND origen = "manual"');
$db->bind(':id', $recibo_id);
$recibo_principal = $db->single();

if (!$recibo_principal) {
     die('<div class="alert alert-danger">Recibo externo no encontrado o no válido.</div>');
}

// --- OBTENER LOS DETALLES DEL SERVICIO ---
$db->query('SELECT * FROM recibo_servicios WHERE recibo_id = :id ORDER BY id ASC');
$db->bind(':id', $recibo_id);
$servicios = $db->resultSet(); // Array con los detalles

// --- OBTENER EL FOLIO Y FECHA DEL ÚLTIMO PAGO ---
$db->query('SELECT folio, fecha_pago FROM recibos_pagos WHERE recibo_id = :recibo_id ORDER BY fecha_pago DESC, id DESC LIMIT 1');
$db->bind(':recibo_id', $recibo_id);
$pago = $db->single();

$folio_imprimir = $pago && !empty($pago->folio) ? $pago->folio : $recibo_principal->id;
$fecha_imprimir_raw = $pago && !empty($pago->fecha_pago) ? $pago->fecha_pago : $recibo_principal->periodo_inicio;
$fecha_imprimir = date("d/m/Y", strtotime($fecha_imprimir_raw));

// --- PREPARAR OTRAS VARIABLES ---
$cliente_nombre = $recibo_principal->externo_nombre ?? "N/A";
$cliente_rfc = $recibo_principal->externo_rfc ?? "N/A";
$cliente_domicilio = ""; // Vacío para externos
$monto_total = (float)$recibo_principal->monto;

// Determinar estado
$estado_recibo_texto = 'Desconocido';
if ($recibo_principal->estatus === 'cancelado') {
    $estado_recibo_texto = 'Cancelado';
} elseif ($recibo_principal->estado === 'pagado') {
    $estado_recibo_texto = 'Pagado';
} elseif ($recibo_principal->estado === 'pendiente') {
    $estado_recibo_texto = 'Pendiente';
} else {
    $estado_recibo_texto = ucfirst($recibo_principal->estatus ?? 'Activo');
}

// Monto pagado para externos (igual al total si no cancelado)
$monto_pagado_imprimir = ($estado_recibo_texto !== 'Cancelado') ? $monto_total : 0.00;
// Calcular saldo (debería ser 0 para externos pagados)
$saldo_pendiente_imprimir = max($monto_total - $monto_pagado_imprimir, 0.0);

$cantidad_letras_imprimir = function_exists('numeroALetras') ? numeroALetras($monto_total) : 'Función numeroALetras no encontrada';

// Logo dinámico (igual que en imprimir.php)
$logo_path = __DIR__ . '/../../uploads/logo.png';
$logo_url = file_exists($logo_path) ? URL_ROOT . '/uploads/logo.png' : null; // Usar URL_ROOT para la URL

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo Externo #<?php echo htmlspecialchars($folio_imprimir); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 10px; margin: 0; } /* Reducido aún más */
        .page { width: 210mm; height: 297mm; padding: 5mm 8mm; margin: auto; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .recibo { width: 100%; border: 1px solid #aaa; height: 135mm; /* Ligeramente más alto para espacio */ padding: 4mm 6mm; position: relative; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between; }
        .header { display: flex; align-items: center; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 4px; }
        .logo { width: 65px; height: 60px; /* Ligeramente más pequeño */ border: 1px solid #ccc; display: flex; justify-content: center; align-items: center; margin-right: 8px; }
        .logo img { max-width: 60px; max-height: 55px; object-fit: contain; }
        .empresa { flex: 1; text-align: center; font-size: 9.5px; } /* Más pequeño */
        .empresa h2 { margin: 0 0 2px 0; font-size: 13px; } /* Más pequeño */
        .info { line-height: 1.2; margin-bottom: 3px; font-size: 10px; } /* Más pequeño */
        .info b { display: inline-block; min-width: 45px;} /* Ajustar ancho mínimo */
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 4px;} /* Más pequeño */
        th, td { border: 1px solid #ccc; padding: 2px 4px; } /* Menos padding */
        .text-end { text-align: right; }
        .total { font-weight: bold; }
        /* Clases de estado copiadas de imprimir.php */
        .estado { font-size: 1.05em; font-weight: bold; text-align: center; padding: 3px; border-radius: 4px; border: 1px solid; margin-top: 5px;}
        .pagado { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .pendiente { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .cancelado { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .activo { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; } /* Estilo para activo */
        .desconocido { background-color: #e2e3e5; color: #383d41; border-color: #d6d8db; }

        .firma hr { width: 40%; border: none; border-top: 1px solid #000; margin: 1px auto; } /* Más delgada */
        .firma { text-align: center; margin-top: 4px; font-size: 9.5px; } /* Más pequeño */
        .firma p { margin: 1px 0; }
        .letras { font-size: 9px; margin-top: 4px; font-style: italic;} /* Cantidad letras más pequeña */

        /* Línea divisoria */
        .divisor { border-top: 1px dashed #aaa; margin: 3mm 0; }

        /* Estilos de Impresión */
        @media print {
             @page {
                size: A4 portrait; /* O 'letter portrait' */
                margin: 5mm; /* Márgenes mínimos */
             }
             body, html { margin: 0; padding: 0; }
             .page { height: auto; /* Ajustar altura automáticamente */ width: 100%; padding: 0; border: none; box-shadow: none; margin: 0; }
             .recibo { height: 138mm; /* Ajustar altura para A4/2 aprox */ border: none; padding: 0; margin: 0; page-break-inside: avoid; }
             .no-print { display: none; }
             .divisor { margin: 2mm 0; } /* Ajustar margen divisor en impresión */
        }
    </style>
</head>
<body>
    <div class="page">
        <?php foreach (['ORIGINAL DESPACHO', 'COPIA CLIENTE'] as $index => $tipo): ?>
            <div class="recibo">
                <div class="header">
                    <div class="logo"><?php if ($logo_url): ?><img src="<?= $logo_url ?>" alt="Logo"><?php else: ?>LOGO<?php endif; ?></div>
                    <div class="empresa">
                        <h2>DESPACHO CONTABLE JLCHAN</h2>
                        CALLE 15 S/N ENTRE 10 Y 12, COL. SAN LUIS OBISPO, C.P. 24600<br> HOPELCHÉN, CAMPECHE<br> CEL: 981-110-2199 / FIJO: 982-822-0321<br>
                        Correo: jlchan@multitekmx.com / jlchandespacho@gmail.com
                    </div>
                    <div style="position: absolute; top: 5px; right: 10px; font-weight: bold; color: #555;"><?= $tipo ?></div>
                </div>

                <div class="info">
                    <b>Folio:</b> <span><?php echo htmlspecialchars($folio_imprimir); ?></span><br>
                    <b>Fecha:</b> <span><?php echo htmlspecialchars($fecha_imprimir); ?></span><br>
                    <b>Cliente:</b> <span><?php echo htmlspecialchars($cliente_nombre); ?></span><br>
                    <b>RFC:</b> <span><?php echo htmlspecialchars($cliente_rfc); ?></span><br>
                    <?php if (!empty($cliente_domicilio)): ?>
                        <b>Domicilio:</b> <span><?php echo htmlspecialchars($cliente_domicilio); ?></span><br>
                    <?php endif; ?>
                </div>

                <table>
                     <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end" style="width: 25%;">Importe</th> </tr>
                    </thead>
                    <tbody>
                         <?php if (empty($servicios)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($recibo_principal->concepto_principal ?? 'Servicio según recibo'); ?></td>
                                <td class="text-end"><?php echo formatMoney($monto_total); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($servicios as $servicio): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($servicio->descripcion); ?></td>
                                    <td class="text-end"><?php echo formatMoney($servicio->importe); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                     <tfoot>
                        <tr>
                            <td class="text-end total">Total:</td>
                            <td class="text-end total"><?php echo formatMoney($monto_total); ?></td>
                        </tr>
                        <tr>
                            <td class="text-end">Pagado:</td>
                            <td class="text-end"><?php echo formatMoney($monto_pagado_imprimir); ?></td>
                        </tr>
                         <tr>
                            <td class="text-end">Saldo:</td>
                            <td class="text-end"><?php echo formatMoney($saldo_pendiente_imprimir); ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="estado <?php echo strtolower($estado_recibo_texto); // Usar nombre de clase simple ?>">
                    Estado: <?php echo $estado_recibo_texto; ?>
                </div>
                 <div class="letras">
                    (<?php echo $cantidad_letras_imprimir; ?>)
                </div>


                <div class="firma">
                    <p>Atentamente,</p>
                    <hr>
                    <p><b>L.C. JORGE LUIS CHAN GONZÁLEZ</b><br>Cédula Profesional 7224903</p>
                </div>
            </div>

            <?php if ($index === 0): /* Solo añadir divisor después del ORIGINAL */ ?>
                <div class="divisor"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
     <div class="no-print" style="text-align: center; margin-top: 10px;">
        <button onclick="window.print();" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
        <button onclick="window.close();" class="btn btn-secondary">Cerrar</button>
    </div>
    <script>window.print();</script>
    </body>
</html>