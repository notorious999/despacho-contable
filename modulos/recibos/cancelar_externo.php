<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
  $recibo_id = (int)$_GET['id'];
  
  $db = new Database();
  // Se cambia el estatus a 'cancelado' y se asegura que sea un recibo externo
  $db->query('UPDATE recibos SET estatus = "cancelado" WHERE id = :id AND cliente_id IS NULL');
  $db->bind(':id', $recibo_id);
  
  if ($db->execute()) {
    flash('mensaje', 'El recibo ha sido cancelado con éxito.');
  } else {
    flash('mensaje', 'Hubo un error al cancelar el recibo.', 'alert-danger');
  }
} else {
  flash('mensaje', 'ID de recibo no válido.', 'alert-danger');
}

redirect(URL_ROOT . '/modulos/recibos/externos.php');
?>