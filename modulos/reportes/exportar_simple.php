<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Inicializar variables
$tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'emitida';
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';
$mes = isset($_GET['mes']) ? sanitize($_GET['mes']) : '';
$busqueda = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';

// Inicializar la base de datos
$database = new Database();

// Obtener datos del cliente (si se especificó)
$cliente_nombre = "Todos los clientes";
if (!empty($cliente_id)) {
    $database->query('SELECT razon_social FROM Clientes WHERE id = :id');
    $database->bind(':id', $cliente_id);
    $cliente = $database->single();
    if ($cliente) {
        $cliente_nombre = $cliente->razon_social;
    }
}

// Consultar facturas según el tipo
if ($tipo == 'emitida') {
    // Construir consulta base
    $sql = 'SELECT e.*, c.razon_social as cliente_nombre 
            FROM CFDIs_Emitidas e 
            LEFT JOIN Clientes c ON e.cliente_id = c.id 
            WHERE 1=1';
    
    // Agregar filtros
    $params = [];
    
    if (!empty($cliente_id)) {
        $sql .= ' AND e.cliente_id = :cliente_id';
        $params[':cliente_id'] = $cliente_id;
    }
    
    if (!empty($mes)) {
        $sql .= ' AND DATE_FORMAT(e.fecha_emision, "%Y-%m") = :mes';
        $params[':mes'] = $mes;
    }
    
    if (!empty($busqueda)) {
        $sql .= ' AND (e.folio_interno LIKE :busqueda OR e.folio_fiscal LIKE :busqueda OR e.nombre_receptor LIKE :busqueda OR e.rfc_receptor LIKE :busqueda OR e.descripcion LIKE :busqueda)';
        $params[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if (!empty($estado)) {
        $sql .= ' AND e.estado = :estado';
        $params[':estado'] = $estado;
    }
    
    $sql .= ' ORDER BY e.fecha_emision ASC';
    
    // Ejecutar consulta
    $database->query($sql);
    
    // Bind params
    foreach ($params as $param => $value) {
        $database->bind($param, $value);
    }
    
    $facturas = $database->resultSet();
    
    // Crear CSV para facturas emitidas
    $filename = 'Reporte_Facturas_Emitidas_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['REPORTE DE FACTURAS EMITIDAS']);
    fputcsv($output, ['Cliente:', $cliente_nombre]);
    fputcsv($output, ['Periodo:', !empty($mes) ? $mes : 'Todos los meses']);
    fputcsv($output, ['Fecha de generación:', date('d/m/Y H:i:s')]);
    fputcsv($output, []); // Línea en blanco
    
    // Encabezados de la tabla
    fputcsv($output, [
        'Folio', 'Folio Fiscal', 'Fecha Emisión', 'Receptor', 'RFC Receptor',
        'Forma Pago', 'Método Pago', 'Subtotal', 'Tasa 0%', 'Tasa 16%',
        'IVA', 'Total', 'Estado', 'Cliente'
    ]);
    
    // Datos
    $totalSubtotal = 0;
    $totalTasa0 = 0;
    $totalTasa16 = 0;
    $totalIva = 0;
    $totalMonto = 0;
    
    foreach ($facturas as $factura) {
        fputcsv($output, [
            $factura->folio_interno,
            $factura->folio_fiscal,
            date('d/m/Y', strtotime($factura->fecha_emision)),
            $factura->nombre_receptor,
            $factura->rfc_receptor,
            getFormaPago($factura->forma_pago),
            getMetodoPago($factura->metodo_pago),
            $factura->subtotal,
            $factura->tasa0,
            $factura->tasa16,
            $factura->iva,
            $factura->total,
            $factura->estado,
            $factura->cliente_nombre
        ]);
        
        // Sumar totales
        if ($factura->estado == 'vigente') {
            $totalSubtotal += $factura->subtotal;
            $totalTasa0 += $factura->tasa0;
            $totalTasa16 += $factura->tasa16;
            $totalIva += $factura->iva;
            $totalMonto += $factura->total;
        }
    }
    
    // Línea de totales
    fputcsv($output, [
        'TOTALES:', '', '', '', '', '', '',
        $totalSubtotal, $totalTasa0, $totalTasa16, $totalIva, $totalMonto
    ]);
    
} else {
    // Facturas recibidas
    // Construir consulta base
    $sql = 'SELECT r.*, c.razon_social as cliente_nombre 
            FROM CFDIs_Recibidas r 
            LEFT JOIN Clientes c ON r.cliente_id = c.id 
            WHERE 1=1';
    
    // Agregar filtros
    $params = [];
    
    if (!empty($cliente_id)) {
        $sql .= ' AND r.cliente_id = :cliente_id';
        $params[':cliente_id'] = $cliente_id;
    }
    
    if (!empty($mes)) {
        $sql .= ' AND DATE_FORMAT(r.fecha_certificacion, "%Y-%m") = :mes';
        $params[':mes'] = $mes;
    }
    
    if (!empty($busqueda)) {
        $sql .= ' AND (r.folio_fiscal LIKE :busqueda OR r.nombre_emisor LIKE :busqueda OR r.rfc_emisor LIKE :busqueda OR r.descripcion LIKE :busqueda)';
        $params[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if (!empty($estado)) {
        $sql .= ' AND r.estado = :estado';
        $params[':estado'] = $estado;
    }
    
    $sql .= ' ORDER BY r.fecha_certificacion ASC';
    
    // Ejecutar consulta
    $database->query($sql);
    
    // Bind params
    foreach ($params as $param => $value) {
        $database->bind($param, $value);
    }
    
    $facturas = $database->resultSet();
    
    // Crear CSV para facturas recibidas
    $filename = 'Reporte_Facturas_Recibidas_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['REPORTE DE FACTURAS RECIBIDAS']);
    fputcsv($output, ['Cliente:', $cliente_nombre]);
    fputcsv($output, ['Periodo:', !empty($mes) ? $mes : 'Todos los meses']);
    fputcsv($output, ['Fecha de generación:', date('d/m/Y H:i:s')]);
    fputcsv($output, []); // Línea en blanco
    
    // Encabezados de la tabla
    fputcsv($output, [
        'Folio Fiscal', 'Fecha Certificación', 'Emisor', 'RFC Emisor',
        'Forma Pago', 'Método Pago', 'Subtotal', 'IVA', 'Ret. IVA',
        'Ret. ISR', 'Ret. IEPS', 'Total', 'Estado', 'Cliente', 'UUID Relacionado'
    ]);
    
    // Datos
    $totalSubtotal = 0;
    $totalIva = 0;
    $totalRetIVA = 0;
    $totalRetISR = 0;
    $totalRetIEPS = 0;
    $totalMonto = 0;
    
    foreach ($facturas as $factura) {
        fputcsv($output, [
            $factura->folio_fiscal,
            date('d/m/Y', strtotime($factura->fecha_certificacion)),
            $factura->nombre_emisor,
            $factura->rfc_emisor,
            getFormaPago($factura->forma_pago),
            getMetodoPago($factura->metodo_pago),
            $factura->subtotal,
            $factura->iva,
            $factura->retencion_iva,
            $factura->retencion_isr,
            $factura->retencion_ieps,
            $factura->total,
            $factura->estado,
            $factura->cliente_nombre,
            $factura->uuid_relacionado
        ]);
        
        // Sumar totales
        if ($factura->estado == 'vigente') {
            $totalSubtotal += $factura->subtotal;
            $totalIva += $factura->iva;
            $totalRetIVA += $factura->retencion_iva;
            $totalRetISR += $factura->retencion_isr;
            $totalRetIEPS += $factura->retencion_ieps;
            $totalMonto += $factura->total;
        }
    }
    
    // Línea de totales
    fputcsv($output, [
        'TOTALES:', '', '', '', '', '',
        $totalSubtotal, $totalIva, $totalRetIVA, $totalRetISR, $totalRetIEPS, $totalMonto
    ]);
}

fclose($output);
exit;

// Funciones auxiliares
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
?>