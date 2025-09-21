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
$database->query('SELECT r.*, c.razon_social as cliente_nombre 
                  FROM Recibos r 
                  LEFT JOIN Clientes c ON r.cliente_id = c.id 
                  WHERE r.id = :id');
$database->bind(':id', $id);
$recibo = $database->single();

// Verificar que el recibo existe
if (!$recibo) {
    flash('mensaje', 'Recibo no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $nuevo_estado = sanitize($_POST['nuevo_estado']);
    $observaciones = sanitize($_POST['observaciones']);
    
    // Actualizar observaciones con el comentario del cambio de estado
    $fecha_actual = date('d/m/Y H:i');
    $usuario_actual = $_SESSION['user_name'];
    $observaciones_actualizadas = $recibo->observaciones . "\n\n[" . $fecha_actual . "] Cambio de estado de '" . $recibo->estado . "' a '" . $nuevo_estado . "' por " . $usuario_actual . ".\n" . $observaciones;
    
    // Actualizar estado del recibo
    $database->query('UPDATE Recibos SET estado = :estado, observaciones = :observaciones WHERE id = :id');
    $database->bind(':estado', $nuevo_estado);
    $database->bind(':observaciones', $observaciones_actualizadas);
    $database->bind(':id', $id);
    
    if ($database->execute()) {
        flash('mensaje', 'Estado del recibo actualizado correctamente', 'alert alert-success');
        redirect(URL_ROOT . '/modulos/recibos/ver.php?id=' . $id);
    } else {
        flash('mensaje', 'Error al actualizar el estado del recibo', 'alert alert-danger');
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Cambiar Estado de Recibo</h2>
        <p class="lead">
            Recibo #<?php echo $recibo->id; ?> - <?php echo $recibo->cliente_nombre; ?>
        </p>
    </div>
    <div class="col-md-6 text-end">
        <a href="ver.php?id=<?php echo $recibo->id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-exchange-alt"></i> Formulario de Cambio de Estado
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Información del Recibo</h5>
                <table class="table table-bordered">
                    <tr>
                        <th width="40%">Cliente:</th>
                        <td><?php echo $recibo->cliente_nombre; ?></td>
                    </tr>
                    <tr>
                        <th>Concepto:</th>
                        <td><?php echo $recibo->concepto; ?></td>
                    </tr>
                    <tr>
                        <th>Monto:</th>
                        <td><strong><?php echo formatMoney($recibo->monto); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Fecha de Pago:</th>
                        <td><?php echo formatDate($recibo->fecha_pago, 'd/m/Y'); ?></td>
                    </tr>
                    <tr>
                        <th>Estado Actual:</th>
                        <td>
                            <?php if($recibo->estado == 'pagado'): ?>
                            <span class="badge bg-success">Pagado</span>
                            <?php elseif($recibo->estado == 'pendiente'): ?>
                            <span class="badge bg-warning">Pendiente</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Cancelado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
                    <div class="mb-3">
                        <label for="nuevo_estado" class="form-label">Nuevo Estado *</label>
                        <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                            <option value="">Seleccione nuevo estado</option>
                            <option value="pagado" <?php echo ($recibo->estado == 'pagado') ? 'disabled' : ''; ?>>Pagado</option>
                            <option value="pendiente" <?php echo ($recibo->estado == 'pendiente') ? 'disabled' : ''; ?>>Pendiente</option>
                            <option value="cancelado" <?php echo ($recibo->estado == 'cancelado') ? 'disabled' : ''; ?>>Cancelado</option>
                        </select>
                        <div class="form-text">Seleccione el nuevo estado para el recibo.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Comentarios</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" placeholder="Agregue un comentario sobre este cambio de estado..."></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('¿Está seguro de cambiar el estado del recibo?');">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>