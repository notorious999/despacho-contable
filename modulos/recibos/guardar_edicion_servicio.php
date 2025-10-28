<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

$db = new Database();
$recibo_id = (int)$_POST['id'];

// Escenario 1: Cancelar el recibo
if (isset($_POST['cancelar']) && $_POST['cancelar'] == '1') {
    $cancel_reason = sanitize($_POST['cancel_reason'] ?? 'Cancelado manualmente por el usuario.');
    $cancelled_by = $_SESSION['user_id'] ?? null;

    $db->query('UPDATE recibos SET estatus = "cancelado", cancel_reason = :reason, cancelled_at = NOW(), cancelled_by = :user_id WHERE id = :id');
    $db->bind(':reason', $cancel_reason);
    $db->bind(':user_id', $cancelled_by);
    $db->bind(':id', $recibo_id);

    if ($db->execute()) {
        flash('mensaje', 'El recibo de servicio ha sido cancelado correctamente.');
    } else {
        flash('mensaje', 'Error al cancelar el recibo.', 'alert alert-danger');
    }
    redirect(URL_ROOT . '/modulos/recibos/index.php');
    exit;
}

// Escenario 2: Actualizar la información del recibo
$cliente_id = (int)$_POST['cliente_id'];
$fecha = sanitize($_POST['fecha']);
$monto_pagado = (float)$_POST['monto_pagado'];
$descripciones = $_POST['descripcion'];
$importes = $_POST['importe'];

$monto_total = 0;
foreach ($importes as $importe) {
    $monto_total += (float)$importe;
}

$estado = ($monto_pagado >= $monto_total) ? 'pagado' : 'pendiente';

// Iniciar transacción
$db->beginTransaction();

try {
    // 1. Actualizar la tabla principal de recibos
    $db->query('UPDATE recibos SET cliente_id = :cid, monto = :monto, monto_pagado = :pagado, estado = :estado, fecha_creacion = :fecha WHERE id = :id');
    $db->bind(':cid', $cliente_id);
    $db->bind(':monto', $monto_total);
    $db->bind(':pagado', $monto_pagado);
    $db->bind(':estado', $estado);
    $db->bind(':fecha', $fecha . ' ' . date('H:i:s')); // Mantener la hora original o actualizarla
    $db->bind(':id', $recibo_id);
    $db->execute();

    // 2. Eliminar los servicios anteriores
    $db->query('DELETE FROM recibo_servicios WHERE recibo_id = :id');
    $db->bind(':id', $recibo_id);
    $db->execute();

    // 3. Insertar los nuevos servicios
    $db->query('INSERT INTO recibo_servicios (recibo_id, descripcion, importe) VALUES (:recibo_id, :desc, :imp)');
    for ($i = 0; $i < count($descripciones); $i++) {
        if (!empty($descripciones[$i])) {
            $db->bind(':recibo_id', $recibo_id);
            $db->bind(':desc', sanitize($descripciones[$i]));
            $db->bind(':imp', (float)$importes[$i]);
            $db->execute();
        }
    }

    // Si todo fue bien, confirmar la transacción
    $db->endTransaction();
    flash('mensaje', 'Recibo de servicio actualizado correctamente.');

} catch (Exception $e) {
    // Si algo falla, revertir los cambios
    $db->cancelTransaction();
    flash('mensaje', 'Error al actualizar el recibo: ' . $e->getMessage(), 'alert alert-danger');
}

redirect(URL_ROOT . '/modulos/recibos/index.php');
?>