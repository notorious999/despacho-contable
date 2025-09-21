<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// Filtros
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';
$desde      = isset($_GET['desde']) ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta      = isset($_GET['hasta']) ? sanitize($_GET['hasta']) : date('Y-m-t');
$base       = isset($_GET['base']) ? sanitize($_GET['base']) : 'devengado'; // futuro: flujo

// Clientes para selector
$db->query('SELECT id, razon_social, rfc FROM Clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $db->resultSet();

// Armar condiciones
$params = [':desde' => $desde.' 00:00:00', ':hasta' => $hasta.' 23:59:59'];
$condCliente = '';
if ($cliente_id !== '') {
    $condCliente = ' AND x.cliente_id = :cliente_id';
    $params[':cliente_id'] = $cliente_id;
}

// Ingresos (Emitidas - Ingreso)
$sqlIngresos = '
    SELECT COALESCE(SUM(x.subtotal),0) AS ingresos
    FROM CFDIs_Emitidas x
    WHERE UPPER(x.tipo_comprobante) = "INGRESO"
      AND (x.estado IS NULL OR x.estado = "vigente")
      AND x.fecha_emision BETWEEN :desde AND :hasta
      ' . $condCliente;
$db->query($sqlIngresos);
foreach ($params as $k=>$v) $db->bind($k,$v);
$rowIng = $db->single();
$ingresos = (float)($rowIng->ingresos ?? 0);

// Notas de crédito (Emitidas - Egreso)
$sqlNC = '
    SELECT COALESCE(SUM(x.subtotal),0) AS nc
    FROM CFDIs_Emitidas x
    WHERE UPPER(x.tipo_comprobante) = "EGRESO"
      AND (x.estado IS NULL OR x.estado = "vigente")
      AND x.fecha_emision BETWEEN :desde AND :hasta
      ' . $condCliente;
$db->query($sqlNC);
foreach ($params as $k=>$v) $db->bind($k,$v);
$rowNC = $db->single();
$nc = (float)($rowNC->nc ?? 0);

// Compras y gastos (Recibidas) — excluye P y N
$sqlGastos = '
    SELECT COALESCE(SUM(x.subtotal),0) AS gastos
    FROM CFDIs_Recibidas x
    WHERE UPPER(x.tipo_comprobante) IN ("INGRESO","EGRESO","TRASLADO")
      AND (x.estado IS NULL OR x.estado = "vigente")
      AND x.fecha_certificacion BETWEEN :desde AND :hasta
      ' . $condCliente;
$db->query($sqlGastos);
foreach ($params as $k=>$v) $db->bind($k,$v);
$rowG = $db->single();
$gastos = (float)($rowG->gastos ?? 0);

// Totales
$ventas_netas = $ingresos - $nc;
$utilidad     = $ventas_netas - $gastos;

// Desglose mensual (YYYY-MM) — FIX: desambiguar seq usando (a.seq + b.seq)
$sqlMensual = '
    SELECT ym.mes,
           COALESCE(ing.ingresos,0) AS ingresos,
           COALESCE(nc.nc,0) AS nc,
           COALESCE(gas.gastos,0) AS gastos
    FROM (
        SELECT DATE_FORMAT(d, "%Y-%m") AS mes
        FROM (
            SELECT DATE_ADD(:desde_date, INTERVAL (a.seq + b.seq) DAY) AS d
            FROM (
                SELECT 0 AS seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
                SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            ) a
            CROSS JOIN (
                SELECT 0 AS seq UNION ALL SELECT 10 UNION ALL SELECT 20 UNION ALL SELECT 30 UNION ALL SELECT 40 UNION ALL
                SELECT 50 UNION ALL SELECT 60 UNION ALL SELECT 70 UNION ALL SELECT 80 UNION ALL SELECT 90
            ) b
        ) days
        WHERE d BETWEEN :desde_date AND :hasta_date
        GROUP BY DATE_FORMAT(d, "%Y-%m")
    ) ym
    LEFT JOIN (
        SELECT DATE_FORMAT(x.fecha_emision, "%Y-%m") AS mes, SUM(x.subtotal) AS ingresos
        FROM CFDIs_Emitidas x
        WHERE UPPER(x.tipo_comprobante) = "INGRESO"
          AND (x.estado IS NULL OR x.estado = "vigente")
          AND x.fecha_emision BETWEEN :desde AND :hasta ' . $condCliente . '
        GROUP BY DATE_FORMAT(x.fecha_emision, "%Y-%m")
    ) ing ON ing.mes = ym.mes
    LEFT JOIN (
        SELECT DATE_FORMAT(x.fecha_emision, "%Y-%m") AS mes, SUM(x.subtotal) AS nc
        FROM CFDIs_Emitidas x
        WHERE UPPER(x.tipo_comprobante) = "EGRESO"
          AND (x.estado IS NULL OR x.estado = "vigente")
          AND x.fecha_emision BETWEEN :desde AND :hasta ' . $condCliente . '
        GROUP BY DATE_FORMAT(x.fecha_emision, "%Y-%m")
    ) nc ON nc.mes = ym.mes
    LEFT JOIN (
        SELECT DATE_FORMAT(x.fecha_certificacion, "%Y-%m") AS mes, SUM(x.subtotal) AS gastos
        FROM CFDIs_Recibidas x
        WHERE UPPER(x.tipo_comprobante) IN ("INGRESO","EGRESO","TRASLADO")
          AND (x.estado IS NULL OR x.estado = "vigente")
          AND x.fecha_certificacion BETWEEN :desde AND :hasta ' . $condCliente . '
        GROUP BY DATE_FORMAT(x.fecha_certificacion, "%Y-%m")
    ) gas ON gas.mes = ym.mes
    ORDER BY ym.mes ASC
';
$db->query($sqlMensual);
$db->bind(':desde', $params[':desde']);
$db->bind(':hasta', $params[':hasta']);
$db->bind(':desde_date', substr($params[':desde'],0,10));
$db->bind(':hasta_date', substr($params[':hasta'],0,10));
if ($cliente_id !== '') $db->bind(':cliente_id', $cliente_id);
$mensual = $db->resultSet();

// Header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-8">
    <h2>Utilidad y Declaraciones</h2>
    <p class="text-muted mb-0">Base: Devengado (subtotales, sin IVA). Excluye CFDIs de tipo Pago y Nómina.</p>
  </div>
  <div class="col-md-4 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Volver a Reportes
    </a>
  </div>
</div>

<form class="card mb-3">
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">Cliente</label>
      <select name="cliente_id" class="form-select">
        <option value="">Todos</option>
        <?php foreach($clientes as $c): ?>
        <option value="<?php echo (int)$c->id; ?>" <?php echo ($cliente_id == $c->id ? 'selected' : ''); ?>>
          <?php echo htmlspecialchars($c->razon_social . ' (' . $c->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Aplicar</button>
    </div>
  </div>
</form>

<div class="row mb-3">
  <div class="col-md-3 mb-2">
    <div class="card bg-light"><div class="card-body text-center p-2">
      <div class="small text-muted">Ingresos facturados</div>
      <div class="h4 mb-0"><?php echo formatMoney($ingresos); ?></div>
    </div></div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="card bg-light"><div class="card-body text-center p-2">
      <div class="small text-muted">Notas de crédito</div>
      <div class="h4 mb-0"><?php echo formatMoney($nc); ?></div>
    </div></div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="card bg-primary text-white"><div class="card-body text-center p-2">
      <div class="small">Ventas netas</div>
      <div class="h4 mb-0"><?php echo formatMoney($ventas_netas); ?></div>
    </div></div>
  </div>
  <div class="col-md-3 mb-2">
    <div class="card bg-warning text-white"><div class="card-body text-center p-2">
      <div class="small">Compras y gastos</div>
      <div class="h4 mb-0"><?php echo formatMoney($gastos); ?></div>
    </div></div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-md-12">
    <div class="card <?php echo ($utilidad >= 0 ? 'border-success' : 'border-danger'); ?>">
      <div class="card-body text-center">
        <div class="small text-muted">Utilidad estimada</div>
        <div class="display-6 <?php echo ($utilidad >= 0 ? 'text-success' : 'text-danger'); ?>">
          <?php echo formatMoney($utilidad); ?>
        </div>
        <div class="small text-muted mt-2">
          Recomendación: separar “Costo” y “Gastos” con categorías en una siguiente versión.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa.list"></i> Desglose mensual</div>
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered">
      <thead>
        <tr>
          <th>Mes</th>
          <th class="text-end">Ingresos</th>
          <th class="text-end">Notas de crédito</th>
          <th class="text-end">Ventas netas</th>
          <th class="text-end">Compras y gastos</th>
          <th class="text-end">Utilidad</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($mensual)): ?>
          <?php foreach ($mensual as $m): 
            $vn = (float)$m->ingresos - (float)$m->nc;
            $ut = $vn - (float)$m->gastos;
          ?>
          <tr>
            <td><?php echo htmlspecialchars($m->mes, ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end"><?php echo formatMoney((float)$m->ingresos); ?></td>
            <td class="text-end"><?php echo formatMoney((float)$m->nc); ?></td>
            <td class="text-end"><?php echo formatMoney($vn); ?></td>
            <td class="text-end"><?php echo formatMoney((float)$m->gastos); ?></td>
            <td class="text-end <?php echo ($ut>=0?'text-success':'text-danger'); ?>"><?php echo formatMoney($ut); ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">Sin datos en el periodo seleccionado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>