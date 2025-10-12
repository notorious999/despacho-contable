<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar parámetros
if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    flash('mensaje', 'Parámetros incorrectos', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

$id = sanitize($_GET['id']);
$tipo = sanitize($_GET['tipo']);

// Validar tipo
if ($tipo != 'emitida' && $tipo != 'recibida') {
    flash('mensaje', 'Tipo de factura inválido', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener datos de la factura según el tipo
$tabla = ($tipo == 'emitida') ? 'CFDIs_Emitidas' : 'CFDIs_Recibidas';
$database->query("SELECT f.*, c.razon_social as cliente_nombre 
                  FROM $tabla f 
                  LEFT JOIN Clientes c ON f.cliente_id = c.id 
                  WHERE f.id = :id");
$database->bind(':id', $id);
$factura = $database->single();

// Verificar que la factura existe
if (!$factura) {
    flash('mensaje', 'Factura no encontrada', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php?tipo=' . $tipo);
}

// Determinar el tipo de comprobante
$tipo_comprobante = '';
if ($tipo == 'emitida') {
    // Determinar tipo de comprobante por el método de pago
    if ($factura->metodo_pago == 'PUE') {
        $tipo_comprobante = 'Ingreso';
    } elseif ($factura->metodo_pago == 'PPD') {
        $tipo_comprobante = 'Crédito';
    } else {
        // Verificar si hay relacionados (Nota de crédito)
        $database->query("SELECT uuid_relacionado FROM CFDIs_Recibidas WHERE uuid_relacionado = :folio_fiscal");
        $database->bind(':folio_fiscal', $factura->folio_fiscal);
        if ($database->single()) {
            $tipo_comprobante = 'Crédito';
        } else {
            $tipo_comprobante = 'Otro';
        }
    }
} else {
    // Si es una factura recibida
    if ($factura->metodo_pago == 'PUE') {
        $tipo_comprobante = 'Ingreso';
    } elseif ($factura->metodo_pago == 'PPD') {
        $tipo_comprobante = 'Crédito';
    } elseif (!empty($factura->uuid_relacionado)) {
        $tipo_comprobante = 'Pago';
    } else {
        $tipo_comprobante = 'Otro';
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Detalle de Factura</h2>
        <p class="lead">
            <?php echo ($tipo == 'emitida') ? 'Factura Emitida' : 'Factura Recibida'; ?> #<?php echo $factura->id; ?>
        </p>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php?tipo=<?php echo $tipo; ?>" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <?php if($factura->estado_sat == 'vigente'): ?>
        <a href="<?php echo URL_ROOT; ?>/modulos/reportes/cancelar_factura.php?id=<?php echo $factura->id; ?>&tipo=<?php echo $tipo; ?>" class="btn btn-danger">
            <i class="fas fa-ban"></i> Cancelar Factura
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-file-invoice"></i> Información de la Factura
            </div>
            <div>
                <span class="badge bg-primary me-2">Tipo: <?php echo $tipo_comprobante; ?></span>
                <?php if($factura->estado_sat == 'Cancelado'): ?>
                <span class="badge bg-danger">CANCELADA</span>
                <?php else: ?>
                <span class="badge bg-success">VIGENTE</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Información General</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Cliente:</th>
                        <td><?php echo $factura->cliente_nombre; ?></td>
                    </tr>
                    <tr>
                        <th>Folio Fiscal (UUID):</th>
                        <td><?php echo $factura->folio_fiscal; ?></td>
                    </tr>
                    <?php if($tipo == 'emitida'): ?>
                    <tr>
                        <th>Folio Interno:</th>
                        <td><?php echo !empty($factura->folio_interno) ? $factura->folio_interno : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <th>Fecha de Emisión:</th>
                        <td><?php echo formatDate($factura->fecha_emision); ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th>Fecha de Certificación:</th>
                        <td><?php echo formatDate($factura->fecha_certificacion); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Forma de Pago:</th>
                        <td><?php echo getFormaPago($factura->forma_pago); ?></td>
                    </tr>
                    <tr>
                        <th>Método de Pago:</th>
                        <td><?php echo getMetodoPago($factura->metodo_pago); ?></td>
                    </tr>
                    <tr>
                        <th>Tipo de Comprobante:</th>
                        <td><strong><?php echo $tipo_comprobante; ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5><?php echo ($tipo == 'emitida') ? 'Datos del Receptor' : 'Datos del Emisor'; ?></h5>
                <table class="table table-bordered">
                    <?php if($tipo == 'emitida'): ?>
                    <tr>
                        <th width="40%">Receptor:</th>
                        <td><?php echo $factura->nombre_receptor; ?></td>
                    </tr>
                    <tr>
                        <th>RFC Receptor:</th>
                        <td><?php echo $factura->rfc_receptor; ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th width="40%">Emisor:</th>
                        <td><?php echo $factura->nombre_emisor; ?></td>
                    </tr>
                    <tr>
                        <th>RFC Emisor:</th>
                        <td><?php echo $factura->rfc_emisor; ?></td>
                    </tr>
                    <?php if(!empty($factura->uuid_relacionado)): ?>
                    <tr>
                        <th>CFDI Relacionado:</th>
                        <td><?php echo $factura->uuid_relacionado; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                </table>
                
                <h5 class="mt-4">Importes</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Subtotal:</th>
                        <td class="text-end"><?php echo formatMoney($factura->subtotal); ?></td>
                    </tr>
                    <tr>
                        <th>Tasa 0%:</th>
                        <td class="text-end"><?php echo formatMoney($factura->tasa0); ?></td>
                    </tr>
                    <tr>
                        <th>Tasa 16%:</th>
                        <td class="text-end"><?php echo formatMoney($factura->tasa16); ?></td>
                    </tr>
                    <tr>
                        <th>IVA:</th>
                        <td class="text-end"><?php echo formatMoney($factura->iva); ?></td>
                    </tr>
                    <?php if($tipo == 'recibida'): ?>
                    <tr>
                        <th>Retención IVA:</th>
                        <td class="text-end"><?php echo formatMoney($factura->retencion_iva); ?></td>
                    </tr>
                    <tr>
                        <th>Retención ISR:</th>
                        <td class="text-end"><?php echo formatMoney($factura->retencion_isr); ?></td>
                    </tr>
                    <tr>
                        <th>Retención IEPS:</th>
                        <td class="text-end"><?php echo formatMoney($factura->retencion_ieps); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Total:</th>
                        <td class="text-end"><strong><?php echo formatMoney($factura->total); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <h5>Conceptos</h5>
                <div class="p-3 bg-light rounded">
                    <?php echo nl2br($factura->descripcion); ?>
                </div>
            </div>
        </div>
        
        <?php if($factura->estado == 'cancelado' && !empty($factura->motivo_cancelacion)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Factura Cancelada</h5>
                    <p><strong>Fecha de Cancelación:</strong> <?php echo formatDate($factura->fecha_cancelacion); ?></p>
                    <p><strong>Motivo:</strong> <?php echo $factura->motivo_cancelacion; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Función para obtener la descripción de la forma de pago
function getFormaPago($codigo) {
    $formasPago = [
        '01' => 'Efectivo',
        '02' => 'Cheque nominativo',
        '03' => 'Transferencia electrónica',
        '04' => 'Tarjeta de crédito',
        '05' => 'Monedero electrónico',
        '06' => 'Dinero electrónico',
        '08' => 'Vales de despensa',
        '12' => 'Dación en pago',
        '13' => 'Pago por subrogación',
        '14' => 'Pago por consignación',
        '15' => 'Condonación',
        '17' => 'Compensación',
        '23' => 'Novación',
        '24' => 'Confusión',
        '25' => 'Remisión de deuda',
        '26' => 'Prescripción o caducidad',
        '27' => 'A satisfacción del acreedor',
        '28' => 'Tarjeta de débito',
        '29' => 'Tarjeta de servicios',
        '30' => 'Aplicación de anticipos',
        '31' => 'Intermediario pagos',
        '99' => 'Por definir',
    ];
    
    if (isset($formasPago[$codigo])) {
        return $codigo . ' - ' . $formasPago[$codigo];
    }
    
    return $codigo;
}

// Función para obtener la descripción del método de pago
function getMetodoPago($codigo) {
    $metodosPago = [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido',
    ];
    
    if (isset($metodosPago[$codigo])) {
        return $codigo . ' - ' . $metodosPago[$codigo];
    }
    
    return $codigo;
}

include_once __DIR__ . '/../../includes/footer.php';
?>