<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}


// Filtros
// Filtros
$tipo       = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : 'emitida';
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';
// --- CORRECCIÓN ---
// Establecer el mes actual como rango de fechas por defecto
$desde      = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta      = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
// --- FIN CORRECCIÓN ---

$hastaFiltro = '';
// ... (el resto del código de filtros sigue igual) ...
if ($hasta !== '') {
  // Si el campo en BD es DATETIME/TIMESTAMP
  $hastaFiltro = date('Y-m-d', strtotime($hasta . ' +1 day'));
}
$mes        = isset($_GET['mes']) ? sanitize($_GET['mes']) : '';
$busqueda   = isset($_GET['busqueda']) ? sanitize($_GET['busqueda']) : '';
$estado     = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$tipos      = isset($_GET['tipos']) ? $_GET['tipos'] : [];
if (!is_array($tipos)) $tipos = [];

$db = new Database();

$tipos_cfdi_opciones = [
  'Ingreso' => 'Ingresos',
  'Egreso'  => 'Egresos',
  'Nómina'  => 'Nóminas',
  'Pago'    => 'Pagos'
];

$mesQuery = ($tipo === 'emitida')
  ? 'SELECT DISTINCT DATE_FORMAT(fecha_emision, "%Y-%m") as mes, DATE_FORMAT(fecha_emision, "%M %Y") as mes_nombre FROM CFDIs_Emitidas ORDER BY mes DESC'
  : 'SELECT DISTINCT DATE_FORMAT(fecha_certificacion, "%Y-%m") as mes, DATE_FORMAT(fecha_certificacion, "%M %Y") as mes_nombre FROM CFDIs_Recibidas ORDER BY mes DESC';
$db->query($mesQuery);
$meses_disponibles = $db->resultSet();

