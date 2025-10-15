<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$recibo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($recibo_id > 0) {
    $db = new Database();
    // Se anexa "(Cortesía)" al concepto original para mantener un registro
    $db->query('UPDATE recibos SET
                    monto = 0,
                    monto_pagado = 0,
                    estado = "pagado",
                    concepto = IF(
                        concepto NOT LIKE "%(Cortesía%)",
                        CONCAT(concepto, " (Cortesía)"),
                        concepto
                    )
                WHERE id = :id');
    $db->bind(':id', $recibo_id);

    if ($db->execute()) {
        flash('mensaje', 'El recibo se ha marcado como cortesía correctamente.');
    } else {
        flash('mensaje', 'Error al marcar el recibo como cortesía.', 'alert alert-danger');
    }
}

redirect(URL_ROOT . '/modulos/recibos/index.php');
exit;