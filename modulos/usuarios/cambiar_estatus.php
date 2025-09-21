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

// Verificar que no se está intentando cambiar el estatus del propio usuario
if ($id == $_SESSION['user_id']) {
    flash('mensaje', 'No puedes cambiar tu propio estatus', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/usuarios/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener datos del usuario
$database->query('SELECT id, username, nombre, apellidos, estatus FROM Usuarios WHERE id = :id');
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
    $estatus = sanitize($_POST['estatus']);
    
    // Actualizar estatus
    $database->query('UPDATE Usuarios SET estatus = :estatus WHERE id = :id');
    $database->bind(':estatus', $estatus);
    $database->bind(':id', $id);
    
    if ($database->execute()) {
        flash('mensaje', 'Estatus actualizado correctamente', 'alert alert-success');
        redirect(URL_ROOT . '/modulos/usuarios/index.php');
    } else {
        flash('mensaje', 'Error al actualizar el estatus', 'alert alert-danger');
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Cambiar Estatus de Usuario</h2>
        <p class="lead">Usuario: <?php echo $usuario->nombre . ' ' . $usuario->apellidos; ?> (<?php echo $usuario->username; ?>)</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user-slash"></i> Cambiar Estatus
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Advertencia: Cambiar el estatus a "Inactivo" impedirá que el usuario inicie sesión en el sistema.
        </div>
        
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id; ?>" method="post">
            <div class="mb-3">
                <label for="estatus" class="form-label">Estatus Actual</label>
                <p>
                    <?php if($usuario->estatus == 'activo'): ?>
                    <span class="badge bg-success">Activo</span>
                    <?php elseif($usuario->estatus == 'inactivo'): ?>
                    <span class="badge bg-secondary">Inactivo</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Suspendido</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="mb-3">
                <label for="estatus" class="form-label">Nuevo Estatus *</label>
                <select class="form-select" id="estatus" name="estatus" required>
                    <option value="activo" <?php echo $usuario->estatus == 'activo' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactivo" <?php echo $usuario->estatus == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>