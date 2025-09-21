<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar si es administrador
if (!hasRole('administrador')) {
    flash('mensaje', 'No tienes permisos para acceder a esta sección', 'alert alert-danger');
    redirect(URL_ROOT . '/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener roles
$database->query('SELECT * FROM Roles ORDER BY nombre');
$roles = $database->resultSet();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $email = sanitize($_POST['email']);
    $nombre = sanitize($_POST['nombre']);
    $apellidos = sanitize($_POST['apellidos']);
    $rol_id = sanitize($_POST['rol_id']);
    $estatus = sanitize($_POST['estatus']);
    
    // Validaciones
    $error = false;
    
    if (empty($username) || empty($password) || empty($email) || empty($nombre) || empty($apellidos)) {
        flash('mensaje', 'Todos los campos marcados con * son obligatorios', 'alert alert-danger');
        $error = true;
    }
    
    if ($password !== $confirm_password) {
        flash('mensaje', 'Las contraseñas no coinciden', 'alert alert-danger');
        $error = true;
    }
    
    if (strlen($password) < 6) {
        flash('mensaje', 'La contraseña debe tener al menos 6 caracteres', 'alert alert-danger');
        $error = true;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('mensaje', 'El correo electrónico no es válido', 'alert alert-danger');
        $error = true;
    }
    
    // Verificar si el username ya existe
    $database->query('SELECT id FROM Usuarios WHERE username = :username');
    $database->bind(':username', $username);
    if ($database->single()) {
        flash('mensaje', 'El nombre de usuario ya está en uso', 'alert alert-danger');
        $error = true;
    }
    
    // Verificar si el email ya existe
    $database->query('SELECT id FROM Usuarios WHERE email = :email');
    $database->bind(':email', $email);
    if ($database->single()) {
        flash('mensaje', 'El correo electrónico ya está registrado', 'alert alert-danger');
        $error = true;
    }
    
    // Si no hay errores, guardar
    if (!$error) {
        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $database->query('INSERT INTO Usuarios (username, password, email, nombre, apellidos, rol_id, estatus) 
                          VALUES (:username, :password, :email, :nombre, :apellidos, :rol_id, :estatus)');
        
        $database->bind(':username', $username);
        $database->bind(':password', $password_hash);
        $database->bind(':email', $email);
        $database->bind(':nombre', $nombre);
        $database->bind(':apellidos', $apellidos);
        $database->bind(':rol_id', $rol_id);
        $database->bind(':estatus', $estatus);
        
        if ($database->execute()) {
            flash('mensaje', 'Usuario agregado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/usuarios/index.php');
        } else {
            flash('mensaje', 'Error al guardar el usuario', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Agregar Usuario</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user-plus"></i> Formulario de Registro
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Nombre de Usuario *</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Correo Electrónico *</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="col-md-6">
                    <label for="apellidos" class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" id="apellidos" name="apellidos" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Contraseña *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">La contraseña debe tener al menos 6 caracteres.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="rol_id" class="form-label">Rol *</label>
                    <select class="form-select" id="rol_id" name="rol_id" required>
                        <?php foreach($roles as $rol): ?>
                        <option value="<?php echo $rol->id; ?>"><?php echo ucfirst($rol->nombre); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="estatus" class="form-label">Estatus *</label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Usuario
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>