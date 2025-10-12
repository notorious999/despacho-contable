<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT . '/modulos/usuarios/login.php'); }

$tipo       = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'emitida'; // emitida | recibida
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';
$desde      = isset($_GET['desde']) ? sanitize($_GET['desde']) : '';
$hasta      = isset($_GET['hasta']) ? sanitize($_GET['hasta']) : '';
$mes        = isset($_GET['mes']) ? sanitize($_GET['mes']) : '';
$busqueda   = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$estado     = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$tipos      = isset($_GET['tipos']) ? $_GET['tipos'] : [];
if (!is_array($tipos)) $tipos = [];

$db = new Database();

if ($tipo === 'emitida') {
    $sql = 'SELECT e.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Emitidas e
            LEFT JOIN Clientes c ON c.id = e.cliente_id
            WHERE 1=1';
    $params = [];
    if ($cliente_id !== '') { $sql .= ' AND e.cliente_id = :cliente_id'; $params[':cliente_id'] = $cliente_id; }
    if ($desde !== '')      { $sql .= ' AND e.fecha_emision >= :desde';  $params[':desde'] = $desde; }
    if ($hasta !== '')      { $sql .= ' AND e.fecha_emision <= :hasta';  $params[':hasta'] = $hasta; }
    if ($mes !== '')        { $sql .= ' AND DATE_FORMAT(e.fecha_emision, "%Y-%m") = :mes'; $params[':mes'] = $mes; }
    if ($busqueda !== '') {
        $sql .= ' AND (e.folio_interno LIKE :q OR e.folio_fiscal LIKE :q OR e.nombre_receptor LIKE :q OR e.rfc_receptor LIKE :q OR e.descripcion LIKE :q OR e.tipo_comprobante LIKE :q OR e.estado_sat LIKE :q OR e.estatus_cancelacion_sat LIKE :q)';
        $params[':q'] = '%'.$busqueda.'%';
    }
    if ($estado !== '') {
        $sql .= ' AND (e.estado = :estado OR e.estado_sat = :estadoSat)';
        $params[':estado'] = $estado;
        $params[':estadoSat'] = ($estado === 'vigente' ? 'Vigente' : ($estado === 'cancelado' ? 'Cancelado' : $estado));
    }
    if (!empty($tipos)) {
        $sql .= ' AND e.tipo_comprobante IN (' . implode(',', array_map(fn($t)=>$db->dbh->quote($t), $tipos)) . ')';
    }
    $sql .= ' ORDER BY e.fecha_emision ASC';
    $db->query($sql);
    foreach ($params as $k=>$v) $db->bind($k,$v);
    $rows = $db->resultSet();

    $headers = [
        'Tipo','Folio','Folio Fiscal','Fecha Emisión','Receptor','RFC Receptor', 'Descripcion',
        'Forma Pago','Método Pago',
        'Subtotal','Tasa 0% (Base)','Tasa 16% (Base)','IVA (Importe)','IEPS (Importe)','ISR (Importe)', 'IVA (Retencion)', 'IEPS (Retencion)', 'ISR (Retencion)','Total','Estado'
    ];
    $data = [];
    foreach ($rows as $f) {
        $estadoMostrar = !empty($f->estatus_cancelacion_sat) ? 'Cancelado' : (!empty($f->estado_sat) ? $f->estado_sat : ($f->estado ?? ''));
        $data[] = [
            (string)($f->tipo_comprobante ?? ''),
            (string)($f->folio_interno ?? ''),
            (string)($f->folio_fiscal ?? ''),
            (string)($f->fecha_emision ?? ''),
            (string)($f->nombre_receptor ?? ''),
            (string)($f->rfc_receptor ?? ''),
            (string)($f->descripcion ?? ''),
            (string)($f->forma_pago ?? ''),
            (string)($f->metodo_pago ?? ''),
            number_format((float)($f->tasa0_base ?? 0), 2),
            number_format((float)($f->tasa0_base ?? 0), 2),
            number_format((float)($f->tasa16_base ?? 0), 2),
            number_format((float)($f->iva_importe ?? 0), 2),
            number_format((float)($f->ieps_importe ?? 0), 2),
            number_format((float)($f->isr_importe ?? 0), 2),
            number_format((float)($f->retencion_iva ?? 0), 2),
            number_format((float)($f->retencion_ieps ?? 0), 2),
            number_format((float)($f->retencion_isr ?? 0), 2),
            number_format((float)($f->total ?? 0), 2),
            (string)$estadoMostrar,
        ];
    }
} else {
    $sql = 'SELECT r.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Recibidas r
            LEFT JOIN Clientes c ON c.id = r.cliente_id
            WHERE 1=1';
    $params = [];
    if ($cliente_id !== '') { $sql .= ' AND r.cliente_id = :cliente_id'; $params[':cliente_id'] = $cliente_id; }
    if ($desde !== '')      { $sql .= ' AND r.fecha_certificacion >= :desde';  $params[':desde'] = $desde; }
    if ($hasta !== '')      { $sql .= ' AND r.fecha_certificacion <= :hasta';  $params[':hasta'] = $hasta; }
    if ($mes !== '')        { $sql .= ' AND DATE_FORMAT(r.fecha_certificacion, "%Y-%m") = :mes'; $params[':mes'] = $mes; }
    if ($busqueda !== '') {
        $sql .= ' AND (r.folio_fiscal LIKE :q OR r.nombre_emisor LIKE :q OR r.rfc_emisor LIKE :q OR r.descripcion LIKE :q OR r.tipo_comprobante LIKE :q OR r.estado_sat LIKE :q OR r.estatus_cancelacion_sat LIKE :q)';
        $params[':q'] = '%'.$busqueda.'%';
    }
    if ($estado !== '') {
        $sql .= ' AND (r.estado = :estado OR r.estado_sat = :estadoSat)';
        $params[':estado'] = $estado;
        $params[':estadoSat'] = ($estado === 'vigente' ? 'Vigente' : ($estado === 'cancelado' ? 'Cancelado' : $estado));
    }
    if (!empty($tipos)) {
        $sql .= ' AND r.tipo_comprobante IN (' . implode(',', array_map(fn($t)=>$db->dbh->quote($t), $tipos)) . ')';
    }
    $sql .= ' ORDER BY r.fecha_certificacion ASC';
    $db->query($sql);
    foreach ($params as $k=>$v) $db->bind($k,$v);
    $rows = $db->resultSet();

    $headers = [
        'Tipo','Folio Fiscal','Fecha Certificación','Emisor','RFC Emisor', 'Descripcion',
        'Forma Pago','Método Pago',
        'Subtotal','Tasa 0% (Base)','Tasa 16% (Base)','IVA (Importe)','IEPS (Importe)','ISR (Importe)', 'IVA (Retencion)', 'IEPS (Retencion)', 'ISR (Retencion)','Total','Estado'
    ];
    $data = [];
    foreach ($rows as $f) {
        $estadoMostrar = !empty($f->estatus_cancelacion_sat) ? 'Cancelado' : (!empty($f->estado_sat) ? $f->estado_sat : ($f->estado ?? ''));
        $data[] = [
            (string)($f->tipo_comprobante ?? ''),
            (string)($f->folio_fiscal ?? ''),
            (string)($f->fecha_certificacion ?? ''),
            (string)($f->nombre_emisor ?? ''),
            (string)($f->rfc_emisor ?? ''),
            (string)($f->descripcion ?? ''),
            (string)($f->forma_pago ?? ''),
            (string)($f->metodo_pago ?? ''),
            number_format((float)($f->tasa0_base ?? 0), 2),
            number_format((float)($f->tasa0_base ?? 0), 2),
            number_format((float)($f->tasa16_base ?? 0), 2),
            number_format((float)($f->iva_importe ?? 0), 2),
            number_format((float)($f->ieps_importe ?? 0), 2),
            number_format((float)($f->isr_importe ?? 0), 2),
            number_format((float)($f->retencion_iva ?? 0), 2),
            number_format((float)($f->retencion_ieps ?? 0), 2),
            number_format((float)($f->retencion_isr ?? 0), 2),
            number_format((float)($f->total ?? 0), 2),
            (string)$estadoMostrar,
        ];
    }
}

// Exportar como CSV con BOM para Excel
$filename = 'reporte_' . $tipo . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo chr(0xEF).chr(0xBB).chr(0xBF); // BOM UTF-8

$fh = fopen('php://output', 'w');
fputcsv($fh, $headers);
foreach ($data as $row) {
    fputcsv($fh, $row);
}
fclose($fh);
exit;