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

// Obtener los datos del recibo
$db->query('SELECT * FROM recibos WHERE id = :id AND cliente_id IS NULL');
$db->bind(':id', $recibo_id);
$recibo = $db->single();

if (!$recibo) {
  flash('mensaje', 'El recibo externo no fue encontrado.', 'alert-danger');
  redirect(URL_ROOT . '/modulos/recibos/externos.php');
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
          <label for="monto" class="form-label">Monto <span class="text-danger">*</span></label>
          <input type="number" step="0.01" class="form-control" id="monto" name="monto" value="<?php echo htmlspecialchars($recibo->monto ?? 0, ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
        <div class="col-md-4 mb-3">
          <label for="periodo_inicio" class="form-label">Fecha del Recibo <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="periodo_inicio" name="periodo_inicio" value="<?php echo htmlspecialchars($recibo->periodo_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>
      </div>

      <div class="mb-3">
        <label for="concepto" class="form-label">Concepto <span class="text-danger">*</span></label>
        <textarea class="form-control" id="concepto" name="concepto" rows="3" required><?php echo htmlspecialchars($recibo->concepto ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>

      <div class="d-flex justify-content-end">
        <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary me-2">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>