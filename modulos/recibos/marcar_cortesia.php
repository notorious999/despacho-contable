<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
// Asegúrate de incluir el servicio de recibos
require_once __DIR__ . '/../../includes/recibos_service.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$recibo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Es buena práctica registrar qué usuario realiza la acción
$usuario_id = $_SESSION['user_id'] ?? null;

if ($recibo_id > 0) {
    $db = new Database();
    $service = new RecibosService();

    // --- LÓGICA MEJORADA ---

    // Paso 1 (Recomendado): Eliminar pagos anteriores para evitar inconsistencias.
    $db->query('DELETE FROM recibos_pagos WHERE recibo_id = :id');
    $db->bind(':id', $recibo_id);
    $db->execute();

    // Paso 2: Actualizar el recibo a su estado final de cortesía.
    $db->query('UPDATE recibos SET
                    monto = 0,
                    monto_pagado = 0,
                    estado = "pagado",
                    fecha_pago = CURDATE(),
                    concepto = IF(
                        concepto NOT LIKE "%(Cortesía%)",
                        CONCAT(concepto, " (Cortesía)"),
                        concepto
                    )
                WHERE id = :id');
    $db->bind(':id', $recibo_id);

    if ($db->execute()) {
        // Paso 3: Registrar un "pago" simbólico de $0.00 sin argumentos nombrados.
        $service->registrarPago(
            $recibo_id,                  // reciboId
            0.00,                       // monto
            date('Y-m-d'),              // fechaPago
            'Cortesía',                 // metodo
            '',                         // referencia
            'Recibo marcado como cortesía.', // observaciones
            $usuario_id                 // usuarioId
        );
        flash('mensaje', 'El recibo se ha marcado como cortesía correctamente.');
    } else {
        flash('mensaje', 'Error al marcar el recibo como cortesía.', 'alert alert-danger');
    }
}

redirect(URL_ROOT . '/modulos/recibos/index.php');
exit;