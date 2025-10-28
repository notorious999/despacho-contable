<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
  redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();

// --- PROCESAMIENTO DE FILTROS ---
// Leer parámetros GET (usando tu lógica original adaptada)
$desde = isset($_GET['desde']) && $_GET['desde'] !== '' ? sanitize($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) && $_GET['hasta'] !== '' ? sanitize($_GET['hasta']) : date('Y-m-t');
$q     = isset($_GET['q']) ? trim(sanitize($_GET['q'])) : '';
$estado_filtro = isset($_GET['estado']) ? strtolower(trim(sanitize($_GET['estado']))) : ''; // Usar nombre claro
if (!in_array($estado_filtro, ['', 'pendiente', 'pagado', 'cancelado'], true)) $estado_filtro = ''; // Añadir 'cancelado'

// --- CONSULTA SQL CORREGIDA ---
// Usar comillas dobles "" para la cadena SQL principal para evitar conflicto con las comillas simples de SEPARATOR
// Añadir r.monto_pagado si existe y es relevante para externos
$sql = "SELECT
            r.id,
            r.externo_nombre,
            r.externo_rfc,
            r.monto,
            r.monto_pagado, -- Añadir si existe y es relevante
            r.periodo_inicio AS fecha,
            r.estatus, -- activo/cancelado
            r.estado,  -- pendiente/pagado
            GROUP_CONCAT(rs.descripcion ORDER BY rs.id ASC SEPARATOR '|||') AS todos_los_conceptos,
            r.concepto AS concepto_principal -- Fallback
        FROM
            recibos r
        LEFT JOIN
            recibo_servicios rs ON r.id = rs.recibo_id
        WHERE
            r.cliente_id IS NULL AND r.origen = 'manual'"; // Condición base para externos

$params = []; // Array para parámetros bind

// --- APLICAR FILTROS ---
if ($desde !== '') {
  // Usar 'fecha' (alias de periodo_inicio) para filtrar, no fecha_pago que puede no aplicar a externos
  $sql .= ' AND r.periodo_inicio >= :desde';
  $params[':desde'] = $desde;
}
if ($hasta !== '') {
  $sql .= ' AND r.periodo_inicio <= :hasta';
  $params[':hasta'] = $hasta;
}
if ($q !== '') {
  // Buscar en nombre, rfc externo y concepto principal
  $sql .= ' AND (r.externo_nombre LIKE :q OR r.externo_rfc LIKE :q OR r.concepto LIKE :q_concepto)';
  $params[':q'] = '%' . $q . '%';
  $params[':q_concepto'] = '%' . $q . '%';
  // Nota: No se puede buscar directamente en GROUP_CONCAT aquí de forma eficiente.
  // Si necesitas buscar en los conceptos detallados, se requeriría un HAVING o subconsulta,
  // lo cual puede ser más lento. Buscar en el concepto principal es un compromiso.
}

// Filtro de estado combinado
if ($estado_filtro !== '') {
  if ($estado_filtro === 'cancelado') {
    $sql .= " AND r.estatus = :estatus_filtro";
    $params[':estatus_filtro'] = 'cancelado';
  } elseif ($estado_filtro === 'pagado') {
    $sql .= " AND r.estatus = 'activo' AND r.estado = :estado_filtro";
    $params[':estado_filtro'] = 'pagado';
  } elseif ($estado_filtro === 'pendiente') {
    // Para externos, ¿puede haber pendientes si se pagan al crear? Ajusta si es necesario
    $sql .= " AND r.estatus = 'activo' AND (r.estado = :estado_filtro OR r.estado IS NULL)";
    $params[':estado_filtro'] = 'pendiente';
  }
}

// Agrupar **ANTES** de ordenar
$sql .= ' GROUP BY r.id ORDER BY r.periodo_inicio DESC, r.id DESC';

// Ejecutar consulta
$db->query($sql);
foreach ($params as $k => $v) $db->bind($k, $v);
$recibos = $db->resultSet();

// --- CÁLCULO DE TOTALES ---
$tot_monto = 0;
$tot_pagado = 0; // Calcular aunque para externos sea igual al monto
$tot_saldo = 0; // Debería ser 0 para externos no cancelados

