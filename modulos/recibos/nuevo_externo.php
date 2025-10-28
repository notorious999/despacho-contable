<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/recibos_service.php'; // Needed for registrarPago

if (!isLoggedIn()) { redirect(URL_ROOT.'/modulos/usuarios/login.php'); }

$new_recibo_id = 0; // Initialize outside the POST block

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitizar y validar los datos generales
    $nombre = trim(sanitize($_POST['externo_nombre'] ?? ''));
    $rfc    = strtoupper(trim(sanitize($_POST['externo_rfc'] ?? '')));
    $fecha  = sanitize($_POST['fecha'] ?? ''); // Fecha del recibo

    // Datos de los servicios
    $descripciones = $_POST['descripcion'] ?? [];
    $importes      = $_POST['importe'] ?? [];

    $monto_total = 0;
    $servicios_validos_count = 0;

    // Calcular monto total y contar servicios válidos
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

    // Validación principal
    if ($nombre === '' || $fecha === '' || $servicios_validos_count === 0) {
        flash('mensaje', 'Completa Nombre/Razón social, Fecha y al menos un servicio con descripción e importe válido.', 'alert alert-danger');
    } else {
        $db = new Database();
        $svc = new RecibosService(); // Para registrar el pago después

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // 2. Insertar el recibo principal en la tabla `recibos` (SIN concepto)
            $db->query('INSERT INTO recibos (
                            externo_nombre, externo_rfc, monto,
                            periodo_inicio, periodo_fin, origen, estatus, estado, /* Agregado estado */
                            usuario_id, fecha_creacion /* Agregado fecha_creacion */
                        ) VALUES (
                            :nombre, :rfc, :monto,
                            :pi, :pf, :origen, :estatus, :estado, /* Agregado estado */
                            :uid, :fecha_creacion /* Agregado fecha_creacion */
                        )');
            $db->bind(':nombre', $nombre);
            $db->bind(':rfc', $rfc);
            $db->bind(':monto', $monto_total);
            $db->bind(':pi', $fecha);
            $db->bind(':pf', $fecha);
            $db->bind(':origen', 'manual');
            $db->bind(':estatus', 'activo'); // Estatus general del registro
            $db->bind(':estado', 'pagado'); // Estado del pago (se paga automáticamente)
            $db->bind(':uid', $_SESSION['user_id'] ?? null);
            $db->bind(':fecha_creacion', $fecha . ' ' . date('H:i:s')); // Guardar fecha y hora

            if (!$db->execute()) {
                 throw new Exception("No se pudo crear el registro principal del recibo.");
            }

            $new_recibo_id = $db->lastInsertId(); // Obtener el ID del recibo recién creado

            if ($new_recibo_id <= 0) {
                throw new Exception("No se pudo obtener el ID del nuevo recibo.");
            }

            // 3. Insertar los detalles del servicio en `recibo_servicios`
            // Preparar la consulta UNA VEZ fuera del bucle
            $db->query('INSERT INTO recibo_servicios (recibo_id, descripcion, importe) VALUES (:recibo_id, :desc, :imp)');

            for ($i = 0; $i < count($descripciones); $i++) {
                 $desc = trim(sanitize($descripciones[$i] ?? ''));
                 $imp  = (float)($importes[$i] ?? 0);

                 // Insertar solo si es un servicio válido
                 if ($desc !== '' && $imp > 0) {
                     $db->bind(':recibo_id', $new_recibo_id);
                     $db->bind(':desc', $desc); // Usar descripción ya sanitizada
                     $db->bind(':imp', $imp);
                     if (!$db->execute()) {
                          throw new Exception("Error al insertar detalle: " . htmlspecialchars($desc));
                     }
                 }
            }

            // 4. Pagar automáticamente el recibo recién creado (Usando RecibosService)
            $userId = $_SESSION['user_id'] ?? null;
            $pagoRegistrado = $svc->registrarPago(
                (int)$new_recibo_id,
                $monto_total, // Pagar el monto total
                $fecha,       // La fecha del pago es la misma que la del recibo
                'Efectivo',   // Método por defecto
                '',           // Referencia vacía
                'Recibo externo creado y pagado automáticamente',
                $userId
            );

            if (!$pagoRegistrado) {
                 // Intentar obtener el error del servicio si está disponible
                 $errorMsg = method_exists($svc, 'getLastError') ? $svc->getLastError() : 'Error desconocido al registrar el pago.';
                 throw new Exception("Recibo creado, pero no se pudo registrar el pago automático: " . $errorMsg);
            }

            // Si todo fue bien, confirmar transacción
            $db->endTransaction();
            flash('mensaje', 'Recibo externo creado y pagado correctamente.', 'alert alert-success');
            // Redirigir a la lista (o a la página de impresión si prefieres)
            redirect(URL_ROOT.'/modulos/recibos/externos.php');
            exit;

        } catch (Exception $e) {
            // Si algo falló, revertir transacción
            $db->cancelTransaction();
            flash('mensaje', 'Error: ' . $e->getMessage(), 'alert alert-danger');
            // No redirigir aquí para que el formulario conserve los datos ingresados
        }
    }
} // Fin del bloque POST

