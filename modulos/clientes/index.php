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

// Consultar clientes
$database->query('SELECT c.*, u.nombre as responsable_nombre, u.apellidos as responsable_apellidos 
                  FROM Clientes c 
                  LEFT JOIN Usuarios u ON c.responsable_id = u.id 
                  ORDER BY c.razon_social');
$clientes = $database->resultSet();

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Gestión de Clientes</h2>
    </div>
    <div class="col-md-6 text-end">
        <a href="agregar.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Agregar Cliente
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-table"></i> Listado de Clientes
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tabla-clientes" class="table table-striped table-bordered" width="100%">
                <thead>
                    <tr>
                        <th>RFC</th>
                        <th>Razon Social</th>
                        <th>Actividad</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Nivel</th>
                        <th>Estatus</th>
                        <th>Responsable</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($clientes as $cliente): ?>
                    <tr>
                        <td><?php echo $cliente->rfc; ?></td>
                        <td><?php echo $cliente->razon_social; ?></td>
                        <td><?php echo $cliente->actividad ?? '-'; ?></td>
                        <td><?php echo $cliente->telefono ?? '-'; ?></td>
                        <td><?php echo $cliente->email ?? '-'; ?></td>
                        <td><?php echo $cliente->nivel; ?></td>
                        <td>
                            <?php if($cliente->estatus == 'activo'): ?>
                            <span class="badge bg-success">Activo</span>
                            <?php elseif($cliente->estatus == 'suspendido'): ?>
                            <span class="badge bg-warning">Suspendido</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Baja</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if(!empty($cliente->responsable_nombre)) {
                                echo $cliente->responsable_nombre . ' ' . $cliente->responsable_apellidos;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="text-center">
                            <a href="ver.php?id=<?php echo $cliente->id; ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="editar.php?id=<?php echo $cliente->id; ?>" class="btn btn-sm btn-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="facturas.php?id=<?php echo $cliente->id; ?>" class="btn btn-sm btn-success" title="Facturas">
                                <i class="fas fa-file-invoice"></i>
                            </a>
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
        $('#tabla-clientes').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            order: [[1, 'asc']], // Ordenar por razón social
            pageLength: 25
        });
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>