foreach ($recibos as $r) {
  if ($r->estatus !== 'cancelado') { // Solo sumar si no está cancelado
    $monto_actual = (float)$r->monto;
    $tot_monto  += $monto_actual;
    // Asumiendo que si no está cancelado, está pagado (monto_pagado = monto)
    $tot_pagado += $monto_actual;
    // $saldo_actual = max($monto_actual - (float)($r->monto_pagado ?? $monto_actual), 0.0);
    // $tot_saldo += $saldo_actual; // Saldo siempre 0 en este caso
  }
}


include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-3 align-items-center">
  <div class="col">
    <h3>Recibos Externos</h3>
  </div>
  <div class="col text-end">
    <a href="<?php echo URL_ROOT; ?>/modulos/recibos/nuevo_externo.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Recibo Externo</a>
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-eye"></i> Ver
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/clientes/index.php"><i class="fas fa-users me-2"></i>Clientes</a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/servicios.php"><i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Clientes)</a></li>
        <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php"><i class="fas fa-file-invoice-dollar me-2"></i>Recibos (Externos)</a></li>
      </ul>
    </div>
  </div>
</div>

<?php flash('mensaje'); ?>

<form class="card mb-3" method="get">
  <div class="card-body row g-3">
    <div class="col-md-3"><label class="form-label">Desde</label><input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($desde, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-md-3"><label class="form-label">Hasta</label><input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-md-3"><label class="form-label">Estado</label><select name="estado" class="form-select">
        <option value="" <?php echo $estado_filtro === '' ? 'selected' : ''; ?>>Todos</option>
        <option value="pendiente" <?php echo $estado_filtro === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
        <option value="pagado" <?php echo $estado_filtro === 'pagado' ? 'selected' : ''; ?>>Pagado</option>
        <option value="cancelado" <?php echo $estado_filtro === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label">Buscar</label><input type="text" name="q" class="form-control" placeholder="Nombre, RFC o concepto" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"></div>
    <div class="col-12 d-flex justify-content-end">
      <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> Aplicar</button>
      <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Limpiar</a>
    </div>
  </div>
</form>

<div class="row mb-3">
  <div class="col-md-4">
    <div class="card bg-light">
      <div class="card-body text-center p-2">
        <div class="small text-muted">Monto Total (No cancelados)</div>
        <div class="h4 mb-0"><?php echo formatMoney($tot_monto); ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-success text-white">
      <div class="card-body text-center p-2">
        <div class="small">Total Pagado (No cancelados)</div>
        <div class="h4 mb-0"><?php echo formatMoney($tot_pagado); ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card bg-warning text-dark">
      <div class="card-body text-center p-2">
        <div class="small">Saldo Pendiente</div>
        <div class="h4 mb-0"><?php echo formatMoney($tot_saldo); ?></div>
      </div>
    </div>
  </div>
</div>


<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha</th>
          <th>Nombre / Razón Social</th>
          <th>RFC</th>
          <th>Concepto</th>
          <th class="text-end">Monto</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($recibos)): foreach ($recibos as $r):
            // Determinar estado y badge (igual que antes)
            $estado_texto = 'Desconocido';
            $badge = 'bg-secondary';
            if (isset($r->estatus) && $r->estatus == 'cancelado') {
              $estado_texto = 'Cancelado';
              $badge = 'bg-danger';
            } elseif (isset($r->estado)) {
              if ($r->estado == 'pagado') {
                $estado_texto = 'Pagado';
                $badge = 'bg-success';
              } elseif ($r->estado == 'pendiente' || is_null($r->estado)) {
                $estado_texto = 'Pendiente';
                $badge = 'bg-warning text-dark';
              } else {
                $estado_texto = ucfirst($r->estado);
                $badge = 'bg-info';
              }
            } elseif (isset($r->estatus)) {
              $estado_texto = ucfirst($r->estatus); // Fallback a estatus si no hay estado
            }
        ?>
            <tr>
              <td><?php echo htmlspecialchars($r->id); ?></td>
              <td><?php echo formatDate($r->fecha); ?></td>
              <td><?php echo htmlspecialchars($r->externo_nombre ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($r->externo_rfc ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php
                // --- LÓGICA PARA MOSTRAR CONCEPTO (Revisada) ---
                $concepto_mostrar = 'Sin concepto asignado';
                $separador = '|||';

                if (isset($r->todos_los_conceptos) && $r->todos_los_conceptos !== null && $r->todos_los_conceptos !== '') {
                  $conceptos_array = explode($separador, $r->todos_los_conceptos);
                  $conceptos_array = array_map('trim', $conceptos_array);
                  $conceptos_array = array_filter($conceptos_array); // Asegura quitar elementos vacíos

                  $num_conceptos = count($conceptos_array);

                  if ($num_conceptos > 0) { // Solo si hay conceptos válidos
                    $primer_concepto = reset($conceptos_array); // Obtener el primero
                    if ($num_conceptos === 1) {
                      $concepto_mostrar = htmlspecialchars($primer_concepto);
                    } else {
                      $concepto_mostrar = htmlspecialchars($primer_concepto) . ' y más...';
                    }
                  }
                } elseif (isset($r->concepto_principal) && !empty($r->concepto_principal)) {
                  // Fallback si no hay detalles pero sí concepto principal
                  $concepto_mostrar = htmlspecialchars($r->concepto_principal);
                }
                echo $concepto_mostrar;
                // --- FIN LÓGICA CONCEPTO ---
                ?>
              </td>
              <td class="text-end"><?php echo formatMoney((float)$r->monto); ?></td>
              <td><span class="badge <?php echo $badge; ?>"><?php echo $estado_texto; ?></span></td>
              <td class="text-center">
                <a href="editar_externo.php?id=<?php echo (int)$r->id; ?>" class="btn btn-sm btn-outline-primary mb-1" title="Editar">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="imprimir_externo.php?id=<?php echo (int)$r->id; ?>" target="_blank" class="btn btn-sm btn-outline-info mb-1" title="Imprimir">
                  <i class="fas fa-print"></i>
                </a>
                <?php if ($estado_texto != 'Cancelado'): // No mostrar botón si ya está cancelado 
                ?>
                  <button type="button" class="btn btn-sm btn-outline-danger mb-1 btn-cancelar" data-id="<?php echo (int)$r->id; ?>" title="Cancelar">
                    <i class="fas fa-ban"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No se encontraron recibos externos<?php echo ($q || $desde !== date('Y-m-01') || $hasta !== date('Y-m-t') || $estado_filtro !== '') ? ' con los filtros aplicados' : ''; ?>.</td>
          </tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="text-end"><strong>Total Montos (No cancelados):</strong></td>
          <td class="text-end"><strong><?php echo formatMoney($tot_monto); ?></strong></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cancelModalLabel">Cancelar Recibo Externo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>¿Estás seguro de que deseas cancelar este recibo? Esta acción no se puede deshacer.</p>
        <form id="cancelForm" action="<?php echo URL_ROOT; ?>/modulos/recibos/cancelar_externo.php" method="post">
          <input type="hidden" name="recibo_id" id="reciboIdCancelar">
          <div class="mb-3">
            <label for="cancel_reason" class="form-label">Motivo de cancelación (opcional):</label>
            <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="2"></textarea>
          </div>
          <input type="hidden" name="tipo_recibo" value="externo">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-danger" id="confirmCancelBtn">Sí, Cancelar</button>
      </div>
    </div>
  </div>
</div>

<script>
  // Script para el modal de cancelación (sin cambios)
  document.addEventListener('DOMContentLoaded', function() {
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    const cancelButtons = document.querySelectorAll('.btn-cancelar');
    const reciboIdInput = document.getElementById('reciboIdCancelar');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const cancelForm = document.getElementById('cancelForm');

    cancelButtons.forEach(button => {
      button.addEventListener('click', function() {
        const reciboId = this.getAttribute('data-id');
        reciboIdInput.value = reciboId;
        const reasonTextarea = document.getElementById('cancel_reason');
        if (reasonTextarea) reasonTextarea.value = '';
        cancelModal.show();
      });
    });

    confirmCancelBtn.addEventListener('click', function() {
      if (cancelForm) {
        cancelForm.submit();
      } else {
        console.error("Formulario de cancelación no encontrado");
      }
    });
  });
</script>


<?php include_once __DIR__ . '/../../includes/footer.php'; ?>