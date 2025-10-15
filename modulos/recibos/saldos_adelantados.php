<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// Obtener todos los pagos adelantados con el nombre del cliente
$db->query('
    SELECT pa.*, c.razon_social, c.rfc, (pa.monto_total - pa.monto_utilizado) as saldo_disponible
    FROM pagos_adelantados pa
    JOIN clientes c ON c.id = pa.cliente_id
    ORDER BY pa.fecha_pago DESC
');
$saldos = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
  <div class="col-md-6">
    <h2>Saldos Adelantados de Clientes</h2>
  </div>
  <div class="col-md-6 text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/index.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Volver a Recibos
    </a>
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/pago_adelantado.php" class="btn btn-primary">
      <i class="fas fa-plus"></i> Registrar Nuevo Adelanto
    </a>
  </div>
</div>

<?php flash('mensaje'); ?>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Fecha del Pago</th>
          <th class="text-end">Monto Original</th>
          <th class="text-end">Monto Utilizado</th>
          <th class="text-end">Saldo Disponible</th>
          <th>Estado</th>
          <th>Referencia</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($saldos)): foreach ($saldos as $saldo): ?>
          <tr>
            <td><?php echo htmlspecialchars($saldo->razon_social, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo formatDate($saldo->fecha_pago); ?></td>
            <td class="text-end"><?php echo formatMoney($saldo->monto_total); ?></td>
            <td class="text-end text-danger"><?php echo formatMoney($saldo->monto_utilizado); ?></td>
            <td class="text-end fw-bold text-success"><?php echo formatMoney($saldo->saldo_disponible); ?></td>
            <td>
              <span class="badge <?php echo $saldo->estado === 'disponible' ? 'bg-success' : 'bg-secondary'; ?>">
                <?php echo ucfirst($saldo->estado); ?>
              </span>
            </td>
            <td><?php echo htmlspecialchars($saldo->referencia ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center text-muted">No hay pagos adelantados registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>