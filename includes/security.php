<?php
/**
 * Funciones de seguridad mejoradas para el sistema de despacho contable
 */

/**
 * Genera un token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitización mejorada para entradas de formularios
 */
function sanitizeInput($data, $type = 'string') {
    if (is_array($data)) {
        return array_map(function($item) use ($type) {
            return sanitizeInput($item, $type);
        }, $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($data, FILTER_SANITIZE_URL);
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        default:
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Valida formato de RFC mexicano
 */
function validateRFC($rfc) {
    $rfc = strtoupper(trim($rfc));
    // Persona física: 4 letras + 6 números + 3 caracteres alfanuméricos
    // Persona moral: 3 letras + 6 números + 3 caracteres alfanuméricos
    $pattern = '/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/';
    return preg_match($pattern, $rfc);
}

/**
 * Valida formato de UUID
 */
function validateUUID($uuid) {
    $pattern = '/^[0-9A-F]{8}-[0-9A-F]{4}-[1-5][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
    return preg_match($pattern, $uuid);
}

/**
 * Configura headers de seguridad
 */
function setSecurityHeaders() {
    // Prevenir ataques XSS
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Política de seguridad de contenido básica
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data:;");
    
    // Prevenir ataques de referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Rate limiting básico
 */
function checkRateLimit($action, $limit = 5, $window = 300) {
    $key = 'rate_limit_' . $action . '_' . ($_SESSION['user_id'] ?? session_id());
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // Resetear contador si ha pasado el tiempo
    if (time() - $data['start'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start' => time()];
        return true;
    }
    
    // Incrementar contador
    $_SESSION[$key]['count']++;
    
    return $_SESSION[$key]['count'] <= $limit;
}

/**
 * Log de eventos de seguridad
 */
function logSecurityEvent($event, $details = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $log_file = APP_ROOT . '/logs/security.log';
    
    // Crear directorio de logs si no existe
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Valida que un archivo sea realmente un XML válido
 */
function validateXMLFile($filepath) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    // Verificar extensión
    $extension = pathinfo($filepath, PATHINFO_EXTENSION);
    if (strtolower($extension) !== 'xml') {
        return false;
    }
    
    // Verificar que sea XML válido
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($filepath);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    
    return $xml !== false && empty($errors);
}