<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// (El código de filtros se mantiene igual)
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$q     = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';
$cliente_id = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? (int)sanitize($_GET['cliente_id']) : null;
$estado = isset($_GET['estado']) ? strtolower(trim(sanitize($_GET['estado']))) : '';
if (!in_array($estado, ['', 'pendiente', 'pagado'], true)) $estado = '';

// Lista de clientes para filtro
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus="activo" ORDER BY razon_social');
$clientes = $db->resultSet();

// --- CONSULTA MODIFICADA ---
// Ahora también obtiene el 'ultimo_pago_id', igual que index.php
$sql = 'SELECT r.*,
               c.razon_social AS razon_social,
               c.rfc          AS rfc,
               (
                 SELECT p.id FROM recibos_pagos p
                 WHERE p.recibo_id = r.id
                 ORDER BY p.fecha_pago DESC, p.id DESC LIMIT 1
               ) AS ultimo_pago_id
        FROM recibos r
        INNER JOIN clientes c ON c.id = r.cliente_id
        WHERE r.estatus = "activo" AND r.tipo = "servicio"';
$params = [];

// (El resto de la consulta y la lógica de filtros se mantiene igual)
if ($desde !== '') {
  $sql .= ' AND r.fecha_creacion >= :desde';
  $params[':desde'] = $desde;
}
if ($hasta !== '') {
  $sql .= ' AND r.fecha_creacion <= :hasta';
  $params[':hasta'] = $hasta;
}
if ($cliente_id) {
  $sql .= ' AND r.cliente_id = :cid';
  $params[':cid'] = $cliente_id;
}
if ($q !== '') {
  $sql .= ' AND (c.razon_social LIKE :q OR c.rfc LIKE :q OR r.concepto LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
if ($estado !== '') {
  $sql .= ' AND r.estado = :estado';
  $params[':estado'] = $estado;
}
$sql .= ' ORDER BY r.fecha_creacion DESC, c.razon_social ASC';


$db->query($sql);
foreach ($params as $k => $v) $db->bind($k, $v);
$recibos = $db->resultSet();

// (La lógica de totales y el header se mantienen igual)
// Totales
$tot_monto = 0;
$tot_pagado = 0;
$tot_saldo = 0;
foreach ($recibos as $r) {
  $tot_monto  += (float)$r->monto;
  $tot_pagado += (float)$r->monto_pagado;
  $tot_saldo  += max(((float)$r->monto - (float)$r->monto_pagado), 0.0);
}


include_once __DIR__ . '/../../includes/header.php';
?>

    <div class="row mb-4 align-items-center">
  <div class="col-md-6">
    <h2 class="mb-0">Recibos de Clientes</h2>
  </div>
  <div class="col-md-6 text-end">

    <div class="btn-group me-2" role="group" aria-label="Acciones Principales">
      <div class="btn-group" role="group">
        <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-plus"></i> Nuevo
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/nuevo_servicio.php">
            <i class="fas fa-file-circle-plus me-2""></i>Nuevo Recibo (Clientes)
          </a></li>
          <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/nuevo_externo.php">
            <i class="fas fa-file-circle-plus me-2"></i>Nuevo Recibo (Externo)
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
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php">
          <i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Clientes)
        </a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php">
          <i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Externos)
        </a></li>
      </ul>
    </div>

  </div>
</div>

<?php flash('mensaje'); ?>

<form class="card mb-3" method="get">
  <div class="card-body row g-3">
    <div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-md-3"><label class="form-label">Cliente</label><select name="cliente_id" class="form-select"><option value="">Todos</option><?php foreach ($clientes as $c): ?><option value="<?php echo (int)$c->id; ?>" <?php echo (!empty($cliente_id) && (int)$cliente_id === (int)$c->id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c->razon_social . ' (' . $c->rfc . ')', ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="" <?php echo $estado === '' ? 'selected' : ''; ?>>Todos</option><option value="pendiente" <?php echo $estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option><option value="pagado" <?php echo $estado === 'pagado'  ? 'selected' : ''; ?>>Pagado</option></select></div>
    <div class="col-md-3"><label class="form-label">Buscar</label><input type="text" name="q" class="form-control" placeholder="Cliente, RFC o concepto" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary"><i class="fas fa-filter"></i> Aplicar</button></div>
  </div>
</form>

<div class="row mb-3">
  <div class="col-md-4"><div class="card bg-light"><div class="card-body text-center p-2"><div class="small text-muted">Monto Total</div><div class="h4 mb-0"><?php echo formatMoney($tot_monto); ?></div></div></div></div>
  <div class="col-md-4"><div class="card bg-success text-white"><div class="card-body text-center p-2"><div class="small">Total Pagado</div><div class="h4 mb-0"><?php echo formatMoney($tot_pagado); ?></div></div></div></div>
  <div class="col-md-4"><div class="card bg-warning text-white"><div class="card-body text-center p-2"><div class="small">Saldo Pendiente</div><div class="h4 mb-0"><?php echo formatMoney($tot_saldo); ?></div></div></div></div>
</div>


