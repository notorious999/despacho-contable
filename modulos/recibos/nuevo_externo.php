<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitizar y validar los datos del formulario
    $nombre = trim(sanitize($_POST['externo_nombre'] ?? ''));
    $rfc    = strtoupper(trim(sanitize($_POST['externo_rfc'] ?? '')));
    $fecha  = sanitize($_POST['fecha'] ?? '');
    $monto  = (float)($_POST['monto'] ?? 0);
    $concepto = trim(sanitize($_POST['concepto'] ?? ''));

    if ($nombre === '' || $fecha === '' || $monto <= 0 || $concepto === '') {
        flash('mensaje', 'Completa Nombre/Razón social, Fecha, Monto y Concepto.', 'alert alert-danger');
    } else {
        $db = new Database();
        $svc = new RecibosService();
        $new_recibo_id = 0;

        // 2. CORRECCIÓN: Insertar el recibo directamente en la base de datos
        $db->query('INSERT INTO recibos (
                        externo_nombre, externo_rfc, concepto, monto,
                        periodo_inicio, periodo_fin, origen, estatus,
                        usuario_id
                    ) VALUES (
                        :nombre, :rfc, :concepto, :monto,
                        :pi, :pf, :origen, :estatus,
                        :uid
                    )');
        $db->bind(':nombre', $nombre);
        $db->bind(':rfc', $rfc);
        $db->bind(':concepto', $concepto);
        $db->bind(':monto', $monto);
        $db->bind(':pi', $fecha); // Usamos la fecha como inicio y fin del periodo
        $db->bind(':pf', $fecha);
        $db->bind(':origen', 'manual');
        $db->bind(':estatus', 'activo');
        $db->bind(':uid', $_SESSION['user_id'] ?? null);

        if ($db->execute()) {
            $new_recibo_id = $db->lastInsertId();
        }

        if ($new_recibo_id > 0) {
            // 3. Pagar automáticamente el recibo recién creado
            $userId = $_SESSION['user_id'] ?? null;
            
            // CORRECCIÓN: Se usa el orden correcto de los argumentos
            $svc->registrarPago(
                (int)$new_recibo_id,
                $monto,
                $fecha, // La fecha del pago es la misma que la del recibo
                'Efectivo', // Método por defecto
                '', // Referencia vacía
                'Recibo externo creado y pagado automáticamente',
                $userId
            );

            flash('mensaje', 'Recibo externo creado y pagado correctamente.', 'alert alert-success');
            // Redirigir a la página de impresión que corresponda (ajusta si es necesario)
            redirect(URL_ROOT.'/modulos/recibos/externos.php');
            exit;
        } else {
            flash('mensaje','No se pudo crear el recibo externo.', 'alert alert-danger');
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

<div class="card">
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
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
                    <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Concepto *</label>
                    <input type="text" name="concepto" class="form-control" required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear y Pagar</button>
                <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>