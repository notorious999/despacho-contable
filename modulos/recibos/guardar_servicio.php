<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Log de datos POST al inicio
error_log("guardar_servicio.php - Datos POST recibidos: " . print_r($_POST, true));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/recibos/index.php');
}

// ---- 1. Obtener y Sanitizar Datos ----
$cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$fecha = sanitize($_POST['fecha'] ?? date('Y-m-d'));
$descripciones = $_POST['descripcion'] ?? [];
$importes = $_POST['importe'] ?? [];
$es_honorario_flags = $_POST['es_honorario'] ?? [];
$filas_ids = $_POST['fila_id'] ?? []; // IDs únicos de fila
$periodos_por_fila_id = $_POST['periodos_pagados'] ?? []; // Array ['fila_id_unico' => ['periodo1', ...]]
$usuario_id = $_SESSION['user_id'] ?? null;
$meses_es = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

// Validaciones
if (!$cliente_id || empty($descripciones) || count($descripciones) !== count($importes) || !$usuario_id || count($descripciones) !== count($es_honorario_flags) || count($descripciones) !== count($filas_ids)) {
    error_log("Error guardar_servicio: Datos incompletos o arrays desalineados.");
    flash('mensaje', 'Datos incompletos, inválidos o sesión no iniciada.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/nuevo_servicio.php');
}

// ---- 2. Calcular Total y Concepto ----
$total = 0;
foreach ($importes as $importe) {
    $total += filter_var($importe, FILTER_VALIDATE_FLOAT) ?: 0;
}
$estado = ($total > 0) ? 'pendiente' : 'pagado';
$concepto_texto = '';
if (!empty($descripciones)) {
    $concepto_texto = count($descripciones) > 1 ? sanitize($descripciones[0]) . " y más" : sanitize($descripciones[0]);
}

// ---- 3. Obtener Monto Honorario Cliente ----
$honorario_mensual_cliente = 0;
if ($cliente_id) {
    // error_log("Obteniendo honorario para cliente ID: {$cliente_id}");
    try {
        $db_temp = new Database();
        $db_temp->query('SELECT honorarios FROM clientes WHERE id = :cliente_id');
        $db_temp->bind(':cliente_id', $cliente_id);
        $cliente_info = $db_temp->single();
        if ($cliente_info && isset($cliente_info->honorarios)) {
            $honorario_mensual_cliente = (float)$cliente_info->honorarios;
            // error_log("Honorario obtenido: {$honorario_mensual_cliente}");
        }
    } catch (Exception $e) { /* Ignorar error de lectura aquí, se validará después */ }
}