<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead><tr><th>Cliente</th><th>Concepto</th><th>Fecha</th><th class="text-end">Monto</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php if (!empty($recibos)): foreach ($recibos as $r):
            $saldo = max(((float)$r->monto - (float)$r->monto_pagado), 0.0);
            $estadoRow = strtolower($r->estado ?? 'pendiente');
            $badge = ($estadoRow === 'pagado') ? 'bg-success' : 'bg-warning';
        ?>
            <tr>
              <td><?php echo htmlspecialchars($r->razon_social ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r->concepto ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo isset($r->fecha_creacion) ? formatDate($r->fecha_creacion) : '-'; ?></td> 
              <td class="text-end"><?php echo formatMoney((float)$r->monto); ?></td>
              <td class="text-end"><?php echo formatMoney((float)$r->monto_pagado); ?></td>
              <td class="text-end fw-bold <?php echo ($saldo > 0 ? 'text-danger' : 'text-success'); ?>"><?php echo formatMoney($saldo); ?></td>
              <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($estadoRow); ?></span></td>
              <td class="text-center">
                <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalPago"
                  data-id="<?php echo (int)$r->id; ?>"
                  data-cliente="<?php echo htmlspecialchars($r->razon_social ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-saldo="<?php echo number_format($saldo, 2, '.', ''); ?>">
                  <i class="fas fa-dollar-sign"></i> Pago
                </button>
                <a class="btn btn-sm btn-outline-secondary mb-1" href="<?php echo URL_ROOT; ?>/modulos/recibos/pagos_servicio.php?recibo_id=<?php echo (int)$r->id; ?>"><i class="fas fa-list"></i> Historial</a>
                
                <a href="editar_servicio.php?id=<?php echo (int)$r->id; ?>" class="btn btn-sm btn-outline-primary mb-1">
                    <i class="fas fa-edit"></i> Editar
                </a>
                
                <?php if ($r->ultimo_pago_id): ?>
                  <a class="btn btn-sm btn-outline-success mb-1"
                    href="<?php echo URL_ROOT; ?>/modulos/recibos/imprimir.php?pago_id=<?php echo (int)$r->ultimo_pago_id; ?>" target="_blank">
                    <i class="fas fa-print"></i> Imprimir
                  </a>
                <?php else: ?>
                  <a class="btn btn-sm btn-outline-primary mb-1"
                    href="<?php echo URL_ROOT; ?>/modulos/recibos/imprimir.php?recibo_id=<?php echo (int)$r->id; ?>" target="_blank">
                    <i class="fas fa-print"></i> Imprimir
                  </a>
                <?php endif; ?>
                

              </td>
            </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="text-center text-muted">No hay recibos de servicios en el periodo.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?php echo URL_ROOT; ?>/modulos/recibos/registrar_pago_servicio.php">
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
              <div class="form-text">Saldo: <span id="pago_saldo"></span></div>
            </div>
          </div>
          <div class="row g-2 mt-1">
            <div class="col-md-6">
              <label class="form-label">Método</label>
              <select name="metodo" class="form-select">
                <option value="">No especificado</option>
                <option>Transferencia</option><option>Efectivo</option><option>Tarjeta</option><option>Cheque</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Referencia</label><input type="text" name="referencia" class="form-control"></div>
          </div>
          <div class="mt-2"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar pago</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Script para el modal de pago
  const modalPago = document.getElementById('modalPago');
  if (modalPago) { // Buena práctica verificar que exista
    modalPago.addEventListener('show.bs.modal', function(event) {
      const btn = event.relatedTarget;
      const id = btn.getAttribute('data-id');
      const cliente = btn.getAttribute('data-cliente');
      const saldo = parseFloat(btn.getAttribute('data-saldo') || '0') || 0;

      // Buscar elementos DENTRO del modal
      const inputReciboId = modalPago.querySelector('#pago_recibo_id');
      const textCliente = modalPago.querySelector('#pago_cliente');
      const textSaldo = modalPago.querySelector('#pago_saldo');
      const inputMonto = modalPago.querySelector('#pago_monto');
      const btnGuardar = modalPago.querySelector('button[type="submit"]'); // Selector más específico

      // Poblar campos
      if(inputReciboId) inputReciboId.value = id;
      if(textCliente) textCliente.textContent = cliente;
      if(textSaldo) textSaldo.textContent = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(saldo);
      
      if(inputMonto) {
        inputMonto.value = saldo.toFixed(2);
        inputMonto.setAttribute('max', saldo.toFixed(2));
        // *** CAMBIO 1: Evitar pagos de 0 ***
        inputMonto.setAttribute('min', '0.01'); // No permitir enviar 0 o menos
      }

      // *** CAMBIO 2: Deshabilitar si el saldo es 0 ***
      if (btnGuardar) {
        if (saldo < 0.01) { // Si el saldo es 0 o céntimos muy bajos
            if(inputMonto) inputMonto.disabled = true;
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fas fa-check"></i> Recibo Pagado';
        } else {
            if(inputMonto) inputMonto.disabled = false;
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar pago';
        }
      }
    });
  }
</script>


<?php include_once __DIR__ . '/../../includes/footer.php'; ?>