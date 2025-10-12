<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sat_consulta.php';

header('Content-Type: application/json; charset=utf-8');

function sat_log(string $msg): void {
    $file = __DIR__ . '/sat.log';
    @file_put_contents($file, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}

try {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL no está habilitado en PHP.');
    }

    $tipo = $_POST['tipo'] ?? '';
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!in_array($tipo, ['emitida', 'recibida'], true) || $id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }

    $db = new Database();

    if ($tipo === 'emitida') {
        // Emisor/Receptor y totales de la tabla
        $db->query('SELECT e.id, e.folio_fiscal, e.total, e.total_raw, e.rfc_emisor, e.rfc_receptor
                    FROM CFDIs_Emitidas e WHERE e.id = :id');
        $db->bind(':id', $id);
        $row = $db->single();
        if (!$row) throw new RuntimeException('Factura emitida no encontrada');

        // Si tienes RFC_PROPIO, puedes usarlo como respaldo si rfc_emisor es NULL:
        $re = strtoupper(trim((string)($row->rfc_emisor ?: (defined('RFC_PROPIO') ? RFC_PROPIO : ''))));
        $rr = strtoupper(trim((string)$row->rfc_receptor));
        $uuid = (string)$row->folio_fiscal;
        $totalRaw = (string)($row->total_raw ?? '');
        $totalNum = (string)$row->total;

    } else {
        $db->query('SELECT r.id, r.folio_fiscal, r.total, r.total_raw, r.rfc_emisor
                    FROM CFDIs_Recibidas r WHERE r.id = :id');
        $db->bind(':id', $id);
        $row = $db->single();
        if (!$row) throw new RuntimeException('Factura recibida no encontrada');

        $re = strtoupper(trim((string)$row->rfc_emisor));
        $rr = strtoupper(trim((string)(defined('RFC_PROPIO') ? RFC_PROPIO : '')));
        $uuid = (string)$row->folio_fiscal;
        $totalRaw = (string)($row->total_raw ?? '');
        $totalNum = (string)$row->total;
    }

    // Normaliza total (si no hay total_raw, usa total)
    $tt = sat_normalizeTotal($totalRaw !== '' ? $totalRaw : $totalNum);
    $expr = sat_buildExpresion($re, $rr, $tt, $uuid);
    sat_log("REQ tipo=$tipo id=$id re=$re rr=$rr tt=$tt uuid=$uuid");

    $resp = sat_consultaSoapPost($expr, false);
    sat_log("RESP tipo=$tipo id=$id estado=" . ($resp['estado'] ?? 'N/D') . " codigo=" . ($resp['codigoEstatus'] ?? 'N/D') . " estatusCancelacion=" . ($resp['estatusCancelacion'] ?? 'N/D'));

    // Deriva el estado solo con estatusCancelacion (tu regla)
    $estatusCancelacion = $resp['estatusCancelacion'] ?? null;
    $estadoDerivado = (!empty($estatusCancelacion)) ? 'Cancelado' : 'Vigente';

    // Guarda cache en BD con el estado derivado
    if ($tipo === 'emitida') {
        $db->query('UPDATE CFDIs_Emitidas
                    SET estado_sat = :estado, codigo_estatus_sat = :codigo,
                        es_cancelable_sat = :esc, estatus_cancelacion_sat = :ec,
                        fecha_consulta_sat = NOW()
                    WHERE id = :id');
    } else {
        $db->query('UPDATE CFDIs_Recibidas
                    SET estado_sat = :estado, codigo_estatus_sat = :codigo,
                        es_cancelable_sat = :esc, estatus_cancelacion_sat = :ec,
                        fecha_consulta_sat = NOW()
                    WHERE id = :id');
    }
    $db->bind(':estado', $estadoDerivado);
    $db->bind(':codigo', $resp['codigoEstatus'] ?? null);
    $db->bind(':esc',    $resp['esCancelable'] ?? null);
    $db->bind(':ec',     $estatusCancelacion);
    $db->bind(':id',     $id);
    $db->execute();

    echo json_encode([
        'success' => true,
        'estado' => $estadoDerivado, // devolvemos el derivado
        'codigoEstatus' => $resp['codigoEstatus'] ?? null,
        'esCancelable' => $resp['esCancelable'] ?? null,
        'estatusCancelacion' => $estatusCancelacion,
    ]);
} catch (Throwable $e) {
    sat_log('ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}