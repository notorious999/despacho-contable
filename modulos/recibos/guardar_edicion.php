<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db = new Database();
    $recibo_id = (int)$_POST['id'];

    // Escenario 1: Cancelar el recibo
    if (isset($_POST['cancelar']) && $_POST['cancelar'] == '1') {
        $cancel_reason = sanitize($_POST['cancel_reason'] ?? 'Cancelado manualmente por el usuario.');
        $cancelled_by = $_SESSION['user_id'] ?? null;

        $db->query('UPDATE recibos SET 
                        estatus = "cancelado",
                        cancel_reason = :reason,
                        cancelled_at = NOW(),
                        cancelled_by = :user_id
                    WHERE id = :id');
        $db->bind(':reason', $cancel_reason);
        $db->bind(':user_id', $cancelled_by);
        $db->bind(':id', $recibo_id);

        if ($db->execute()) {
            // Opcional: Podrías considerar eliminar los pagos asociados para mantener la consistencia
            // $db->query('DELETE FROM recibos_pagos WHERE recibo_id = :id');
            // $db->bind(':id', $recibo_id);
            // $db->execute();
            flash('mensaje', 'El recibo ha sido cancelado correctamente.');
        } else {
            flash('mensaje', 'Error al cancelar el recibo.', 'alert alert-danger');
        }

    // Escenario 2: Actualizar la información del recibo
    } else {
        $cliente_id = (int)$_POST['cliente_id'];
        $concepto = sanitize($_POST['concepto']);
        $monto = (float)$_POST['monto'];
        $monto_pagado = (float)$_POST['monto_pagado'];
        $periodo_inicio = sanitize($_POST['periodo_inicio']);
        $periodo_fin = sanitize($_POST['periodo_fin']);

        // Determinar el nuevo estado basado en los montos
        $estado = ($monto_pagado >= $monto) ? 'pagado' : 'pendiente';

        $db->query('UPDATE recibos SET 
                        cliente_id = :cliente_id,
                        concepto = :concepto,
                        monto = :monto,
                        monto_pagado = :monto_pagado,
                        periodo_inicio = :periodo_inicio,
                        periodo_fin = :periodo_fin,
                        estado = :estado
                    WHERE id = :id');

        $db->bind(':cliente_id', $cliente_id);
        $db->bind(':concepto', $concepto);
        $db->bind(':monto', $monto);
        $db->bind(':monto_pagado', $monto_pagado);
        $db->bind(':periodo_inicio', $periodo_inicio);
        $db->bind(':periodo_fin', $periodo_fin);
        $db->bind(':estado', $estado);
        $db->bind(':id', $recibo_id);

        if ($db->execute()) {
            flash('mensaje', 'Recibo actualizado correctamente.');
        } else {
            flash('mensaje', 'Error al actualizar el recibo.', 'alert alert-danger');
        }
    }

    redirect(URL_ROOT . '/modulos/recibos/index.php');
} else {
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}
?>