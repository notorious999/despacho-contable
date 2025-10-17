<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();
$recibo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recibo_id === 0) {
    redirect(URL_ROOT . '/modulos/recibos');
}

// Obtener datos del recibo
$db->query('SELECT * FROM recibos WHERE id = :id');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

if (!$recibo) {
    flash('mensaje', 'El recibo no fue encontrado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos');
}

// Lista de clientes
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Editar Recibo de Honorarios</h3>
            </div>
            <div class="card-body">
                <form action="<?php echo URL_ROOT; ?>/modulos/recibos/guardar_edicion.php" method="post">
                    <input type="hidden" name="id" value="<?php echo $recibo->id; ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cliente_id" class="form-label">Cliente:</label>
                            <select name="cliente_id" id="cliente_id" class="form-select" required>
                                <?php foreach ($clientes as $cliente) : ?>
                                    <option value="<?php echo (int)$cliente->id; ?>" <?php echo ($recibo->cliente_id == $cliente->id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente->razon_social . ' (' . $cliente->rfc . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="concepto" class="form-label">Concepto:</label>
                            <input type="text" name="concepto" class="form-control" value="<?php echo htmlspecialchars($recibo->concepto); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="monto" class="form-label">Monto Total:</label>
                            <input type="number" step="0.01" name="monto" class="form-control" value="<?php echo htmlspecialchars($recibo->monto); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="monto_pagado" class="form-label">Monto Pagado (Editar con precaución):</label>
                            <input type="number" step="0.01" name="monto_pagado" class="form-control" value="<?php echo htmlspecialchars($recibo->monto_pagado); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="periodo_inicio" class="form-label">Periodo Inicio:</label>
                            <input type="date" name="periodo_inicio" class="form-control" value="<?php echo htmlspecialchars($recibo->periodo_inicio); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="periodo_fin" class="form-label">Periodo Fin:</label>
                            <input type="date" name="periodo_fin" class="form-control" value="<?php echo htmlspecialchars($recibo->periodo_fin); ?>">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo URL_ROOT; ?>/modulos/recibos" class="btn btn-secondary">Cancelar Cambios</a>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </form>

                <?php if ($recibo->estatus === 'activo') : ?>
                <hr>
                <div class="mt-4 p-3 border border-danger rounded">
                    <h4>Cancelar Recibo</h4>
                    <p class="text-danger"><strong>¡Atención!</strong> Esta acción es irreversible. El recibo se marcará como cancelado.</p>
                    <form action="<?php echo URL_ROOT; ?>/modulos/recibos/guardar_edicion.php" method="post" onsubmit="return confirm('¿Estás seguro de que deseas cancelar este recibo? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="id" value="<?php echo $recibo->id; ?>">
                        <input type="hidden" name="cancelar" value="1">
                        <div class="mb-3">
                            <label for="cancel_reason" class="form-label">Motivo de la cancelación (opcional):</label>
                            <textarea name="cancel_reason" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>