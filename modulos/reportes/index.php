<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$tipo       = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'emitida'; // emitida | recibida
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';
$mes        = isset($_GET['mes']) ? sanitize($_GET['mes']) : '';
$busqueda   = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$estado     = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';

$db = new Database();

// Clientes
$db->query('SELECT id, razon_social, rfc FROM Clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $db->resultSet();

// Meses disponibles
$mesQuery = ($tipo === 'emitida')
    ? 'SELECT DISTINCT DATE_FORMAT(fecha_emision, "%Y-%m") as mes, DATE_FORMAT(fecha_emision, "%M %Y") as mes_nombre FROM CFDIs_Emitidas ORDER BY mes DESC'
    : 'SELECT DISTINCT DATE_FORMAT(fecha_certificacion, "%Y-%m") as mes, DATE_FORMAT(fecha_certificacion, "%M %Y") as mes_nombre FROM CFDIs_Recibidas ORDER BY mes DESC';
$db->query($mesQuery);
$meses_disponibles = $db->resultSet();

// Cargar facturas según filtros
if ($tipo === 'emitida') {
    $sql = 'SELECT e.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Emitidas e
            LEFT JOIN Clientes c ON c.id = e.cliente_id
            WHERE 1=1';
    $params = [];
    if ($cliente_id !== '') { $sql .= ' AND e.cliente_id = :cliente_id'; $params[':cliente_id'] = $cliente_id; }
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
    $sql .= ' ORDER BY e.fecha_emision ASC';
    $db->query($sql);
    foreach ($params as $k=>$v) $db->bind($k,$v);
    $facturas = $db->resultSet();

    // Totales
    $totales = [
        'subtotal'=>0,'tasa0_base'=>0,'tasa16_base'=>0,'iva_importe'=>0,'ieps_importe'=>0,'isr_importe'=>0,
        'retencion_iva'=>0,'retencion_ieps'=>0,'retencion_isr'=>0,'total'=>0
    ];
    foreach ($facturas as $f) {
        if (!isset($f->estado) || ($f->estado ?? '') === 'vigente') {
            $totales['subtotal']        += (float)($f->subtotal ?? 0);
            $totales['tasa0_base']      += (float)($f->tasa0_base ?? 0);
            $totales['tasa16_base']     += (float)($f->tasa16_base ?? 0);
            $totales['iva_importe']     += (float)($f->iva_importe ?? 0);
            $totales['ieps_importe']    += (float)($f->ieps_importe ?? 0);
            $totales['isr_importe']     += (float)($f->isr_importe ?? 0);
            $totales['retencion_iva']   += (float)($f->retencion_iva ?? 0);
            $totales['retencion_ieps']  += (float)($f->retencion_ieps ?? 0);
            $totales['retencion_isr']   += (float)($f->retencion_isr ?? 0);
            $totales['total']           += (float)($f->total ?? 0);
        }
    }
} else {
    $sql = 'SELECT r.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Recibidas r
            LEFT JOIN Clientes c ON c.id = r.cliente_id
            WHERE 1=1';
    $params = [];
    if ($cliente_id !== '') { $sql .= ' AND r.cliente_id = :cliente_id'; $params[':cliente_id'] = $cliente_id; }
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
    $sql .= ' ORDER BY r.fecha_certificacion ASC';
    $db->query($sql);
    foreach ($params as $k=>$v) $db->bind($k,$v);
    $facturas = $db->resultSet();

    $totales = [
        'subtotal'=>0,'tasa0_base'=>0,'tasa16_base'=>0,'iva_importe'=>0,'ieps_importe'=>0,'isr_importe'=>0,
        'retencion_iva'=>0,'retencion_ieps'=>0,'retencion_isr'=>0,'total'=>0
    ];
    foreach ($facturas as $f) {
        if (!isset($f->estado) || ($f->estado ?? '') === 'vigente') {
            $totales['subtotal']        += (float)($f->subtotal ?? 0);
            $totales['tasa0_base']      += (float)($f->tasa0_base ?? 0);
            $totales['tasa16_base']     += (float)($f->tasa16_base ?? 0);
            $totales['iva_importe']     += (float)($f->iva_importe ?? 0);
            $totales['ieps_importe']    += (float)($f->ieps_importe ?? 0);
            $totales['isr_importe']     += (float)($f->isr_importe ?? 0);
            $totales['retencion_iva']   += (float)($f->retencion_iva ?? 0);
            $totales['retencion_ieps']  += (float)($f->retencion_ieps ?? 0);
            $totales['retencion_isr']   += (float)($f->retencion_isr ?? 0);
            $totales['total']           += (float)($f->total ?? 0);
        }
    }
}

// Helpers locales
if (!function_exists('satEstadoBadgeClass')) {
    function satEstadoBadgeClass(?string $estado): string {
        $e = strtolower((string)$estado);
        if ($e === 'vigente') return 'bg-success';
        if ($e === 'cancelado') return 'bg-danger';
        if ($e === 'no encontrado') return 'bg-warning';
        return 'bg-secondary';
    }
}
if (!function_exists('getFormaPago')) {
    function getFormaPago($c){ $map=['01'=>'Efectivo','02'=>'Cheque nominativo','03'=>'Transferencia electrónica','04'=>'Tarjeta de crédito','28'=>'Tarjeta de débito','29'=>'Tarjeta de servicios','99'=>'Por definir']; return isset($map[$c]) ? "$c - ".$map[$c] : (string)$c; }
}
if (!function_exists('getMetodoPago')) {
    function getMetodoPago($c){ $map=['PUE'=>'Pago en una sola exhibición','PPD'=>'Pago en parcialidades o diferido']; return isset($map[$c]) ? "$c - ".$map[$c] : (string)$c; }
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-6">
    <h2>Reportes de Facturas</h2>
    <p class="lead mb-0"><?php echo ($tipo === 'emitida') ? 'Facturas Emitidas' : 'Facturas Recibidas'; ?></p>
  </div>
  <div class="col-md-6 text-end">
    <a href="<?php echo URL_ROOT; ?>/index.php" class="btn btn-secondary me-2">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
    <a href="cargar_xml.php" class="btn btn-primary me-2">
      <i class="fas fa-upload"></i> Cargar XML
    </a>
    <button id="btn-actualizar-sat" class="btn btn-outline-secondary me-2" title="Consultar al SAT el estado de todos los CFDI del listado actual">
      <i class="fas fa-sync"></i> Consultar estados (cancelados)
    </button>
    <button id="exportarExcel" class="btn btn-success">
      <i class="fas fa-file-excel"></i> Exportar a Excel (.xlsx)
    </button>
  </div>
</div>

<!-- Progreso SAT masivo -->
<div id="sat-progress" class="alert alert-info d-none" role="alert">
  <div class="d-flex align-items-center">
    <div class="flex-grow-1">
      <div class="progress">
        <div id="sat-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%">0%</div>
      </div>
      <small id="sat-progress-text" class="text-muted">Pendiente...</small>
    </div>
    <button id="sat-cancel" class="btn btn-sm btn-link">Cancelar</button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs">
      <li class="nav-item">
        <a class="nav-link <?php echo ($tipo==='emitida'?'active':''); ?>" href="?tipo=emitida">
          <i class="fas fa-file-invoice-dollar"></i> Emitidas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo ($tipo==='recibida'?'active':''); ?>" href="?tipo=recibida">
          <i class="fas fa-file-invoice"></i> Recibidas
        </a>
      </li>
    </ul>
  </div>
  <div class="card-body">

    <?php flash('mensaje'); ?>

    <!-- Filtros -->
    <form class="row g-3 mb-4">
      <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>">

      <div class="col-md-3">
        <label for="cliente_id" class="form-label">Cliente</label>
        <select id="cliente_id" name="cliente_id" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($clientes as $c): ?>
          <option value="<?php echo (int)$c->id; ?>" <?php echo ($cliente_id == $c->id ? 'selected' : ''); ?>>
            <?php echo htmlspecialchars($c->razon_social . ' (' . $c->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label for="mes" class="form-label">Mes</label>
        <select id="mes" name="mes" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($meses_disponibles as $m): ?>
          <option value="<?php echo htmlspecialchars($m->mes, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($mes === $m->mes ? 'selected' : ''); ?>>
            <?php echo ucfirst($m->mes_nombre); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label for="busqueda" class="form-label">Buscar</label>
        <input type="text" id="busqueda" name="busqueda" class="form-control" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>" placeholder="RFC, nombre, folio...">
      </div>

      <div class="col-md-2">
        <label for="estado" class="form-label">Estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="vigente" <?php echo ($estado==='vigente'?'selected':''); ?>>Vigente</option>
          <option value="cancelado" <?php echo ($estado==='cancelado'?'selected':''); ?>>Cancelado</option>
        </select>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">
          <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="?tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary">
          <i class="fas fa-times"></i> Limpiar
        </a>
      </div>
    </form>

    <!-- Resumen de Totales -->
    <div class="card mb-4">
      <div class="card-header bg-light"><h5 class="mb-0">Resumen de Totales</h5></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">Subtotal</h6><h5 class="mb-0"><?php echo formatMoney($totales['subtotal']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">Tasa 0% (Base)</h6><h5 class="mb-0"><?php echo formatMoney($totales['tasa0_base']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">Tasa 16% (Base)</h6><h5 class="mb-0"><?php echo formatMoney($totales['tasa16_base']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">IVA (Importe)</h6><h5 class="mb-0"><?php echo formatMoney($totales['iva_importe']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">IEPS (Importe)</h6><h5 class="mb-0"><?php echo formatMoney($totales['ieps_importe']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-light"><div class="card-body text-center p-2"><h6 class="mb-1">ISR (Importe)</h6><h5 class="mb-0"><?php echo formatMoney($totales['isr_importe']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-warning text-white"><div class="card-body text-center p-2"><h6 class="mb-1">IVA Retención</h6><h5 class="mb-0"><?php echo formatMoney($totales['retencion_iva']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-warning text-white"><div class="card-body text-center p-2"><h6 class="mb-1">IEPS Retención</h6><h5 class="mb-0"><?php echo formatMoney($totales['retencion_ieps']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-warning text-white"><div class="card-body text-center p-2"><h6 class="mb-1">ISR Retención</h6><h5 class="mb-0"><?php echo formatMoney($totales['retencion_isr']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-primary text-white"><div class="card-body text-center p-2"><h6 class="mb-1">Total</h6><h5 class="mb-0"><?php echo formatMoney($totales['total']); ?></h5></div></div></div>
          <div class="col-md-2"><div class="card bg-info text-white"><div class="card-body text-center p-2"><h6 class="mb-1">Facturas</h6><h5 class="mb-0"><?php echo count($facturas); ?></h5></div></div></div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <?php if (count($facturas) > 0): ?>
      <div class="table-responsive">
        <table id="tabla-facturas" class="table table-striped table-bordered" width="100%">
          <thead>
            <tr>
              <?php if ($tipo === 'emitida'): ?>
                <th>Tipo</th>
                <th>Folio</th>
                <th>Folio Fiscal</th>
                <th>Fecha Emisión</th>
                <th>Receptor</th>
                <th>RFC Receptor</th>
              <?php else: ?>
                <th>Tipo</th>
                <th>Folio Fiscal</th>
                <th>Fecha Certificación</th>
                <th>Emisor</th>
                <th>RFC Emisor</th>
              <?php endif; ?>
              <th>Forma Pago</th>
              <th>Método Pago</th>
              <th class="text-end">Subtotal</th>
              <th class="text-end">Tasa 0% (Base)</th>
              <th class="text-end">Tasa 16% (Base)</th>
              <th class="text-end">IVA (Importe)</th>
              <th class="text-end">IEPS (Importe)</th>
              <th class="text-end">ISR (Importe)</th>
              <th class="text-end">IVA Retención</th>
              <th class="text-end">IEPS Retención</th>
              <th class="text-end">ISR Retención</th>
              <th class="text-end">Total</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($facturas as $f): ?>
            <tr class="<?php echo (($f->estado ?? '') === 'cancelado') ? 'table-danger' : ''; ?>">
              <td data-search="<?php echo htmlspecialchars($f->tipo_comprobante ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php
                  if (function_exists('getTipoComprobanteBadge')) {
                    echo getTipoComprobanteBadge($f->tipo_comprobante ?? '');
                  } else {
                    echo htmlspecialchars($f->tipo_comprobante ?? '', ENT_QUOTES, 'UTF-8');
                  }
                ?>
              </td>

              <?php if ($tipo === 'emitida'): ?>
                <td><?php echo htmlspecialchars($f->folio_interno ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td title="<?php echo htmlspecialchars($f->folio_fiscal ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(substr((string)$f->folio_fiscal, 0, 10) . '...', ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td><?php echo formatDate($f->fecha_emision ?? ''); ?></td>
                <td><?php echo htmlspecialchars($f->nombre_receptor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($f->rfc_receptor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <?php else: ?>
                <td title="<?php echo htmlspecialchars($f->folio_fiscal ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars(substr((string)$f->folio_fiscal, 0, 10) . '...', ENT_QUOTES, 'UTF-8'); ?>
                </td>
                <td><?php echo formatDate($f->fecha_certificacion ?? ''); ?></td>
                <td><?php echo htmlspecialchars($f->nombre_emisor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($f->rfc_emisor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <?php endif; ?>

              <td><?php echo htmlspecialchars(getFormaPago($f->forma_pago ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(getMetodoPago($f->metodo_pago ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

              <td class="text-end" data-order="<?php echo (float)($f->subtotal ?? 0); ?>"><?php echo formatMoney($f->subtotal ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->tasa0_base ?? 0); ?>"><?php echo formatMoney($f->tasa0_base ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->tasa16_base ?? 0); ?>"><?php echo formatMoney($f->tasa16_base ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->iva_importe ?? 0); ?>"><?php echo formatMoney($f->iva_importe ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->ieps_importe ?? 0); ?>"><?php echo formatMoney($f->ieps_importe ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->isr_importe ?? 0); ?>"><?php echo formatMoney($f->isr_importe ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->retencion_iva ?? 0); ?>"><?php echo formatMoney($f->retencion_iva ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->retencion_ieps ?? 0); ?>"><?php echo formatMoney($f->retencion_ieps ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->retencion_isr ?? 0); ?>"><?php echo formatMoney($f->retencion_isr ?? 0); ?></td>
              <td class="text-end" data-order="<?php echo (float)($f->total ?? 0); ?>"><?php echo formatMoney($f->total ?? 0); ?></td>

              <td class="estado-sat-cell">
                <?php
                  $estadoMostrar = !empty($f->estatus_cancelacion_sat) ? 'Cancelado' : (!empty($f->estado_sat) ? $f->estado_sat : ($f->estado ?? ''));
                  $detalle = [];
                  if (!empty($f->codigo_estatus_sat)) $detalle[] = 'Código: ' . $f->codigo_estatus_sat;
                  if (!empty($f->es_cancelable_sat)) $detalle[] = 'EsCancelable: ' . $f->es_cancelable_sat;
                  if (!empty($f->estatus_cancelacion_sat)) $detalle[] = 'EstatusCanc: ' . $f->estatus_cancelacion_sat;
                  if (!empty($f->fecha_consulta_sat)) $detalle[] = 'Consulta: ' . $f->fecha_consulta_sat;
                  $title = implode(' | ', $detalle);
                ?>
                <span class="badge estado-sat-badge <?php echo satEstadoBadgeClass($estadoMostrar); ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($estadoMostrar ?: 'N/D', ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <button class="btn btn-sm btn-outline-secondary btn-refresh-sat" data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" data-id="<?php echo (int)$f->id; ?>" title="Actualizar estado con SAT">
                  <i class="fas fa-sync"></i>
                </button>
              </td>

              <td class="text-center">
                <a href="ver_factura.php?id=<?php echo (int)$f->id; ?>&tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-info" title="Ver detalle">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if ($tipo === 'emitida' && (($f->estado ?? '') === 'vigente' || empty($f->estado))): ?>
                  <a href="cancelar_factura.php?id=<?php echo (int)$f->id; ?>&tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-danger" title="Marcar como cancelada">
                    <i class="fas fa-ban"></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info"><i class="fas fa-info-circle"></i> No se encontraron facturas con los criterios seleccionados.</div>
    <?php endif; ?>

  </div>
</div>

<script>
// Utilidades DOM
const qs = (s, c)=> (c||document).querySelector(s);
const qsa= (s, c)=> Array.prototype.slice.call((c||document).querySelectorAll(s));

// Badge estado
function setBadge(cell, estado, detail) {
  const badge = cell.querySelector('.estado-sat-badge');
  if (!badge) return;
  badge.textContent = estado || 'N/D';
  badge.classList.remove('bg-success','bg-danger','bg-secondary','bg-warning','bg-info','bg-primary');
  let cls = 'bg-secondary';
  const e = String(estado||'').toLowerCase();
  if (e === 'vigente') cls = 'bg-success';
  else if (e === 'cancelado') cls = 'bg-danger';
  else if (e.indexOf('no encontrado') >= 0) cls = 'bg-warning';
  badge.classList.add(cls);
  if (detail) badge.title = detail;
}

// POST a SAT
async function postSAT(tipo, id) {
  const data = new URLSearchParams({ tipo:String(tipo), id:String(id) });
  const resp = await fetch('api_sat_estado.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8' },
    body: data.toString(),
  });
  const json = await resp.json().catch(()=>null);
  if (!resp.ok || !json || json.success !== true) {
    const msg = (json && json.message) ? json.message : (resp.status + ' ' + resp.statusText);
    throw new Error(msg);
  }
  return json;
}

// Refresh por fila
document.addEventListener('click', async (ev)=>{
  const btn = ev.target.closest('.btn-refresh-sat');
  if (!btn) return;
  ev.preventDefault();
  const tipo = btn.getAttribute('data-tipo');
  const id   = btn.getAttribute('data-id');
  const cell = btn.closest('td');
  const prev = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const r = await postSAT(tipo, id);
    const estadoDerivado = (r && r.estatusCancelacion) ? 'Cancelado' : (r.estado || 'Vigente');
    const detalle = [
      r.codigoEstatus ? ('Código: ' + r.codigoEstatus) : '',
      r.esCancelable ? ('EsCancelable: ' + r.esCancelable) : '',
      r.estatusCancelacion ? ('EstatusCanc: ' + r.estatusCancelacion) : '',
      r.fechaConsulta ? ('Consulta: ' + r.fechaConsulta) : ''
    ].filter(Boolean).join(' | ');
    setBadge(cell, estadoDerivado, detalle);
  } catch (e) {
    alert('Error SAT: ' + (e && e.message ? e.message : 'Desconocido'));
  } finally {
    btn.disabled = false;
    btn.innerHTML = prev;
  }
});

// SAT masivo con progreso
let cancelBulk = false;
const btnBulk = qs('#btn-actualizar-sat');
const barWrap = qs('#sat-progress');
const bar = qs('#sat-progress-bar');
const barTxt = qs('#sat-progress-text');
const btnCancel = qs('#sat-cancel');

if (btnCancel) btnCancel.addEventListener('click', (e)=>{ e.preventDefault(); cancelBulk = true; });

if (btnBulk) {
  btnBulk.addEventListener('click', async ()=>{
    const btns = qsa('#tabla-facturas .btn-refresh-sat');
    if (!btns.length) { alert('No hay registros para actualizar.'); return; }
    cancelBulk = false;
    barWrap.classList.remove('d-none');
    const prev = btnBulk.innerHTML;
    btnBulk.disabled = true;
    btnBulk.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';

    let total = btns.length, done = 0, ok = 0, err = 0;
    const concurrency = 2;
    let i = 0, active = 0;

    function updateUI() {
      const pct = Math.round((done/total)*100);
      bar.style.width = pct + '%';
      bar.textContent = pct + '%';
      barTxt.textContent = 'Procesados: ' + done + '/' + total + ' | OK: ' + ok + ' | Errores: ' + err;
    }
    function finish() {
      btnBulk.disabled = false;
      btnBulk.innerHTML = prev;
      setTimeout(()=>barWrap.classList.add('d-none'), 600);
    }
    async function runOne(btn) {
      active++;
      const tipo = btn.getAttribute('data-tipo');
      const id   = btn.getAttribute('data-id');
      const cell = btn.closest('td');
      try {
        const r = await postSAT(tipo, id);
        const estadoDerivado = (r && r.estatusCancelacion) ? 'Cancelado' : (r.estado || 'Vigente');
        const detalle = [
          r.codigoEstatus ? ('Código: ' + r.codigoEstatus) : '',
          r.esCancelable ? ('EsCancelable: ' + r.esCancelable) : '',
          r.estatusCancelacion ? ('EstatusCanc: ' + r.estatusCancelacion) : '',
          r.fechaConsulta ? ('Consulta: ' + r.fechaConsulta) : ''
        ].filter(Boolean).join(' | ');
        setBadge(cell, estadoDerivado, detalle);
        ok++;
      } catch(e) {
        err++;
      } finally {
        done++; active--; updateUI(); pump();
      }
    }
    function pump() {
      if (cancelBulk) { barTxt.textContent = 'Cancelado por el usuario.'; finish(); return; }
      while (i < total && active < concurrency) runOne(btns[i++]);
      if (done >= total) finish();
    }
    updateUI();
    pump();
  });
}

// Exportar a Excel .xlsx
const exportBtn = qs('#exportarExcel');
if (exportBtn) {
  exportBtn.addEventListener('click', ()=>{
    const url = new URL('exportar_xlsx.php', window.location.href);
    url.searchParams.set('tipo', '<?php echo $tipo; ?>');
    url.searchParams.set('cliente_id', '<?php echo $cliente_id; ?>');
    url.searchParams.set('mes', '<?php echo $mes; ?>');
    url.searchParams.set('busqueda', '<?php echo $busqueda; ?>');
    url.searchParams.set('estado', '<?php echo $estado; ?>');
    window.location = url.toString();
  });
}

// DataTables (opcional)
(function initDT(){
  try {
    if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
      jQuery(function($){
        $('#tabla-facturas').DataTable({
          language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
          pageLength: 25,
          responsive: true,
          order: []
        });
      });
    }
  } catch(e) { console.warn('DataTables init error:', e); }
})();
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>