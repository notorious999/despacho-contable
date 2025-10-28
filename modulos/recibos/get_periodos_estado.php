<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php'; // Tu clase Database

header('Content-Type: application/json');
$response = ['success' => false, 'periodos' => (object)[]]; // Devolver objeto vacío por defecto

// Activar reporte de errores detallado temporalmente para depuración si es necesario
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if (isset($_POST['cliente_id']) && !empty($_POST['cliente_id'])) {
    $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);

    // Definir el rango de años a consultar (un año antes, actual, un año después)
    $anio_actual = date('Y');
    $anio_inicio = $anio_actual - 1;
    $anio_fin = $anio_actual + 1;

    if ($clienteId) {
        error_log("get_periodos_estado: Consultando estados para Cliente ID: {$clienteId}, Años: {$anio_inicio}-{$anio_fin}"); // Log
        try {
            $db = new Database();
            $db->query('SELECT anio, mes, estado FROM control_honorarios
                        WHERE cliente_id = :cliente_id AND anio BETWEEN :anio_inicio AND :anio_fin');
            $db->bind(':cliente_id', $clienteId);
            $db->bind(':anio_inicio', $anio_inicio);
            $db->bind(':anio_fin', $anio_fin);

            $resultados = $db->resultSet(); // Obtener todos los registros

            $estados_periodos = []; // Usar array normal
            if ($resultados) {
                error_log("get_periodos_estado: Se encontraron " . count($resultados) . " registros."); // Log
                foreach ($resultados as $row) {
                    // Acceder como objeto
                    $periodo_key = $row->anio . '-' . str_pad($row->mes, 2, '0', STR_PAD_LEFT);
                    $estados_periodos[$periodo_key] = $row->estado;
                }
            } else {
                 error_log("get_periodos_estado: No se encontraron registros para el cliente {$clienteId}."); // Log
            }
            $response['success'] = true;
            // Convertir a objeto JSON al final si es necesario, aunque array funciona bien con JS
            $response['periodos'] = (object)$estados_periodos;
            // error_log("get_periodos_estado: Respuesta: " . json_encode($response)); // Log (puede ser muy largo)

        } catch (Exception $e) {
            error_log("!!! Error en get_periodos_estado: " . $e->getMessage()); // Log
            $response['error'] = 'Error al consultar estados de periodos.';
        }
    } else {
        $response['error'] = 'ID de cliente inválido.';
    }
} else {
    $response['error'] = 'ID de cliente no proporcionado.';
}

echo json_encode($response);
exit();
?>