<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// 1. Validar que el usuario esté logueado
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// 2. Obtener y validar el ID del cliente desde la URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash('mensaje', 'ID de cliente no válido.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

// 3. Obtener toda la información del cliente y el nombre del responsable
$database = new Database();
$sql = 'SELECT c.*, u.nombre AS responsable_nombre, u.apellidos AS responsable_apellidos
        FROM clientes c
        LEFT JOIN usuarios u ON c.responsable_id = u.id
        WHERE c.id = :id';
$database->query($sql);
$database->bind(':id', $id);
$cliente = $database->single();

// 4. Si el cliente no existe, redirigir
if (!$cliente) {
    flash('mensaje', 'Cliente no encontrado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Detalles del Cliente</h2>
        <h4 class="text-muted"><?php echo htmlspecialchars($cliente->razon_social); ?></h4>
    </div>
    <div class="col-md-4 text-end align-self-center">
        <a href="index.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver al Listado
        </a>
        <a href="editar.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Editar Cliente
        </a>
    </div>
</div>

<?php flash('mensaje'); ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-file-invoice-dollar"></i> Información Fiscal
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Razón Social:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($cliente->razon_social); ?></dd>

                    <dt class="col-sm-3">RFC:</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($cliente->rfc); ?></dd>

                    <dt class="col-sm-3">Actividad:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->actividad) ? htmlspecialchars($cliente->actividad) : 'N/D'; ?></dd>

                    <dt class="col-sm-3">Régimen Fiscal:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->regimen_fiscal) ? htmlspecialchars($cliente->regimen_fiscal) : 'N/D'; ?></dd>
                </dl>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-address-card"></i> Información de Contacto
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Email:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->email) ? htmlspecialchars($cliente->email) : 'N/D'; ?></dd>

                    <dt class="col-sm-3">Teléfono:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->telefono) ? htmlspecialchars($cliente->telefono) : 'N/D'; ?></dd>

                    <dt class="col-sm-3">Domicilio Fiscal:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->domicilio_fiscal) ? nl2br(htmlspecialchars($cliente->domicilio_fiscal)) : 'N/D'; ?></dd>

                    <dt class="col-sm-3">Código Postal:</dt>
                    <dd class="col-sm-9"><?php echo !empty($cliente->codigo_postal) ? htmlspecialchars($cliente->codigo_postal) : 'N/D'; ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-cog"></i> Información Administrativa
            </div>
            <div class="card-body">
                <dl>
                    <dt>Fecha de Alta:</dt>
                    <dd><?php echo htmlspecialchars(date('d/m/Y', strtotime($cliente->fecha_alta))); ?></dd>
                    <hr>
                    <dt>Estatus:</dt>
                    <dd>
                        <?php
                            $estatus_class = 'bg-secondary';
                            if ($cliente->estatus === 'activo') {
                                $estatus_class = 'bg-success';
                            } elseif ($cliente->estatus === 'baja' || $cliente->estatus === 'suspendido') {
                                $estatus_class = 'bg-danger';
                            }
                        ?>
                        <span class="badge <?php echo $estatus_class; ?> fs-6">
                            <?php echo ucfirst(htmlspecialchars($cliente->estatus)); ?>
                        </span>
                    </dd>
                    <hr>
                    <dt>Responsable Asignado:</dt>
                    <dd>
                        <?php 
                            if ($cliente->responsable_id) {
                                echo htmlspecialchars($cliente->responsable_nombre . ' ' . $cliente->responsable_apellidos);
                            } else {
                                echo 'Ninguno asignado';
                            }
                        ?>
                    </dd>
                    <hr>
                    <dt>Honorarios:</dt>
                    <dd class="fs-5 fw-bold text-success">$<?php echo htmlspecialchars(number_format($cliente->honorarios, 2)); ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>