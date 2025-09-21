<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si ya está logueado, redirigir al index
if (isLoggedIn()) {
    redirect(URL_ROOT . '/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Chequear si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Procesar el formulario
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    
    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña";
    } else {
        // Consultar el usuario
        $database->query('SELECT * FROM Usuarios WHERE username = :username AND estatus = "activo"');
        $database->bind(':username', $username);
        $row = $database->single();
        
        if ($row) {
            // Verificar contraseña
            if (password_verify($password, $row->password)) {
                // Crear sesión
                $_SESSION['user_id'] = $row->id;
                $_SESSION['user_name'] = $row->nombre . ' ' . $row->apellidos;
                $_SESSION['user_email'] = $row->email;
                
                // Obtener rol
                $database->query('SELECT nombre FROM Roles WHERE id = :id');
                $database->bind(':id', $row->rol_id);
                $rol = $database->single();
                $_SESSION['user_role'] = $rol->nombre;
                
                // Actualizar último acceso
                $database->query('UPDATE Usuarios SET ultimo_acceso = NOW() WHERE id = :id');
                $database->bind(':id', $row->id);
                $database->execute();
                
                redirect(URL_ROOT . '/index.php');
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado o inactivo";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/styles.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-5">
                <div class="card shadow-lg border-0 rounded-lg mt-5">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center font-weight-light my-4">
                            <i class="fas fa-chart-line me-2"></i> <?php echo SITE_NAME; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <h4 class="text-center mb-4">Iniciar Sesión</h4>
                        
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php flash('mensaje'); ?>
                        
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <div class="form-floating mb-3">
                                <input class="form-control" id="username" name="username" type="text" placeholder="Usuario" required />
                                <label for="username">Usuario</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="password" name="password" type="password" placeholder="Contraseña" required />
                                <label for="password">Contraseña</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-center mt-4 mb-0">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <div class="small">Sistema de Gestión para Despacho Contable</div>
                        <div class="small text-muted mt-2">Fecha actual: <?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>