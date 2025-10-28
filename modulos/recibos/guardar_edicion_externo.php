<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitizar los datos generales del POST
  $recibo_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $externo_nombre = sanitize($_POST['externo_nombre']);
  $externo_rfc = sanitize($_POST['externo_rfc']);
  $periodo_inicio = sanitize($_POST['periodo_inicio']); // Fecha

  // Datos de los servicios
  $descripciones = $_POST['descripcion'] ?? [];
  $importes      = $_POST['importe'] ?? [];

  $monto_total = 0;
  $servicios_validos_count = 0;

  // Calcular monto total y contar servicios válidos ANTES de guardar
  if (is_array($descripciones) && count($descripciones) > 0) {
      for ($i = 0; $i < count($descripciones); $i++) {
          $desc = trim(sanitize($descripciones[$i] ?? ''));
          $imp  = (float)($importes[$i] ?? 0);

          if ($desc !== '' && $imp > 0) {
              $monto_total += $imp;
              $servicios_validos_count++;
          }
      }
  }

  // Validaciones generales
  if (empty($externo_nombre) || $monto_total <= 0 || empty($periodo_inicio) || $servicios_validos_count === 0 || $recibo_id <= 0) {
    flash('mensaje', 'Completa Nombre, Fecha y al menos un servicio con descripción e importe válido.', 'alert-danger');
    redirect(URL_ROOT . '/modulos/recibos/editar_externo.php?id=' . $recibo_id);
    exit;
  }

  $db = new Database();
  // Iniciar transacción
  $db->beginTransaction();

  try {
      // 1. Actualizar el recibo principal (tabla `recibos`)
      //    (Se mantiene sin el campo 'concepto')
      //    **AÑADIDO: Actualizar fecha_creacion para reflejar la fecha editada**
      $sql_recibo = 'UPDATE recibos SET
                        externo_nombre = :externo_nombre,
                        externo_rfc = :externo_rfc,
                        monto = :monto,
                        periodo_inicio = :periodo_inicio,
                        periodo_fin = :periodo_fin,
                        fecha_creacion = :fecha /* Actualizar fecha */
                      WHERE id = :id AND cliente_id IS NULL';

      $db->query($sql_recibo);
      $db->bind(':externo_nombre', $externo_nombre);
      $db->bind(':externo_rfc', $externo_rfc);
      $db->bind(':monto', $monto_total);
      $db->bind(':periodo_inicio', $periodo_inicio);
      $db->bind(':periodo_fin', $periodo_inicio);
      // Obtener hora actual o de la base de datos si existe para no perderla
      $db_temp = new Database();
      $db_temp->query('SELECT fecha_creacion FROM recibos WHERE id = :id');
      $db_temp->bind(':id', $recibo_id);
      $recibo_actual = $db_temp->single();
      $hora_actual = $recibo_actual ? date('H:i:s', strtotime($recibo_actual->fecha_creacion)) : date('H:i:s');
      $db->bind(':fecha', $periodo_inicio . ' ' . $hora_actual); // Combinar fecha editada con hora
      $db->bind(':id', $recibo_id);

      if (!$db->execute()) {
          throw new Exception("Error al actualizar el recibo principal.");
      }

      // 2. Eliminar los detalles de servicio anteriores
      $db->query('DELETE FROM recibo_servicios WHERE recibo_id = :recibo_id');
      $db->bind(':recibo_id', $recibo_id);
      if (!$db->execute()) {
          throw new Exception("Error al eliminar detalles anteriores.");
      }


      // 3. Insertar los nuevos detalles de servicio (Lógica copiada de guardar_edicion_servicio)
      $db->query('INSERT INTO recibo_servicios (recibo_id, descripcion, importe) VALUES (:recibo_id, :desc, :imp)');
      for ($i = 0; $i < count($descripciones); $i++) {
            // Validar y sanitizar DENTRO del bucle
            $desc_actual = sanitize($descripciones[$i] ?? '');
            $imp_actual = (float)($importes[$i] ?? 0);
          if (!empty($desc_actual) && $imp_actual > 0) { // Asegurarse de no insertar vacíos
              $db->bind(':recibo_id', $recibo_id);
              $db->bind(':desc', $desc_actual);
              $db->bind(':imp', $imp_actual);
              if (!$db->execute()) {
                 // Lanzar excepción si falla la inserción del detalle
                 throw new Exception("Error al insertar detalle: " . htmlspecialchars($desc_actual));
              }
          }
      }

      // Si todo fue bien, confirmar transacción
      $db->endTransaction();
      flash('mensaje', 'El recibo ha sido actualizado correctamente.');

  } catch (Exception $e) {
      // Si algo falló, revertir transacción
      $db->cancelTransaction();
      $error_message = 'Hubo un error al actualizar el recibo.';
      // $error_message .= ' Detalles: ' . $e->getMessage(); // Descomentar para depurar
      flash('mensaje', $error_message, 'alert alert-danger');

      // Redirigir de vuelta al formulario de edición
      redirect(URL_ROOT . '/modulos/recibos/editar_externo.php?id=' . $recibo_id);
      exit;
  }

  // Redirigir a la lista si todo salió bien
  redirect(URL_ROOT . '/modulos/recibos/externos.php');

} else {
  // Si no es POST, redirigir
  redirect(URL_ROOT . '/modulos/recibos/externos.php');
}
?>