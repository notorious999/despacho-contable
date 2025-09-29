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
    flash('mensaje', 'ID de recibo no especificado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del recibo
$database->query('SELECT r.*, c.razon_social as cliente_nombre, c.rfc as cliente_rfc, 
                  u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                  FROM Recibos r 
                  LEFT JOIN Clientes c ON r.cliente_id = c.id 
                  LEFT JOIN Usuarios u ON r.usuario_id = u.id 
                  WHERE r.id = :id');
$database->bind(':id', $id);
$recibo = $database->single();

// Verificar que el recibo existe
if (!$recibo) {
    flash('mensaje', 'Recibo no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Detalle de Recibo</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <?php if ($recibo->estado != 'cancelado'): ?>
            <a href="editar.php?id=<?php echo $recibo->id; ?>" class="btn btn-primary me-2">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="cambiar_estado.php?id=<?php echo $recibo->id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-exchange-alt"></i> Cambiar Estado
            </a>
            <a href="imprimir.php?id=<?php echo $recibo->id; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-print"></i> Imprimir
            </a>
        <?php else: ?>
            <a href="imprimir.php?id=<?php echo $recibo->id; ?>" class="btn btn-info">
                <i class="fas fa-print"></i> Imprimir
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-receipt"></i> Información del Recibo #<?php echo $recibo->id; ?>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Información General</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Cliente:</th>
                        <td><?php echo $recibo->cliente_nombre; ?></td>
                    </tr>
                    <tr>
                        <th>RFC:</th>
                        <td><?php echo $recibo->cliente_rfc; ?></td>
                    </tr>
                    <tr>
                        <th>Concepto:</th>
                        <td><?php echo $recibo->concepto; ?></td>
                    </tr>
                    <tr>
                        <th>Tipo de Pago:</th>
                        <td><?php echo ucfirst($recibo->tipo); ?></td>
                    </tr>
                    <tr>
                        <th>Monto:</th>
                        <td><strong><?php echo formatMoney($recibo->monto); ?></strong></td>
                    </tr>
                </table>
            </div>

            <div class="col-md-6">
                <h5>Fechas y Estado</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Fecha de Pago:</th>
                        <td><?php echo formatDate($recibo->fecha_pago, 'd/m/Y'); ?></td>
                    </tr>
                    <?php if (!empty($recibo->vencimiento)): ?>
                        <tr>
                            <th>Vencimiento:</th>
                            <td>
                                <?php echo formatDate($recibo->vencimiento, 'd/m/Y'); ?>
                                <small class="text-muted">(<?php echo $recibo->duracion; ?>)</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <?php if ($recibo->estado == 'pagado'): ?>
                                <span class="badge bg-success">Pagado</span>
                            <?php elseif ($recibo->estado == 'pendiente'): ?>
                                <span class="badge bg-warning">Pendiente</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Desconocido</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Fecha de Registro:</th>
                        <td><?php echo formatDate($recibo->fecha_creacion, 'd/m/Y H:i'); ?></td>
                    </tr>
                    <tr>
                        <th>Registrado por:</th>
                        <td>
                            <?php
                            if (!empty($recibo->usuario_nombre)) {
                                echo $recibo->usuario_nombre . ' ' . $recibo->usuario_apellidos;
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if (!empty($recibo->observaciones)): ?>
            <div class="row">
                <div class="col-12">
                    <h5>Observaciones</h5>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br($recibo->observaciones); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>