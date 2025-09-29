<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['results'=>[],'pagination'=>['more'=>false]], JSON_UNESCAPED_UNICODE);
  exit;
}

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$db = new Database();
$sql = 'SELECT id, razon_social, rfc
        FROM clientes
        WHERE estatus = "activo"';
$params = [];

if ($q !== '') {
  $sql .= ' AND (razon_social LIKE :q OR rfc LIKE :q)';
  $params[':q'] = '%'.$q.'%';
}
$sql .= ' ORDER BY razon_social LIMIT :lim OFFSET :off';

$db->query($sql);
foreach ($params as $k=>$v) $db->bind($k,$v);
$db->bind(':lim', $perPage, PDO::PARAM_INT);
$db->bind(':off', $offset, PDO::PARAM_INT);

$list = $db->resultSet();
$results = [];
foreach ($list as $row) {
  $results[] = [
    'id' => (int)$row->id,
    'text' => $row->razon_social . ' (' . $row->rfc . ')'
  ];
}

echo json_encode(['results'=>$results, 'pagination'=>['more'=>count($results)===$perPage]], JSON_UNESCAPED_UNICODE);