<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$recibo_id = isset($_GET['recibo_id']) ? (int)sanitize($_GET['recibo_id']) : 0;
if ($recibo_id <= 0) {
    flash('mensaje', 'Recibo no especificado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

$db = new Database();
$db->query('SELECT r.*, c.razon_social, c.rfc, c.domicilio_fiscal
            FROM recibos r JOIN clientes c ON c.id = r.cliente_id
            WHERE r.id = :id');
$db->bind(':id', $recibo_id);
$recibo = $db->single();
if (!$recibo) {
    flash('mensaje', 'Recibo no encontrado.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}
$db->query('SELECT p.* FROM recibos_pagos p WHERE p.recibo_id = :id ORDER BY p.fecha_pago ASC, p.id ASC');
$db->bind(':id', $recibo_id);
$pagos = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-3">
  <div class="col-md-8">
    <h3>Pagos del recibo</h3>
    <div class="text-muted"><?php echo htmlspecialchars($recibo->razon_social, ENT_QUOTES, 'UTF-8'); ?> — RFC: <?php echo htmlspecialchars($recibo->rfc, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>
  <div class="col-md-4 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fecha</th>
          <th class="text-end">Monto</th>
          <th>Método</th>
          <th>Referencia</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($pagos)): foreach ($pagos as $p): ?>
        <tr>
          <td><?php echo (int)$p->folio; ?></td>
          <td><?php echo htmlspecialchars(formatDate($p->fecha_pago), ENT_QUOTES, 'UTF-8'); ?></td>
          <td class="text-end"><?php echo formatMoney((float)$p->monto); ?></td>
          <td><?php echo htmlspecialchars($p->metodo ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($p->referencia ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <a class="btn btn-sm btn-outline-primary"
               href="<?php echo URL_ROOT; ?>/modulos/recibos/imprimir_pago.php?id=<?php echo (int)$p->id; ?>" target="_blank">
               <i class="fas fa-print"></i> Imprimir
            </a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="text-center text-muted">Sin pagos registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>