if ($tipo === 'emitida') {
  $sql = 'SELECT e.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Emitidas e
            LEFT JOIN Clientes c ON c.id = e.cliente_id
            WHERE 1=1';
  $params = [];
  if ($cliente_id !== '') {
    $sql .= ' AND e.cliente_id = :cliente_id';
    $params[':cliente_id'] = $cliente_id;
  }
  if ($desde !== '') {
    $sql .= ' AND e.fecha_emision >= :desde';
    $params[':desde'] = $desde;
  }
  if ($hastaFiltro !== '') {
    $sql .= ' AND e.fecha_emision < :hasta';  // Para emitidas
    $params[':hasta'] = $hastaFiltro;
  }
  if ($mes !== '') {
    $sql .= ' AND DATE_FORMAT(e.fecha_emision, "%Y-%m") = :mes';
    $params[':mes'] = $mes;
  }
  if ($busqueda !== '') {
    $sql .= ' AND (e.folio_interno LIKE :q OR e.folio_fiscal LIKE :q OR e.nombre_receptor LIKE :q OR e.rfc_receptor LIKE :q OR e.descripcion LIKE :q OR e.tipo_comprobante LIKE :q OR e.estado_sat LIKE :q OR e.estatus_cancelacion_sat LIKE :q)';
    $params[':q'] = '%' . $busqueda . '%';
  }
  if ($estado !== '') {
    $sql .= ' AND (e.estado = :estado OR e.estado_sat = :estadoSat)';
    $params[':estado'] = $estado;
    $params[':estadoSat'] = ($estado === 'vigente' ? 'Vigente' : ($estado === 'cancelado' ? 'Cancelado' : $estado));
  }
  if (!empty($tipos)) {
    $sql .= ' AND e.tipo_comprobante IN (' . implode(',', array_map(fn($t) => $db->dbh->quote($t), $tipos)) . ')';
  }
  $sql .= ' ORDER BY e.fecha_emision ASC';
  $db->query($sql);
  foreach ($params as $k => $v) $db->bind($k, $v);
  $facturas = $db->resultSet();

  $totales = [
    'subtotal' => 0,
    'tasa0_base' => 0,
    'tasa16_base' => 0,
    'iva_importe' => 0,
    'ieps_importe' => 0,
    'isr_importe' => 0,
    'retencion_iva' => 0,
    'retencion_ieps' => 0,
    'retencion_isr' => 0,
    'total' => 0
  ];
  foreach ($facturas as $f) {
    $isCancelado = (strtolower($f->estado_sat ?? '') === 'cancelado' || strtolower($f->estatus_cancelacion_sat ?? '') === 'cancelado');
    if ($isCancelado) continue;
    $factor = (strtolower($f->tipo_comprobante ?? '') === 'egreso') ? -1 : 1;
    $totales['subtotal']        += $factor * (float)($f->subtotal ?? 0);
    $totales['tasa0_base']      += $factor * (float)($f->tasa0_base ?? 0);
    $totales['tasa16_base']     += $factor * (float)($f->tasa16_base ?? 0);
    $totales['iva_importe']     += $factor * (float)($f->iva_importe ?? 0);
    $totales['ieps_importe']    += $factor * (float)($f->ieps_importe ?? 0);
    $totales['isr_importe']     += $factor * (float)($f->isr_importe ?? 0);
    $totales['retencion_iva']   += $factor * (float)($f->retencion_iva ?? 0);
    $totales['retencion_ieps']  += $factor * (float)($f->retencion_ieps ?? 0);
    $totales['retencion_isr']   += $factor * (float)($f->retencion_isr ?? 0);
    $totales['total']           += $factor * (float)($f->total ?? 0);
  }
} else {
  $sql = 'SELECT r.*, c.razon_social AS cliente_nombre
            FROM CFDIs_Recibidas r
            LEFT JOIN Clientes c ON c.id = r.cliente_id
            WHERE 1=1';
  $params = [];
  if ($cliente_id !== '') {
    $sql .= ' AND r.cliente_id = :cliente_id';
    $params[':cliente_id'] = $cliente_id;
  }
  if ($desde !== '') {
    $sql .= ' AND r.fecha_certificacion >= :desde';
    $params[':desde'] = $desde;
  }
  if ($hastaFiltro !== '') {
    $sql .= ' AND r.fecha_certificacion < :hasta';  // Para recibidas
    $params[':hasta'] = $hastaFiltro;
  }
  if ($mes !== '') {
    $sql .= ' AND DATE_FORMAT(r.fecha_certificacion, "%Y-%m") = :mes';
    $params[':mes'] = $mes;
  }
  if ($busqueda !== '') {
    $sql .= ' AND (r.folio_fiscal LIKE :q OR r.nombre_emisor LIKE :q OR r.rfc_emisor LIKE :q OR r.descripcion LIKE :q OR r.tipo_comprobante LIKE :q OR r.estado_sat LIKE :q OR r.estatus_cancelacion_sat LIKE :q)';
    $params[':q'] = '%' . $busqueda . '%';
  }
  if ($estado !== '') {
    $sql .= ' AND (r.estado = :estado OR r.estado_sat = :estadoSat)';
    $params[':estado'] = $estado;
    $params[':estadoSat'] = ($estado === 'vigente' ? 'Vigente' : ($estado === 'cancelado' ? 'Cancelado' : $estado));
  }
  if (!empty($tipos)) {
    $sql .= ' AND r.tipo_comprobante IN (' . implode(',', array_map(fn($t) => $db->dbh->quote($t), $tipos)) . ')';
  }
  $sql .= ' ORDER BY r.fecha_certificacion ASC';
  $db->query($sql);
  foreach ($params as $k => $v) $db->bind($k, $v);
  $facturas = $db->resultSet();

  $totales = [
    'subtotal' => 0,
    'tasa0_base' => 0,
    'tasa16_base' => 0,
    'iva_importe' => 0,
    'ieps_importe' => 0,
    'isr_importe' => 0,
    'retencion_iva' => 0,
    'retencion_ieps' => 0,
    'retencion_isr' => 0,
    'total' => 0
  ];
  foreach ($facturas as $f) {
    $isCancelado = (strtolower($f->estado_sat ?? '') === 'cancelado' || strtolower($f->estatus_cancelacion_sat ?? '') === 'cancelado');
    if ($isCancelado) continue;
    $factor = (strtolower($f->tipo_comprobante ?? '') === 'egreso') ? -1 : 1;
    $totales['subtotal']        += $factor * (float)($f->subtotal ?? 0);
    $totales['tasa0_base']      += $factor * (float)($f->tasa0_base ?? 0);
    $totales['tasa16_base']     += $factor * (float)($f->tasa16_base ?? 0);
    $totales['iva_importe']     += $factor * (float)($f->iva_importe ?? 0);
    $totales['ieps_importe']    += $factor * (float)($f->ieps_importe ?? 0);
    $totales['isr_importe']     += $factor * (float)($f->isr_importe ?? 0);
    $totales['retencion_iva']   += $factor * (float)($f->retencion_iva ?? 0);
    $totales['retencion_ieps']  += $factor * (float)($f->retencion_ieps ?? 0);
    $totales['retencion_isr']   += $factor * (float)($f->retencion_isr ?? 0);
    $totales['total']           += $factor * (float)($f->total ?? 0);
  }
}

