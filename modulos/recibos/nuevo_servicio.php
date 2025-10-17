<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();
$db->query('SELECT id, razon_social, rfc FROM clientes WHERE estatus="activo" ORDER BY razon_social');
$clientes = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Nuevo Recibo de Servicio</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="servicios.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="guardar_servicio.php" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente *</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Seleccione un cliente</option>
                        <?php foreach($clientes as $cliente): ?>
                        <option value="<?php echo $cliente->id; ?>"><?php echo htmlspecialchars($cliente->razon_social . ' (' . $cliente->rfc . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="fecha" class="form-label">Fecha del Recibo *</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <hr>
            <h5>Servicios</h5>
            <div id="servicios-container">
                <div class="row servicio-item mb-2">
                    <div class="col-md-7"><input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" required></div>
                    <div class="col-md-3"><input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.01" min="0.00" required></div>
                    <div class="col-md-2"><button type="button" class="btn btn-danger remove-servicio">Eliminar</button></div>
                </div>
            </div>
            <button type="button" id="add-servicio" class="btn btn-success mb-3"><i class="fas fa-plus"></i> Agregar Servicio</button>

            <div class="row">
                <div class="col-md-12 text-end">
                    <h4>Total: $<span id="total">0.00</span></h4>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear Recibo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar el buscador para el selector de clientes
    const clienteSelect = new Choices('#cliente_id', {
      searchEnabled: true,
      itemSelectText: 'Presiona para seleccionar',
      noResultsText: 'No se encontraron resultados',
      noChoicesText: 'No hay más opciones para elegir',
      placeholder: true,
      placeholderValue: 'Busca o selecciona un cliente...'
    });

    // --- Tu código original para la suma y agregar/eliminar servicios (sin cambios) ---
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

    // Inicializar el total al cargar la página por si hay valores precargados
    updateTotal();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>