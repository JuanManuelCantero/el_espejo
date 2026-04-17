<?php
// Incluir configuración de seguridad centralizada
require_once __DIR__ . '/config.php';

// Registrar evento de logout
logSecurityEvent('LOGOUT', ['user' => $_SESSION['user'] ?? 'UNKNOWN']);

// Destruir sesión de forma segura
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirigir a login
header('Location: login.php');
exit;
?>