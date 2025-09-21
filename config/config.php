<?php
// Configuración general del sistema
define('SITE_NAME', 'Despacho Contable');
define('APP_ROOT', dirname(dirname(__FILE__)));

// Detectar entorno
define('ENVIRONMENT', getenv('APP_ENV') ?: 'development');

// Configuración de URL según el entorno
if (ENVIRONMENT === 'production') {
    define('URL_ROOT', getenv('APP_URL') ?: 'https://your-domain.com');
} else {
    define('URL_ROOT', 'http://localhost/despacho-contable');
}

define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('XML_UPLOAD_PATH', UPLOAD_PATH . '/xml');

// Configuración de la base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'despacho_contable');

// Configuración de seguridad
if (ENVIRONMENT === 'production') {
    // En producción, deshabilitar errores de PHP
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', APP_ROOT . '/logs/php_errors.log');
} else {
    // En desarrollo, mostrar errores
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Asegúrate de que existan los directorios necesarios
$directories = [UPLOAD_PATH, XML_UPLOAD_PATH, APP_ROOT . '/logs'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// RFC de TU EMPRESA (usado para consultas al SAT).
// IMPORTANTE: Reemplaza ESTE valor por el RFC real de tu empresa, en MAYÚSCULAS.
if (!defined('RFC_PROPIO')) {
    define('RFC_PROPIO', getenv('RFC_PROPIO') ?: 'XAXX010101000'); // <-- CAMBIA ESTO por tu RFC real
}