<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Validar que el ID es un entero
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash('mensaje', 'ID de cliente no válido', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

$database = new Database();

// Obtener usuarios activos para Responsable
$database->query('SELECT id, nombre, apellidos FROM usuarios WHERE estatus = "activo" ORDER BY nombre, apellidos');
$usuarios = $database->resultSet();

// Normalizador de montos
function normalize_money($val): float {
    $s = trim((string)$val);
    if ($s === '') return 0.0;
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace(',', '', $s);
    } else {
        $s = str_replace(',', '.', $s);
    }
    return (float)$s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Información Fiscal ---
    $razon_social   = sanitize($_POST['razon_social'] ?? '');
    $actividad      = sanitize($_POST['actividad'] ?? '');
    $rfc            = strtoupper(trim(sanitize($_POST['rfc'] ?? '')));
    $regimen_fiscal = sanitize($_POST['regimen_fiscal'] ?? '');

    // --- Información de Contacto ---
    $telefono         = sanitize($_POST['telefono'] ?? '');
    $email            = sanitize($_POST['email'] ?? '');
    $codigo_postal    = sanitize($_POST['codigo_postal'] ?? '');
    $domicilio_fiscal = sanitize($_POST['domicilio_fiscal'] ?? '');

    // --- Otros ---
    $honorarios     = normalize_money($_POST['honorarios'] ?? '0');
    $responsable_id = isset($_POST['responsable_id']) && $_POST['responsable_id'] !== '' ? (int)sanitize($_POST['responsable_id']) : null;
    $estatus        = sanitize($_POST['estatus'] ?? 'activo');
    $fecha_alta     = sanitize($_POST['fecha_alta'] ?? date('Y-m-d'));

    // Validaciones
    $error = false;
    if ($razon_social === '') {
        flash('mensaje', 'La razón social es obligatoria', 'alert alert-danger');
        $error = true;
    }
    if ($rfc === '' || strlen($rfc) < 12 || strlen($rfc) > 13) {
        flash('mensaje', 'El RFC debe tener entre 12 y 13 caracteres', 'alert alert-danger');
        $error = true;
    }
    if ($fecha_alta === '') {
        flash('mensaje', 'La fecha de alta es obligatoria', 'alert alert-danger');
        $error = true;
    }

    // RFC único (excepto para el cliente actual)
    $database->query('SELECT id FROM clientes WHERE rfc = :rfc AND id != :id');
    $database->bind(':rfc', $rfc);
    $database->bind(':id', $id);
    if ($database->single()) {
        flash('mensaje', 'El RFC ya está registrado para otro cliente', 'alert alert-danger');
        $error = true;
    }

    if (!$error) {
        $sql = 'UPDATE clientes SET
                    razon_social = :razon_social,
                    actividad = :actividad,
                    rfc = :rfc,
                    regimen_fiscal = :regimen_fiscal,
                    telefono = :telefono,
                    email = :email,
                    codigo_postal = :codigo_postal,
                    domicilio_fiscal = :domicilio_fiscal,
                    honorarios = :honorarios,
                    responsable_id = :responsable_id,
                    estatus = :estatus,
                    fecha_alta = :fecha_alta
                WHERE id = :id';

        $database->query($sql);
        $database->bind(':razon_social', $razon_social);
        $database->bind(':actividad', $actividad);
        $database->bind(':rfc', $rfc);
        $database->bind(':regimen_fiscal', $regimen_fiscal);
        $database->bind(':telefono', $telefono);
        $database->bind(':email', $email);
        $database->bind(':codigo_postal', $codigo_postal);
        $database->bind(':domicilio_fiscal', $domicilio_fiscal);
        $database->bind(':honorarios', $honorarios);
        $database->bind(':responsable_id', $responsable_id);
        $database->bind(':estatus', $estatus);
        $database->bind(':fecha_alta', $fecha_alta);
        $database->bind(':id', $id);

        if ($database->execute()) {
            flash('mensaje', 'Cliente actualizado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/clientes/index.php');
        } else {
            flash('mensaje', 'Error al actualizar el cliente', 'alert alert-danger');
        }
    }
}

// Obtener datos del cliente para el formulario
$database->query('SELECT * FROM clientes WHERE id = :id');
$database->bind(':id', $id);
$cliente = $database->single();

if (!$cliente) {
    flash('mensaje', 'Cliente no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Editar Cliente</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<?php flash('mensaje'); ?>

<form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="post">

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-file-invoice-dollar"></i> Información Fiscal</div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="razon_social" class="form-label">Razón Social *</label>
                    <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?php echo htmlspecialchars($cliente->razon_social, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="actividad" class="form-label">Actividad</label>
                    <input type="text" class="form-control" id="actividad" name="actividad" value="<?php echo htmlspecialchars($cliente->actividad, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="rfc" class="form-label">RFC *</label>
                    <input type="text" class="form-control" id="rfc" name="rfc" maxlength="13" value="<?php echo htmlspecialchars($cliente->rfc, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="regimen_fiscal" class="form-label">Régimen Fiscal</label>
                    <input type="text" class="form-control" id="regimen_fiscal" name="regimen_fiscal" placeholder="Ej. 626" value="<?php echo htmlspecialchars($cliente->regimen_fiscal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-address-card"></i> Información de Contacto</div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($cliente->telefono, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cliente->email, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label for="codigo_postal" class="form-label">Código Postal</label>
                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" maxlength="5" value="<?php echo htmlspecialchars($cliente->codigo_postal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="domicilio_fiscal" class="form-label">Domicilio</label>
                    <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2"><?php echo htmlspecialchars($cliente->domicilio_fiscal, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-cog"></i> Información Administrativa</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Honorarios</label>
                    <input type="text" name="honorarios" class="form-control" inputmode="decimal" placeholder="0.00" value="<?php echo htmlspecialchars(number_format($cliente->honorarios, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="responsable_id" class="form-label">Responsable</label>
                    <select class="form-select" id="responsable_id" name="responsable_id">
                        <option value="">Seleccione responsable</option>
                        <?php foreach($usuarios as $usuario): ?>
                            <option value="<?php echo (int)$usuario->id; ?>" <?php echo ($cliente->responsable_id == $usuario->id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario->nombre . ' ' . $usuario->apellidos, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estatus" class="form-label">Estatus *</label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="activo" <?php echo ($cliente->estatus === 'activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="suspendido" <?php echo ($cliente->estatus === 'suspendido') ? 'selected' : ''; ?>>Suspendido</option>
                        <option value="baja" <?php echo ($cliente->estatus === 'baja') ? 'selected' : ''; ?>>Baja</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha_alta" class="form-label">Fecha Alta *</label>
                    <input type="date" class="form-control" id="fecha_alta" name="fecha_alta" value="<?php echo htmlspecialchars($cliente->fecha_alta, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </div>
</form>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>