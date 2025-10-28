<?php // <-- SIN ESPACIOS NI LÍNEAS ANTES

// 1. Incluir archivos
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';     // Tu clase Database
require_once __DIR__ . '/../../includes/functions.php'; // Para isLoggedIn()

// 2. Iniciar Sesión (si no está iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Establecer cabecera JSON (¡MUY IMPORTANTE!)
// Debe ser la PRIMERA salida enviada al navegador.
header('Content-Type: application/json');

// 4. Respuesta por defecto
$response = ['success' => false, 'message' => 'Error desconocido procesando la solicitud.'];

// 5. *** CORRECCIÓN: Definir el array de meses LOCALMENTE ***
$meses_es_local = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// 6. Validaciones iniciales
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$is_logged = isLoggedIn();
$has_data = isset($_POST['cliente_id'], $_POST['anio'], $_POST['mes']);

if ($is_post && $is_logged && $has_data)
{
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $anio = filter_input(INPUT_POST, 'anio', FILTER_VALIDATE_INT);
    $mes = filter_input(INPUT_POST, 'mes', FILTER_VALIDATE_INT);
    $usuario_id = $_SESSION['user_id'] ?? null;

    // Validar datos filtrados
    if ($cliente_id && $anio && $mes >= 1 && $mes <= 12 && $usuario_id) {

        $db = null;
        $transaccion_iniciada = false;

        try {
            $db = new Database();

            // Verificar cliente (solo validación)
            $db->query("SELECT honorarios FROM clientes WHERE id = :cliente_id AND estatus = 'activo'");
            $db->bind(':cliente_id', $cliente_id);
            $cliente_info = $db->single();
            if (!$cliente_info || ($cliente_info->honorarios ?? 0) <= 0) {
                 throw new Exception('Cliente no válido o sin honorarios.');
            }
            // $honorario_original = $cliente_info->honorarios ?? 0; // Se puede usar para log si se quiere

            // Verificar estado actual
            $db->query("SELECT id, estado FROM control_honorarios
                        WHERE cliente_id = :cliente_id AND anio = :anio AND mes = :mes");
            $db->bind(':cliente_id', $cliente_id); $db->bind(':anio', $anio); $db->bind(':mes', $mes);
            $control_actual = $db->single();
            $control_id_existente = null; $estado_actual = 'pendiente';
            if ($control_actual) { $control_id_existente = $control_actual->id; $estado_actual = $control_actual->estado; }

            if ($estado_actual === 'pendiente') {
                if (method_exists($db, 'beginTransaction')) {
                    $db->beginTransaction(); $transaccion_iniciada = true;
                }

                if ($control_id_existente) { // ACTUALIZAR
                    $db->query("UPDATE control_honorarios
                                SET estado = 'cortesia', monto_mensual = 0.00, fecha_pago = CURDATE()
                                WHERE id = :id");
                    $db->bind(':id', $control_id_existente);
                    $accion = "actualizado";
                } else { // INSERTAR
                    $db->query("INSERT INTO control_honorarios
                                (cliente_id, anio, mes, monto_mensual, estado, fecha_pago, recibo_servicio_id)
                                VALUES
                                (:cliente_id, :anio, :mes, 0.00, 'cortesia', CURDATE(), NULL)");
                    $db->bind(':cliente_id', $cliente_id); $db->bind(':anio', $anio); $db->bind(':mes', $mes);
                    $accion = "insertado";
                }
                $db->execute();

                // Asumir éxito si no hubo excepción
                if (true) {
                    if ($transaccion_iniciada && method_exists($db, 'commit')) { $db->commit(); }

                    $response['success'] = true;
                    // *** CORRECCIÓN: Usar el array local $meses_es_local ***
                    $nombre_mes = $meses_es_local[$mes] ?? "Mes {$mes}"; // Asegura que $mes sea índice válido
                    $response['message'] = "Honorario {$accion} como cortesía para {$nombre_mes}/{$anio}.";
                    // error_log("Usuario ID {$usuario_id} marcó CORTESIA..."); // Log opcional

                }
                // Ya no se necesita el 'else' aquí

            } else { // Si el estado actual NO es 'pendiente'
                 throw new Exception('El honorario para este periodo ya está marcado como \'' . htmlspecialchars($estado_actual) . '\'.');
            }

        } catch (Exception $e) {
             if ($transaccion_iniciada && $db && method_exists($db, 'rollBack')) {
                try { $db->rollBack(); } catch (Exception $re) { /* Ignorar error */ }
            }
            error_log("!!! Error en marcar_cortesia.php: " . $e->getMessage()); // Mantener log de errores reales
            $response['message'] = 'Error: ' . $e->getMessage(); // Devolver mensaje de error
        }

    } else { // Datos inválidos
        $response['message'] = 'Datos de cliente, año o mes inválidos.';
    }
} else { // Condición inicial falló
    // Determinar causa específica
    $specific_error = 'Condición desconocida.';
    if (!$is_post) { $specific_error = 'Método no permitido.'; }
    elseif (!$is_logged) { $specific_error = 'Sesión inválida.'; }
    elseif (!$has_data) { $specific_error = 'Faltan datos.'; }
    $response['message'] = 'Error: ' . $specific_error;
}

// 7. Enviar respuesta JSON y terminar SIEMPRE
echo json_encode($response);
exit();
// NADA DEBE IR DESPUÉS DE ESTO