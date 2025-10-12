<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$q = trim((string)($_GET['term'] ?? ''));
$db = new Database();
$sql = 'SELECT id, razon_social, rfc FROM Clientes WHERE estatus="activo"';
$params = [];
if ($q !== '') {
    $sql .= ' AND (razon_social LIKE :q OR rfc LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY razon_social LIMIT 20';
$db->query($sql);
foreach ($params as $k=>$v) $db->bind($k,$v);
$results = $db->resultSet();
$out = [];
foreach ($results as $row) {
    $out[] = [
        'id' => $row->id,
        'label' => $row->razon_social . ' (' . $row->rfc . ')',
        'value' => $row->razon_social . ' (' . $row->rfc . ')'
    ];
}
echo json_encode($out);