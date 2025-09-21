<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Inicializar la base de datos
$database = new Database();

// Obtener datos del usuario actual
$database->query('SELECT u.*, r.nombre as rol_nombre 
                  FROM Usuarios u 
                  LEFT JOIN Roles r ON u.rol_id = r.id 
                  WHERE u.id = :id');
$database->bind(':id', $_SESSION['user_id']);
$usuario = $database->single();

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanear entradas
    $email = sanitize($_POST['email']);
    $nombre = sanitize($_POST['nombre']);
    $apellidos = sanitize($_POST['apellidos']);
    
    // Validaciones
    $error = false;
    
    if (empty($email) || empty($nombre) || empty($apellidos)) {
        flash('mensaje', 'Todos los campos son obligatorios', 'alert alert-danger');
        $error = true;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('mensaje', 'El correo electrónico no es válido', 'alert alert-danger');
        $error = true;
    }
    
    // Verificar si el email ya existe para otro usuario
    $database->query('SELECT id FROM Usuarios WHERE email = :email AND id != :id');
    $database->bind(':email', $email);
    $database->bind(':id', $_SESSION['user_id']);
    if ($database->single()) {
        flash('mensaje', 'El correo electrónico ya está registrado para otro usuario', 'alert alert-danger');
        $error = true;
    }
    
    // Si no hay errores, actualizar
    if (!$error) {
        $database->query('UPDATE Usuarios SET 
                          email = :email, 
                          nombre = :nombre, 
                          apellidos = :apellidos
                          WHERE id = :id');
        
        $database->bind(':email', $email);
        $database->bind(':nombre', $nombre);
        $database->bind(':apellidos', $apellidos);
        $database->bind(':id', $_SESSION['user_id']);
        
        if ($database->execute()) {
            // Actualizar datos de sesión
            $_SESSION['user_name'] = $nombre . ' ' . $apellidos;
            $_SESSION['user_email'] = $email;
            
            flash('mensaje', 'Perfil actualizado correctamente', 'alert alert-success');
            redirect(URL_ROOT . '/modulos/usuarios/perfil.php');
        } else {
            flash('mensaje', 'Error al actualizar el perfil', 'alert alert-danger');
        }
    }
}

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Mi Perfil</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="<?php echo URL_ROOT; ?>/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver al Inicio
        </a>
        <a href="reset_password.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn btn-warning">
            <i class="fas fa-key"></i> Cambiar Contraseña
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-user-circle"></i> Información de Usuario
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle">
                        <span class="initials">
                            <?php 
                            echo substr($usuario->nombre, 0, 1) . substr($usuario->apellidos, 0, 1); 
                            ?>
                        </span>
                    </div>
                </div>
                
                <h4 class="text-center"><?php echo $usuario->nombre . ' ' . $usuario->apellidos; ?></h4>
                <p class="text-center text-muted mb-4"><?php echo ucfirst($usuario->rol_nombre); ?></p>
                
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Usuario:</strong>
                        <span><?php echo $usuario->username; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Email:</strong>
                        <span><?php echo $usuario->email; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Último acceso:</strong>
                        <span>
                            <?php 
                            if(!empty($usuario->ultimo_acceso)) {
                                echo formatDate($usuario->ultimo_acceso, 'd/m/Y H:i');
                            } else {
                                echo 'Nunca';
                            }
                            ?>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-edit"></i> Editar Perfil
            </div>
            <div class="card-body">
                <?php flash('mensaje'); ?>
                
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
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
                            <label for="username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" value="<?php echo $usuario->username; ?>" readonly>
                            <div class="form-text">El nombre de usuario no se puede modificar.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Correo Electrónico *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $usuario->email; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Actualizar Perfil
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 120px;
    height: 120px;
    background-color: #007bff;
    text-align: center;
    border-radius: 50%;
    -webkit-border-radius: 50%;
    -moz-border-radius: 50%;
    margin: 0 auto;
}

.initials {
    position: relative;
    top: 25px;
    font-size: 50px;
    line-height: 70px;
    color: #fff;
    font-weight: bold;
}
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>