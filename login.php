<?php
// Incluir configuración de seguridad centralizada
require_once __DIR__ . '/config.php';

// Redirigir si ya está autenticado
if (isset($_SESSION['user'])) {
    header('Location: agenda.php');
    exit;
}

// Cargar usuarios desde archivo JSON
$usuariosFile = 'data/usuarios.json';
if (!file_exists($usuariosFile)) {
    @mkdir('data', 0755, true);
    $defaultUsuarios = [
        [
            'username' => 'juan',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Dr. Juan Pérez'
        ],
        [
            'username' => 'maria',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Dra. María García'
        ],
        [
            'username' => 'ana',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Lic. Ana López'
        ]
    ];
    file_put_contents($usuariosFile, json_encode($defaultUsuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$usuarios = json_decode(file_get_contents($usuariosFile), true);
$error = '';

// === RATE LIMITING: PROTECCIÓN CONTRA ATAQUES DE FUERZA BRUTA ===
function checkRateLimit($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Limpiar intentos antiguos (más de 15 minutos)
    $current_time = time();
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], 
        function($attempt) use ($current_time) {
            return $attempt['time'] > ($current_time - 900); // 900 segundos = 15 minutos
        }
    );
    
    // Contar intentos del usuario en los últimos 15 minutos
    $user_attempts = count($_SESSION['login_attempts']);
    
    return $user_attempts;
}

function recordLoginAttempt($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][] = ['time' => time(), 'ip' => $ip];
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $attempts = checkRateLimit($ip);
    
    // Bloquear después de 5 intentos en 15 minutos
    if ($attempts >= 5) {
        $error = 'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.';
        logSecurityEvent('LOGIN_BLOCKED_RATE_LIMIT', ['ip' => $ip, 'attempts' => $attempts]);
    } else {
        // Validar CSRF token
        if (!validateCSRFToken()) {
            $error = 'Token de seguridad inválido. Intenta de nuevo.';
            logSecurityEvent('LOGIN_FAILED', ['reason' => 'invalid_csrf', 'ip' => $ip]);
            recordLoginAttempt($ip);
        } else {
            // Sanitizar entrada
            $username = sanitize($_POST['username'] ?? '', 'string');
            $password = $_POST['password'] ?? '';

            // Validar campos requeridos
            if (empty($username) || empty($password)) {
                $error = 'Usuario y contraseña son requeridos.';
                logSecurityEvent('LOGIN_FAILED', ['reason' => 'missing_fields', 'ip' => $ip]);
                recordLoginAttempt($ip);
            } else {
                // Buscar usuario
                $user = null;
                foreach ($usuarios as $u) {
                    if ($u['username'] === $username) {
                        $user = $u;
                        break;
                    }
                }

                // Verificar credenciales
                if ($user && password_verify($password, $user['password'])) {
                    // Login exitoso - limpiar intentos
                    $_SESSION['login_attempts'] = [];
                    $_SESSION['user'] = $username;
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['login_time'] = time();
                    logSecurityEvent('LOGIN_SUCCESS', ['username' => $username, 'ip' => $ip]);
                    header('Location: agenda.php');
                    exit;
                } else {
                    // Login fallido
                    $error = 'Usuario o contraseña incorrectos.';
                    logSecurityEvent('LOGIN_FAILED', ['reason' => 'invalid_credentials', 'username' => $username, 'ip' => $ip]);
                    recordLoginAttempt($ip);
                }
            }
        }
    }
}