if (!function_exists('satEstadoBadgeClass')) {
  function satEstadoBadgeClass(?string $estado): string
  {
    $e = strtolower((string)$estado);
    if ($e === 'vigente') return 'bg-success';
    if ($e === 'cancelado') return 'bg-danger';
    if ($e === 'no encontrado') return 'bg-warning';
    return 'bg-secondary';
  }
}
if (!function_exists('getFormaPago')) {
  function getFormaPago($c)
  {
    $map = ['01' => 'Efectivo', '02' => 'Cheque nominativo', '03' => 'Transferencia electrónica', '04' => 'Tarjeta de crédito', '28' => 'Tarjeta de débito', '29' => 'Tarjeta de servicios', '99' => 'Por definir'];
    return isset($map[$c]) ? "$c - " . $map[$c] : (string)$c;
  }
}
if (!function_exists('getMetodoPago')) {
  function getMetodoPago($c)
  {
    $map = ['PUE' => 'Pago en una sola exhibición', 'PPD' => 'Pago en parcialidades o diferido'];
    return isset($map[$c]) ? "$c - " . $map[$c] : (string)$c;
  }
}

include_once __DIR__ . '/../../includes/header.php';
?>

<style>
    /* 1. Define una altura máxima para el contenedor de la tabla y habilita el scroll vertical */
    .table-responsive {
        max-height: 70vh; /* Usa el 70% de la altura de la pantalla, puedes ajustarlo */
        overflow-y: auto;
    }

    /* 2. Fija los encabezados de la tabla en la parte superior */
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 10; /* Se asegura que el encabezado esté por encima de otros elementos */
        background-color: #ffffff; /* Un color de fondo para que el contenido no se vea a través */
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.15); /* Sombra sutil para separar el encabezado */
    }
</style>

