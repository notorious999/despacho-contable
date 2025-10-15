<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// Consulta para obtener todos los lotes de pagos adelantados
$db->query('SELECT l.*, c.razon_social, u.nombre as usuario_nombre
            FROM pagos_adelantados_lotes l
            JOIN clientes c ON c.id = l.cliente_id
            LEFT JOIN usuarios u ON u.id = l.usuario_id
            ORDER BY l.fecha_pago DESC, l.id DESC');
$lotes = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-4 align-items-center">
  <div class="col-md-6">
    <h2 class="mb-0">Historial de Pagos Adelantados</h2>
  </div>
  <div class="col-md-6 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/pago_adelantado.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Registrar Nuevo Pago Adelantado
    </a>
  </div>
</div>

<?php flash('mensaje'); ?>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Folio Lote</th>
          <th>Cliente</th>
          <th>Fecha de Pago</th>
          <th class="text-end">Monto Total</th>
          <th>Método</th>
          <th>Registrado por</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($lotes)): foreach ($lotes as $lote): ?>
            <tr>
              <td><?php echo htmlspecialchars($lote->id); ?></td>
              <td><?php echo htmlspecialchars($lote->razon_social); ?></td>
              <td><?php echo formatDate($lote->fecha_pago); ?></td>
              <td class="text-end"><?php echo formatMoney((float)$lote->monto_total); ?></td>
              <td><?php echo htmlspecialchars($lote->metodo ?: 'N/A'); ?></td>
              <td><?php echo htmlspecialchars($lote->usuario_nombre ?: 'Sistema'); ?></td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-success"
                  href="<?php echo URL_ROOT; ?>/modulos/recibos/imprimir_pago_adelantado.php?lote_id=<?php echo (int)$lote->id; ?>" target="_blank">
                  <i class="fas fa-print"></i> Ver / Imprimir
                </a>
              </td>
            </tr>
          <?php endforeach; else: ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No se han registrado pagos adelantados.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>