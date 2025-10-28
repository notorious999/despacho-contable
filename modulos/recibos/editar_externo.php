<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Validar que el ID es un número
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  flash('mensaje', 'ID de recibo no válido.', 'alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/externos.php');
}

$recibo_id = (int)$_GET['id'];
$db = new Database();

// Obtener los datos del recibo principal
$db->query('SELECT * FROM recibos WHERE id = :id AND cliente_id IS NULL');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

if (!$recibo) {
  flash('mensaje', 'El recibo externo no fue encontrado.', 'alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/externos.php');
}

// *** NUEVO: Obtener los detalles del servicio de la tabla recibo_servicios ***
$db->query('SELECT id, descripcion, importe FROM recibo_servicios WHERE recibo_id = :recibo_id ORDER BY id ASC');
$db->bind(':recibo_id', $recibo_id);
$servicios = $db->resultSet();
// Si no hay servicios (quizás es un recibo antiguo), creamos una fila vacía por defecto
if(empty($servicios)) {
    $servicios = [ (object)['id' => 0, 'descripcion' => $recibo->concepto, 'importe' => $recibo->monto] ]; // Usar concepto/monto principal como fallback
}


include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-4 align-items-center">
  <div class="col-md-6">
    <h2>Editar Recibo Externo</h2>
  </div>
</div>

<?php flash('mensaje'); ?>

<div class="card">
  <div class="card-body">
    <form action="<?php echo URL_ROOT; ?>/modulos/recibos/guardar_edicion_externo.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?php echo (int)$recibo->id; ?>">

      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="externo_nombre" class="form-label">Nombre / Razón Social <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="externo_nombre" name="externo_nombre" value="<?php echo htmlspecialchars($recibo->externo_nombre ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="externo_rfc" class="form-label">RFC</label>
          <input type="text" class="form-control" id="externo_rfc" name="externo_rfc" value="<?php echo htmlspecialchars($recibo->externo_rfc ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label for="periodo_inicio" class="form-label">Fecha del Recibo <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="periodo_inicio" name="periodo_inicio" value="<?php echo htmlspecialchars($recibo->periodo_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
      </div>

      <hr>
      <h5>Servicios</h5>
      <div id="servicios-container">
        <?php foreach ($servicios as $servicio): ?>
        <div class="row servicio-item mb-2">
            <div class="col-md-7">
                <input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" required
                       value="<?php echo htmlspecialchars($servicio->descripcion ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-3">
                <input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.01" min="0.01" required
                       value="<?php echo htmlspecialchars($servicio->importe ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger remove-servicio">Eliminar</button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

      <button type="button" id="add-servicio" class="btn btn-success mb-3"><i class="fas fa-plus"></i> Agregar Servicio</button>

      <div class="row">
          <div class="col-md-12 text-end">
              <h4>Total: $<span id="total">0.00</span></h4>
          </div>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary me-2">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('servicios-container');

    document.getElementById('add-servicio').addEventListener('click', function () {
        const newItem = document.createElement('div');
        newItem.classList.add('row', 'servicio-item', 'mb-2');
        newItem.innerHTML = `
            <div class="col-md-7"><input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" required></div>
            <div class="col-md-3"><input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.01" min="0.01" required></div>
            <div class="col-md-2"><button type="button" class="btn btn-danger remove-servicio">Eliminar</button></div>
        `;
        container.appendChild(newItem);
        updateTotal(); // Actualizar el total al agregar
    });

    container.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('remove-servicio')) {
            // Prevenir que se elimine el último item
            if (container.querySelectorAll('.servicio-item').length > 1) {
                e.target.closest('.servicio-item').remove();
                updateTotal();
            } else {
                alert('Debe registrar al menos un servicio.');
            }
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

    // Inicializar el total al cargar la página
    updateTotal();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>