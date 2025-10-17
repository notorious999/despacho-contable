<?php
// Configuración general del sistema
define('SITE_NAME', 'Despacho Contable');
define('APP_ROOT', dirname(dirname(__FILE__)));
define('URL_ROOT', 'http://localhost/despacho-contable');
define('UPLOAD_PATH', APP_ROOT . '/uploads');
define('XML_UPLOAD_PATH', UPLOAD_PATH . '/xml');

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'despacho_contable');


if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
if (!file_exists(XML_UPLOAD_PATH)) {
    mkdir(XML_UPLOAD_PATH, 0777, true);
}


if (!defined('RFC_PROPIO')) {
    define('RFC_PROPIO', 'XAXX010101000');
}