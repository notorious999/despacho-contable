<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();
$service = new RecibosService();

// Filtros
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$q     = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';
$cliente_id = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? (int)sanitize($_GET['cliente_id']) : null;
$estado = isset($_GET['estado']) ? strtolower(trim(sanitize($_GET['estado']))) : '';
if (!in_array($estado, ['', 'pendiente', 'vencido', 'pagado'], true)) $estado = '';
$vencimiento_new = isset($_GET['vencimiento']) && $_GET['vencimiento'] !== '' ? sanitize($_GET['vencimiento']) : ''; // solo para generación

// Generar recibos del periodo (usa vencimiento_new si viene)
if (isset($_GET['generar']) && $_GET['generar'] === '1') {
    $generados = $service->generarRecibosPeriodo($desde, $hasta, $cliente_id, $vencimiento_new ?: null);
    flash('mensaje', "Generados $generados recibo(s) para el periodo.", $generados > 0 ? 'alert alert-success' : 'alert alert-info');
    $redir = URL_ROOT . "/modulos/recibos/index.php?desde=$desde&hasta=$hasta";
    if ($q) $redir .= '&q=' . urlencode($q);
    if ($cliente_id) $redir .= '&cliente_id=' . $cliente_id;
    if ($estado) $redir .= '&estado=' . urlencode($estado);
    if ($vencimiento_new) $redir .= '&vencimiento=' . urlencode($vencimiento_new);
    redirect($redir);
    exit;
}

// Clientes para selector
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $db->resultSet();

// Consulta principal
$sql = 'SELECT r.*, c.razon_social, c.rfc
        FROM recibos r
        JOIN clientes c ON c.id = r.cliente_id
        WHERE 1=1';
$params = [];

if ($desde !== '') { $sql .= ' AND r.periodo_inicio >= :desde'; $params[':desde'] = $desde; }
if ($hasta !== '') { $sql .= ' AND r.periodo_fin <= :hasta';   $params[':hasta'] = $hasta; }
if ($cliente_id)   { $sql .= ' AND r.cliente_id = :cid';       $params[':cid'] = $cliente_id; }
if ($q !== '') {
    $sql .= ' AND (c.razon_social LIKE :q OR c.rfc LIKE :q OR r.concepto LIKE :q)';
    $params[':q'] = '%'.$q.'%';
}
if ($estado !== '') {
    $sql .= ' AND r.estado = :estado';
    $params[':estado'] = $estado;
}
$sql .= ' ORDER BY r.periodo_inicio DESC, c.razon_social ASC';

$db->query($sql);
foreach ($params as $k=>$v) $db->bind($k,$v);
$recibos = $db->resultSet();