<div class="row mb-4">
  <div class="col-md-6">
    <h2>Reportes de Facturas</h2>
    <p class="lead mb-0"><?php echo ($tipo === 'emitida') ? 'Facturas Emitidas' : 'Facturas Recibidas'; ?></p>
  </div>
  <div class="col-md-6 text-end">
    <!--<a href="<?php echo URL_ROOT; ?>/index.php" class="btn btn-secondary me-2"><i class="fas fa-arrow-left"></i> Volver</a>-->
    <a href="cargar_xml.php" class="btn btn-primary me-2"><i class="fas fa-upload"></i> Cargar XML</a>
    <button id="btn-actualizar-sat" class="btn btn-outline-secondary me-2" title="Consultar al SAT el estado de todos los CFDI del listado actual"><i class="fas fa-sync"></i> Consultar estados (cancelados)</button>
    <button id="exportarExcel" class="btn btn-success"><i class="fas fa-file-excel"></i> Exportar a Excel</button>
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
      <li class="nav-item"><a class="nav-link <?php echo ($tipo === 'emitida' ? 'active' : ''); ?>" href="?tipo=emitida"><i class="fas fa-file-invoice-dollar"></i> Emitidas</a></li>
      <li class="nav-item"><a class="nav-link <?php echo ($tipo === 'recibida' ? 'active' : ''); ?>" href="?tipo=recibida"><i class="fas fa-file-invoice"></i> Recibidas</a></li>
    </ul>
  </div>
  <div class="card-body">

    <?php flash('mensaje'); ?>

    <!-- Filtros -->
    <form class="row g-3 mb-4" method="get" id="form-reportes">
      <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>">

      <!-- Buscador de cliente sin jQuery, JS puro -->
      <div class="col-md-3">
        <label for="cliente_search" class="form-label">Buscar cliente (Razón social o RFC)</label>
        <input type="text" id="cliente_search" class="form-control" placeholder="Escribe para buscar...">
        <div id="cliente_results" class="list-group mt-2" style="max-height:220px; overflow:auto;">
          <div class="list-group-item text-muted small">Escribe para buscar…</div>
        </div>
        <input type="hidden" name="cliente_id" id="cliente_id" value="<?php echo htmlspecialchars($cliente_id, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-text">Haz clic en un resultado para seleccionarlo.</div>
        <div class="input-group mt-2">
          <input type="text" id="cliente_selected_text" class="form-control" placeholder="Ninguno" readonly>
          <button type="button" id="cliente_clear" class="btn btn-outline-danger" title="Quitar selección">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>



      <div class="col-md-2">
        <label for="desde" class="form-label">Desde</label>
        <input type="date" id="desde" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-md-2">
        <label for="hasta" class="form-label">Hasta</label>
        <input type="date" id="hasta" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>">
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
        <label class="form-label">Tipo CFDI</label>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($tipos_cfdi_opciones as $key => $label): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="tipos[]" id="tipo_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo in_array($key, $tipos) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="tipo_<?php echo $key; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-md-2">
        <label for="estado" class="form-label">Estado</label>
        <select id="estado" name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="vigente" <?php echo ($estado === 'vigente' ? 'selected' : ''); ?>>Vigente</option>
          <option value="cancelado" <?php echo ($estado === 'cancelado' ? 'selected' : ''); ?>>Cancelado</option>
        </select>
      </div>

      <div class="col-md-3">
        <label for="busqueda" class="form-label">Buscar</label>
        <input type="text" id="busqueda" name="busqueda" class="form-control" value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>" placeholder="RFC, nombre, folio...">
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="?tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
      </div>
    </form>

    <script>
      (function() {
        const inputSearch = document.getElementById('cliente_search');
        const results = document.getElementById('cliente_results');
        const selText = document.getElementById('cliente_selected_text');
        const selId = document.getElementById('cliente_id');
        const btnClear = document.getElementById('cliente_clear');
        const form = document.getElementById('form-reportes');

        let t = null;

        function debounce(fn, ms) {
          return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), ms);
          };
        }

        async function fetchClientes(term, page = 1) {
          const url = '<?php echo URL_ROOT; ?>/modulos/clientes/buscar.php?q=' + encodeURIComponent(term || '') + '&page=' + page;
          try {
            const r = await fetch(url, {
              headers: {
                'Accept': 'application/json'
              }
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            return data && data.results ? data.results : [];
          } catch (e) {
            console.warn('Error buscando clientes:', e);
            return [];
          }
        }

        function renderResults(items) {
          results.innerHTML = '';
          if (!items.length) {
            results.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
            return;
          }
          items.forEach(it => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = it.text;
            btn.dataset.id = it.id;
            btn.dataset.text = it.text;
            btn.addEventListener('click', () => {
              selId.value = it.id;
              selText.value = it.text;
              results.innerHTML = '<div class="list-group-item small text-success">Seleccionado: ' + it.text + '</div>';
            });
            results.appendChild(btn);
          });
        }

        const doSearch = debounce(async function() {
          const term = inputSearch.value.trim();
          const items = await fetchClientes(term, 1);
          renderResults(items);
        }, 300);

        inputSearch.addEventListener('input', doSearch);

        btnClear.addEventListener('click', () => {
          selId.value = '';
          selText.value = '';
          inputSearch.value = '';
          inputSearch.focus();
          results.innerHTML = '<div class="list-group-item text-muted small">Escribe para buscar…</div>';
        });

        // Si quieres forzar selección antes de enviar el filtro, descomenta:
        // form.addEventListener('submit', (e) => {
        //   if (!selId.value) {
        //     e.preventDefault();
        //     alert('Selecciona un cliente de la lista.');
        //     inputSearch.focus();
        //     return false;
        //   }
        // });

        // Mostrar nombre si el filtro viene con cliente_id
        <?php
        if ($cliente_id !== '') {
          // Busca el nombre del cliente por id
          $nombreCliente = '';
          $db->query('SELECT razon_social, rfc FROM Clientes WHERE id = :id');
          $db->bind(':id', $cliente_id);
          $cl = $db->single();
          if ($cl) $nombreCliente = $cl->razon_social . ' (' . $cl->rfc . ')';
          if ($nombreCliente) {
            echo "selText.value = " . json_encode($nombreCliente) . ";";
          }
        }
        ?>

        // Carga inicial (primeros 20)
        doSearch();
      })();
    </script>

    <div class="card mb-4">
      <div class="card-header bg-light">
        <h5 class="mb-0">Resumen de Totales</h5>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">Subtotal</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['subtotal']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">Tasa 0% (Base)</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['tasa0_base']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">Tasa 16% (Base)</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['tasa16_base']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">IVA (Importe)</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['iva_importe']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">IEPS (Importe)</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['ieps_importe']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-light">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">ISR (Importe)</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['isr_importe']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-warning text-white">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">IVA Retención</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['retencion_iva']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-warning text-white">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">IEPS Retención</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['retencion_ieps']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-warning text-white">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">ISR Retención</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['retencion_isr']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-primary text-white">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">Total</h6>
                <h5 class="mb-0"><?php echo formatMoney($totales['total']); ?></h5>
              </div>
            </div>
          </div>
          <div class="col-md-2">
            <div class="card bg-info text-white">
              <div class="card-body text-center p-2">
                <h6 class="mb-1">Facturas</h6>
                <h5 class="mb-0"><?php echo count($facturas); ?></h5>
              </div>
            </div>
          </div>
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
                <th>Concepto</th>
              <?php else: ?>
                <th>Tipo</th>
                <th>Folio Fiscal</th>
                <th>Fecha Certificación</th>
                <th>Emisor</th>
                <th>RFC Emisor</th>
                <th>Concepto</th>
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
              <?php
              $isCancelado = (strtolower($f->estado_sat ?? '') === 'cancelado' || strtolower($f->estatus_cancelacion_sat ?? '') === 'cancelado');
              $subtotal     = $isCancelado ? 0.00 : (float)($f->subtotal ?? 0);
              $tasa0_base   = $isCancelado ? 0.00 : (float)($f->tasa0_base ?? 0);
              $tasa16_base  = $isCancelado ? 0.00 : (float)($f->tasa16_base ?? 0);
              $iva_importe  = $isCancelado ? 0.00 : (float)($f->iva_importe ?? 0);
              $ieps_importe = $isCancelado ? 0.00 : (float)($f->ieps_importe ?? 0);
              $isr_importe  = $isCancelado ? 0.00 : (float)($f->isr_importe ?? 0);
              $retencion_iva = $isCancelado ? 0.00 : (float)($f->retencion_iva ?? 0);
              $retencion_ieps = $isCancelado ? 0.00 : (float)($f->retencion_ieps ?? 0);
              $retencion_isr = $isCancelado ? 0.00 : (float)($f->retencion_isr ?? 0);
              $total         = $isCancelado ? 0.00 : (float)($f->total ?? 0);
              ?>
              <tr class="<?php echo $isCancelado ? 'table-danger' : ''; ?>">
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
                  <td><?php echo htmlspecialchars($f->descripcion ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <?php else: ?>
                  <td title="<?php echo htmlspecialchars($f->folio_fiscal ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(substr((string)$f->folio_fiscal, 0, 10) . '...', ENT_QUOTES, 'UTF-8'); ?>
                  </td>
                  <td><?php echo formatDate($f->fecha_certificacion ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($f->nombre_emisor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($f->rfc_emisor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars($f->descripcion ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                <?php endif; ?>

                <td><?php echo htmlspecialchars(getFormaPago($f->forma_pago ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars(getMetodoPago($f->metodo_pago ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>

                <td class="text-end" data-order="<?php echo $subtotal; ?>"><?php echo formatMoney($subtotal); ?></td>
                <td class="text-end" data-order="<?php echo $tasa0_base; ?>"><?php echo formatMoney($tasa0_base); ?></td>
                <td class="text-end" data-order="<?php echo $tasa16_base; ?>"><?php echo formatMoney($tasa16_base); ?></td>
                <td class="text-end" data-order="<?php echo $iva_importe; ?>"><?php echo formatMoney($iva_importe); ?></td>
                <td class="text-end" data-order="<?php echo $ieps_importe; ?>"><?php echo formatMoney($ieps_importe); ?></td>
                <td class="text-end" data-order="<?php echo $isr_importe; ?>"><?php echo formatMoney($isr_importe); ?></td>
                <td class="text-end" data-order="<?php echo $retencion_iva; ?>"><?php echo formatMoney($retencion_iva); ?></td>
                <td class="text-end" data-order="<?php echo $retencion_ieps; ?>"><?php echo formatMoney($retencion_ieps); ?></td>
                <td class="text-end" data-order="<?php echo $retencion_isr; ?>"><?php echo formatMoney($retencion_isr); ?></td>
                <td class="text-end" data-order="<?php echo $total; ?>"><?php echo formatMoney($total); ?></td>

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

                  <?php if ((($f->estado ?? '') === 'vigente' || empty($f->estado))): ?>
                    <a href="cancelar_factura.php?id=<?php echo (int)$f->id; ?>&tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-warning" title="Marcar como cancelada">
                      <i class="fas fa-ban"></i>
                    </a>
                  <?php endif; ?>

                  <a href="eliminar_cfdi_individual.php?id=<?php echo (int)$f->id; ?>&tipo=<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>&cliente_id=<?php echo (int)$cliente_id; ?>"
                    class="btn btn-danger btn-sm"
                    title="Eliminar permanentemente"
                    onclick="return confirm('¿Estás seguro de que quieres eliminar permanentemente este CFDI? Esta acción no se puede deshacer.');">
                    <i class="fas fa-trash"></i>
                  </a>
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

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  // Buscador rápido jQuery en la tabla
  $('#buscadorRapido').on('input', function() {
    let filtro = $(this).val().toLowerCase();
    $('#tabla-facturas tbody tr').each(function() {
      let texto = $(this).text().toLowerCase();
      $(this).toggle(texto.indexOf(filtro) !== -1);
    });
  });

  // Utilidades DOM
  const qs = (s, c) => (c || document).querySelector(s);
  const qsa = (s, c) => Array.prototype.slice.call((c || document).querySelectorAll(s));

  // Badge estado
  function setBadge(cell, estado, detail) {
    const badge = cell.querySelector('.estado-sat-badge');
    if (!badge) return;
    badge.textContent = estado || 'N/D';
    badge.classList.remove('bg-success', 'bg-danger', 'bg-secondary', 'bg-warning', 'bg-info', 'bg-primary');
    let cls = 'bg-secondary';
    const e = String(estado || '').toLowerCase();
    if (e === 'vigente') cls = 'bg-success';
    else if (e === 'cancelado') cls = 'bg-danger';
    else if (e.indexOf('no encontrado') >= 0) cls = 'bg-warning';
    badge.classList.add(cls);
    if (detail) badge.title = detail;
  }

  // POST a SAT
  async function postSAT(tipo, id) {
    const data = new URLSearchParams({
      tipo: String(tipo),
      id: String(id)
    });
    const resp = await fetch('api_sat_estado.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
      },
      body: data.toString(),
    });
    const json = await resp.json().catch(() => null);
    if (!resp.ok || !json || json.success !== true) {
      const msg = (json && json.message) ? json.message : (resp.status + ' ' + resp.statusText);
      throw new Error(msg);
    }
    return json;
  }

  // Refresh por fila
  document.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.btn-refresh-sat');
    if (!btn) return;
    ev.preventDefault();
    const tipo = btn.getAttribute('data-tipo');
    const id = btn.getAttribute('data-id');
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

  if (btnCancel) btnCancel.addEventListener('click', (e) => {
    e.preventDefault();
    cancelBulk = true;
  });

  if (btnBulk) {
    btnBulk.addEventListener('click', async () => {
      const btns = qsa('#tabla-facturas .btn-refresh-sat');
      if (!btns.length) {
        alert('No hay registros para actualizar.');
        return;
      }
      cancelBulk = false;
      barWrap.classList.remove('d-none');
      const prev = btnBulk.innerHTML;
      btnBulk.disabled = true;
      btnBulk.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';

      let total = btns.length,
        done = 0,
        ok = 0,
        err = 0;
      const concurrency = 2;
      let i = 0,
        active = 0;

      function updateUI() {
        const pct = Math.round((done / total) * 100);
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        barTxt.textContent = 'Procesados: ' + done + '/' + total + ' | OK: ' + ok + ' | Errores: ' + err;
      }

      function finish() {
        btnBulk.disabled = false;
        btnBulk.innerHTML = prev;
        setTimeout(() => barWrap.classList.add('d-none'), 600);
      }
      async function runOne(btn) {
        active++;
        const tipo = btn.getAttribute('data-tipo');
        const id = btn.getAttribute('data-id');
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
        } catch (e) {
          err++;
        } finally {
          done++;
          active--;
          updateUI();
          pump();
        }
      }

      function pump() {
        if (cancelBulk) {
          barTxt.textContent = 'Cancelado por el usuario.';
          finish();
          return;
        }
        while (i < total && active < concurrency) runOne(btns[i++]);
        if (done >= total) finish();
      }
      updateUI();
      pump();
    });
  }

  // Exportar a Excel .xlsx
  const exportBtn = document.getElementById('exportarExcel');
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const facturas = <?php echo json_encode($facturas); ?>;
      const tipoReporte = '<?php echo $tipo; ?>';

      if (!facturas || facturas.length === 0) {
        alert('No hay datos para exportar.');
        return;
      }

      let headers = [];
      // ... (la sección de las cabeceras/headers no cambia y puede quedar igual)
      if (tipoReporte === 'emitida') {
        headers = [
          'Tipo', 'Folio', 'Folio Fiscal', 'Fecha Emisión', 'Receptor', 'RFC Receptor', 'Descripcion',
          'Forma Pago', 'Método Pago', 'Subtotal', 'Tasa 0% (Base)', 'Tasa 16% (Base)',
          'IVA (Importe)', 'IEPS (Importe)', 'ISR (Importe)', 'IVA (Retencion)',
          'IEPS (Retencion)', 'ISR (Retencion)', 'Total', 'Estado'
        ];
      } else { // recibida
        headers = [
          'Tipo', 'Folio Fiscal', 'Fecha Certificación', 'Emisor', 'RFC Emisor', 'Descripcion',
          'Forma Pago', 'Método Pago', 'Subtotal', 'Tasa 0% (Base)', 'Tasa 16% (Base)',
          'IVA (Importe)', 'IEPS (Importe)', 'ISR (Importe)', 'IVA (Retencion)',
          'IEPS (Retencion)', 'ISR (Retencion)', 'Total', 'Estado'
        ];
      }

      // 3. Preparar los datos creando OBJETOS DE CELDA para los números
      const data = facturas.map(f => {
        const estadoMostrar = f.estatus_cancelacion_sat ? 'Cancelado' : (f.estado_sat || f.estado || '');

        // Función para crear el objeto de celda numérica
        const createNumericCell = (val) => {
          const number = parseFloat(val || 0);
          // v = valor (el número puro)
          // t = tipo ('n' para número)
          // z = formato (similar a los formatos de celda de Excel)
          return {
            v: number,
            t: 'n',
            z: '#,##0.00'
          };
        };

        if (tipoReporte === 'emitida') {
          return [
            f.tipo_comprobante || '',
            f.folio_interno || '',
            f.folio_fiscal || '',
            f.fecha_emision || '',
            f.nombre_receptor || '',
            f.rfc_receptor || '',
            f.descripcion || '',
            f.forma_pago || '',
            f.metodo_pago || '',
            // Aplicamos la nueva función a todas las celdas de cantidad
            createNumericCell(f.subtotal),
            createNumericCell(f.tasa0_base),
            createNumericCell(f.tasa16_base),
            createNumericCell(f.iva_importe),
            createNumericCell(f.ieps_importe),
            createNumericCell(f.isr_importe),
            createNumericCell(f.retencion_iva),
            createNumericCell(f.retencion_ieps),
            createNumericCell(f.retencion_isr),
            createNumericCell(f.total),
            estadoMostrar
          ];
        } else { // recibida
          return [
            f.tipo_comprobante || '',
            f.folio_fiscal || '',
            f.fecha_certificacion || '',
            f.nombre_emisor || '',
            f.rfc_emisor || '',
            f.descripcion || '',
            f.forma_pago || '',
            f.metodo_pago || '',
            // Aplicamos la nueva función a todas las celdas de cantidad
            createNumericCell(f.subtotal),
            createNumericCell(f.tasa0_base),
            createNumericCell(f.tasa16_base),
            createNumericCell(f.iva_importe),
            createNumericCell(f.ieps_importe),
            createNumericCell(f.isr_importe),
            createNumericCell(f.retencion_iva),
            createNumericCell(f.retencion_ieps),
            createNumericCell(f.retencion_isr),
            createNumericCell(f.total),
            estadoMostrar
          ];
        }
      });

      // ... (el resto del código para crear y descargar el XLSX no cambia)
      const worksheet = XLSX.utils.aoa_to_sheet([headers, ...data]);
      const workbook = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(workbook, worksheet, 'Reporte');

      const colWidths = headers.map((_, i) => ({
        wch: 20
      })); // Ancho fijo o dinámico
      worksheet['!cols'] = colWidths;

      const today = new Date();
      const dateStr = today.getFullYear() + ('0' + (today.getMonth() + 1)).slice(-2) + ('0' + today.getDate()).slice(-2);
      const timeStr = ('0' + today.getHours()).slice(-2) + ('0' + today.getMinutes()).slice(-2) + ('0' + today.getSeconds()).slice(-2);
      const filename = `reporte_${tipoReporte}_${dateStr}_${timeStr}.xlsx`;

      XLSX.writeFile(workbook, filename);
    });
  }

  // DataTables (opcional)
  (function initDT() {
    try {
      if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
        jQuery(function($) {
          $('#tabla-facturas').DataTable({
            language: {
              url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            pageLength: 25,
            responsive: true,
            order: []
          });
        });
      }
    } catch (e) {
      console.warn('DataTables init error:', e);
    }
  })();
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>