<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$view = isset($_GET['view']) && $_GET['view'] === 'iva' ? 'iva' : 'totales';

if ($view === 'iva') {
    $select_emitidas = 'SUM(CASE WHEN UPPER(tipo_comprobante)="INGRESO" THEN (iva_importe - retencion_iva) ELSE -(iva_importe - retencion_iva) END)';
    $select_recibidas = 'SUM(CASE WHEN UPPER(tipo_comprobante)="INGRESO" THEN (iva_importe - retencion_iva) ELSE -(iva_importe - retencion_iva) END)';
    $titulo_reporte = 'Totales de IVA Neto por Cliente';
    $header_emitidas = 'IVA Emitidas';
    $header_recibidas = 'IVA Recibidas';
    $header_utilidad = 'Diferencia IVA';
} else {
    $select_emitidas = 'SUM(CASE WHEN UPPER(tipo_comprobante)="INGRESO" THEN total ELSE -total END)';
    $select_recibidas = 'SUM(CASE WHEN UPPER(tipo_comprobante)="INGRESO" THEN total ELSE -total END)';
    $titulo_reporte = 'Totales por Cliente';
    $header_emitidas = 'Total Emitidas';
    $header_recibidas = 'Total Recibidas';
    $header_utilidad = 'Utilidad';
}

$sql = "
SELECT
  c.razon_social, c.rfc, c.actividad,
  COALESCE(e.neto, 0) AS emitidas_neto,
  COALESCE(r.neto, 0) AS recibidas_neto,
  (COALESCE(e.neto, 0) - COALESCE(r.neto, 0)) AS utilidad
FROM clientes c
LEFT JOIN (
  SELECT cliente_id, {$select_emitidas} AS neto FROM cfdis_emitidas WHERE fecha_emision BETWEEN :d1 AND :h1 AND estado='vigente' GROUP BY cliente_id
) e ON e.cliente_id = c.id
LEFT JOIN (
  SELECT cliente_id, {$select_recibidas} AS neto FROM cfdis_recibidas WHERE fecha_certificacion BETWEEN :d2 AND :h2 AND estado='vigente' GROUP BY cliente_id
) r ON r.cliente_id = c.id
ORDER BY c.razon_social ASC";

$params = [':d1' => $desde, ':h1' => $hasta, ':d2' => $desde, ':h2' => $hasta];

$db->query($sql);
// --- CORRECCIÓN AQUÍ: Reemplazar bindAll con el bucle foreach ---
foreach ($params as $key => $value) {
    $db->bind($key, $value);
}
$rows = $db->resultSet();

$tot_emit_net = array_sum(array_column($rows, 'emitidas_neto'));
$tot_rec_net  = array_sum(array_column($rows, 'recibidas_neto'));
$tot_utilidad = array_sum(array_column($rows, 'utilidad'));

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h2><?php echo $titulo_reporte; ?></h2>
        <p class="text-muted mb-0">Periodo: <?php echo htmlspecialchars($desde); ?> a <?php echo htmlspecialchars($hasta); ?></p>
    </div>
    <div class="col-md-5 text-end">
        <button id="btn-exportar" class="btn btn-success"><i class="fas fa-file-excel"></i> Exportar a Excel</button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <form method="get" class="row g-2">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <div class="col"><input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde); ?>"></div>
                    <div class="col"><input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta); ?>"></div>
                    <div class="col-auto"><button type="submit" class="btn btn-primary">Filtrar</button></div>
                </form>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-end">
                <div class="btn-group" role="group">
                    <a href="?desde=<?php echo $desde; ?>&hasta=<?php echo $hasta; ?>&view=totales" class="btn <?php echo $view === 'totales' ? 'btn-dark' : 'btn-outline-dark'; ?>">Ver Totales</a>
                    <a href="?desde=<?php echo $desde; ?>&hasta=<?php echo $hasta; ?>&view=iva" class="btn <?php echo $view === 'iva' ? 'btn-dark' : 'btn-outline-dark'; ?>">Ver IVA</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <label for="buscador-js" class="form-label">Buscar en la tabla actual</label>
        <input type="text" id="buscador-js" class="form-control" placeholder="Escribe para filtrar por Razón Social o RFC...">
    </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle" id="tabla-reporte">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>RFC</th>
          <th class="text-end"><?php echo $header_emitidas; ?></th>
          <th class="text-end"><?php echo $header_recibidas; ?></th>
          <th class="text-end bg-light"><?php echo $header_utilidad; ?></th>
        </tr>
      </thead>
      <tbody id="tabla-clientes">
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <?php echo htmlspecialchars($r->razon_social); ?>
              <?php if (!empty($r->actividad)): ?>
                <div class="small text-muted"><?php echo htmlspecialchars($r->actividad); ?></div>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($r->rfc); ?></td>
            <td class="text-end fw-bold <?php echo ((float)$r->emitidas_neto) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney((float)$r->emitidas_neto); ?></td>
            <td class="text-end fw-bold <?php echo ((float)$r->recibidas_neto) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney((float)$r->recibidas_neto); ?></td>
            <td class="text-end fw-bold bg-light <?php echo ((float)$r->utilidad) >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney((float)$r->utilidad); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-light">
          <th colspan="2" class="text-end">Totales:</th>
          <th class="text-end"><?php echo formatMoney($tot_emit_net); ?></th>
          <th class="text-end"><?php echo formatMoney($tot_rec_net); ?></th>
          <th class="text-end"><?php echo formatMoney($tot_utilidad); ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script>
const reportData = <?php echo json_encode($rows); ?>;
const footerData = {
    emitidas_neto: <?php echo $tot_emit_net; ?>,
    recibidas_neto: <?php echo $tot_rec_net; ?>,
    utilidad: <?php echo $tot_utilidad; ?>
};

document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador-js');
    // ... (el código del buscador no necesita cambios)

    const btnExportar = document.getElementById('btn-exportar');
    if (btnExportar) {
        btnExportar.addEventListener('click', () => {
            
            const createNumericCell = (val) => ({ v: parseFloat(val || 0), t: 'n', z: '#,##0.00' });

            const headers = [
                "Cliente", "RFC", 
                '<?php echo $header_emitidas; ?>', 
                '<?php echo $header_recibidas; ?>', 
                '<?php echo $header_utilidad; ?>'
            ];
            
            let datos = [headers];

            // Usar los datos puros de PHP
            reportData.forEach(row => {
                datos.push([
                    row.razon_social,
                    row.rfc,
                    createNumericCell(row.emitidas_neto),
                    createNumericCell(row.recibidas_neto),
                    createNumericCell(row.utilidad)
                ]);
            });

            // Añadir la fila de totales
            datos.push([
                "", "Totales:",
                createNumericCell(footerData.emitidas_neto),
                createNumericCell(footerData.recibidas_neto),
                createNumericCell(footerData.utilidad)
            ]);

            const ws = XLSX.utils.aoa_to_sheet(datos);
            // --- AJUSTE OPCIONAL: Ancho de columnas ---
            ws['!cols'] = [ { wch: 40 }, { wch: 15 }, { wch: 18 }, { wch: 18 }, { wch: 18 } ];

            const libro = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(libro, ws, "Reporte");
            
            const tipoReporte = '<?php echo $view; ?>'.toUpperCase();
            const fechaDesde = '<?php echo $desde; ?>';
            const fechaHasta = '<?php echo $hasta; ?>';
            const nombreArchivo = `Reporte_${tipoReporte}_${fechaDesde}_a_${fechaHasta}.xlsx`;

            XLSX.writeFile(libro, nombreArchivo);
        });
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>