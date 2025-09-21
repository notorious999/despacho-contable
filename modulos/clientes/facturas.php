<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Si no está logueado, redirigir a login
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/modulos/usuarios/login.php');
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash('mensaje', 'ID de cliente no especificado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

$id = sanitize($_GET['id']);

// Inicializar la base de datos
$database = new Database();

// Obtener datos del cliente
$database->query('SELECT * FROM Clientes WHERE id = :id');
$database->bind(':id', $id);
$cliente = $database->single();

// Verificar que el cliente existe
if (!$cliente) {
    flash('mensaje', 'Cliente no encontrado', 'alert alert-danger');
    redirect(URL_ROOT . '/modulos/clientes/index.php');
}

// Consultar facturas del cliente
$database->query('SELECT id, tipo, tipo_comprobante, folio_fiscal, serie, folio, fecha_emision, 
                  rfc_emisor, nombre_emisor, rfc_receptor, nombre_receptor, 
                  subtotal, iva, total, estado
                  FROM CFDIs 
                  WHERE cliente_id = :cliente_id
                  ORDER BY fecha_emision DESC');
$database->bind(':cliente_id', $id);
$facturas = $database->resultSet();

// Incluir el header
include_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2>Facturas del Cliente</h2>
        <p class="lead"><?php echo $cliente->razon_social; ?> (<?php echo $cliente->rfc; ?>)</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="ver.php?id=<?php echo $cliente->id; ?>" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <a href="../reportes/cargar_xml.php?cliente_id=<?php echo $cliente->id; ?>" class="btn btn-primary">
            <i class="fas fa-upload"></i> Cargar Facturas
        </a>
        <button id="exportarExcel" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filtros
    </div>
    <div class="card-body">
        <form id="filtroForm" class="row g-3">
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo</label>
                <select id="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="emitida">Emitidas</option>
                    <option value="recibida">Recibidas</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="fechaInicio" class="form-label">Fecha Inicio</label>
                <input type="date" id="fechaInicio" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="fechaFin" class="form-label">Fecha Fin</label>
                <input type="date" id="fechaFin" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="estado" class="form-label">Estado</label>
                <select id="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="vigente">Vigente</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="col-12">
                <button type="button" id="aplicarFiltro" class="btn btn-primary">Aplicar</button>
                <button type="button" id="limpiarFiltro" class="btn btn-secondary">Limpiar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-table"></i> Listado de Facturas
    </div>
    <div class="card-body">
        <?php if (count($facturas) > 0): ?>
        <div class="table-responsive">
            <table id="tabla-facturas" class="table table-striped table-bordered" width="100%">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Efecto</th>
                        <th>Folio Fiscal</th>
                        <th>Serie-Folio</th>
                        <th>Fecha</th>
                        <th>Emisor</th>
                        <th>Receptor</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($facturas as $factura): ?>
                    <tr>
                        <td><?php echo ucfirst($factura->tipo); ?></td>
                        <td>
                            <?php 
                            $tipo_desc = '';
                            switch($factura->tipo_comprobante) {
                                case 'I': $tipo_desc = 'Ingreso'; break;
                                case 'E': $tipo_desc = 'Egreso'; break;
                                case 'P': $tipo_desc = 'Pago'; break;
                                case 'N': $tipo_desc = 'Nómina'; break;
                                case 'T': $tipo_desc = 'Traslado'; break;
                            }
                            echo $tipo_desc;
                            ?>
                        </td>
                        <td><?php echo $factura->folio_fiscal; ?></td>
                        <td>
                            <?php 
                            if(!empty($factura->serie) || !empty($factura->folio)) {
                                echo $factura->serie . '-' . $factura->folio;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo formatDate($factura->fecha_emision); ?></td>
                        <td><?php echo $factura->nombre_emisor . '<br>' . $factura->rfc_emisor; ?></td>
                        <td><?php echo $factura->nombre_receptor . '<br>' . $factura->rfc_receptor; ?></td>
                        <td class="text-end"><?php echo formatMoney($factura->subtotal); ?></td>
                        <td class="text-end"><?php echo formatMoney($factura->iva); ?></td>
                        <td class="text-end"><?php echo formatMoney($factura->total); ?></td>
                        <td>
                            <?php if($factura->estado == 'vigente'): ?>
                            <span class="badge bg-success">Vigente</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Cancelado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="../reportes/ver_factura.php?id=<?php echo $factura->id; ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="../reportes/descargar_xml.php?id=<?php echo $factura->id; ?>" class="btn btn-sm btn-secondary" title="Descargar XML">
                                <i class="fas fa-file-code"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No hay facturas registradas para este cliente.
        </div>
        <div class="mt-3">
            <a href="../reportes/cargar_xml.php?cliente_id=<?php echo $cliente->id; ?>" class="btn btn-primary">
                <i class="fas fa-upload"></i> Cargar Facturas
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Inicializar DataTable
        var tabla = $('#tabla-facturas').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            order: [[4, 'desc']], // Ordenar por fecha descendente
            pageLength: 25
        });
        
        // Aplicar filtros
        $('#aplicarFiltro').click(function() {
            tabla.draw();
        });
        
        // Limpiar filtros
        $('#limpiarFiltro').click(function() {
            $('#filtroForm')[0].reset();
            tabla.draw();
        });
        
        // Exportar a Excel
        $('#exportarExcel').click(function() {
            window.location = '../reportes/exportar_xlsx.php?cliente_id=<?php echo $cliente->id; ?>';
        });
        
        // Filtros personalizados para DataTables
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                var tipo = $('#tipo').val();
                var fechaInicio = $('#fechaInicio').val() ? new Date($('#fechaInicio').val()) : null;
                var fechaFin = $('#fechaFin').val() ? new Date($('#fechaFin').val()) : null;
                var estado = $('#estado').val();
                
                // Datos de la fila
                var tipoFila = data[0].toLowerCase(); // columna tipo
                var fechaFilaStr = data[4]; // columna fecha
                var estadoFila = data[10].includes('Vigente') ? 'vigente' : 'cancelado'; // columna estado
                
                // Convertir fecha de DD/MM/YYYY a objeto Date
                var partes = fechaFilaStr.split('/');
                var fechaFila = new Date(partes[2], partes[1] - 1, partes[0]);
                
                // Verificar filtros
                if (tipo && tipo !== tipoFila) return false;
                if (fechaInicio && fechaFila < fechaInicio) return false;
                if (fechaFin && fechaFila > fechaFin) return false;
                if (estado && estado !== estadoFila) return false;
                
                return true;
            }
        );
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>