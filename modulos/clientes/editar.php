<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash('mensaje', 'ID de cliente no especificado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del cliente
$database->query('SELECT * FROM Clientes WHERE id = :id');
$database->bind(':id', $id);
$cliente = $database->single();

// Verificar que el cliente existe
if (!$cliente) {
    flash('mensaje', 'Cliente no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

// Obtener usuarios para asignar responsable
$database->query('SELECT id, nombre, apellidos FROM Usuarios WHERE estatus = "activo" ORDER BY nombre, apellidos');
$usuarios = $database->resultSet();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $razon_social = sanitize($_POST['razon_social']);
    $nombre_comercial = sanitize($_POST['nombre_comercial']);
    $rfc = strtoupper(sanitize($_POST['rfc']));
    $regimen_fiscal = sanitize($_POST['regimen_fiscal']);
    $domicilio_fiscal = sanitize($_POST['domicilio_fiscal']);
    $codigo_postal = sanitize($_POST['codigo_postal']);
    $telefono = sanitize($_POST['telefono']);
    $email = sanitize($_POST['email']);
    $fecha_alta = sanitize($_POST['fecha_alta']);
    $estatus = sanitize($_POST['estatus']);
    $responsable_id = !empty($_POST['responsable_id']) ? sanitize($_POST['responsable_id']) : null;
    $notas = sanitize($_POST['notas']);
    
    // Validaciones
    $error = false;
    
    if (empty($razon_social)) {
        flash('mensaje', 'La razón social es obligatoria', 'alert alert-danger');
        $error = true;
    }
    
    if (empty($rfc) || strlen($rfc) < 12 || strlen($rfc) > 13) {
        flash('mensaje', 'El RFC debe tener entre 12 y 13 caracteres', 'alert alert-danger');
        $error = true;
    }
    
    if (empty($fecha_alta)) {
        flash('mensaje', 'La fecha de alta es obligatoria', 'alert alert-danger');
        $error = true;
    }
    
    // Verificar si el RFC ya existe para otro cliente
    $database->query('SELECT id FROM Clientes WHERE rfc = :rfc AND id != :id');
    $database->bind(':rfc', $rfc);
    $database->bind(':id', $id);
    if ($database->single()) {
        flash('mensaje', 'El RFC ya está registrado para otro cliente', 'alert alert-danger');
        $error = true;
    }
    
    // Si no hay errores, actualizar
    if (!$error) {
        $database->query('UPDATE Clientes SET 
                          razon_social = :razon_social, 
                          nombre_comercial = :nombre_comercial, 
                          rfc = :rfc, 
                          regimen_fiscal = :regimen_fiscal, 
                          domicilio_fiscal = :domicilio_fiscal, 
                          codigo_postal = :codigo_postal, 
                          telefono = :telefono, 
                          email = :email, 
                          fecha_alta = :fecha_alta, 
                          estatus = :estatus, 
                          responsable_id = :responsable_id, 
                          notas = :notas
                          WHERE id = :id');
        
        $database->bind(':razon_social', $razon_social);
        $database->bind(':nombre_comercial', $nombre_comercial);
        $database->bind(':rfc', $rfc);
        $database->bind(':regimen_fiscal', $regimen_fiscal);
        $database->bind(':domicilio_fiscal', $domicilio_fiscal);
        $database->bind(':codigo_postal', $codigo_postal);
        $database->bind(':telefono', $telefono);
        $database->bind(':email', $email);
        $database->bind(':fecha_alta', $fecha_alta);
        $database->bind(':estatus', $estatus);
        $database->bind(':responsable_id', $responsable_id);
        $database->bind(':notas', $notas);
        $database->bind(':id', $id);
        
        if ($database->execute()) {
            flash('mensaje', 'Cliente actualizado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/clientes/index.php');
        } else {
            flash('mensaje', 'Error al actualizar el cliente', 'alert alert-danger');
        }
    }
}

// Incluir el header
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

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Formulario de Edición
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="razon_social" class="form-label">Razón Social *</label>
                    <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?php echo $cliente->razon_social; ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="nombre_comercial" class="form-label">Nombre Comercial</label>
                    <input type="text" class="form-control" id="nombre_comercial" name="nombre_comercial" value="<?php echo $cliente->nombre_comercial; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="rfc" class="form-label">RFC *</label>
                    <input type="text" class="form-control" id="rfc" name="rfc" maxlength="13" value="<?php echo $cliente->rfc; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="regimen_fiscal" class="form-label">Régimen Fiscal</label>
                    <select class="form-select" id="regimen_fiscal" name="regimen_fiscal">
                        <option value="">Seleccione régimen fiscal</option>
                        <option value="601" <?php echo $cliente->regimen_fiscal == '601' ? 'selected' : ''; ?>>601 - General de Ley Personas Morales</option>
                        <option value="603" <?php echo $cliente->regimen_fiscal == '603' ? 'selected' : ''; ?>>603 - Personas Morales con Fines no Lucrativos</option>
                        <option value="605" <?php echo $cliente->regimen_fiscal == '605' ? 'selected' : ''; ?>>605 - Sueldos y Salarios e Ingresos Asimilados a Salarios</option>
                        <option value="606" <?php echo $cliente->regimen_fiscal == '606' ? 'selected' : ''; ?>>606 - Arrendamiento</option>
                        <option value="607" <?php echo $cliente->regimen_fiscal == '607' ? 'selected' : ''; ?>>607 - Régimen de Enajenación o Adquisición de Bienes</option>
                        <option value="608" <?php echo $cliente->regimen_fiscal == '608' ? 'selected' : ''; ?>>608 - Demás ingresos</option>
                        <option value="609" <?php echo $cliente->regimen_fiscal == '609' ? 'selected' : ''; ?>>609 - Consolidación</option>
                        <option value="610" <?php echo $cliente->regimen_fiscal == '610' ? 'selected' : ''; ?>>610 - Residentes en el Extranjero sin Establecimiento Permanente en México</option>
                        <option value="611" <?php echo $cliente->regimen_fiscal == '611' ? 'selected' : ''; ?>>611 - Ingresos por Dividendos (socios y accionistas)</option>
                        <option value="612" <?php echo $cliente->regimen_fiscal == '612' ? 'selected' : ''; ?>>612 - Personas Físicas con Actividades Empresariales y Profesionales</option>
                        <option value="614" <?php echo $cliente->regimen_fiscal == '614' ? 'selected' : ''; ?>>614 - Ingresos por intereses</option>
                        <option value="616" <?php echo $cliente->regimen_fiscal == '616' ? 'selected' : ''; ?>>616 - Sin obligaciones fiscales</option>
                        <option value="620" <?php echo $cliente->regimen_fiscal == '620' ? 'selected' : ''; ?>>620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos</option>
                        <option value="621" <?php echo $cliente->regimen_fiscal == '621' ? 'selected' : ''; ?>>621 - Incorporación Fiscal</option>
                        <option value="622" <?php echo $cliente->regimen_fiscal == '622' ? 'selected' : ''; ?>>622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                        <option value="623" <?php echo $cliente->regimen_fiscal == '623' ? 'selected' : ''; ?>>623 - Opcional para Grupos de Sociedades</option>
                        <option value="624" <?php echo $cliente->regimen_fiscal == '624' ? 'selected' : ''; ?>>624 - Coordinados</option>
                        <option value="625" <?php echo $cliente->regimen_fiscal == '625' ? 'selected' : ''; ?>>625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas</option>
                        <option value="626" <?php echo $cliente->regimen_fiscal == '626' ? 'selected' : ''; ?>>626 - Régimen Simplificado de Confianza</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="codigo_postal" class="form-label">Código Postal</label>
                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" maxlength="5" value="<?php echo $cliente->codigo_postal; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="domicilio_fiscal" class="form-label">Domicilio Fiscal</label>
                    <textarea class="form-control" id="domicilio_fiscal" name="domicilio_fiscal" rows="2"><?php echo $cliente->domicilio_fiscal; ?></textarea>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo $cliente->telefono; ?>">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $cliente->email; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="fecha_alta" class="form-label">Fecha Alta *</label>
                    <input type="date" class="form-control" id="fecha_alta" name="fecha_alta" value="<?php echo $cliente->fecha_alta; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="estatus" class="form-label">Estatus *</label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="activo" <?php echo $cliente->estatus == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="suspendido" <?php echo $cliente->estatus == 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                        <option value="baja" <?php echo $cliente->estatus == 'baja' ? 'selected' : ''; ?>>Baja</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="responsable_id" class="form-label">Responsable</label>
                    <select class="form-select" id="responsable_id" name="responsable_id">
                        <option value="">Seleccione responsable</option>
                        <?php foreach($usuarios as $usuario): ?>
                        <option value="<?php echo $usuario->id; ?>" <?php echo $cliente->responsable_id == $usuario->id ? 'selected' : ''; ?>>
                            <?php echo $usuario->nombre . ' ' . $usuario->apellidos; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="notas" class="form-label">Notas</label>
                    <textarea class="form-control" id="notas" name="notas" rows="3"><?php echo $cliente->notas; ?></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Cliente
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>