<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash('mensaje', 'ID de cliente no especificado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del cliente
$database->query('
    SELECT c.*, u.nombre as responsable_nombre, u.apellidos as responsable_apellidos 
    FROM Clientes c 
    LEFT JOIN Usuarios u ON c.responsable_id = u.id 
    WHERE c.id = :id
');
$database->bind(':id', $id);
$cliente = $database->single();

// Verificar que el cliente existe
if (!$cliente) {
    flash('mensaje', 'Cliente no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

// Estadísticas: calcula con las dos tablas (sin depender de una vista CFDIs)
$database->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS suma FROM CFDIs_Emitidas WHERE cliente_id = :cliente_id');
$database->bind(':cliente_id', $id);
$emit = $database->single();
$emitidas_cnt = (int)($emit->cnt ?? 0);
$total_emitidas = (float)($emit->suma ?? 0);

$database->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS suma FROM CFDIs_Recibidas WHERE cliente_id = :cliente_id');
$database->bind(':cliente_id', $id);
$rec = $database->single();
$recibidas_cnt = (int)($rec->cnt ?? 0);
$total_recibidas = (float)($rec->suma ?? 0);

// Armar objeto de estadísticas similar al que usabas
$estadisticas = (object)[
    'total'           => $emitidas_cnt + $recibidas_cnt,
    'emitidas'        => $emitidas_cnt,
    'recibidas'       => $recibidas_cnt,
    'total_emitidas'  => $total_emitidas,
    'total_recibidas' => $total_recibidas,
];

// Facturas recientes (últimas 5) uniendo ambas tablas
$database->query('
    (SELECT 
        e.id,
        "emitida" AS tipo,
        e.tipo_comprobante,
        e.folio_fiscal,
        NULL AS serie,
        e.folio_interno AS folio,
        e.fecha_emision,
        NULL AS rfc_emisor,
        NULL AS nombre_emisor,
        e.rfc_receptor,
        e.nombre_receptor,
        e.total,
        "vigente" AS estado
     FROM CFDIs_Emitidas e
     WHERE e.cliente_id = :cliente_id
    )
    UNION ALL
    (SELECT
        r.id,
        "recibida" AS tipo,
        r.tipo_comprobante,
        r.folio_fiscal,
        NULL AS serie,
        NULL AS folio,
        r.fecha_certificacion AS fecha_emision,
        r.rfc_emisor,
        r.nombre_emisor,
        NULL AS rfc_receptor,
        NULL AS nombre_receptor,
        r.total,
        "vigente" AS estado
     FROM CFDIs_Recibidas r
     WHERE r.cliente_id = :cliente_id
    )
    ORDER BY fecha_emision DESC
    LIMIT 5
');
$database->bind(':cliente_id', $id);
$facturas = $database->resultSet();

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Detalle del Cliente</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="editar.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-primary me-2">
            <i class="fas fa-edit"></i> Editar
        </a>
        <a href="facturas.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-success">
            <i class="fas fa-file-invoice"></i> Facturas
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-user-tie"></i> Información del Cliente
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Razón Social</h5>
                        <p><?php echo htmlspecialchars($cliente->razon_social ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Nombre Comercial</h5>
                        <p><?php echo htmlspecialchars($cliente->nombre_comercial ?? 'No especificado', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <h5>RFC</h5>
                        <p><?php echo htmlspecialchars($cliente->rfc ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="col-md-4">
                        <h5>Régimen Fiscal</h5>
                        <p>
                            <?php 
                            if (!empty($cliente->regimen_fiscal)) {
                                echo htmlspecialchars($cliente->regimen_fiscal, ENT_QUOTES, 'UTF-8') . ' - ';
                                if ($cliente->regimen_fiscal == '601') echo 'General de Ley Personas Morales';
                                elseif ($cliente->regimen_fiscal == '626') echo 'Régimen Simplificado de Confianza';
                                else echo 'Régimen';
                            } else {
                                echo 'No especificado';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h5>Código Postal</h5>
                        <p><?php echo htmlspecialchars($cliente->codigo_postal ?? 'No especificado', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h5>Domicilio Fiscal</h5>
                        <p><?php echo htmlspecialchars($cliente->domicilio_fiscal ?? 'No especificado', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h5>Teléfono</h5>
                        <p><?php echo htmlspecialchars($cliente->telefono ?? 'No especificado', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Email</h5>
                        <p><?php echo htmlspecialchars($cliente->email ?? 'No especificado', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <h5>Fecha Alta</h5>
                        <p><?php echo formatDate($cliente->fecha_alta ?? ''); ?></p>
                    </div>
                    <div class="col-md-4">
                        <h5>Estatus</h5>
                        <p>
                            <?php if(($cliente->estatus ?? '') == 'activo'): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php elseif(($cliente->estatus ?? '') == 'suspendido'): ?>
                            <span class="badge bg-warning">Suspendido</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Baja</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h5>Responsable</h5>
                        <p>
                            <?php 
                            if(!empty($cliente->responsable_nombre)) {
                                echo htmlspecialchars($cliente->responsable_nombre . ' ' . $cliente->responsable_apellidos, ENT_QUOTES, 'UTF-8');
                            } else {
                                echo 'No asignado';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <?php if(!empty($cliente->notas)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <h5>Notas</h5>
                        <p><?php echo nl2br(htmlspecialchars($cliente->notas, ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Estadísticas
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h5>Total de Facturas</h5>
                    <h2 class="display-4"><?php echo (int)($estadisticas->total ?? 0); ?></h2>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="card bg-success text-white">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0">Emitidas</h6>
                                        <h4 class="mb-0"><?php echo (int)($estadisticas->emitidas ?? 0); ?></h4>
                                    </div>
                                    <div>
                                        <i class="fas fa-file-invoice-dollar fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-warning text-white">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-0">Recibidas</h6>
                                        <h4 class="mb-0"><?php echo (int)($estadisticas->recibidas ?? 0); ?></h4>
                                    </div>
                                    <div>
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h5>Importes Totales</h5>
                    <div class="row">
                        <div class="col-6">
                            <p class="mb-1">Emitidas:</p>
                            <h5><?php echo formatMoney((float)($estadisticas->total_emitidas ?? 0)); ?></h5>
                        </div>
                        <div class="col-6">
                            <p class="mb-1">Recibidas:</p>
                            <h5><?php echo formatMoney((float)($estadisticas->total_recibidas ?? 0)); ?></h5>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <a href="../reportes/cargar_xml.php?cliente_id=<?php echo (int)$cliente->id; ?>" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Cargar Facturas
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-file-invoice"></i> Facturas Recientes
    </div>
    <div class="card-body">
        <?php if (!empty($facturas)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Folio Fiscal</th>
                        <th>Serie-Folio</th>
                        <th>Fecha</th>
                        <th>Emisor</th>
                        <th>Receptor</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facturas as $factura): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(ucfirst($factura->tipo ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($factura->folio_fiscal ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php 
                            $serie = $factura->serie ?? '';
                            $folio = $factura->folio ?? '';
                            echo (!empty($serie) || !empty($folio)) 
                                ? htmlspecialchars($serie . '-' . $folio, ENT_QUOTES, 'UTF-8')
                                : '-';
                            ?>
                        </td>
                        <td><?php echo formatDate($factura->fecha_emision ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($factura->rfc_emisor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($factura->rfc_receptor ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo formatMoney((float)($factura->total ?? 0)); ?></td>
                        <td>
                            <?php if(($factura->estado ?? 'vigente') === 'vigente'): ?>
                            <span class="badge bg-success">Vigente</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Cancelado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="../reportes/ver_factura.php?id=<?php echo (int)$factura->id; ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <a href="facturas.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-primary">Ver todas las facturas</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No hay facturas registradas para este cliente.
        </div>
        <div class="mt-3">
            <a href="../reportes/cargar_xml.php?cliente_id=<?php echo (int)$cliente->id; ?>" class="btn btn-primary">
                <i class="fas fa-upload"></i> Cargar Facturas
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>