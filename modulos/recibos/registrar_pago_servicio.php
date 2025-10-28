<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... (Código de redirección y obtención de datos POST igual que antes) ...
$redirect_url = URL_ROOT . '/modulos/recibos/index.php';
// ... (Reconstrucción de $redirect_params igual que antes) ...

$recibo_id = filter_input(INPUT_POST, 'recibo_id', FILTER_VALIDATE_INT);
$monto_pago = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
$fecha_pago = sanitize($_POST['fecha_pago'] ?? date('Y-m-d'));
$metodo = sanitize($_POST['metodo'] ?? '');
$referencia = sanitize($_POST['referencia'] ?? '');
$observaciones = sanitize($_POST['observaciones'] ?? '');
$usuario_id = $_SESSION['user_id'] ?? null;

if (!$recibo_id || !$monto_pago || $monto_pago <= 0 || !$usuario_id) {
    flash('mensaje', 'Datos de pago inválidos, faltantes o sesión no iniciada.', 'alert alert-danger');
    redirect($redirect_url);
}

$db = new Database();
$transaccion_iniciada = false;

try {
    // Iniciar transacción
    if (method_exists($db, 'beginTransaction')) {
        $db->beginTransaction();
        $transaccion_iniciada = true;
    }

    // 1. Obtener datos actuales del recibo
    $db->query('SELECT monto, monto_pagado FROM recibos WHERE id = :recibo_id');
    $db->bind(':recibo_id', $recibo_id);
    $recibo = $db->single();

    if (!$recibo) {
        throw new Exception("Recibo ID {$recibo_id} no encontrado.");
    }

    // *** CORRECCIÓN: Calcular siguiente folio GLOBAL (basado en tu recibos_service.php) ***
    $db->query('SELECT MAX(folio) + 1 AS next_folio FROM recibos_pagos'); // SIN WHERE
    $row_folio = $db->single();
    $siguiente_folio = $row_folio->next_folio ?? 1; // Inicia en 1 si la tabla está vacía (MAX(folio) es NULL)
    error_log("Siguiente folio GLOBAL calculado: {$siguiente_folio}");
    // *** FIN CORRECCIÓN ***

    $monto_total_recibo = (float)($recibo->monto ?? 0);
    $monto_pagado_actual = (float)($recibo->monto_pagado ?? 0);
    $saldo_actual = round($monto_total_recibo - $monto_pagado_actual, 2);

    // Validar pago excedente
    if ($monto_pago > ($saldo_actual + 0.001)) {
         throw new Exception("El monto del pago (\$" . number_format($monto_pago, 2) . ") excede el saldo pendiente (\$" . number_format($saldo_actual, 2) . ").");
    }

    $nuevo_monto_pagado = round($monto_pagado_actual + $monto_pago, 2);

    // 2. Insertar pago en recibos_pagos (AÑADIR FOLIO)
    $db->query('INSERT INTO recibos_pagos (recibo_id, folio, fecha_pago, monto, metodo, referencia, observaciones, usuario_id)
                VALUES (:recibo_id, :folio, :fecha_pago, :monto, :metodo, :referencia, :observaciones, :usuario_id)');
    $db->bind(':recibo_id', $recibo_id);
    $db->bind(':folio', $siguiente_folio); // <-- Usar el folio global calculado
    $db->bind(':fecha_pago', $fecha_pago);
    $db->bind(':monto', $monto_pago);
    $db->bind(':metodo', $metodo);
    $db->bind(':referencia', $referencia);
    $db->bind(':observaciones', $observaciones);
    $db->bind(':usuario_id', $usuario_id);
    $db->execute();

    // 3. Actualizar recibo (sin cambios)
    $nuevo_estado_recibo = ($nuevo_monto_pagado >= ($monto_total_recibo - 0.001)) ? 'pagado' : 'pendiente';
    $db->query('UPDATE recibos SET monto_pagado = :monto_pagado, estado = :estado, fecha_pago = :fecha_pago WHERE id = :recibo_id');
    $db->bind(':monto_pagado', $nuevo_monto_pagado);
    $db->bind(':estado', $nuevo_estado_recibo);
    $db->bind(':fecha_pago', $fecha_pago);
    $db->bind(':recibo_id', $recibo_id);
    $db->execute();

    // --- LÓGICA ACTUALIZAR control_honorarios (sin cambios) ---
    if ($nuevo_estado_recibo === 'pagado') {
        // ... (código existente para actualizar control_honorarios) ...
        $db->query('SELECT id FROM recibo_servicios WHERE recibo_id = :recibo_id');
        $db->bind(':recibo_id', $recibo_id);
        $lineas_servicio = $db->resultSet();
        if ($lineas_servicio) {
            $lineas_ids = array_map(function($linea) { return $linea->id ?? null; }, $lineas_servicio);
            $lineas_ids = array_filter($lineas_ids);
            if (!empty($lineas_ids)) {
                $placeholders_named = [];
                $bind_map = [':fecha_pago' => $fecha_pago];
                foreach ($lineas_ids as $index => $linea_id) {
                    $key = ':linea_id_' . $index;
                    $placeholders_named[] = $key;
                    $bind_map[$key] = $linea_id;
                }
                $placeholders_in_string = implode(', ', $placeholders_named);
                $sql_update_control = "UPDATE control_honorarios SET estado = 'pagado', fecha_pago = :fecha_pago WHERE recibo_servicio_id IN ({$placeholders_in_string}) AND estado != 'cortesia'";
                $db->query($sql_update_control);
                foreach ($bind_map as $placeholder => $value) { $db->bind($placeholder, $value); }
                $db->execute();
            }
        }
    }
    // --- FIN LÓGICA ---

    // Confirmar transacción
    if ($transaccion_iniciada && method_exists($db, 'commit')) {
        $db->commit();
    }
    flash('mensaje', 'Pago registrado correctamente con Folio #' . $siguiente_folio);

} catch (Exception $e) {
    if ($transaccion_iniciada && $db && method_exists($db, 'rollBack')) {
        try { $db->rollBack(); } catch (Exception $re) { /* Ignorar */ }
    }
    error_log("!!! Error al registrar pago para Recibo ID {$recibo_id}: " . $e->getMessage());
    flash('mensaje', 'Error al registrar el pago: ' . $e->getMessage(), 'alert alert-danger');
}

// Redirigir siempre al final
redirect($redirect_url);
?>