// --- El resto del archivo (HTML y JavaScript) sigue igual ---
// Incluir header, mostrar flash messages, el formulario HTML, el script JS y el footer.

include_once __DIR__ . '/../../includes/header.php';
?>
<div class="row mb-3">
    <div class="col"><h3>Nuevo recibo (externo)</h3></div>
    <div class="col text-end">
        <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</div>

<?php flash('mensaje'); ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre/Razón social *</label>
                    <input type="text" name="externo_nombre" class="form-control" value="<?php echo htmlspecialchars($_POST['externo_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">RFC</label>
                    <input type="text" name="externo_rfc" class="form-control" value="<?php echo htmlspecialchars($_POST['externo_rfc'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="13">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')); ?>" required>
                </div>
            </div>

            <hr>
            <h5>Servicios</h5>
            <div id="servicios-container">
                <?php
                 // Mostrar las filas ingresadas si hubo un error al guardar
                 $descripciones_post = $_POST['descripcion'] ?? ['']; // Asegurar al menos una fila vacía
                 $importes_post = $_POST['importe'] ?? [''];
                 $num_servicios = count($descripciones_post);

                 for ($i = 0; $i < $num_servicios; $i++):
                     $desc_val = htmlspecialchars($descripciones_post[$i] ?? '', ENT_QUOTES, 'UTF-8');
                     $imp_val = htmlspecialchars($importes_post[$i] ?? '', ENT_QUOTES, 'UTF-8');
                 ?>
                <div class="row servicio-item mb-2">
                    <div class="col-md-7">
                        <input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" value="<?php echo $desc_val; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.00" min="0.00" value="<?php echo $imp_val; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger remove-servicio">Eliminar</button>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <button type="button" id="add-servicio" class="btn btn-success mb-3"><i class="fas fa-plus"></i> Agregar Servicio</button>

            <div class="row">
                <div class="col-md-12 text-end">
                    <h4>Total: $<span id="total">0.00</span></h4>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear y Pagar</button>
                <a href="<?php echo URL_ROOT; ?>/modulos/recibos/externos.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// --- (El JavaScript es el mismo que en la versión anterior) ---
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('servicios-container');

    document.getElementById('add-servicio').addEventListener('click', function () {
        const newItem = document.createElement('div');
        newItem.classList.add('row', 'servicio-item', 'mb-2');
        newItem.innerHTML = `
            <div class="col-md-7"><input type="text" name="descripcion[]" class="form-control" placeholder="Descripción del servicio" required></div>
            <div class="col-md-3"><input type="number" name="importe[]" class="form-control importe" placeholder="Importe" step="0.00" min="0.00" required></div>
            <div class="col-md-2"><button type="button" class="btn btn-danger remove-servicio">Eliminar</button></div>
        `;
        container.appendChild(newItem);
        updateTotal(); // Actualizar el total al agregar
    });

    container.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('remove-servicio')) {
            // Prevenir que se elimine el último item
            if (container.querySelectorAll('.servicio-item').length > 1) {
                e.target.closest('.servicio-item').remove();
                updateTotal();
            } else {
                alert('Debe registrar al menos un servicio.');
            }
        }
    });

    container.addEventListener('input', function (e) {
        if (e.target && e.target.classList.contains('importe')) {
            updateTotal();
        }
    });

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.importe').forEach(function (input) {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('total').textContent = total.toFixed(2);
    }

    // Inicializar el total al cargar la página (considerando valores POST si hay error)
    updateTotal();
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>