<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// 1. Obtener ID y Tipo del CFDI desde la URL.
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo = sanitize($_GET['tipo'] ?? '');

// 2. Validar que los datos mínimos para operar son correctos.
if ($id <= 0 || !in_array($tipo, ['emitida', 'recibida'])) {
    flash('mensaje', 'Solicitud no válida: El ID o el tipo de CFDI son incorrectos.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}

$database = new Database();
// Determinar el nombre correcto de la tabla según tu BD
$tabla = ($tipo === 'emitida') ? 'cfdis_emitidas' : 'cfdis_recibidas';
$cliente_id_para_redirigir = 0;

try {
    // 3. ANTES de borrar, obtenemos el cliente_id para saber a dónde regresar.
    $sql_find = "SELECT cliente_id FROM {$tabla} WHERE id = :id";
    $database->query($sql_find);
    $database->bind(':id', $id);
    $registro = $database->single();

    if ($registro) {
        $cliente_id_para_redirigir = $registro->cliente_id;
    }

    // 4. Procedemos a eliminar el registro de forma permanente.
    $sql_delete = "DELETE FROM {$tabla} WHERE id = :id";
    $database->query($sql_delete);
    $database->bind(':id', $id);

    if ($database->execute()) {
        flash('mensaje', 'El CFDI ha sido eliminado permanentemente.', 'alert alert-success');
    } else {
        flash('mensaje', 'No se pudo eliminar el registro (es posible que ya haya sido borrado).', 'alert alert-warning');
    }

} catch (Exception $e) {
    flash('mensaje', 'Ocurrió un error grave durante la eliminación: ' . $e->getMessage(), 'alert alert-danger');
}

// 5. Redirigimos al usuario de forma inteligente.
if ($cliente_id_para_redirigir > 0) {
    // Si encontramos el cliente, volvemos a su página de reportes.
    redirect(URL_ROOT . "/modulos/reportes/index.php?cliente_id={$cliente_id_para_redirigir}");
} else {
    // Si no, lo llevamos a la página principal de reportes.
    redirect(URL_ROOT . '/modulos/reportes/index.php');
}