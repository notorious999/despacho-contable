<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sat_consulta.php';

if (!isLoggedIn()) { die('No autorizado'); }
if (!defined('RFC_PROPIO') || !RFC_PROPIO) { die('Config RFC_PROPIO no definido en config/config.php'); }

$tipo = $_GET['tipo'] ?? '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!in_array($tipo, ['emitida','recibida'], true) || $id <= 0) die('Parámetros inválidos');

$db = new Database();

if ($tipo === 'emitida') {
    $db->query('SELECT e.id, e.folio_fiscal, e.total, e.total_raw, e.rfc_emisor, e.rfc_receptor
                FROM CFDIs_Emitidas e WHERE e.id = :id');
    $db->bind(':id', $id);
    $row = $db->single();
    if (!$row) die('Factura emitida no encontrada');
    $re = strtoupper(trim((string)($row->rfc_emisor ?: RFC_PROPIO)));
    $rr = strtoupper(trim((string)$row->rfc_receptor));
} else {
    $db->query('SELECT r.id, r.folio_fiscal, r.total, r.total_raw, r.rfc_emisor
                FROM CFDIs_Recibidas r WHERE r.id = :id');
    $db->bind(':id', $id);
    $row = $db->single();
    if (!$row) die('Factura recibida no encontrada');
    $re = strtoupper(trim((string)$row->rfc_emisor));
    $rr = strtoupper(trim((string)RFC_PROPIO));
}

$uuid = (string)$row->folio_fiscal;
$tt   = sat_normalizeTotal((string)($row->total_raw ?: $row->total));
$expr = sat_buildExpresion($re, $rr, $tt, $uuid);

echo "<pre>";
echo "Tipo: $tipo\nID: $id\nRFC Emisor (re): $re\nRFC Receptor (rr): $rr\nUUID: $uuid\n";
echo "Total raw: ".($row->total_raw ?? '')."\nTotal num: ".$row->total."\nTT normalizado: $tt\n";
echo "Expresión: $expr\n\n";
try {
    $resp = sat_consultaSoapPost($expr, false);
    print_r($resp);
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}
echo "</pre>";