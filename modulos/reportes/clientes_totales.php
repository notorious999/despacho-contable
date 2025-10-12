<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// Filtros
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$hastaFiltro = date('Y-m-d', strtotime($hasta . ' +1 day'));
$q     = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';

// Query principal: se añade el cálculo de la Utilidad
$sql = "
SELECT
  c.id,
  c.razon_social,
  c.actividad,
  c.rfc,
  COALESCE(e.ingresos,0)   AS emitidas_ingresos,
  COALESCE(e.egresos,0)    AS emitidas_egresos,
  COALESCE(e.neto,0)       AS emitidas_neto,
  COALESCE(r.ingresos,0)   AS recibidas_ingresos,
  COALESCE(r.egresos,0)    AS recibidas_egresos,
  COALESCE(r.neto,0)       AS recibidas_neto,
  (COALESCE(e.neto,0) - COALESCE(r.neto,0)) AS utilidad -- --> CAMBIO: Se añade el cálculo de la Utilidad
FROM clientes c
LEFT JOIN (
  SELECT
    cliente_id,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE 0 END) AS ingresos,
    SUM(CASE WHEN UPPER(tipo_comprobante)='EGRESO' THEN total ELSE 0 END)  AS egresos,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE 0 END)
    - SUM(CASE WHEN UPPER(tipo_comprobante)='EGRESO' THEN total ELSE 0 END) AS neto
  FROM cfdis_emitidas
  WHERE fecha_emision BETWEEN :desde1 AND :hasta1
    AND (estado IS NULL OR estado='vigente')
  GROUP BY cliente_id
) e ON e.cliente_id = c.id
LEFT JOIN (
  SELECT
    cliente_id,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE 0 END) AS ingresos,
    SUM(CASE WHEN UPPER(tipo_comprobante)='EGRESO' THEN total ELSE 0 END)  AS egresos,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE 0 END)
    - SUM(CASE WHEN UPPER(tipo_comprobante)='EGRESO' THEN total ELSE 0 END) AS neto
  FROM cfdis_recibidas
  WHERE fecha_certificacion BETWEEN :desde2 AND :hasta2
    AND (estado IS NULL OR estado='vigente')
  GROUP BY cliente_id
) r ON r.cliente_id = c.id
WHERE 1=1
";

$params = [
  ':desde1' => $desde.' 00:00:00',
  ':hasta1' => $hastaFiltro.' 00:00:00',
  ':desde2' => $desde.' 00:00:00',
  ':hasta2' => $hastaFiltro.' 00:00:00',
];

if ($q !== '') {
  $sql .= " AND (c.razon_social LIKE :q OR c.actividad LIKE :q OR c.rfc LIKE :q) ";
  $params[':q'] = '%'.$q.'%';
}

$sql .= " ORDER BY c.razon_social ASC";

$db->query($sql);
foreach ($params as $k=>$v) { $db->bind($k, $v); }
$rows = $db->resultSet();

// Totales generales
$tot_emit_ing = 0; $tot_emit_egr = 0; $tot_emit_net = 0;
$tot_rec_ing  = 0; $tot_rec_egr  = 0; $tot_rec_net  = 0;
$tot_utilidad = 0; // --> CAMBIO: Variable para el total de utilidad
foreach ($rows as $r) {
  $tot_emit_ing += (float)$r->emitidas_ingresos;
  $tot_emit_egr += (float)$r->emitidas_egresos;
  $tot_emit_net += (float)$r->emitidas_neto;
  $tot_rec_ing  += (float)$r->recibidas_ingresos;
  $tot_rec_egr  += (float)$r->recibidas_egresos;
  $tot_rec_net  += (float)$r->recibidas_neto;
  $tot_utilidad += (float)$r->utilidad; // --> CAMBIO: Sumar la utilidad al total
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-7">
    <h2>Totales por Cliente</h2>
    <p class="text-muted mb-0">Periodo: <?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?> a <?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?></p>
  </div>
  <div class="col-md-5 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php" class="btn btn-secondary me-2">
      <i class="fas fa-arrow-left"></i> Volver a Reportes
    </a>
    <button id="btn-exportar" class="btn btn-success">
      <i class="fas fa-file-excel"></i> Exportar (.xlsx)
    </button>
  </div>
</div>

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
    <div class="col-md-4">
      <label class="form-label">Buscar cliente / RFC</label>
      <input type="text" name="q" class="form-control" placeholder="Razón social, nombre comercial o RFC" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar</button>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>RFC</th>
          <th class="text-end">Total Emitidas</th>
          <th class="text-end">Total Recibidas</th>
          <th class="text-end bg-light">Utilidad</th> </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <?php echo htmlspecialchars($r->razon_social, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($r->actividad)): ?>
                  <div class="small text-muted"><?php echo htmlspecialchars($r->actividad, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($r->rfc, ENT_QUOTES, 'UTF-8'); ?></td>
              <td class="text-end fw-bold <?php echo ((float)$r->emitidas_neto)>=0 ? 'text-success':'text-danger'; ?>">
                <?php echo formatMoney((float)$r->emitidas_neto); ?>
              </td>
              <td class="text-end fw-bold <?php echo ((float)$r->recibidas_neto)>=0 ? 'text-success':'text-danger'; ?>">
                <?php echo formatMoney((float)$r->recibidas_neto); ?>
              </td>
              <td class="text-end fw-bold bg-light <?php echo ((float)$r->utilidad)>=0 ? 'text-success':'text-danger'; ?>">
                <?php echo formatMoney((float)$r->utilidad); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted">Sin resultados para los criterios seleccionados.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="2" class="text-end">Totales:</th>
          <th class="text-end"><?php echo formatMoney($tot_emit_net); ?></th>
          <th class="text-end"><?php echo formatMoney($tot_rec_net); ?></th>
          <th class="text-end"><?php echo formatMoney($tot_utilidad); ?></th> </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
document.getElementById('btn-exportar')?.addEventListener('click', ()=>{
  const url = new URL('exportar_clientes_totales_xlsx.php', window.location.href);
  url.searchParams.set('desde', '<?php echo $desde; ?>');
  url.searchParams.set('hasta', '<?php echo $hasta; ?>');
  url.searchParams.set('q', '<?php echo htmlspecialchars($q, ENT_QUOTES, "UTF-8"); ?>');
  window.location = url.toString();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>