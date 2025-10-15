<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// --- CAMBIO: El filtro 'q' ya no se usa en PHP, se manejará con JS ---
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');

// Query principal: Se obtienen los datos SIN el filtro de búsqueda de texto
$sql = "
SELECT
  c.id,
  c.razon_social,
  c.actividad,
  c.rfc,
  COALESCE(e.neto, 0) AS emitidas_neto,
  COALESCE(r.neto, 0) AS recibidas_neto,
  (COALESCE(e.neto, 0) - COALESCE(r.neto, 0)) AS utilidad
FROM clientes c
LEFT JOIN (
  SELECT
    cliente_id,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE -total END) AS neto
  FROM cfdis_emitidas
  WHERE fecha_emision BETWEEN :desde1 AND :hasta1
    AND (estado IS NULL OR estado='vigente')
  GROUP BY cliente_id
) e ON e.cliente_id = c.id
LEFT JOIN (
  SELECT
    cliente_id,
    SUM(CASE WHEN UPPER(tipo_comprobante)='INGRESO' THEN total ELSE -total END) AS neto
  FROM cfdis_recibidas
  WHERE fecha_certificacion BETWEEN :desde2 AND :hasta2
    AND (estado IS NULL OR estado='vigente')
  GROUP BY cliente_id
) r ON r.cliente_id = c.id
ORDER BY c.razon_social ASC";

$params = [
  ':desde1' => $desde,
  ':hasta1' => $hasta,
  ':desde2' => $desde,
  ':hasta2' => $hasta,
];

$db->query($sql);
foreach ($params as $k=>$v) { $db->bind($k, $v); }
$rows = $db->resultSet();

// Cálculo de totales generales
$tot_emit_net = array_sum(array_column($rows, 'emitidas_neto'));
$tot_rec_net  = array_sum(array_column($rows, 'recibidas_neto'));
$tot_utilidad = array_sum(array_column($rows, 'utilidad'));

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-7">
    <h2>Totales por Cliente</h2>
    <p class="text-muted mb-0">Periodo: <?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?> a <?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?></p>
  </div>
  <div class="col-md-5 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php" class="btn btn-secondary me-2">
      <i class="fas fa-arrow-left"></i> Volver
    </a>
    <button id="btn-exportar" class="btn btn-success">
      <i class="fas fa-file-excel"></i> Exportar (.xlsx)
    </button>
  </div>
</div>

<form class="card mb-3" method="get">
  <div class="card-body row g-3">
    <div class="col-md-4">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="col-md-4 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filtrar por Fecha</button>
    </div>
  </div>
</form>

<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <label for="buscador-js" class="form-label">Buscar en los resultados</label>
                <input type="text" id="buscador-js" class="form-control" placeholder="Escribe para filtrar por Razón Social o RFC...">
            </div>
        </div>
    </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>RFC</th>
          <th class="text-end">Total Emitidas</th>
          <th class="text-end">Total Recibidas</th>
          <th class="text-end bg-light">Utilidad</th>
        </tr>
      </thead>
      <tbody id="tabla-clientes">
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
              <td class="text-end fw-bold <?php echo ((float)$r->emitidas_neto) >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo formatMoney((float)$r->emitidas_neto); ?>
              </td>
              <td class="text-end fw-bold <?php echo ((float)$r->recibidas_neto) >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo formatMoney((float)$r->recibidas_neto); ?>
              </td>
              <td class="text-end fw-bold bg-light <?php echo ((float)$r->utilidad) >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo formatMoney((float)$r->utilidad); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted">Sin resultados para el periodo seleccionado.</td></tr>
        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador-js');
    const tablaCuerpo = document.getElementById('tabla-clientes');
    const filas = tablaCuerpo.getElementsByTagName('tr');

    buscador.addEventListener('keyup', function(e) {
        const textoBusqueda = e.target.value.toLowerCase();

        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            const celdaCliente = fila.getElementsByTagName('td')[0];
            const celdaRfc = fila.getElementsByTagName('td')[1];

            if (celdaCliente && celdaRfc) {
                const textoCliente = (celdaCliente.textContent || celdaCliente.innerText).toLowerCase();
                const textoRfc = (celdaRfc.textContent || celdaRfc.innerText).toLowerCase();

                // Si el texto de búsqueda se encuentra en el nombre del cliente O en el RFC, muestra la fila
                if (textoCliente.indexOf(textoBusqueda) > -1 || textoRfc.indexOf(textoBusqueda) > -1) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            }
        }
    });

    // Código para el botón de exportar (mantenido)
    const btnExportar = document.getElementById('btn-exportar');
    if (btnExportar) {
        btnExportar.addEventListener('click', () => {
            const url = new URL('exportar_clientes_totales_xlsx.php', window.location.href);
            url.searchParams.set('desde', '<?php echo $desde; ?>');
            url.searchParams.set('hasta', '<?php echo $hasta; ?>');
            // Ya no pasamos 'q' al exportador, ya que el filtro es solo visual
            window.location = url.toString();
        });
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>