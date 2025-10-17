<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitizar los datos del POST
  $recibo_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $externo_nombre = sanitize($_POST['externo_nombre']);
  $externo_rfc = sanitize($_POST['externo_rfc']);
  $monto = (float)sanitize($_POST['monto']);
  $periodo_inicio = sanitize($_POST['periodo_inicio']);
  $concepto = sanitize($_POST['concepto']);

  // Validaciones
  if (empty($externo_nombre) || $monto <= 0 || empty($periodo_inicio) || empty($concepto) || $recibo_id <= 0) {
    flash('mensaje', 'Por favor, completa todos los campos obligatorios.', 'alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/editar_externo.php?id=' . $recibo_id);
  }

  $db = new Database();

  // Actualizar el recibo
  $sql = 'UPDATE recibos SET 
            externo_nombre = :externo_nombre,
            externo_rfc = :externo_rfc,
            monto = :monto,
            periodo_inicio = :periodo_inicio,
            periodo_fin = :periodo_fin,
            concepto = :concepto
          WHERE id = :id AND cliente_id IS NULL';
          
  $db->query($sql);
  $db->bind(':externo_nombre', $externo_nombre);
  $db->bind(':externo_rfc', $externo_rfc);
  $db->bind(':monto', $monto);
  $db->bind(':periodo_inicio', $periodo_inicio);
  $db->bind(':periodo_fin', $periodo_inicio); // Para externos, fin es igual a inicio
  $db->bind(':concepto', $concepto);
  $db->bind(':id', $recibo_id);

  if ($db->execute()) {
    flash('mensaje', 'El recibo ha sido actualizado correctamente.');
  } else {
    flash('mensaje', 'Hubo un error al actualizar el recibo. Inténtalo de nuevo.', 'alert-danger');
  }
  
  redirect(URL_ROOT . '/modulos/recibos/externos.php');

} else {
  // Si no es POST, redirigir
  redirect(URL_ROOT . '/modulos/recibos/externos.php');
}
?>