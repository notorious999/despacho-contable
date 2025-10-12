<?php
// Tu código PHP de la parte superior no cambia...
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (isLoggedIn()) {
    redirect(URL_ROOT . '/index.php');
}

$database = new Database();
$step = 1;
$username_to_check = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && !isset($_POST['password'])) {
        $userInput = sanitize($_POST['username']);
        if (empty($userInput)) {
            $error = "Por favor ingrese su usuario o email";
        } else {
            $database->query('SELECT id, username FROM Usuarios WHERE (username = :userInput OR email = :userInput) AND estatus = "activo"');
            $database->bind(':userInput', $userInput);
            $user_exists = $database->single();
            if ($user_exists) {
                $step = 2;
                $username_to_check = $user_exists->username;
            } else {
                $error = "Usuario no encontrado o inactivo";
            }
        }
    } elseif (isset($_POST['username']) && isset($_POST['password'])) {
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $username_to_check = $username;
        $step = 2;
        if (empty($password)) {
            $error = "Por favor ingrese la contraseña";
        } else {
            $database->query('SELECT * FROM Usuarios WHERE username = :username AND estatus = "activo"');
            $database->bind(':username', $username);
            $row = $database->single();
            if ($row && password_verify($password, $row->password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row->id;
                $_SESSION['user_name'] = $row->nombre . ' ' . $row->apellidos;
                $_SESSION['user_email'] = $row->email;
                $database->query('SELECT nombre FROM Roles WHERE id = :id');
                $database->bind(':id', $row->rol_id);
                $rol = $database->single();
                $_SESSION['user_role'] = $rol->nombre;
                $database->query('UPDATE Usuarios SET ultimo_acceso = NOW() WHERE id = :id');
                $database->bind(':id', $row->id);
                $database->execute();
                redirect(URL_ROOT . '/index.php');
            } else {
                $error = "Contraseña incorrecta";
            }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/styles.css">

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-card-animation {
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .login-logo {
            max-width: 150px;
            height: auto;
        }
    </style>
    </head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-5">
                <div class="card shadow-lg border-0 rounded-lg mt-5 login-card-animation">
                    <div class="card-header bg-primary text-white">
                        <h3 class="text-center font-weight-light my-4">
                            <i class=""></i> <?php echo SITE_NAME; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        
                        <div class="text-center mb-4">
                            <img src="<?php echo URL_ROOT; ?>/uploads/logo.png" alt="Logo de <?php echo SITE_NAME; ?>" class="login-logo">
                        </div>
                        <h4 class="text-center mb-4">Iniciar Sesión</h4>
                        
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php flash('mensaje'); ?>
                        
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <?php if ($step == 1): ?>
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="username" name="username" type="text" placeholder="Usuario o Email" required autofocus />
                                    <label for="username">Usuario o Email</label>
                                </div>
                                <div class="d-flex align-items-center justify-content-center mt-4 mb-0">
                                    <button type="submit" class="btn btn-primary w-100">
                                        Continuar <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="text-center">
                                    <i class="fas fa-user-circle me-2"></i><strong><?php echo htmlspecialchars($username_to_check); ?></strong>
                                </p>
                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username_to_check); ?>">
                                <div class="form-floating mb-3">
                                    <input class="form-control" id="password" name="password" type="password" placeholder="Contraseña" required autofocus />
                                    <label for="password">Contraseña</label>
                                </div>
                                <div class="d-flex align-items-center justify-content-between mt-4 mb-0">
                                    <a class="small" href="?">Volver</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <div class="small">Bienvenido</div>
                        <div class="small text-muted mt-2">Fecha actual: <?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>