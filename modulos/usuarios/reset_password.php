<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar si es administrador o si es el propio usuario
if (!hasRole('administrador') && $_GET['id'] != $_SESSION['user_id']) {
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

// Obtener datos básicos del usuario
$database->query('SELECT id, username, nombre, apellidos FROM Usuarios WHERE id = :id');
$database->bind(':id', $id);
$usuario = $database->single();

// Verificar que el usuario existe
if (!$usuario) {
    flash('mensaje', 'Usuario no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/usuarios/index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validaciones
    $error = false;
    
    if (empty($password) || empty($confirm_password)) {
        flash('mensaje', 'Ambos campos son obligatorios', 'alert alert-danger');
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
    
    // Si no hay errores, actualizar contraseña
    if (!$error) {
        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $database->query('UPDATE Usuarios SET password = :password WHERE id = :id');
        $database->bind(':password', $password_hash);
        $database->bind(':id', $id);
        
        if ($database->execute()) {
            flash('mensaje', 'Contraseña actualizada correctamente', 'alert alert-success');
            
            // Redirigir según el rol
            if (hasRole('administrador') && $id != $_SESSION['user_id']) {
                redirect(URL_ROOT . '/modulos/usuarios/index.php');
            } else {
                redirect(URL_ROOT . '/modulos/usuarios/perfil.php');
            }
        } else {
            flash('mensaje', 'Error al actualizar la contraseña', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Cambiar Contraseña</h2>
        <p class="lead">Usuario: <?php echo $usuario->nombre . ' ' . $usuario->apellidos; ?> (<?php echo $usuario->username; ?>)</p>
    </div>
    <div class="col-md-6 text-end">
        <?php if (hasRole('administrador') && $id != $_SESSION['user_id']): ?>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <?php else: ?>
        <a href="perfil.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-key"></i> Formulario de Cambio de Contraseña
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Nueva Contraseña *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">La contraseña debe tener al menos 6 caracteres.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Contraseña
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>