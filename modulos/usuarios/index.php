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

// Consultar usuarios
$database->query('SELECT u.*, r.nombre as rol_nombre 
                  FROM Usuarios u 
                  LEFT JOIN Roles r ON u.rol_id = r.id 
                  ORDER BY u.nombre, u.apellidos');
$usuarios = $database->resultSet();

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Gestión de Usuarios</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="agregar.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Agregar Usuario
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-users"></i> Listado de Usuarios
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        
        <div class="table-responsive">
            <table id="tabla-usuarios" class="table table-striped table-bordered" width="100%">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estatus</th>
                        <th>Último Acceso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo $usuario->username; ?></td>
                        <td><?php echo $usuario->nombre . ' ' . $usuario->apellidos; ?></td>
                        <td><?php echo $usuario->email; ?></td>
                        <td><?php echo ucfirst($usuario->rol_nombre); ?></td>
                        <td>
                            <?php if($usuario->estatus == 'activo'): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php elseif($usuario->estatus == 'inactivo'): ?>
                            <span class="badge bg-secondary">Inactivo</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Suspendido</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if(!empty($usuario->ultimo_acceso)) {
                                echo formatDate($usuario->ultimo_acceso, 'd/m/Y H:i');
                            } else {
                                echo 'Nunca';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <a href="editar.php?id=<?php echo $usuario->id; ?>" class="btn btn-sm btn-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if($usuario->id != $_SESSION['user_id']): ?>
                            <a href="reset_password.php?id=<?php echo $usuario->id; ?>" class="btn btn-sm btn-warning" title="Resetear Contraseña">
                                <i class="fas fa-key"></i>
                            </a>
                            <a href="cambiar_estatus.php?id=<?php echo $usuario->id; ?>" class="btn btn-sm btn-danger" title="Cambiar Estatus">
                                <i class="fas fa-user-slash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#tabla-usuarios').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>