// ---- 4. Iniciar Transacción y Guardar ----
$db = new Database();
$transaccion_iniciada = false;
error_log("Iniciando proceso de guardado...");
try {
    if (method_exists($db, 'beginTransaction')) {
        $db->beginTransaction(); $transaccion_iniciada = true;
        // error_log("Transacción iniciada.");
    }

    // Insertar Cabecera del Recibo
    $db->query('INSERT INTO recibos (cliente_id, concepto, monto, fecha_creacion, estado, tipo, origen, usuario_id)
                VALUES (:cliente_id, :concepto, :monto, :fecha, :estado, "servicio", "manual", :usuario_id)');
    $db->bind(':cliente_id', $cliente_id);
    $db->bind(':concepto', $concepto_texto);
    $db->bind(':monto', $total);
    $db->bind(':fecha', $fecha);
    $db->bind(':estado', $estado);
    $db->bind(':usuario_id', $usuario_id);
    $db->execute();
    $recibo_id = $db->lastInsertId();
    if (!$recibo_id) { throw new Exception("No se pudo obtener el ID del recibo insertado."); }
    // error_log("Cabecera de recibo insertada con ID: {$recibo_id}");

    // Insertar Líneas de Servicio y Actualizar Control Honorarios
    // error_log("Iniciando bucle para insertar líneas de servicio...");
    for ($i = 0; $i < count($descripciones); $i++) {
        $descripcion_linea = sanitize($descripciones[$i]);
        $importe_linea = filter_var($importes[$i], FILTER_VALIDATE_FLOAT) ?: 0;
        $es_honorario = !empty($es_honorario_flags[$i]) && $es_honorario_flags[$i] == '1';
        $fila_id_actual = $filas_ids[$i] ?? null;

        // error_log("Procesando línea {$i}: EsHonorario=" . ($es_honorario ? 'SI' : 'NO') . ", FilaID='{$fila_id_actual}'");

        if (empty($descripcion_linea) && $importe_linea == 0 && !$es_honorario) {
            // error_log("Línea {$i} normal vacía, saltando.");
            continue;
        }

        $db->query('INSERT INTO recibo_servicios (recibo_id, descripcion, importe) VALUES (:recibo_id, :descripcion, :importe)');
        $db->bind(':recibo_id', $recibo_id);
        $db->bind(':descripcion', $descripcion_linea);
        $db->bind(':importe', $importe_linea);
        $db->execute();
        $last_servicio_id = $db->lastInsertId();
        if (!$last_servicio_id) { throw new Exception("No se pudo obtener el ID de la línea {$i}."); }
        // error_log("Línea servicio {$i} insertada con ID: {$last_servicio_id}");

        // ¿Es honorario Y tenemos periodos para esta fila?
        if ($es_honorario && $fila_id_actual && isset($periodos_por_fila_id[$fila_id_actual]) && is_array($periodos_por_fila_id[$fila_id_actual])) {
            // error_log("Línea {$i} es honorario. Procesando periodos...");
            $periodos_pagados = $periodos_por_fila_id[$fila_id_actual];
            
            // Validar que el monto de honorario se obtuvo
            if ($cliente_id && $honorario_mensual_cliente >= 0) { // Permitir 0 para cortesías
                $sql_control = "INSERT INTO control_honorarios (cliente_id, anio, mes, monto_mensual, estado, fecha_pago, recibo_servicio_id)
                                VALUES (:cliente_id, :anio, :mes, :monto_mensual, :estado, :fecha_pago, :recibo_servicio_id)
                                ON DUPLICATE KEY UPDATE
                                   estado = VALUES(estado), fecha_pago = VALUES(fecha_pago),
                                   recibo_servicio_id = VALUES(recibo_servicio_id), monto_mensual = VALUES(monto_mensual)";

                foreach ($periodos_pagados as $periodo) {
                    if (preg_match('/^(\d{4})-(\d{2})$/', $periodo, $matches)) {
                        $anio = intval($matches[1]);
                        $mes = intval($matches[2]);

                        // Si el importe de la línea era 0, es CORTESIA
                        $es_cortesia_linea = ($importe_linea == 0);
                        $estado_a_guardar = $es_cortesia_linea ? 'cortesia' : 'pendiente'; // Poner PENDIENTE por defecto
                        $monto_a_guardar = $es_cortesia_linea ? 0.00 : $honorario_mensual_cliente;
                        $fecha_pago_a_guardar = $es_cortesia_linea ? $fecha : null; // Fecha solo si es cortesía

                        error_log("=== INTENTANDO INSERTAR/ACTUALIZAR control_honorarios ===");
                        error_log("Datos: Cliente={$cliente_id}, Periodo={$anio}-{$mes}, Monto={$monto_a_guardar}, Estado={$estado_a_guardar}, ServicioID={$last_servicio_id}");

                        try {
                            $db->query($sql_control);
                            $db->bind(':cliente_id', $cliente_id);
                            $db->bind(':anio', $anio);
                            $db->bind(':mes', $mes);
                            $db->bind(':monto_mensual', $monto_a_guardar);
                            $db->bind(':estado', $estado_a_guardar);
                            $db->bind(':fecha_pago', $fecha_pago_a_guardar);
                            $db->bind(':recibo_servicio_id', $last_servicio_id);
                            $db->execute();
                            error_log(">>> ÉXITO al actualizar/insertar control_honorarios para {$anio}-{$mes}");
                        } catch (Exception $e_control) {
                            error_log("!!! ERROR SQL control_honorarios {$anio}-{$mes}: " . $e_control->getMessage());
                            throw $e_control; // Re-lanzar para que falle la transacción
                        }
                    } else { error_log("Formato de periodo inválido: " . $periodo); }
                } // Fin foreach $periodos_pagados
            } else {
                 error_log("ADVERTENCIA: Línea {$i} es honorario pero cliente_id ({$cliente_id}) no es válido o honorario ({$honorario_mensual_cliente}) es negativo.");
            }
        } elseif ($es_honorario) {
             error_log("ADVERTENCIA: Línea {$i} marcada como honorario pero faltan datos: FilaID='{$fila_id_actual}' o periodos en \$_POST['periodos_pagados']['{$fila_id_actual}'].");
        }
    } // Fin bucle for

    // --- Si todo fue bien, confirmar ----
    if ($transaccion_iniciada && method_exists($db, 'commit')) {
        $db->commit();
        // error_log("Commit realizado.");
    }
    flash('mensaje', 'Recibo de servicio creado correctamente.');
    redirect(URL_ROOT . '/modulos/recibos/index.php');

} catch (Exception $e) {
    // --- Si algo falla, revertir ---
    error_log("!!! EXCEPCIÓN CAPTURADA EN TRANSACCIÓN: " . $e->getMessage());
    try {
        if ($transaccion_iniciada && $db && method_exists($db, 'rollBack')) {
            $db->rollBack();
            // error_log("Rollback realizado.");
        }
    } catch (Exception $rollbackEx) { error_log("!!! Error adicional durante rollback: " . $rollbackEx->getMessage()); }

    flash('mensaje', 'Error al crear el recibo: Se produjo un error. Revise los logs.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/nuevo_servicio.php');
}
?>