// Generar CSRF token para el formulario
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>

  <!-- ** Basic Page Needs ** -->
  <meta charset="utf-8">
  <title>Login - El Espejo | Espacio Psicoterapéutico</title>
  <!-- Css fuentes -->
  <link rel="stylesheet" href="css/fonts.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">

  <!-- ** SEO & Meta ** -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Acceso para profesionales - El Espejo Espacio Psicoterapéutico">
  <meta name="keywords" content="login, profesionales, El Espejo">
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="author" content="El Espejo - Espacio Psicoterapéutico">
  <meta name="theme-color" content="#3a7ca5">

  <!-- ** Plugins Needed for the Project ** -->
  <!-- bootstrap -->
  <link rel="stylesheet" href="plugins/bootstrap/bootstrap.min.css">
  <!-- fontawesome -->
  <link rel="stylesheet" href="plugins/fontawesome/css/all.min.css">
  <!-- animate.css -->
  <link rel="stylesheet" href="plugins/animation/animate.min.css">
  <!-- Google Fonts-->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">	
  <!-- Stylesheets -->
  <link href="css/style.css" rel="stylesheet">
  <!--Favicon-->
  <link rel="icon" href="images/favicon.png" type="image/x-icon">

  <style>
    body {
      background: #f2f6fb;
      color: #1f2d3d;
      font-family: 'Work Sans', sans-serif;
    }
    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 20px;
    }
    .login-card {
      width: 100%;
      max-width: 1100px;
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      background: #ffffff;
      border-radius: 30px;
      overflow: hidden;
      box-shadow: 0 40px 90px rgba(31, 45, 64, 0.14);
    }
    .login-image {
      position: relative;
      background: linear-gradient(180deg, rgba(53, 131, 196, 0.92), rgba(242, 137, 21, 0.72)),
        url('images/slider/fachada/fachada1.jpeg');
      background-size: cover;
      background-position: center right;
      min-height: 600px;
    }
    .login-image::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(53, 131, 196, 0.55), rgba(242, 137, 21, 0.55));
    }
    .login-image-caption {
      position: absolute;
      left: 40px;
      bottom: 40px;
      color: #ffffff;
      z-index: 2;
      max-width: 300px;
    }
    .login-image-caption h3 {
      margin: 0 0 12px;
      font-size: 28px;
      line-height: 1.15;
      letter-spacing: 0.02em;
      color: #ffffff;
    }
    .login-image-caption p {
      margin: 0;
      font-size: 15px;
      line-height: 1.8;
      color: rgba(255,255,255,0.95);
    }
    .login-panel {
      padding: 60px 50px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      background: #ffffff;
    }
    .login-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 18px;
      margin-bottom: 45px;
      text-align: center;
      padding-top: 10px;
    }
    .login-brand img {
      width: 192px;
      height: auto;
    }
    .login-brand h1 {
      font-size: 40px;
      margin: 0;
      font-family: 'Dancing Script', cursive;
      letter-spacing: 0.03em;
    }
    .login-title {
      font-size: 28px;
      margin: 0 0 12px;
      letter-spacing: 0.03em;
      color: #17283b;
    }
    .login-subtitle {
      color: #62768b;
      margin-bottom: 32px;
      line-height: 1.8;
      max-width: 420px;
    }
    .login-form {
      width: 100%;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 10px;
      color: #4b5d70;
      font-weight: 600;
    }
    .form-control {
      width: 100%;
      padding: 16px 18px;
      border: 1px solid #d5dee8;
      border-radius: 14px;
      font-size: 15px;
      background: #f8fbff;
      color: #1f2d3d;
      box-shadow: none;
    }
    .form-control:focus {
      outline: none;
      border-color: #3583C4;
      box-shadow: 0 0 0 4px rgba(53, 131, 196, 0.18);
    }
    .btn-login {
      width: 100%;
      padding: 16px;
      border-radius: 14px;
      border: none;
      background: #F28915;
      color: #ffffff;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.02em;
      transition: background 0.25s ease;
      box-shadow: 0 12px 25px rgba(242, 137, 21, 0.25);
      cursor: pointer;
    }
    .btn-login:hover {
      background: #d97712;
    }
    .form-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 18px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .footer-links {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
    }
    .footer-links a {
      color: #3583C4;
      text-decoration: none;
      font-size: 14px;
    }
    .footer-links a:hover {
      text-decoration: underline;
    }
    .btn-outline-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 18px;
      border-radius: 14px;
      border: 1px solid #3583C4;
      background: transparent;
      color: #3583C4;
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      transition: background 0.25s ease, color 0.25s ease;
    }
    .btn-outline-primary:hover {
      background: rgba(53, 131, 196, 0.1);
      color: #214f73;
      text-decoration: none;
    }
    .error-message {
      margin-top: 16px;
      min-height: 22px;
      color: #d64541;
      font-weight: 600;
      padding: 12px;
      background: #fde8e8;
      border-radius: 6px;
      border-left: 4px solid #d64541;
    }
    .btn-home {
      position: fixed;
      top: 20px;
      left: 20px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: linear-gradient(135deg, #3583C4 0%, #2a6a9e 100%);
      color: #ffffff;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 8px 24px rgba(53, 131, 196, 0.3);
      transition: all 0.3s ease;
      z-index: 1000;
    }
    .btn-home:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 32px rgba(53, 131, 196, 0.4);
      color: #ffffff;
      text-decoration: none;
    }
    .btn-home i {
      font-size: 16px;
    }
    @media (max-width: 991px) {
      .login-card {
        grid-template-columns: 1fr;
      }
      .login-image {
        min-height: 300px;
      }
      .login-image-caption {
        left: 24px;
        bottom: 24px;
        max-width: 100%;
      }
      .login-panel {
        padding: 40px 28px;
      }
      .login-title {
        font-size: 24px;
      }
      .login-brand h1 {
        font-size: 28px;
      }
    }
    /* Desactivar backdrop modal */
    .modal-backdrop {
      display: none !important;
    }
    .modal.show {
      display: none !important;
    }
  </style>
</head>

<body>
  <a href="index.html" class="btn-home" title="Volver a El Espejo">
    <i class="fas fa-arrow-left"></i>
    <span>Volver al Sitio</span>
  </a>
  <div class="login-page">
    <div class="login-card">
      <div class="login-image">
        <div class="login-image-caption">
          <h3>Acceso profesional</h3>
          <p>Un espacio de inicio de sesión tranquilo, limpio y alineado al concepto visual de El Espejo.</p>
        </div>
      </div>
      <div class="login-panel">
        <div class="login-brand">
          <a href="index.html" style="display: inline-block; text-decoration: none;">
            <img src="images/logo/EL_ESPEJO_ISOLOGO_H_320px.png" alt="El Espejo logo">
          </a>
          <h1>Acceso de Usuario</h1>
        </div>
        <p class="login-subtitle">Ingresá tus datos para gestionar tu agenda y administrar turnos desde un acceso profesional.</p>
        <form action="login.php" method="post" class="login-form">
          <!-- Token CSRF para prevenir ataques de falsificación -->
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
          
          <div class="form-group">
            <label for="username">Usuario</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Ingrese usuario" required autocomplete="username">
          </div>
          <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese su contraseña" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn btn-login">Ingresar</button>
          <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="plugins/jquery.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="plugins/bootstrap/bootstrap.min.js"></script>

</body>
</html>