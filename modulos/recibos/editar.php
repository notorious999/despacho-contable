<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$id = isset($_GET['id']) ? (int)sanitize($_GET['id']) : 0;
$db = new Database();
$db->query('SELECT r.*, c.razon_social, c.rfc
            FROM recibos r
            LEFT JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id = :id');
$db->bind(':id', $id);
$recibo = $db->single();
if (!$recibo) {
  flash('mensaje','Recibo no encontrado.','alert alert-danger');
  redirect(URL_ROOT.'/modulos/recibos/index.php'); exit;
}

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-3">
  <div class="col"><h3>Editar recibo</h3></div>
  <div class="col text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>
<?php flash('mensaje'); ?>

<div class="card"><div class="card-body">
  <form method="post" action="<?php echo URL_ROOT; ?>/modulos/recibos/guardar_edicion.php">
    <input type="hidden" name="id" value="<?php echo (int)$recibo->id; ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Cliente / Externo</label>
        <div class="form-control" readonly>
          <?php
            if ($recibo->cliente_id) {
              echo htmlspecialchars($recibo->razon_social . ' (' . $recibo->rfc . ')', ENT_QUOTES, 'UTF-8');
            } else {
              echo htmlspecialchars(($recibo->externo_nombre ?? 'Externo') . ' (' . ($recibo->externo_rfc ?? '-') . ')', ENT_QUOTES, 'UTF-8');
            }
          ?>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Periodo inicio</label>
        <input type="date" class="form-control" name="periodo_inicio" value="<?php echo htmlspecialchars($recibo->periodo_inicio, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Periodo fin</label>
        <input type="date" class="form-control" name="periodo_fin" value="<?php echo htmlspecialchars($recibo->periodo_fin, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div class="col-md-8">
        <label class="form-label">Concepto</label>
        <input type="text" class="form-control" name="concepto" value="<?php echo htmlspecialchars($recibo->concepto ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">Monto</label>
        <input type="number" step="0.01" min="<?php echo number_format((float)$recibo->monto_pagado,2,'.',''); ?>" name="monto" class="form-control" value="<?php echo htmlspecialchars(number_format((float)$recibo->monto,2,'.',''), ENT_QUOTES, 'UTF-8'); ?>" required>
        <div class="form-text">Pagado: <?php echo number_format((float)$recibo->monto_pagado,2,'.',','); ?></div>
      </div>
      <div class="col-md-2">
        <label class="form-label">Vencimiento</label>
        <input type="date" class="form-control" name="fecha_vencimiento" value="<?php echo htmlspecialchars($recibo->fecha_vencimiento ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-md-12">
        <label class="form-label">Observaciones</label>
        <textarea class="form-control" name="observaciones" rows="2"><?php echo htmlspecialchars($recibo->observaciones ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
      </div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
      <?php if ((float)$recibo->monto_pagado <= 0 && $recibo->estatus !== 'cancelado'): ?>
      <a class="btn btn-outline-danger" href="<?php echo URL_ROOT; ?>/modulos/recibos/cancelar.php?id=<?php echo (int)$recibo->id; ?>"
         onclick="return confirm('¿Cancelar este recibo? Esta acción no se puede deshacer.');">
        <i class="fas fa-ban"></i> Cancelar
      </a>
      <?php else: ?>
        <button type="button" class="btn btn-outline-secondary" disabled title="No se puede cancelar: tiene pagos o ya está cancelado">
          <i class="fas fa-ban"></i> Cancelar
        </button>
      <?php endif; ?>
    </div>
  </form>
</div></div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>