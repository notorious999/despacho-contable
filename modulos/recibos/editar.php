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
$database->query('SELECT * FROM Recibos WHERE id = :id');
$database->bind(':id', $id);
$recibo = $database->single();

// Verificar que el recibo existe
if (!$recibo) {
    flash('mensaje', 'Recibo no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// No permitir editar recibos cancelados
if ($recibo->estado == 'cancelado') {
    flash('mensaje', 'No se pueden editar recibos cancelados', 'alert alert-warning');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// Obtener clientes para el selector
$database->query('SELECT id, razon_social, rfc FROM Clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $database->resultSet();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $cliente_id = sanitize($_POST['cliente_id']);
    $concepto = sanitize($_POST['concepto']);
    $tipo = sanitize($_POST['tipo']);
    $monto = sanitize($_POST['monto']);
    $fecha_pago = sanitize($_POST['fecha_pago']);
    $estado = sanitize($_POST['estado']);
    $observaciones = sanitize($_POST['observaciones']);
    
    // Procesar vencimiento si aplica
    $vencimiento = null;
    $duracion = null;
    
    if (isset($_POST['con_vencimiento']) && $_POST['con_vencimiento'] == '1') {
        $vencimiento = sanitize($_POST['vencimiento']);
        $duracion = sanitize($_POST['duracion']);
    }
    
    // Validaciones
    $error = false;
    
    if (empty($cliente_id) || empty($concepto) || empty($monto) || empty($fecha_pago)) {
        flash('mensaje', 'Todos los campos marcados con * son obligatorios', 'alert alert-danger');
        $error = true;
    }
    
    if (!is_numeric($monto) || $monto <= 0) {
        flash('mensaje', 'El monto debe ser un valor numérico positivo', 'alert alert-danger');
        $error = true;
    }
    
    // Si no hay errores, actualizar
    if (!$error) {
        $database->query('UPDATE Recibos SET 
                          cliente_id = :cliente_id, 
                          concepto = :concepto, 
                          tipo = :tipo, 
                          monto = :monto, 
                          fecha_pago = :fecha_pago, 
                          vencimiento = :vencimiento, 
                          duracion = :duracion, 
                          estado = :estado, 
                          observaciones = :observaciones
                          WHERE id = :id');
        
        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':concepto', $concepto);
        $database->bind(':tipo', $tipo);
        $database->bind(':monto', $monto);
        $database->bind(':fecha_pago', $fecha_pago);
        $database->bind(':vencimiento', $vencimiento);
        $database->bind(':duracion', $duracion);
        $database->bind(':estado', $estado);
        $database->bind(':observaciones', $observaciones);
        $database->bind(':id', $id);
        
        if ($database->execute()) {
            flash('mensaje', 'Recibo actualizado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/recibos/index.php');
        } else {
            flash('mensaje', 'Error al actualizar el recibo', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Editar Recibo</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Formulario de Edición
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?php echo $cliente->id; ?>" <?php echo ($recibo->cliente_id == $cliente->id) ? 'selected' : ''; ?>>
                            <?php echo $cliente->razon_social . ' (' . $cliente->rfc . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="concepto" class="form-label">Concepto *</label>
                    <input type="text" class="form-control" id="concepto" name="concepto" value="<?php echo $recibo->concepto; ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Pago *</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="contado" <?php echo ($recibo->tipo == 'contado') ? 'selected' : ''; ?>>Contado</option>
                        <option value="abono" <?php echo ($recibo->tipo == 'abono') ? 'selected' : ''; ?>>Abono</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="monto" class="form-label">Monto *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" value="<?php echo $recibo->monto; ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="fecha_pago" class="form-label">Fecha de Pago *</label>
                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?php echo $recibo->fecha_pago; ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado *</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="pagado" <?php echo ($recibo->estado == 'pagado') ? 'selected' : ''; ?>>Pagado</option>
                        <option value="pendiente" <?php echo ($recibo->estado == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="con_vencimiento" name="con_vencimiento" value="1" <?php echo (!empty($recibo->vencimiento)) ? 'checked' : ''; ?> onchange="toggleVencimiento()">
                        <label class="form-check-label" for="con_vencimiento">
                            Establecer vencimiento
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3" id="seccion_vencimiento" style="display: <?php echo (!empty($recibo->vencimiento)) ? 'flex' : 'none'; ?>;">
                <div class="col-md-6">
                    <label for="vencimiento" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="vencimiento" name="vencimiento" value="<?php echo $recibo->vencimiento; ?>">
                </div>
                <div class="col-md-6">
                    <label for="duracion" class="form-label">Duración</label>
                    <select class="form-select" id="duracion" name="duracion">
                        <option value="1 mes" <?php echo ($recibo->duracion == '1 mes') ? 'selected' : ''; ?>>1 mes</option>
                        <option value="2 meses" <?php echo ($recibo->duracion == '2 meses') ? 'selected' : ''; ?>>2 meses</option>
                        <option value="3 meses" <?php echo ($recibo->duracion == '3 meses') ? 'selected' : ''; ?>>3 meses</option>
                        <option value="6 meses" <?php echo ($recibo->duracion == '6 meses') ? 'selected' : ''; ?>>6 meses</option>
                        <option value="1 año" <?php echo ($recibo->duracion == '1 año') ? 'selected' : ''; ?>>1 año</option>
                        <option value="2 años" <?php echo ($recibo->duracion == '2 años') ? 'selected' : ''; ?>>2 años</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?php echo $recibo->observaciones; ?></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Recibo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleVencimiento() {
        var checkbox = document.getElementById('con_vencimiento');
        var seccion = document.getElementById('seccion_vencimiento');
        
        if (checkbox.checked) {
            seccion.style.display = 'flex';
            
            // Calcular fecha de vencimiento automáticamente si no hay una previamente
            if (!document.getElementById('vencimiento').value) {
                var fechaPago = document.getElementById('fecha_pago').value;
                if (fechaPago) {
                    var fecha = new Date(fechaPago);
                    fecha.setMonth(fecha.getMonth() + 1); // Por defecto, un mes
                    
                    var year = fecha.getFullYear();
                    var month = (fecha.getMonth() + 1).toString().padStart(2, '0');
                    var day = fecha.getDate().toString().padStart(2, '0');
                    
                    document.getElementById('vencimiento').value = `${year}-${month}-${day}`;
                }
            }
        } else {
            seccion.style.display = 'none';
            document.getElementById('vencimiento').value = '';
            document.getElementById('duracion').value = '1 mes';
        }
    }
    
    // Actualizar vencimiento cuando cambia la duración
    document.getElementById('duracion').addEventListener('change', function() {
        var fechaPago = new Date(document.getElementById('fecha_pago').value);
        var duracion = this.value;
        
        if (duracion.includes('mes')) {
            var meses = parseInt(duracion);
            fechaPago.setMonth(fechaPago.getMonth() + meses);
        } else if (duracion.includes('año')) {
            var años = parseInt(duracion);
            fechaPago.setFullYear(fechaPago.getFullYear() + años);
        }
        
        var year = fechaPago.getFullYear();
        var month = (fechaPago.getMonth() + 1).toString().padStart(2, '0');
        var day = fechaPago.getDate().toString().padStart(2, '0');
        
        document.getElementById('vencimiento').value = `${year}-${month}-${day}`;
    });
    
    // Actualizar vencimiento cuando cambia la fecha de pago
    document.getElementById('fecha_pago').addEventListener('change', function() {
        if (document.getElementById('con_vencimiento').checked) {
            var fechaPago = new Date(this.value);
            var duracion = document.getElementById('duracion').value;
            
            if (duracion.includes('mes')) {
                var meses = parseInt(duracion);
                fechaPago.setMonth(fechaPago.getMonth() + meses);
            } else if (duracion.includes('año')) {
                var años = parseInt(duracion);
                fechaPago.setFullYear(fechaPago.getFullYear() + años);
            }
            
            var year = fechaPago.getFullYear();
            var month = (fechaPago.getMonth() + 1).toString().padStart(2, '0');
            var day = fechaPago.getDate().toString().padStart(2, '0');
            
            document.getElementById('vencimiento').value = `${year}-${month}-${day}`;
        }
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>