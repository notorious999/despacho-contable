<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// Filtros mínimos
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$q     = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';

// Solo recibos externos (cliente_id IS NULL), activos
$sql = 'SELECT r.*
        FROM recibos r
        WHERE r.estatus = "activo" AND r.cliente_id IS NULL';
$params = [];
if ($desde !== '') {
  $sql .= ' AND r.periodo_inicio >= :desde';
  $params[':desde'] = $desde;
}
if ($hasta !== '') {
  $sql .= ' AND r.periodo_fin <= :hasta';
  $params[':hasta'] = $hasta;
}
if ($q !== '') {
  $sql .= ' AND (r.externo_nombre LIKE :q OR r.externo_rfc LIKE :q OR r.concepto LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY r.periodo_inicio DESC, r.externo_nombre ASC';

$db->query($sql);
foreach ($params as $k => $v) $db->bind($k, $v);
$recibos = $db->resultSet();

// Totales
$tot_monto = 0;
$tot_pagado = 0;
foreach ($recibos as $r) {
  $tot_monto  += (float)$r->monto;
  $tot_pagado += (float)$r->monto_pagado;
}

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-4 align-items-center">
  <div class="col-md-6">
    <h2 class="mb-0">Recibos Externos</h2>
  </div>
  <div class="col-md-6 text-end">

    <div class="btn-group me-2" role="group" aria-label="Acciones Principales">
      <a href="?desde=<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>&hasta=<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>&generar=1"
        class="btn btn-primary" onclick="return confirm('¿Generar recibos para el periodo seleccionado?');">
        <i class="fas fa-magic"></i> Generar del Periodo
      </a>

      <div class="btn-group" role="group">
        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-plus"></i> Nuevo
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/nuevo_servicio.php">
              <i class="fas fa-file-circle-plus me-2""></i>Nuevo Recibo (Clientes)
          </a></li>
          <li><a class=" dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/nuevo_externo.php">
                <i class="fas fa-file-circle-plus me-2"></i>Nuevo Recibo (Externo)
            </a></li>
          <li>
            <hr class="dropdown-divider">
          </li>
          <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/pago_adelantado.php">
              <i class="fas fa-forward me-2"></i>Pago Adelantado
            </a></li>
        </ul>
      </div>
    </div>

    <div class="btn-group" role="group">
      <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-eye"></i> Ver
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/clientes/index.php">
            <i class="fas fa-users me-2"></i>Clientes
          </a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/servicios.php">
            <i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Clientes)
          </a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php">
            <i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Externos)
          </a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/pagos_adelantados.php">
            <i class="fas fa-forward"></i>Pagos Adelantados
          </a></li>
      </ul>
    </div>

  </div>
</div>

<?php flash('mensaje'); ?>

<form class="card mb-3" method="get">
  <div class="card-body row g-3">
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" class="form-control" placeholder="Nombre/Razón social, RFC o concepto" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary"><i class="fas fa-filter"></i> Aplicar</button>
    </div>
  </div>
</form>

<div class="row mb-3">
  <div class="col-md-6">
    <div class="card bg-light">
      <div class="card-body text-center p-2">
        <div class="small text-muted">Monto Total</div>
        <div class="h4 mb-0"><?php echo formatMoney($tot_monto); ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card bg-success text-white">
      <div class="card-body text-center p-2">
        <div class="small">Total Pagado</div>
        <div class="h4 mb-0"><?php echo formatMoney($tot_pagado); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Nombre/Razón social</th>
          <th>RFC</th>
          <th>Fecha</th>
          <th>Concepto</th>
          <th class="text-end">Monto</th>
          <th class="text-end">Pagado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($recibos)): foreach ($recibos as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r->externo_nombre ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r->externo_rfc ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo formatDate($r->periodo_inicio); ?></td>
              <td><?php echo htmlspecialchars($r->concepto ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="text-end"><?php echo formatMoney((float)$r->monto); ?></td>
              <td class="text-end"><?php echo formatMoney((float)$r->monto_pagado); ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-primary mb-1"
                  href="<?php echo URL_ROOT; ?>/modulos/recibos/editar_externo.php?id=<?php echo (int)$r->id; ?>">
                  <i class="fas fa-edit"></i> Editar
                </a>
                <a class="btn btn-sm btn-outline-success mb-1"
                  href="<?php echo URL_ROOT; ?>/modulos/recibos/imprimir_externo.php?recibo_id=<?php echo (int)$r->id; ?>" target="_blank">
                  <i class="fas fa-print"></i> Imprimir
                </a>
                <a class="btn btn-sm btn-outline-danger mb-1"
                  href="<?php echo URL_ROOT; ?>/modulos/recibos/cancelar_externo.php?id=<?php echo (int)$r->id; ?>"
                  onclick="return confirm('¿Estás seguro de que quieres cancelar este recibo?');">
                  <i class="fas fa-ban"></i> Cancelar
                </a>
              </td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="7" class="text-center text-muted">Sin recibos externos en el periodo.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>