<?php
// Asegúrate que las rutas sean correctas
require_once __DIR__ . '/../../config/config.php'; // Define URL_ROOT, etc.
require_once __DIR__ . '/../../config/db.php';     // Tu clase Database
require_once __DIR__ . '/../../includes/functions.php';

// Asegúrate que session_start() se llame solo una vez.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: " . URL_ROOT . "/modulos/usuarios/login.php");
    exit();
}

try {
    $db = new Database();
} catch (Exception $e) {
    die("Error al conectar a la base de datos: " . $e->getMessage());
}

// --- Lógica de Filtros (de control_honorarios) ---
$anio_filtro = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$mes_filtro = max(1, min(12, $mes_filtro));
$anio_actual = date('Y');
$anio_filtro = max($anio_actual - 10, min($anio_actual + 5, $anio_filtro));
$estado_filtro = isset($_GET['estado']) ? $_GET['estado'] : '';
if (!in_array($estado_filtro, ['', 'pagado', 'pendiente', 'cortesia'])) { $estado_filtro = ''; }
$buscar_cliente = isset($_GET['buscar']) ? trim(sanitize($_GET['buscar'])) : '';
$meses_es = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// Lista de clientes para filtro (de recibos/index.php, es compatible)
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus="activo" ORDER BY razon_social');
$clientes = $db->resultSet();

// --- Consultas para KPIs (de control_honorarios) ---
$num_total_honorarios = 0;
$monto_total_definido = 0;
$monto_pagado_periodo = 0;
$num_pagados_periodo = 0;
$num_cortesia_periodo = 0;
$monto_cortesia_definido_periodo = 0;
$num_pendientes_periodo = 0;

