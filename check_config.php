<?php
/**
 * Verificador de configuración del sistema
 * Ejecutar este archivo para verificar que la configuración esté correcta
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$checks = [];
$errors = [];
$warnings = [];

// Verificar PHP
$phpVersion = PHP_VERSION;
$checks['PHP Version'] = $phpVersion;
if (version_compare($phpVersion, '8.0.0', '<')) {
    $errors[] = "PHP 8.0+ requerido. Versión actual: $phpVersion";
}

// Verificar extensiones PHP
$requiredExtensions = ['pdo', 'pdo_mysql', 'xml', 'json', 'mbstring', 'curl'];
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $checks["Extensión $ext"] = $loaded ? 'OK' : 'FALTA';
    if (!$loaded) {
        $errors[] = "Extensión PHP requerida: $ext";
    }
}

// Verificar directorios
$directories = [
    'uploads' => UPLOAD_PATH,
    'xml' => XML_UPLOAD_PATH,
    'logs' => APP_ROOT . '/logs'
];

foreach ($directories as $name => $path) {
    $exists = file_exists($path);
    $writable = $exists && is_writable($path);
    $checks["Directorio $name"] = $exists ? ($writable ? 'OK' : 'NO ESCRIBIBLE') : 'NO EXISTE';
    
    if (!$exists) {
        $errors[] = "Directorio no existe: $path";
    } elseif (!$writable) {
        $errors[] = "Directorio no escribible: $path";
    }
}

// Verificar conexión a base de datos
try {
    $database = new Database();
    $checks['Conexión DB'] = 'OK';
} catch (Exception $e) {
    $checks['Conexión DB'] = 'ERROR';
    $errors[] = "Error de conexión a base de datos: " . $e->getMessage();
}

// Verificar configuración de seguridad
if (RFC_PROPIO === 'XAXX010101000') {
    $warnings[] = 'RFC_PROPIO no ha sido configurado con un RFC real';
}

if (ENVIRONMENT === 'production') {
    if (ini_get('display_errors')) {
        $warnings[] = 'display_errors debería estar deshabilitado en producción';
    }
    if (!ini_get('log_errors')) {
        $warnings[] = 'log_errors debería estar habilitado en producción';
    }
}

// Verificar configuración de archivos
$uploadMaxSize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$checks['upload_max_filesize'] = $uploadMaxSize;
$checks['post_max_size'] = $postMaxSize;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Configuración - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-cog"></i> Verificación de Configuración
                        </h3>
                    </div>
                    <div class="card-body">
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle"></i> Errores Críticos</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($warnings)): ?>
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-circle"></i> Advertencias</h5>
                            <ul class="mb-0">
                                <?php foreach ($warnings as $warning): ?>
                                <li><?php echo htmlspecialchars($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (empty($errors)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Sistema configurado correctamente
                        </div>
                        <?php endif; ?>

                        <h5>Estado del Sistema</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Componente</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($checks as $component => $status): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($component); ?></td>
                                        <td>
                                            <?php if ($status === 'OK' || $status === true): ?>
                                                <span class="badge bg-success">OK</span>
                                            <?php elseif (strpos($status, 'ERROR') !== false || $status === false): ?>
                                                <span class="badge bg-danger">ERROR</span>
                                            <?php elseif (strpos($status, 'FALTA') !== false): ?>
                                                <span class="badge bg-danger">FALTA</span>
                                            <?php elseif (strpos($status, 'NO ') !== false): ?>
                                                <span class="badge bg-warning">PROBLEMA</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6>Información del Sistema</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Entorno:</strong> <?php echo ENVIRONMENT; ?></li>
                                    <li><strong>URL Root:</strong> <?php echo URL_ROOT; ?></li>
                                    <li><strong>App Root:</strong> <?php echo APP_ROOT; ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Base de Datos</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Host:</strong> <?php echo DB_HOST; ?></li>
                                    <li><strong>Base:</strong> <?php echo DB_NAME; ?></li>
                                    <li><strong>Usuario:</strong> <?php echo DB_USER; ?></li>
                                </ul>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Ir al Sistema
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>