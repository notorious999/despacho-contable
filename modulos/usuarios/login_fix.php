<?php
require_once '../../config/config.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Si ya está logueado, redirigir al index
if (isLoggedIn()) {
    redirect(URL_ROOT . '/index.php');
}

// Inicializar la base de datos
$database = new Database();

// Crear un usuario administrador directo en la base de datos
if (isset($_GET['setup']) && $_GET['setup'] == 'init') {
    // Primero verificar si hay usuarios en el sistema
    $database->query('SELECT COUNT(*) as total FROM Usuarios');
    $total = $database->single()->total;
    
    if ($total == 0) {
        // No hay usuarios, crear uno
        $password_hash = password_hash('12345', PASSWORD_DEFAULT);
        
        $database->query('INSERT INTO Usuarios (username, password, email, nombre, apellidos, rol_id) 
                          VALUES (:username, :password, :email, :nombre, :apellidos, :rol_id)');
        $database->bind(':username', 'admin');
        $database->bind(':password', $password_hash);
        $database->bind(':email', 'admin@example.com');
        $database->bind(':nombre', 'Administrador');
        $database->bind(':apellidos', 'Sistema');
        $database->bind(':rol_id', 1);
        
        if ($database->execute()) {
            echo '<div class="alert alert-success">Usuario administrador creado correctamente. Usuario: admin, Contraseña: 12345</div>';
        } else {
            echo '<div class="alert alert-danger">Error al crear el usuario administrador.</div>';
        }
    } else {
        echo '<div class="alert alert-info">Ya existen usuarios en el sistema.</div>';
    }
}

// Chequear si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Procesar el formulario
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validaciones básicas
    if (empty($username) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña";
    } else {
        // Consultar el usuario directamente
        $database->query('SELECT * FROM Usuarios WHERE username = :username');
        $database->bind(':username', $username);
        $row = $database->single();
        
        if ($row) {
            // Para debug, guarda la contraseña hash en una variable
            $stored_hash = $row->password;
            
            // Intenta verificar con password_verify
            if (password_verify($password, $stored_hash)) {
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
                // Como fallback, intentar una comparación directa (solo temporal)
                if ($password == '12345' && $username == 'admin') {
                    // Crear sesión de emergencia
                    $_SESSION['user_id'] = $row->id;
                    $_SESSION['user_name'] = $row->nombre . ' ' . $row->apellidos;
                    $_SESSION['user_email'] = $row->email;
                    $_SESSION['user_role'] = 'administrador';
                    
                    redirect(URL_ROOT . '/index.php');
                } else {
                    $error = "Contraseña incorrecta (Hash almacenado: " . substr($stored_hash, 0, 15) . "...)";
                }
            }
        } else {
            $error = "Usuario no encontrado";
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
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-5">
                <div class="card shadow-lg border-0 rounded-lg mt-5">
                    <div class="card-header">
                        <h3 class="text-center font-weight-light my-4">Iniciar Sesión</h3>
                        <div class="text-center">
                            <a href="?setup=init" class="btn btn-sm btn-outline-secondary">Crear usuario admin</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php flash('mensaje'); ?>
                        
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <div class="form-floating mb-3">
                                <input class="form-control" id="username" name="username" type="text" placeholder="Usuario" value="admin" required />
                                <label for="username">Usuario</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="password" name="password" type="password" placeholder="Contraseña" value="12345" required />
                                <label for="password">Contraseña</label>
                            </div>
                            <div class="d-flex align-items-center justify-content-center mt-4 mb-0">
                                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <div class="small">
                            Este es un script de login alternativo con información de depuración
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>