<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener clientes para el selector
$database->query('SELECT id, razon_social, rfc FROM Clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $database->resultSet();

// Obtener cliente_id de la URL si está presente
$cliente_id = isset($_GET['cliente_id']) ? sanitize($_GET['cliente_id']) : '';

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
    
    // Si no hay errores, guardar
    if (!$error) {
        $database->query('INSERT INTO Recibos (cliente_id, concepto, tipo, monto, fecha_pago, vencimiento, duracion, estado, observaciones, usuario_id) 
                          VALUES (:cliente_id, :concepto, :tipo, :monto, :fecha_pago, :vencimiento, :duracion, :estado, :observaciones, :usuario_id)');
        
        $database->bind(':cliente_id', $cliente_id);
        $database->bind(':concepto', $concepto);
        $database->bind(':tipo', $tipo);
        $database->bind(':monto', $monto);
        $database->bind(':fecha_pago', $fecha_pago);
        $database->bind(':vencimiento', $vencimiento);
        $database->bind(':duracion', $duracion);
        $database->bind(':estado', $estado);
        $database->bind(':observaciones', $observaciones);
        $database->bind(':usuario_id', $_SESSION['user_id']);
        
        if ($database->execute()) {
            flash('mensaje', 'Recibo guardado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/recibos/index.php');
        } else {
            flash('mensaje', 'Error al guardar el recibo', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Nuevo Recibo</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-receipt"></i> Formulario de Registro
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?php echo $cliente->id; ?>" <?php echo ($cliente_id == $cliente->id) ? 'selected' : ''; ?>>
                            <?php echo $cliente->razon_social . ' (' . $cliente->rfc . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="concepto" class="form-label">Concepto *</label>
                    <input type="text" class="form-control" id="concepto" name="concepto" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Pago *</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="contado">Contado</option>
                        <option value="abono">Abono</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="monto" class="form-label">Monto *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="monto" name="monto" step="0.01" min="0.01" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="fecha_pago" class="form-label">Fecha de Pago *</label>
                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado *</label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="pagado">Pagado</option>
                        <option value="pendiente">Pendiente</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="con_vencimiento" name="con_vencimiento" value="1" onchange="toggleVencimiento()">
                        <label class="form-check-label" for="con_vencimiento">
                            Establecer vencimiento
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3" id="seccion_vencimiento" style="display: none;">
                <div class="col-md-6">
                    <label for="vencimiento" class="form-label">Fecha de Vencimiento</label>
                    <input type="date" class="form-control" id="vencimiento" name="vencimiento">
                </div>
                <div class="col-md-6">
                    <label for="duracion" class="form-label">Duración</label>
                    <select class="form-select" id="duracion" name="duracion">
                        <option value="1 mes">1 mes</option>
                        <option value="2 meses">2 meses</option>
                        <option value="3 meses">3 meses</option>
                        <option value="6 meses">6 meses</option>
                        <option value="1 año">1 año</option>
                        <option value="2 años">2 años</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Recibo
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
            
            // Calcular fecha de vencimiento automáticamente
            var fechaPago = document.getElementById('fecha_pago').value;
            if (fechaPago) {
                var fecha = new Date(fechaPago);
                fecha.setMonth(fecha.getMonth() + 1); // Por defecto, un mes
                
                var year = fecha.getFullYear();
                var month = (fecha.getMonth() + 1).toString().padStart(2, '0');
                var day = fecha.getDate().toString().padStart(2, '0');
                
                document.getElementById('vencimiento').value = `${year}-${month}-${day}`;
            }
        } else {
            seccion.style.display = 'none';
            document.getElementById('vencimiento').value = '';
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