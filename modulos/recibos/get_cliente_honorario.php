<?php
// Asegúrate que las rutas sean correctas desde este archivo
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php'; // Aquí está tu clase Database
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

// Inicializa la respuesta por defecto
$response = ['success' => false, 'honorario' => 0, 'error' => ''];

// Activar reporte de errores detallado temporalmente para depuración (si es necesario)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

if (isset($_POST['cliente_id']) && !empty($_POST['cliente_id'])) {
    $clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);

    if ($clienteId) {
        try {
            // ***** CORRECCIÓN AQUÍ *****
            // Instanciar tu clase Database en lugar de llamar a getDbConnection()
            $db = new Database();

            // Preparar la consulta usando los métodos de tu clase
            // Asegúrate que tu método query() prepare la sentencia
            $db->query('SELECT honorarios FROM clientes WHERE id = :cliente_id');

            // Bind del parámetro
            // Asegúrate que tu método bind() funcione así
            $db->bind(':cliente_id', $clienteId);

            // Ejecutar y obtener un solo resultado
            // Asegúrate que tu método single() exista y devuelva un objeto o array
            $cliente = $db->single();

            if ($cliente) {
                $response['success'] = true;
                // Acceder a la propiedad 'honorarios'. Ajusta si tu clase devuelve array ['honorarios']
                $response['honorario'] = isset($cliente->honorarios) ? (float)$cliente->honorarios : 0;
            } else {
                 // Cliente encontrado pero sin honorarios o no encontrado
                 $response['success'] = true; // La consulta fue exitosa
                 $response['honorario'] = 0;
                 // Opcional: podrías añadir una nota si el cliente no fue encontrado
                 // $response['message'] = 'Cliente no encontrado.';
            }

        } catch (PDOException $e) { // Capturar específicamente errores de PDO si tu clase los usa
            error_log("Error DB en get_cliente_honorario: " . $e->getMessage()); // Log del error
            $response['error'] = 'Error al consultar la base de datos (PDO).';
        } catch (Exception $e) { // Capturar otros errores
            error_log("Error general en get_cliente_honorario: " . $e->getMessage()); // Log del error
            $response['error'] = 'Error interno del servidor.';
        }
        // La conexión se cierra automáticamente si tu clase Database usa PDO y lo maneja en el destructor
    } else {
         $response['error'] = 'ID de cliente inválido.';
    }

} else {
     $response['error'] = 'ID de cliente no proporcionado.';
}

// Siempre devolver una respuesta JSON válida
echo json_encode($response);
exit(); // Terminar el script aquí para evitar cualquier salida adicional
?>