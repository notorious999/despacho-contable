<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$db = new Database();
$db->query('SELECT id, razon_social, rfc, honorarios FROM clientes WHERE estatus="activo" ORDER BY razon_social');
$clientes = $db->resultSet();

include_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<style>
    /* Estilos (sin cambios) */
    #listaPeriodosHonorarios { max-height: 300px; overflow-y: auto; padding: 15px; border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1rem; background-color: #f8f9fa; }
    .form-check-label.text-muted { text-decoration: line-through; opacity: 0.7; }
    .form-check-label .badge { vertical-align: middle; margin-left: 0.3rem; font-size: 0.75em; }
    .servicio-item .btn-danger { height: calc(1.5em + 0.5rem + 2px); display: flex; align-items: center; justify-content: center; }
    .form-control-sm.text-end { text-align: right; }
</style>

<div class="container-fluid px-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-6"> <h1 class="mt-4 mb-0">Nuevo Recibo de Servicio</h1> </div>
        <div class="col-md-6 text-end"> <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Volver a Recibos</a> </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php flash('mensaje'); ?>
            <form action="guardar_servicio.php" method="post" id="formNuevoServicio">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cliente_id" class="form-label">Cliente *</label>
                        <select class="form-select" id="cliente_id" name="cliente_id" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente->id; ?>" data-honorarios="<?php echo htmlspecialchars($cliente->honorarios ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($cliente->razon_social . ' (' . $cliente->rfc . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="fecha" class="form-label">Fecha del Recibo *</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Servicios / Conceptos</h5>
                
                <!-- *** CORRECCIÓN: Contenedor VACÍO *** -->
                <div id="servicios-container" class="mb-3">
                    <!-- NADA AQUÍ - El JS (en footer.php) añadirá la primera fila -->
                </div>
                
                <div class="mb-3">
                    <button type="button" id="add-servicio" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i> Agregar Servicio</button>
                    <button type="button" class="btn btn-info btn-sm" id="btnAgregarHonorarios" style="margin-left: 10px;">
                        <i class="fas fa-hand-holding-usd me-1"></i> Agregar Honorarios
                    </button>
                </div>

                <div class="row justify-content-end mt-3">
                    <div class="col-md-4 col-lg-3">
                         <div class="text-end border-top pt-2">
                            <h5 class="mb-0">Total: $<span id="total" class="fw-bold">0.00</span></h5>
                            <input type="hidden" name="monto_total_calculado" id="monto_total_hidden" value="0.00">
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 border-top pt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i> Crear Recibo</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal (Sin cambios) -->
    <div class="modal fade" id="modalHonorarios" tabindex="-1" aria-labelledby="modalHonorariosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg"> <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalHonorariosLabel">Seleccionar Periodos de Honorarios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Cliente: <strong id="modalClienteNombre"></strong></p>
                <p>Monto Honorario Mensual: $<strong id="modalMontoHonorario">0.00</strong></p>
                <hr>
                <p class="mb-2">Selecciona los periodos (mes/año) que deseas incluir:</p>
                <div id="listaPeriodosHonorarios" class="row border rounded pt-2 pb-1 mb-3 bg-light">
                    <div class="text-center p-3 text-muted">Cargando periodos...</div>
                </div>
                <p class="mt-3 fw-bold">Total Seleccionado (sin cortesías): $<span id="modalTotalSeleccionado">0.00</span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarAgregarHonorarios">Agregar al Recibo</button>
            </div>
        </div> </div>
    </div>
</div> <!-- Fin container-fluid -->

<!-- El script <script>...</script> ha sido movido a footer.php -->

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>