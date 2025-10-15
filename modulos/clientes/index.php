<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

$database = new Database();

// Obtener todos los clientes con el nombre del responsable
$sql = 'SELECT c.id, c.razon_social, c.rfc, c.regimen_fiscal, c.telefono, c.email, c.estatus,
               u.nombre as responsable_nombre, u.apellidos as responsable_apellidos
        FROM clientes c
        LEFT JOIN usuarios u ON c.responsable_id = u.id
        ORDER BY c.razon_social';
$database->query($sql);
$clientes = $database->resultSet();

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
        <div class="row">
            <div class="col-md-6">
                <i class="fas fa-users"></i> Listado de Clientes
            </div>
            <div class="col-md-6">
                 <input type="text" id="buscador" class="form-control" placeholder="Buscar cliente por Razón Social o RFC...">
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php flash('mensaje'); ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Razón Social</th>
                        <th>RFC</th>
                        <th>Régimen Fiscal</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Responsable</th>
                        <th>Estatus</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-clientes">
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay clientes registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cliente->razon_social, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cliente->rfc, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cliente->regimen_fiscal, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cliente->telefono, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cliente->email, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cliente->responsable_nombre . ' ' . $cliente->responsable_apellidos, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($cliente->estatus === 'activo') ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($cliente->estatus); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="ver.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-info btn-sm" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="editar.php?id=<?php echo (int)$cliente->id; ?>" class="btn btn-warning btn-sm" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.getElementById('buscador');
    const tabla = document.getElementById('tabla-clientes');
    const filas = tabla.getElementsByTagName('tr');

    buscador.addEventListener('keyup', function() {
        const textoBusqueda = this.value.toLowerCase();

        for (let i = 0; i < filas.length; i++) {
            const fila = filas[i];
            // Celdas de Razón Social (índice 0) y RFC (índice 1)
            const celdaRazonSocial = fila.getElementsByTagName('td')[0];
            const celdaRfc = fila.getElementsByTagName('td')[1];

            if (celdaRazonSocial && celdaRfc) {
                const textoRazonSocial = celdaRazonSocial.textContent || celdaRazonSocial.innerText;
                const textoRfc = celdaRfc.textContent || celdaRfc.innerText;

                if (textoRazonSocial.toLowerCase().indexOf(textoBusqueda) > -1 ||
                    textoRfc.toLowerCase().indexOf(textoBusqueda) > -1) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>