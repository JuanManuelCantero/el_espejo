<?php
/**
 * CONFIGURACIÓN DE SESIÓN Y SEGURIDAD
 * =====================================
 * Este archivo centraliza toda la configuración de sesión y seguridad
 * Nunca se debe versionar en control de versiones públicos
 */

// === CONFIGURACIÓN DE SESIÓN ===
$SESSION_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'sessions';

// Crear directorio si no existe
if (!is_dir($SESSION_DIR)) {
    @mkdir($SESSION_DIR, 0700, true);
}

// Configurar sesiones de forma segura
ini_set('session.save_path', $SESSION_DIR);
ini_set('session.cookie_httponly', 1);      // Prevenir acceso desde JavaScript
ini_set('session.cookie_secure', 0);         // Cambiar a 1 si usas HTTPS
ini_set('session.cookie_samesite', 'Strict'); // Protección CSRF adicional
ini_set('session.use_strict_mode', 1);       // Modo estricto
session_start();

// === CREDENCIALES (Estas deberían venir de variables de entorno en producción) ===
// IMPORTANTE: Reemplaza estos valores con tus credenciales reales DESPUÉS de regenerarlas
define('TWITTER_CONSUMER_KEY', getenv('TWITTER_CONSUMER_KEY') ?: 'PLACEHOLDER_CONSUMER_KEY');
define('TWITTER_CONSUMER_SECRET', getenv('TWITTER_CONSUMER_SECRET') ?: 'PLACEHOLDER_CONSUMER_SECRET');
define('TWITTER_ACCESS_TOKEN', getenv('TWITTER_ACCESS_TOKEN') ?: 'PLACEHOLDER_ACCESS_TOKEN');
define('TWITTER_TOKEN_SECRET', getenv('TWITTER_TOKEN_SECRET') ?: 'PLACEHOLDER_TOKEN_SECRET');

// === FUNCIONES DE SEGURIDAD ===

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 */
function validateCSRFToken($token = null) {
    if (empty($token)) {
        $token = $_POST['csrf_token'] ?? '';
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitizar entrada de usuario
 */
function sanitize($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validar entrada
 */
function validate($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        case 'int':
            return is_numeric($input) && (int)$input >= 0;
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false;
        case 'date':
            return strtotime($input) !== false;
        case 'string':
        default:
            return !empty(trim($input)) && strlen($input) <= 255;
    }
}

/**
 * Registrar evento de seguridad
 */
function logSecurityEvent($event, $details = []) {
    $log_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0700, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = "[$timestamp] IP: $ip | EVENT: $event | " . json_encode($details) . "\n";
    
    @file_put_contents($log_dir . DIRECTORY_SEPARATOR . 'security.log', $log_entry, FILE_APPEND);
}

/**
 * Responder con JSON (para APIs)
 */
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => $success];
    if (!empty($message)) {
        $response['message'] = $message;
    }
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verificar si el usuario está autenticado
 */
function requireAuth() {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verificar autenticación para API (JSON)
 */
function requireAuthAPI() {
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        jsonResponse(false, 'No autenticado');
    }
}

?>
