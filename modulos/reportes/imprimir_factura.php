<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Validar sesión y parámetros
if (!isLoggedIn() || !isset($_GET['id']) || !isset($_GET['tipo'])) {
    exit('Acceso denegado o parámetros incorrectos.');
}

$id = (int)$_GET['id'];
$tipo = sanitize($_GET['tipo']);

if ($tipo != 'emitida' && $tipo != 'recibida') {
    exit('Tipo de factura no válido.');
}

// Conectar a la BD
$db = new Database();

// Obtener datos de la factura
$tabla = ($tipo == 'emitida') ? 'CFDIs_Emitidas' : 'CFDIs_Recibidas';
$db->query("SELECT * FROM $tabla WHERE id = :id");
$db->bind(':id', $id);
$factura = $db->single();

if (!$factura) {
    exit('No se encontró la factura especificada.');
}

// --- Lógica para determinar el Tipo de Comprobante (copiada de ver_factura.php) ---
$tipo_comprobante = 'Otro';
if ($factura->tipo_comprobante == 'Ingreso') {
        $tipo_comprobante = 'Ingreso';
    } elseif ($factura->tipo_comprobante == 'Egreso') {
        $tipo_comprobante = 'Egreso';
    } elseif ($factura->tipo_comprobante == 'Nomina') {
        $tipo_comprobante = 'Nómina';
    } elseif ($factura->tipo_comprobante == 'Pago') {
        $tipo_comprobante = 'Pago';
    }

// --- Determinar nombres de emisor y receptor ---
$emisor_nombre =  $factura->nombre_emisor;
$emisor_rfc = $factura->rfc_emisor;
$receptor_nombre =  $factura->nombre_receptor ;
$receptor_rfc =  $factura->rfc_receptor ;
$fecha_factura = ($tipo == 'emitida') ? $factura->fecha_emision : $factura->fecha_certificacion;
$label_fecha = ($tipo == 'emitida') ? 'Fecha de Emisión' : 'Fecha de Certificación';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura <?php echo htmlspecialchars($factura->folio_fiscal); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .invoice-container { max-width: 800px; margin: 20px auto; padding: 30px; background-color: #fff; border: 1px solid #dee2e6; }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
        .print-button { position: fixed; bottom: 20px; right: 20px; z-index: 100; }
        .totals-table { width: 60%; margin-left: auto; }
        .details-table th { width: 40%; background-color: #f8f9fa; }
        @media print {
            body { background-color: #fff; margin: 0; }
            .invoice-container { box-shadow: none; border: none; margin: 0; padding: 0; max-width: 100%; }
            .print-button { display: none; }
        }
    </style>
</head>
<body>

<div class="container invoice-container">
    <header class="row invoice-header align-items-center">
        <div class="col-8">
            <h4 class="mb-1"><strong>Emisor:</strong> <?php echo htmlspecialchars($emisor_nombre); ?></h4>
            <p class="mb-0"><strong>RFC:</strong> <?php echo htmlspecialchars($emisor_rfc); ?></p>
        </div>
        <div class="col-4 text-end">
            <h2 class="mb-1">FACTURA</h2>
            <?php if (!empty($factura->folio_interno)): ?>
            <p class="mb-0"><strong>Folio:</strong> <?php echo htmlspecialchars($factura->folio_interno); ?></p>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <div class="row mb-4">
            <div class="col-8">
                <h5>Receptor</h5>
                <p class="mb-0"><strong><?php echo htmlspecialchars($receptor_nombre); ?></strong></p>
                <p class="mb-0"><strong>RFC:</strong> <?php echo htmlspecialchars($receptor_rfc); ?></p>
            </div>
            <div class="col-4 text-end">
                <span class="badge bg-secondary fs-6"><?php echo htmlspecialchars($tipo_comprobante); ?></span>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <table class="table table-sm table-bordered details-table">
                    <tr>
                        <th>Folio Fiscal (UUID)</th>
                        <td><?php echo htmlspecialchars($factura->folio_fiscal); ?></td>
                    </tr>
                     <tr>
                        <th><?php echo $label_fecha; ?></th>
                        <td><?php echo formatDate($fecha_factura); ?></td>
                    </tr>
                    <tr>
                        <th>Método de Pago</th>
                        <td><?php echo getMetodoPago($factura->metodo_pago); ?></td>
                    </tr>
                    <tr>
                        <th>Forma de Pago</th>
                        <td><?php echo getFormaPago($factura->forma_pago); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <h5 class="mt-4">Conceptos</h5>
        <div class="p-3 border bg-light rounded mb-4">
             <?php echo nl2br(htmlspecialchars($factura->descripcion)); ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?php if (!empty($factura->uuid_relacionado)): ?>
                    <h5>CFDI Relacionado</h5>
                    <p class="small"><?php echo htmlspecialchars($factura->uuid_relacionado); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <table class="table totals-table">
                    <tbody>
                        <tr>
                            <th class="text-end">Subtotal:</th>
                            <td class="text-end"><?php echo formatMoney($factura->subtotal); ?></td>
                        </tr>
                        <?php if ((float)$factura->iva_importe > 0): ?>
                        <tr>
                            <th class="text-end">IVA (16%):</th>
                            <td class="text-end"><?php echo formatMoney($factura->iva_importe); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ((float)$factura->ieps_importe > 0): ?>
                        <tr>
                            <th class="text-end">IEPS:</th>
                            <td class="text-end"><?php echo formatMoney($factura->ieps_importe); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ((float)$factura->retencion_iva > 0): ?>
                        <tr>
                            <th class="text-end">Retención IVA:</th>
                            <td class="text-end">- <?php echo formatMoney($factura->retencion_iva); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ((float)$factura->retencion_isr > 0): ?>
                        <tr>
                            <th class="text-end">Retención ISR:</th>
                            <td class="text-end">- <?php echo formatMoney($factura->retencion_isr); ?></td>
                        </tr>
                        <?php endif; ?>
                         <?php if ((float)$factura->retencion_ieps > 0): ?>
                        <tr>
                            <th class="text-end">Retención IEPS:</th>
                            <td class="text-end">- <?php echo formatMoney($factura->retencion_ieps); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <th class="text-end fs-5">Total:</th>
                            <td class="text-end fs-5"><strong><?php echo formatMoney($factura->total); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<button class="btn btn-primary print-button" onclick="window.print();">
    <i class="fas fa-print"></i> Imprimir
</button>

</body>
</html>