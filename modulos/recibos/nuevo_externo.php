<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$svc = new RecibosService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = trim(sanitize($_POST['externo_nombre'] ?? ''));
  $rfc    = strtoupper(trim(sanitize($_POST['externo_rfc'] ?? '')));
  $fecha  = sanitize($_POST['fecha'] ?? '');
  $monto  = (float)sanitize($_POST['monto'] ?? 0);
  $concepto = trim(sanitize($_POST['concepto'] ?? ''));

  if ($nombre === '' || $fecha === '' || $monto <= 0 || $concepto === '') {
    flash('mensaje', 'Completa Nombre/Razón social, Fecha, Monto y Concepto. RFC es opcional.', 'alert alert-danger');
  } else {
    // Usamos una sola fecha (inicio=fin)
    $pi = $fecha;
    $pf = $fecha;

    $id = $svc->crearReciboSiNoExiste(null, null, $pi, $pf, $monto, 'manual', null,
      ['nombre'=>$nombre, 'rfc'=>$rfc, 'domicilio'=>null, 'email'=>null, 'tel'=>null]);

    if ($id) {
      // Fijar concepto explícitamente
      $db = new Database();
      $db->query('UPDATE recibos SET concepto = :c WHERE id = :id');
      $db->bind(':c', $concepto);
      $db->bind(':id', $id);
      $db->execute();

      // Pagar automáticamente
      $userId = $_SESSION['user_id'] ?? null;
      $svc->registrarPago((int)$id, date('Y-m-d'), $monto, null, null, 'Pago automático (externo)', $userId);

      flash('mensaje','Recibo externo creado y pagado.','alert alert-success');
      redirect(URL_ROOT.'/modulos/recibos/imprimir_externo.php?recibo_id='.$id);
      exit;
    } else {
      flash('mensaje','No se pudo crear el recibo externo. Posible duplicado activo con misma fecha.', 'alert alert-danger');
    }
  }
}

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-3">
  <div class="col"><h3>Nuevo recibo (externo)</h3></div>
  <div class="col text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>

<?php flash('mensaje'); ?>

<div class="card"><div class="card-body">
  <form method="post">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nombre/Razón social *</label>
        <input type="text" name="externo_nombre" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">RFC</label>
        <input type="text" name="externo_rfc" class="form-control" maxlength="13">
      </div>

      <div class="col-md-4">
        <label class="form-label">Fecha *</label>
        <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Monto *</label>
        <input type="number" step="0.01" min="0" name="monto" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Concepto *</label>
        <input type="text" name="concepto" class="form-control" required>
      </div>
    </div>
    <div class="mt-3">
      <button class="btn btn-primary"><i class="fas fa-save"></i> Crear</button>
      <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary">Cancelar</a>
    </div>
  </form>
</div></div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>