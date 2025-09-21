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

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash('mensaje', 'ID de usuario no especificado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/usuarios/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del usuario
$database->query('SELECT * FROM Usuarios WHERE id = :id');
$database->bind(':id', $id);
$usuario = $database->single();

// Verificar que el usuario existe
if (!$usuario) {
    flash('mensaje', 'Usuario no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/usuarios/index.php');
}

// Obtener roles
$database->query('SELECT * FROM Roles ORDER BY nombre');
$roles = $database->resultSet();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $email = sanitize($_POST['email']);
    $nombre = sanitize($_POST['nombre']);
    $apellidos = sanitize($_POST['apellidos']);
    $rol_id = sanitize($_POST['rol_id']);
    $estatus = sanitize($_POST['estatus']);
    
    // Validaciones
    $error = false;
    
    if (empty($email) || empty($nombre) || empty($apellidos)) {
        flash('mensaje', 'Todos los campos marcados con * son obligatorios', 'alert alert-danger');
        $error = true;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('mensaje', 'El correo electrónico no es válido', 'alert alert-danger');
        $error = true;
    }
    
    // Verificar si el email ya existe para otro usuario
    $database->query('SELECT id FROM Usuarios WHERE email = :email AND id != :id');
    $database->bind(':email', $email);
    $database->bind(':id', $id);
    if ($database->single()) {
        flash('mensaje', 'El correo electrónico ya está registrado para otro usuario', 'alert alert-danger');
        $error = true;
    }
    
    // Si no hay errores, actualizar
    if (!$error) {
        $database->query('UPDATE Usuarios SET 
                          email = :email, 
                          nombre = :nombre, 
                          apellidos = :apellidos, 
                          rol_id = :rol_id, 
                          estatus = :estatus
                          WHERE id = :id');
        
        $database->bind(':email', $email);
        $database->bind(':nombre', $nombre);
        $database->bind(':apellidos', $apellidos);
        $database->bind(':rol_id', $rol_id);
        $database->bind(':estatus', $estatus);
        $database->bind(':id', $id);
        
        if ($database->execute()) {
            // Si se está editando al usuario actual, actualizar la sesión
            if ($id == $_SESSION['user_id']) {
                $_SESSION['user_name'] = $nombre . ' ' . $apellidos;
                $_SESSION['user_email'] = $email;
                
                // Actualizar rol en sesión
                $database->query('SELECT nombre FROM Roles WHERE id = :id');
                $database->bind(':id', $rol_id);
                $rol = $database->single();
                $_SESSION['user_role'] = $rol->nombre;
            }
            
            flash('mensaje', 'Usuario actualizado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/usuarios/index.php');
        } else {
            flash('mensaje', 'Error al actualizar el usuario', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Editar Usuario</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user-edit"></i> Formulario de Edición
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control" id="username" value="<?php echo $usuario->username; ?>" readonly>
                    <div class="form-text">El nombre de usuario no se puede modificar.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Correo Electrónico *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario->email; ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $usuario->nombre; ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="apellidos" class="form-label">Apellidos *</label>
                    <input type="text" class="form-control" id="apellidos" name="apellidos" value="<?php echo $usuario->apellidos; ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="rol_id" class="form-label">Rol *</label>
                    <select class="form-select" id="rol_id" name="rol_id" required>
                        <?php foreach($roles as $rol): ?>
                        <option value="<?php echo $rol->id; ?>" <?php echo $usuario->rol_id == $rol->id ? 'selected' : ''; ?>>
                            <?php echo ucfirst($rol->nombre); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="estatus" class="form-label">Estatus *</label>
                    <select class="form-select" id="estatus" name="estatus" required>
                        <option value="activo" <?php echo $usuario->estatus == 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $usuario->estatus == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Usuario
                    </button>
                    <a href="reset_password.php?id=<?php echo $id; ?>" class="btn btn-warning">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>