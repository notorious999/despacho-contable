<?php
// Usar rutas absolutas para evitar problemas
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

/* /assets/css/styles.css */


// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener estadísticas para el dashboard
// Total de clientes activos
$database->query('SELECT COUNT(*) as total FROM Clientes WHERE estatus = "activo"');
$clientesActivos = $database->single()->total;

// Total de facturas por tipo (combinando ambas tablas)
$database->query('SELECT "emitida" as tipo, COUNT(*) as total FROM CFDIs_Emitidas WHERE estado = "vigente"
                 UNION ALL
                 SELECT "recibida" as tipo, COUNT(*) as total FROM CFDIs_Recibidas WHERE estado = "vigente"');
$facturasPorTipo = $database->resultSet();

// Facturas recientes (últimas 5) - combinando ambas tablas
$database->query('SELECT "emitida" as origen, id, "emitida" as tipo, folio_fiscal, fecha_emision as fecha, 
                  nombre_receptor as nombre_contraparte, rfc_receptor as rfc_contraparte, total 
                  FROM CFDIs_Emitidas
                  UNION ALL
                  SELECT "recibida" as origen, id, "recibida" as tipo, folio_fiscal, fecha_certificacion as fecha, 
                  nombre_emisor as nombre_contraparte, rfc_emisor as rfc_contraparte, total 
                  FROM CFDIs_Recibidas
                  ORDER BY fecha DESC LIMIT 5');
$facturasRecientes = $database->resultSet();

// Facturas por mes (para gráfico) - combinando ambas tablas
$database->query('SELECT DATE_FORMAT(fecha_emision, "%Y-%m") as mes, 
                 COUNT(*) as total, 
                 COUNT(*) as emitidas,
                 0 as recibidas
                 FROM CFDIs_Emitidas 
                 WHERE fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND estado = "vigente"
                 GROUP BY DATE_FORMAT(fecha_emision, "%Y-%m")
                 UNION ALL
                 SELECT DATE_FORMAT(fecha_certificacion, "%Y-%m") as mes, 
                 COUNT(*) as total, 
                 0 as emitidas,
                 COUNT(*) as recibidas
                 FROM CFDIs_Recibidas 
                 WHERE fecha_certificacion >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND estado = "vigente"
                 GROUP BY DATE_FORMAT(fecha_certificacion, "%Y-%m")
                 ORDER BY mes ASC');
$facturasPorMesRaw = $database->resultSet();

// Procesar los datos para agrupar por mes
$facturasPorMes = [];
foreach ($facturasPorMesRaw as $row) {
    if (!isset($facturasPorMes[$row->mes])) {
        $facturasPorMes[$row->mes] = (object)[
            'mes' => $row->mes,
            'total' => 0,
            'emitidas' => 0,
            'recibidas' => 0
        ];
    }
    $facturasPorMes[$row->mes]->total += $row->total;
    $facturasPorMes[$row->mes]->emitidas += $row->emitidas;
    $facturasPorMes[$row->mes]->recibidas += $row->recibidas;
}
$facturasPorMes = array_values($facturasPorMes);

// Obtener totales de recibos
$database->query('SELECT 
                  COUNT(*) as total,
                  SUM(CASE WHEN estado = "pagado" THEN 1 ELSE 0 END) as pagados,
                  SUM(CASE WHEN estado = "pendiente" THEN 1 ELSE 0 END) as pendientes,
                  SUM(CASE WHEN estado = "pagado" THEN monto ELSE 0 END) as total_pagado
                  FROM Recibos');
$recibosStats = $database->single();

// Incluir el header
include_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Dashboard</h2>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title">Clientes Activos</h5>
                        <h2 class="display-4"><?php echo $clientesActivos; ?></h2>
                    </div>
                    <div>
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                </div>
                <a href="<?php echo URL_ROOT; ?>/modulos/clientes/index.php" class="text-white">Ver todos <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title">Facturas Emitidas</h5>
                        <h2 class="display-4">
                            <?php 
                            $emitidas = 0;
                            foreach ($facturasPorTipo as $factura) {
                                if ($factura->tipo == 'emitida') {
                                    $emitidas = $factura->total;
                                    break;
                                }
                            }
                            echo $emitidas;
                            ?>
                        </h2>
                    </div>
                    <div>
                        <i class="fas fa-file-invoice-dollar fa-3x"></i>
                    </div>
                </div>
                <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php?tipo=emitida" class="text-white">Ver todas <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title">Facturas Recibidas</h5>
                        <h2 class="display-4">
                            <?php 
                            $recibidas = 0;
                            foreach ($facturasPorTipo as $factura) {
                                if ($factura->tipo == 'recibida') {
                                    $recibidas = $factura->total;
                                    break;
                                }
                            }
                            echo $recibidas;
                            ?>
                        </h2>
                    </div>
                    <div>
                        <i class="fas fa-file-invoice fa-3x"></i>
                    </div>
                </div>
                <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php?tipo=recibida" class="text-white">Ver todas <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title">Recibos</h5>
                        <h2 class="display-4"><?php echo isset($recibosStats->total) ? $recibosStats->total : 0; ?></h2>
                    </div>
                    <div>
                        <i class="fas fa-receipt fa-3x"></i>
                    </div>
                </div>
                <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="text-white">Ver todos <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Facturas por Mes
            </div>
            <div class="card-body">
                <canvas id="facturasPorMes"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tasks"></i> Recibos y Pagos
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Recibos Pagados</h5>
                                <h3 class="text-success"><?php echo isset($recibosStats->pagados) ? $recibosStats->pagados : 0; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Recibos Pendientes</h5>
                                <h3 class="text-warning"><?php echo isset($recibosStats->pendientes) ? $recibosStats->pendientes : 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <h5>Total Cobrado</h5>
                    <h2 class="text-primary"><?php echo isset($recibosStats->total_pagado) ? formatMoney($recibosStats->total_pagado) : '$0.00'; ?></h2>
                </div>
                <div class="d-grid gap-2 mt-3">
                    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/agregar.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Recibo
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-table"></i> Facturas Recientes
    </div>
    <div class="card-body">
        <?php if (count($facturasRecientes) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Folio Fiscal</th>
                        <th>Fecha</th>
                        <th>Emisor/Receptor</th>
                        <th>RFC</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facturasRecientes as $factura): ?>
                    <tr>
                        <td>
                            <?php if($factura->tipo == 'emitida'): ?>
                            <span class="badge bg-success">Emitida</span>
                            <?php else: ?>
                            <span class="badge bg-warning">Recibida</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo substr($factura->folio_fiscal, 0, 10) . '...'; ?></td>
                        <td><?php echo formatDate($factura->fecha); ?></td>
                        <td><?php echo $factura->nombre_contraparte; ?></td>
                        <td><?php echo $factura->rfc_contraparte; ?></td>
                        <td class="text-end"><?php echo formatMoney($factura->total); ?></td>
                        <td class="text-center">
                            <a href="modulos/reportes/ver_factura.php?id=<?php echo $factura->id; ?>&tipo=<?php echo $factura->origen; ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No hay facturas registradas.
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Datos para gráfico de facturas por mes
    var facturasPorMes = <?php echo json_encode($facturasPorMes); ?>;
    
    // Preparar datos para Chart.js
    var meses = facturasPorMes.map(function(item) {
        var parts = item.mes.split('-');
        var year = parts[0];
        var month = parts[1];
        var monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return monthNames[parseInt(month) - 1] + ' ' + year;
    });
    
    var emitidas = facturasPorMes.map(function(item) {
        return item.emitidas;
    });
    
    var recibidas = facturasPorMes.map(function(item) {
        return item.recibidas;
    });
    
    // Crear gráfico
    var ctx = document.getElementById('facturasPorMes').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Emitidas',
                data: emitidas,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }, {
                label: 'Recibidas',
                data: recibidas,
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                borderColor: 'rgba(255, 193, 7, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>