// Totales
$tot_monto = 0; $tot_pagado = 0; $tot_saldo = 0;
foreach ($recibos as $r) {
    $tot_monto  += (float)$r->monto;
    $tot_pagado += (float)$r->monto_pagado;
    $tot_saldo  += ((float)$r->monto - (float)$r->monto_pagado);
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-6">
    <h2>Recibos</h2>
    
  </div>
  <div class="col-md-6 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/clientes/index.php" class="btn btn-secondary me-2">
      <i class="fas fa-users"></i> Clientes
    </a>
    <a href="?desde=<?php echo $desde; ?>&hasta=<?php echo $hasta; ?>&cliente_id=<?php echo (int)$cliente_id; ?>&estado=<?php echo urlencode($estado); ?>&vencimiento=<?php echo urlencode($vencimiento_new); ?>&generar=1" class="btn btn-primary"
       onclick="return confirm('¿Generar recibos para el periodo seleccionado?');">
      <i class="fas fa-magic"></i> Generar recibos del periodo
    </a>
  </div>
</div>

<?php flash('mensaje'); ?>

<form class="card mb-3" method="get">
  <div class="card-body row g-3">
    <div class="col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Cliente</label>
      <select name="cliente_id" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($clientes as $c): ?>
        <option value="<?php echo (int)$c->id; ?>" <?php echo ($cliente_id == $c->id ? 'selected' : ''); ?>>
          <?php echo htmlspecialchars($c->razon_social . ' (' . $c->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="" <?php echo $estado===''?'selected':''; ?>>Todos</option>
        <option value="pendiente" <?php echo $estado==='pendiente'?'selected':''; ?>>Pendiente</option>
        <option value="vencido"   <?php echo $estado==='vencido'  ?'selected':''; ?>>Vencido</option>
        <option value="pagado"    <?php echo $estado==='pagado'   ?'selected':''; ?>>Pagado</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" class="form-control" placeholder="Cliente, RFC o concepto" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">Establecer Vencimiento</label>
      <input type="date" name="vencimiento" class="form-control" value="<?php echo htmlspecialchars($vencimiento_new, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary"><i class="fas fa-filter"></i> Aplicar</button>
    </div>
  </div>
</form>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="card bg-light"><div class="card-body text-center p-2">
      <div class="small text-muted">Total Monto</div>
      <div class="h4 mb-0"><?php echo formatMoney($tot_monto); ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card bg-success text-white"><div class="card-body text-center p-2">
      <div class="small">Total Pagado</div>
      <div class="h4 mb-0"><?php echo formatMoney($tot_pagado); ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card bg-warning text-white"><div class="card-body text-center p-2">
      <div class="small">Saldo Pendiente</div>
      <div class="h4 mb-0"><?php echo formatMoney($tot_saldo); ?></div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>RFC</th>
          <th>Concepto</th>
          <th>Periodo</th>
          <th>Vence</th>
          <th class="text-end">Monto</th>
          <th class="text-end">Pagado</th>
          <th class="text-end">Saldo</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($recibos)): foreach ($recibos as $r):
          $saldo = ((float)$r->monto - (float)$r->monto_pagado);
          $estadoRow = strtolower($r->estado ?? 'pendiente');
          $badge = 'bg-secondary';
          if ($estadoRow === 'pagado') $badge = 'bg-success';
          elseif ($estadoRow === 'pendiente') $badge = 'bg-warning';
          elseif ($estadoRow === 'vencido') $badge = 'bg-danger';
        ?>
        <tr>
          <td><?php echo htmlspecialchars($r->razon_social, ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r->rfc, ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($r->concepto ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo formatDate($r->periodo_inicio) . ' — ' . formatDate($r->periodo_fin); ?></td>
          <td><?php echo $r->fecha_vencimiento ? formatDate($r->fecha_vencimiento) : '-'; ?></td>
          <td class="text-end"><?php echo formatMoney((float)$r->monto); ?></td>
          <td class="text-end"><?php echo formatMoney((float)$r->monto_pagado); ?></td>
          <td class="text-end fw-bold <?php echo ($saldo>0 ? 'text-danger' : 'text-success'); ?>"><?php echo formatMoney($saldo); ?></td>
          <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($estadoRow); ?></span></td>
          <td class="text-center">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalPago"
              data-id="<?php echo (int)$r->id; ?>"
              data-cliente="<?php echo htmlspecialchars($r->razon_social, ENT_QUOTES, 'UTF-8'); ?>"
              data-saldo="<?php echo number_format($saldo,2,'.',''); ?>">
              <i class="fas fa-dollar-sign"></i> Pago
            </button>
            <a class="btn btn-sm btn-outline-secondary"
               href="<?php echo URL_ROOT; ?>/modulos/recibos/pagos.php?recibo_id=<?php echo (int)$r->id; ?>">
              <i class="fas fa-list"></i> Historial
            </a>
            <!--
            <button class="btn btn-sm btn-outline-primary"
              data-bs-toggle="modal" data-bs-target="#modalVencimiento"
              data-id="<?php echo (int)$r->id; ?>"
              data-venc="<?php echo htmlspecialchars($r->fecha_vencimiento ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              <i class="fas fa-calendar-alt"></i> Vencimiento
            </button>
            !-->
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="10" class="text-center text-muted">Sin recibos en el periodo.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Pago (sin cambios salvo el JS con clamp) -->
<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="<?php echo URL_ROOT; ?>/modulos/recibos/registrar_pago.php">
      <div class="modal-header">
        <h5 class="modal-title">Registrar pago</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="recibo_id" id="pago_recibo_id">
        <div class="mb-2"><strong id="pago_cliente"></strong></div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Fecha de pago</label>
            <input type="date" name="fecha_pago" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" min="0" name="monto" id="pago_monto" class="form-control" required>
            <div class="form-text">Saldo sugerido: <span id="pago_saldo"></span></div>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-6">
            <label class="form-label">Método</label>
            <select name="metodo" class="form-select">
              <option value="">No especificado</option>
              <option>Transferencia</option>
              <option>Efectivo</option>
              <option>Tarjeta</option>
              <option>Cheque</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Referencia</label>
            <input type="text" name="referencia" class="form-control">
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Observaciones</label>
          <textarea name="observaciones" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar pago</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Modal Vencimiento -->
<div class="modal fade" id="modalVencimiento" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" action="<?php echo URL_ROOT; ?>/modulos/recibos/actualizar_vencimiento.php">
      <div class="modal-header">
        <h5 class="modal-title">Actualizar fecha de vencimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="recibo_id" id="venc_recibo_id">
        <div class="mb-2">
          <label class="form-label">Fecha de vencimiento</label>
          <input type="date" name="fecha_vencimiento" id="venc_fecha" class="form-control">
          <div class="form-text">Déjalo vacío para quitar el vencimiento.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </form>
  </div></div>
</div>

<script>
const modalPago = document.getElementById('modalPago');
modalPago.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  const id = btn.getAttribute('data-id');
  const cliente = btn.getAttribute('data-cliente');
  const saldo = parseFloat(btn.getAttribute('data-saldo') || '0') || 0;
  document.getElementById('pago_recibo_id').value = id;
  document.getElementById('pago_cliente').textContent = cliente;
  document.getElementById('pago_saldo').textContent = new Intl.NumberFormat('es-MX', {style:'currency', currency:'MXN'}).format(saldo);

  const input = document.getElementById('pago_monto');
  input.value = saldo.toFixed(2);
  input.setAttribute('max', saldo.toFixed(2));
  const handler = function() {
    let v = parseFloat(this.value || '0');
    if (v > saldo) v = saldo;
    if (v < 0) v = 0;
    this.value = (isNaN(v) ? 0 : v).toFixed(2);
  };
  input.removeEventListener('input', handler);
  input.addEventListener('input', handler);
});

const modalVenc = document.getElementById('modalVencimiento');
modalVenc.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  const id = btn.getAttribute('data-id');
  const venc = btn.getAttribute('data-venc') || '';
  document.getElementById('venc_recibo_id').value = id;
  document.getElementById('venc_fecha').value = venc;
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>