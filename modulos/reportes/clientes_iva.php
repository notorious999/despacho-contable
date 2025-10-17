<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
$cliente_id = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? (int)$_GET['cliente_id'] : null;

// Obtener lista de clientes para el filtro
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes_filtro = $db->resultSet();

// --- IVA de Facturas Emitidas ---
$sql_emitidas = 'SELECT 
                    c.id, c.razon_social, c.rfc,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 1 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as ene,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 2 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as feb,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 3 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as mar,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 4 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as abr,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 5 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as may,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 6 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as jun,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 7 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as jul,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 8 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as ago,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 9 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as sep,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 10 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as oct,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 11 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as nov,
                    SUM(CASE WHEN MONTH(e.fecha_emision) = 12 THEN e.iva_importe - e.retencion_iva ELSE 0 END) as dic,
                    SUM(e.iva_importe - e.retencion_iva) as total_anual
                FROM cfdis_emitidas e
                JOIN clientes c ON e.cliente_id = c.id
                WHERE YEAR(e.fecha_emision) = :anio AND e.estado = "vigente"';

if ($cliente_id) {
    $sql_emitidas .= ' AND e.cliente_id = :cliente_id';
}
$sql_emitidas .= ' GROUP BY c.id, c.razon_social, c.rfc ORDER BY c.razon_social';
$db->query($sql_emitidas);
$db->bind(':anio', $anio);
if ($cliente_id) {
    $db->bind(':cliente_id', $cliente_id);
}
$totales_emitidas = $db->resultSet();

// --- IVA de Facturas Recibidas ---
$sql_recibidas = 'SELECT 
                    c.id, c.razon_social, c.rfc,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 1 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as ene,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 2 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as feb,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 3 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as mar,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 4 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as abr,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 5 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as may,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 6 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as jun,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 7 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as jul,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 8 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as ago,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 9 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as sep,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 10 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as oct,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 11 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as nov,
                    SUM(CASE WHEN MONTH(r.fecha_certificacion) = 12 THEN r.iva_importe - r.retencion_iva ELSE 0 END) as dic,
                    SUM(r.iva_importe - r.retencion_iva) as total_anual
                FROM cfdis_recibidas r
                JOIN clientes c ON r.cliente_id = c.id
                WHERE YEAR(r.fecha_certificacion) = :anio AND r.estado = "vigente"';

if ($cliente_id) {
    $sql_recibidas .= ' AND r.cliente_id = :cliente_id';
}
$sql_recibidas .= ' GROUP BY c.id, c.razon_social, c.rfc ORDER BY c.razon_social';
$db->query($sql_recibidas);
$db->bind(':anio', $anio);
if ($cliente_id) {
    $db->bind(':cliente_id', $cliente_id);
}
$totales_recibidas = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h2 class="mb-0">Reporte Anual de IVA por Cliente (<?php echo $anio; ?>)</h2>
        <p class="text-muted">Muestra el IVA neto (IVA Cobrado/Pagado menos Retenciones).</p>
    </div>
    <div class="col-md-4">
        <form method="get" class="row g-2 align-items-center">
            <div class="col">
                <select name="anio" class="form-select">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $anio) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Ver</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">IVA en Facturas Emitidas (Ingresos)</h4>
        <button class="btn btn-sm btn-success" onclick="exportToExcel('tabla-emitidas', 'IVA_Emitidas_<?php echo $anio; ?>.xlsx')">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped" id="tabla-emitidas">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <?php foreach (['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'] as $mes) echo "<th>$mes</th>"; ?>
                    <th>Total Anual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($totales_emitidas as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row->razon_social); ?></td>
                        <?php foreach (['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as $mes) echo '<td>' . formatMoney($row->$mes) . '</td>'; ?>
                        <td><strong><?php echo formatMoney($row->total_anual); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">IVA en Facturas Recibidas (Gastos)</h4>
        <button class="btn btn-sm btn-success" onclick="exportToExcel('tabla-recibidas', 'IVA_Recibidas_<?php echo $anio; ?>.xlsx')">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-bordered table-striped" id="tabla-recibidas">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <?php foreach (['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'] as $mes) echo "<th>$mes</th>"; ?>
                    <th>Total Anual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($totales_recibidas as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row->razon_social); ?></td>
                        <?php foreach (['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as $mes) echo '<td>' . formatMoney($row->$mes) . '</td>'; ?>
                        <td><strong><?php echo formatMoney($row->total_anual); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, { sheet: "Datos" });
    XLSX.writeFile(wb, filename);
}
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>