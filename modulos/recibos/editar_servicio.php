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
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// Obtener datos del recibo
$db->query('SELECT * FROM recibos WHERE id = :id AND tipo = "servicio"');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

if (!$recibo) {
    flash('mensaje', 'El recibo de servicio no fue encontrado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// Obtener servicios asociados
$db->query('SELECT * FROM recibo_servicios WHERE recibo_id = :id ORDER BY id ASC');
$db->bind(':id', $recibo_id);
$servicios = $db->resultSet();

// Lista de clientes
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus = "activo" ORDER BY razon_social');
$clientes = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Editar Recibo de Servicio #<?php echo $recibo->id; ?></h3>
            </div>
            <div class="card-body">
                <form action="guardar_edicion_servicio.php" method="post">
                    <input type="hidden" name="id" value="<?php echo $recibo->id; ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cliente_id" class="form-label">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required>
                                <?php foreach($clientes as $cliente): ?>
                                <option value="<?php echo $cliente->id; ?>" <?php echo ($recibo->cliente_id == $cliente->id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente->razon_social . ' (' . $cliente->rfc . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha" class="form-label">Fecha del Recibo *</label>
                            <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($recibo->fecha_creacion))); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="monto_pagado" class="form-label">Monto Pagado (Editar con precaución):</label>
                        <input type="number" step="0.01" name="monto_pagado" class="form-control" value="<?php echo htmlspecialchars($recibo->monto_pagado); ?>" required>
                    </div>

                    <hr>
                    <h5>Servicios</h5>
                    <div id="servicios-container">
                        <?php foreach($servicios as $index => $servicio): ?>
                        <div class="row servicio-item mb-2">
                            <div class="col-md-7"><input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" value="<?php echo htmlspecialchars($servicio->descripcion); ?>" required></div>
                            <div class="col-md-3"><input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.01" min="0.00" value="<?php echo htmlspecialchars($servicio->importe); ?>" required></div>
                            <div class="col-md-2"><button type="button" class="btn btn-danger remove-servicio">Eliminar</button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-servicio" class="btn btn-success mb-3"><i class="fas fa-plus"></i> Agregar Servicio</button>

                    <div class="row">
                        <div class="col-md-12 text-end">
                            <h4>Total: $<span id="total">0.00</span></h4>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary">Cancelar Cambios</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>

                <?php if ($recibo->estatus === 'activo') : ?>
                <hr>
                <div class="mt-4 p-3 border border-danger rounded">
                    <h4>Cancelar Recibo</h4>
                    <p class="text-danger"><strong>¡Atención!</strong> Esta acción es irreversible y marcará el recibo como cancelado.</p>
                    <form action="guardar_edicion_servicio.php" method="post" onsubmit="return confirm('¿Estás seguro de que deseas cancelar este recibo? Esta acción no se puede deshacer.');">
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

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect = new Choices('#cliente_id', {
      searchEnabled: true,
      itemSelectText: 'Presiona para seleccionar',
    });
    
    const container = document.getElementById('servicios-container');
    
    document.getElementById('add-servicio').addEventListener('click', function () {
        const newItem = document.createElement('div');
        newItem.classList.add('row', 'servicio-item', 'mb-2');
        newItem.innerHTML = `
            <div class="col-md-7"><input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" required></div>
            <div class="col-md-3"><input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.01" min="0.00" required></div>
            <div class="col-md-2"><button type="button" class="btn btn-danger remove-servicio">Eliminar</button></div>
        `;
        container.appendChild(newItem);
    });

    container.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('remove-servicio')) {
            e.target.closest('.servicio-item').remove();
            updateTotal();
        }
    });

    container.addEventListener('input', function (e) {
        if (e.target && e.target.classList.contains('importe')) {
            updateTotal();
        }
    });

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.importe').forEach(function (input) {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('total').textContent = total.toFixed(2);
    }
    
    updateTotal(); // Calcular total al cargar la página
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>