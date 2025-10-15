<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/recibos/servicios.php');
}

$db = new Database();

$cliente_id = sanitize($_POST['cliente_id']);
$fecha = sanitize($_POST['fecha']);
$descripciones = $_POST['descripcion'];
$importes = $_POST['importe'];
$concepto_texto = '';
$total = 0;
foreach ($importes as $importe) {
    $total += (float)$importe;
}

// --- LÓGICA AÑADIDA ---
// Si el monto total es 0 (o menor), se marca como pagado.
// De lo contrario, se marca como pendiente.
$estado = ($total > 0) ? 'pendiente' : 'pagado';
// --- FIN DE LA LÓGICA AÑADIDA ---





// Insertar en la tabla `recibos`
$db->query('INSERT INTO recibos (cliente_id, concepto, monto, fecha_pago, estado, tipo, origen, usuario_id) 
            VALUES (:cliente_id, :concepto, :monto, :fecha, :estado, "servicio", "manual", :usuario_id)');
$db->bind(':cliente_id', $cliente_id);
if (count($descripciones) > 1) {
    // Si hay MÁS DE UNO, toma el primero y le agregas " y mas".
    $concepto_texto = $descripciones[0] . " y más";
} else {
    // Si solo hay UNO (o ninguno, aunque en ese caso podría dar un error si el array está vacío),
    // simplemente toma el primer elemento.
    $concepto_texto = $descripciones[0];
}

$db->bind(':concepto', $concepto_texto);


//$db->bind(':concepto', $concepto);
$db->bind(':monto', $total);
$db->bind(':fecha', $fecha);
$db->bind(':estado', $estado); // Se usa la variable $estado en lugar de un valor fijo
$db->bind(':usuario_id', $_SESSION['user_id']);

if ($db->execute()) {
    $recibo_id = $db->lastInsertId();

    // Insertar cada servicio en la tabla `recibo_servicios`
    // Este bucle solo se ejecutará si se proporcionaron servicios
    if (!empty($descripciones)) {
        for ($i = 0; $i < count($descripciones); $i++) {
            $db->query('INSERT INTO recibo_servicios (recibo_id, descripcion, importe) VALUES (:recibo_id, :descripcion, :importe)');
            $db->bind(':recibo_id', $recibo_id);
            $db->bind(':descripcion', sanitize($descripciones[$i]));
            $db->bind(':importe', (float)$importes[$i]);
            $db->execute();
        }
    }

    flash('mensaje', 'Recibo de servicio creado correctamente.');
    redirect(URL_ROOT . '/modulos/recibos/servicios.php');
} else {
    flash('mensaje', 'Error al crear el recibo de servicio.', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/nuevo_servicio.php');
}
?>