try {
    $db->query('SELECT COUNT(id) as num_clientes, SUM(honorarios) as monto_total
                FROM clientes WHERE honorarios > 0 AND estatus = "activo"');
    $totales_fijos = $db->single();
    $num_total_honorarios = isset($totales_fijos->num_clientes) ? (int)$totales_fijos->num_clientes : 0;
    $monto_total_definido = isset($totales_fijos->monto_total) ? (float)$totales_fijos->monto_total : 0;
} catch (Exception $e) { error_log("Error KPI Fijos: " . $e->getMessage()); }

try {
    $sql_stats = "SELECT
                      ch.estado, COUNT(ch.id) as num_estado,
                      SUM(CASE WHEN ch.estado = 'pagado' THEN ch.monto_mensual ELSE 0 END) as monto_pagado_estado,
                      SUM(CASE WHEN ch.estado = 'cortesia' THEN c.honorarios ELSE 0 END) as monto_cortesia_definido
                  FROM control_honorarios ch
                  JOIN clientes c ON ch.cliente_id = c.id
                  WHERE ch.anio = :anio AND ch.mes = :mes AND c.estatus = 'activo'
                  GROUP BY ch.estado";
    $db->query($sql_stats);
    $db->bind(':anio', $anio_filtro);
    $db->bind(':mes', $mes_filtro);
    $stats_periodo = $db->resultSet();
    if ($stats_periodo) {
        foreach($stats_periodo as $stat) {
            if ($stat->estado === 'pagado') {
                $monto_pagado_periodo = isset($stat->monto_pagado_estado) ? (float)$stat->monto_pagado_estado : 0;
                $num_pagados_periodo = isset($stat->num_estado) ? (int)$stat->num_estado : 0;
            } elseif ($stat->estado === 'cortesia') {
                $num_cortesia_periodo = isset($stat->num_estado) ? (int)$stat->num_estado : 0;
                $monto_cortesia_definido_periodo = isset($stat->monto_cortesia_definido) ? (float)$stat->monto_cortesia_definido : 0;
            }
        }
    }
} catch (Exception $e) { error_log("Error KPI Stats Periodo: " . $e->getMessage()); }

// Cálculo KPIs Finales
$saldo_pendiente_periodo = $monto_total_definido - $monto_pagado_periodo - $monto_cortesia_definido_periodo;
$saldo_pendiente_periodo = max(0, $saldo_pendiente_periodo);
$num_pendientes_periodo = $num_total_honorarios - $num_pagados_periodo - $num_cortesia_periodo;
$num_pendientes_periodo = max(0, $num_pendientes_periodo);

// --- Consulta para la Tabla Principal (de control_honorarios) ---
$sql_tabla = "SELECT
                  c.id AS cliente_id_col, c.razon_social, c.rfc, c.honorarios AS monto_honorario_definido,
                  ch.id as control_id, ch.monto_mensual,
                  IFNULL(ch.estado, 'pendiente') AS estado_periodo,
                  ch.fecha_pago, ch.recibo_servicio_id, rs.recibo_id
              FROM clientes c
              LEFT JOIN control_honorarios ch ON c.id = ch.cliente_id AND ch.anio = :anio AND ch.mes = :mes
              LEFT JOIN recibo_servicios rs ON ch.recibo_servicio_id = rs.id
              WHERE c.honorarios > 0 AND c.estatus = 'activo'";
$bindings = [':anio' => $anio_filtro, ':mes' => $mes_filtro];
if ($buscar_cliente) {
    $sql_tabla .= " AND (c.razon_social LIKE :buscar OR c.rfc LIKE :buscar)";
    $bindings[':buscar'] = "%" . $buscar_cliente . "%";
}
if ($estado_filtro === 'pagado') { $sql_tabla .= " AND ch.estado = 'pagado'"; }
elseif ($estado_filtro === 'pendiente') { $sql_tabla .= " AND IFNULL(ch.estado, 'pendiente') = 'pendiente'"; }
elseif ($estado_filtro === 'cortesia') { $sql_tabla .= " AND ch.estado = 'cortesia'"; }
$sql_tabla .= " ORDER BY c.razon_social";

$resultados_tabla = [];
try {
    $db->query($sql_tabla);
    foreach ($bindings as $key => $value) { $db->bind($key, $value); }
    $resultados_tabla = $db->resultSet();
} catch (Exception $e) {
    error_log("Error Tabla Principal (control_honorarios): " . $e->getMessage());
    flash('mensaje', 'Error al cargar los datos de la tabla.', 'alert alert-danger');
}
?>

<?php include '../../includes/header.php'; ?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="mb-0">Control de Honorarios - <?php echo htmlspecialchars($meses_es[$mes_filtro] . ' ' . $anio_filtro); ?></h2>
    </div>
    <div class="col-md-6 text-end">
        </div>
</div>

<?php flash('mensaje'); ?>

<form class="card mb-3" method="get">
    <div class="card-body row g-3">
        <div class="col-md-2">
            <label class="form-label" for="anio">Año</label>
            <select id="anio" name="anio" class="form-select">
                <?php for ($a = date('Y') + 1; $a >= date('Y') - 5; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo ($a == $anio_filtro) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="mes">Mes</label>
            <select id="mes" name="mes" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m == $mes_filtro) ? 'selected' : ''; ?>><?php echo $meses_es[$m]; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="col-md-3">
            <label class="form-label" for="cliente_id_filtro">Cliente</label>
            <select name="cliente_id" id="cliente_id_filtro" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?php echo (int)$c->id; ?>" <?php echo (!empty($cliente_id) && (int)$cliente_id === (int)$c->id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c->razon_social . ' (' . $c->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label" for="estado_filtro">Estado</label>
            <select name="estado" id="estado_filtro" class="form-select">
                <option value="" <?php echo ($estado_filtro == '') ? 'selected' : ''; ?>>Todos</option>
                <option value="pagado" <?php echo ($estado_filtro == 'pagado') ? 'selected' : ''; ?>>Pagado</option>
                <option value="pendiente" <?php echo ($estado_filtro == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="cortesia" <?php echo ($estado_filtro == 'cortesia') ? 'selected' : ''; ?>>Cortesía</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label class="form-label" for="buscar">Buscar</label>
            <input type="text" id="buscar" name="buscar" class="form-control" placeholder="Cliente o RFC..." value="<?php echo htmlspecialchars($buscar_cliente); ?>">
        </div>
        
        <div class="col-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Aplicar</button>
            <a href="index.php" class="btn btn-secondary ms-2"><i class="fas fa-times"></i> Limpiar</a> </div>
    </div>
</form>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-light h-100"><div class="card-body text-center p-2">
            <div class="small text-muted">Monto Global Def.</div>
            <div class="h4 mb-0"><?php echo formatMoney($monto_total_definido); ?></div>
            <div class="small text-muted">(<?php echo $num_total_honorarios; ?> Clientes)</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100"><div class="card-body text-center p-2">
            <div class="small">Monto Pagado (Mes)</div>
            <div class="h4 mb-0"><?php echo formatMoney($monto_pagado_periodo); ?></div>
             <div class="small">(<?php echo $num_pagados_periodo; ?> Pagados)</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100"><div class="card-body text-center p-2">
            <div class="small">Saldo Pendiente (Mes)</div>
            <div class="h4 mb-0"><?php echo formatMoney($saldo_pendiente_periodo); ?></div>
            <div class="small">(<?php echo $num_pendientes_periodo; ?> Pendientes)</div>
        </div></div>
    </div>
     <div class="col-md-3">
        <div class="card bg-info text-dark h-100"><div class="card-body text-center p-2">
            <div class="small">Cortesías (Mes)</div>
            <div class="h4 mb-0"><?php echo number_format($num_cortesia_periodo); ?></div>
            <div class="small">(Valor Omitido: <?php echo formatMoney($monto_cortesia_definido_periodo); ?>)</div>
        </div></div>
    </div>
</div>


<div class="card">
    <div class="card-body table-responsive">
        <table id="datatablesSimple" class="table table-striped table-bordered align-middle" style="width:100%;">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th class="text-end">H. Definido</th>
                    <th class="text-end">Monto Periodo</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Fecha Pago/Cort.</th>
                    <th class="text-center">Recibo #</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($resultados_tabla)): ?>
                    <?php foreach ($resultados_tabla as $row): ?>
                        <?php
                            $estadoActual = $row->estado_periodo ?? 'pendiente';
                            // ESTILO de badge copiado de recibos/index.php
                            if ($estadoActual == 'pagado') { $badge = 'bg-success'; }
                            elseif ($estadoActual == 'cortesia') { $badge = 'bg-info'; } // Añadido
                            else { $badge = 'bg-warning text-dark'; } // Añadido text-dark para warning
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($row->razon_social ?? ''); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($row->rfc ?? ''); ?></small>
                            </td>
                            <td class="text-end"><?php echo formatMoney($row->monto_honorario_definido ?? 0); ?></td>
                            <td class="text-end">
                                <?php
                                    if ($estadoActual == 'pagado') { echo formatMoney($row->monto_mensual ?? 0); }
                                    elseif ($estadoActual == 'cortesia') { echo '<span class="text-info">' . formatMoney(0) . '</span>'; }
                                    else { echo '<span class="text-muted">' . formatMoney($row->monto_honorario_definido ?? 0) . '</span>'; }
                                ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($estadoActual); ?></span>
                            </td>
                            <td class="text-center small"><?php echo (isset($row->fecha_pago) && $row->fecha_pago) ? date('d/m/Y', strtotime($row->fecha_pago)) : '-'; ?></td>
                            <td class="text-center">
                                <?php if (isset($row->recibo_id) && $row->recibo_id): ?>
                                    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/ver.php?id=<?php echo $row->recibo_id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0 px-1" title="Ver Recibo <?php echo $row->recibo_id; ?>">
                                        <i class="fas fa-external-link-alt me-1"></i><?php echo $row->recibo_id; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($estadoActual == 'pendiente'): ?>
                                    <button class="btn btn-outline-info btn-sm btn-marcar-cortesia py-0 px-1"
                                            data-cliente-id="<?php echo htmlspecialchars($row->cliente_id_col ?? ''); ?>"
                                            data-anio="<?php echo $anio_filtro; ?>"
                                            data-mes="<?php echo $mes_filtro; ?>"
                                            data-cliente-nombre="<?php echo htmlspecialchars($row->razon_social ?? ''); ?>"
                                            title="Marcar <?php echo htmlspecialchars($meses_es[$mes_filtro] ?? ''); ?>/<?php echo $anio_filtro; ?> como Cortesía">
                                        <i class="fas fa-gift"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted fst-italic py-3">No hay clientes con honorarios definidos que coincidan con los filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div> <?php include '../../includes/footer.php'; ?>