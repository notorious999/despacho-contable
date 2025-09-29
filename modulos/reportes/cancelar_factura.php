<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar parámetros
if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    flash('mensaje', 'Parámetros incorrectos', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

$id = sanitize($_GET['id']);
$tipo = sanitize($_GET['tipo']);

// Validar tipo
if ($tipo != 'emitida' && $tipo != 'recibida') {
    flash('mensaje', 'Tipo de factura inválido', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Verificar que la factura existe
$tabla = ($tipo == 'emitida') ? 'CFDIs_Emitidas' : 'CFDIs_Recibidas';
$database->query("SELECT id, estado FROM $tabla WHERE id = :id");
$database->bind(':id', $id);
$factura = $database->single();

if (!$factura) {
    flash('mensaje', 'Factura no encontrada', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php?tipo=' . $tipo);
}

if ($factura->estado == 'cancelado') {
    flash('mensaje', 'La factura ya está cancelada', 'alert alert-warning');
    redirect(URL_ROOT . '/modulos/reportes/index.php?tipo=' . $tipo);
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $motivo_cancelacion = sanitize($_POST['motivo_cancelacion']);
    
    // Actualizar la factura a cancelada y poner a cero los montos
    $database->query("UPDATE $tabla SET 
                     estado = 'cancelado', 
                     fecha_cancelacion = NOW(), 
                     motivo_cancelacion = :motivo_cancelacion,
                     total = 0,
                     subtotal = 0,
                     iva_importe = 0,
                     tasa0_base = 0,
                     tasa16_base = 0
                     " . ($tipo == 'recibida' ? ", retencion_iva = 0, retencion_isr = 0, retencion_ieps = 0" : "") . "
                     WHERE id = :id");
    
    $database->bind(':motivo_cancelacion', $motivo_cancelacion);
    $database->bind(':id', $id);
    
    if ($database->execute()) {
        flash('mensaje', 'Factura marcada como cancelada correctamente', 'alert alert-success');
        redirect(URL_ROOT . '/modulos/reportes/index.php?tipo=' . $tipo);
    } else {
        flash('mensaje', 'Error al cancelar la factura', 'alert alert-danger');
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Cancelar Factura</h2>
        <p class="lead">
            Factura <?php echo ($tipo == 'emitida') ? 'Emitida' : 'Recibida'; ?> #<?php echo $id; ?>
        </p>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?php echo URL_ROOT; ?>/modulos/reportes/index.php?tipo=<?php echo $tipo; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-ban"></i> Formulario de Cancelación
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Importante:</strong> Marcar una factura como cancelada pondrá todos sus valores monetarios en cero y la excluirá de los reportes financieros.
        </div>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id . '&tipo=' . $tipo; ?>" method="post">
            <div class="mb-3">
                <label for="motivo_cancelacion" class="form-label">Motivo de Cancelación *</label>
                <textarea class="form-control" id="motivo_cancelacion" name="motivo_cancelacion" rows="3" required></textarea>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro de marcar esta factura como cancelada? Esta acción no se puede deshacer.');">
                        <i class="fas fa-ban"></i> Cancelar Factura
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>