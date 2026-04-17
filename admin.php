<?php
// Incluir configuración de seguridad centralizada
require_once __DIR__ . '/config.php';

// Verificar autenticación
requireAuth();

// Verificar que sea admin
$usuariosFile = 'data/usuarios.json';
$usuarios = json_decode(file_get_contents($usuariosFile), true) ?? [];
$currentUserIndex = array_search($_SESSION['user'], array_column($usuarios, 'username'));
$currentUser = $usuarios[$currentUserIndex] ?? null;

// Si no es admin, redirigir a agenda
if (!isset($currentUser['is_admin']) || !$currentUser['is_admin']) {
    header('Location: agenda.php');
    exit;
}

$success = '';
$error = '';

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!validateCSRFToken()) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = sanitize($_POST['accion'] ?? '', 'string');

        // CREAR USUARIO
        if ($action === 'crear') {
            $username = sanitize($_POST['username'] ?? '', 'string');
            $password = $_POST['password'] ?? '';
            $name = sanitize($_POST['name'] ?? '', 'string');
            $is_admin = isset($_POST['is_admin']) ? (bool)$_POST['is_admin'] : false;

            // Validación
            if (empty($username) || empty($password) || empty($name)) {
                $error = 'Todos los campos son requeridos';
            } elseif (strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres';
            } elseif (strlen($username) < 3) {
                $error = 'El usuario debe tener al menos 3 caracteres';
            } elseif (array_search($username, array_column($usuarios, 'username')) !== false) {
                $error = 'El usuario ya existe';
            } else {
                // Crear nuevo usuario
                $nuevoUsuario = [
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'name' => $name,
                    'is_admin' => $is_admin,
                    'creado' => date('Y-m-d H:i:s')
                ];
                $usuarios[] = $nuevoUsuario;

                if (file_put_contents($usuariosFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $success = 'Usuario creado exitosamente';
                    logSecurityEvent('USUARIO_CREADO', [
                        'admin' => $_SESSION['user'],
                        'nuevo_usuario' => $username,
                        'is_admin' => $is_admin
                    ]);
                } else {
                    $error = 'Error al guardar usuario';
                }
            }
        }

        // EDITAR USUARIO
        elseif ($action === 'editar') {
            $edit_index = (int)($_POST['edit_index'] ?? -1);
            $new_name = sanitize($_POST['edit_name'] ?? '', 'string');
            $new_password = $_POST['edit_password'] ?? '';
            $new_is_admin = isset($_POST['edit_is_admin']) ? (bool)$_POST['edit_is_admin'] : false;

            if ($edit_index < 0 || !isset($usuarios[$edit_index])) {
                $error = 'Usuario no encontrado';
            } elseif (empty($new_name)) {
                $error = 'El nombre no puede estar vacío';
            } else {
                // No permitir editar a sí mismo como admin (prevenir bloqueo)
                if ($edit_index === $currentUserIndex && !$new_is_admin) {
                    $error = 'No puedes quitarte permisos de administrador';
                } else {
                    $usuarios[$edit_index]['name'] = $new_name;
                    $usuarios[$edit_index]['is_admin'] = $new_is_admin;

                    // Actualizar contraseña si se proporciona
                    if (!empty($new_password)) {
                        if (strlen($new_password) < 6) {
                            $error = 'La contraseña debe tener al menos 6 caracteres';
                        } else {
                            $usuarios[$edit_index]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                        }
                    }

                    if (empty($error)) {
                        if (file_put_contents($usuariosFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                            $success = 'Usuario actualizado exitosamente';
                            logSecurityEvent('USUARIO_EDITADO', [
                                'admin' => $_SESSION['user'],
                                'usuario' => $usuarios[$edit_index]['username'],
                                'cambios' => 'nombre, admin_status'
                            ]);
                        } else {
                            $error = 'Error al guardar cambios';
                        }
                    }
                }
            }
        }

        // ELIMINAR USUARIO
        elseif ($action === 'eliminar') {
            $delete_index = (int)($_POST['delete_index'] ?? -1);

            if ($delete_index < 0 || !isset($usuarios[$delete_index])) {
                $error = 'Usuario no encontrado';
            } elseif ($delete_index === $currentUserIndex) {
                $error = 'No puedes eliminar tu propio usuario';
            } else {
                $deletedUser = $usuarios[$delete_index];
                unset($usuarios[$delete_index]);
                $usuarios = array_values($usuarios); // Reindexar

                if (file_put_contents($usuariosFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $success = 'Usuario eliminado exitosamente';
                    logSecurityEvent('USUARIO_ELIMINADO', [
                        'admin' => $_SESSION['user'],
                        'usuario_eliminado' => $deletedUser['username']
                    ]);
                } else {
                    $error = 'Error al eliminar usuario';
                }
            }
        }
    }

    // Redirigir para evitar resubmit
    header('Location: admin.php');
    exit;
}

// Recargar usuarios para mostrar cambios
$usuarios = json_decode(file_get_contents($usuariosFile), true) ?? [];

// Generar token CSRF
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>

  <!-- ** Basic Page Needs ** -->
  <meta charset="utf-8">
  <title>Panel de Administración - El Espejo | Espacio Psicoterapéutico</title>
  <!-- Css fuentes -->
  <link rel="stylesheet" href="css/fonts.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">

  <!-- ** SEO & Meta ** -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Panel de administración - El Espejo Espacio Psicoterapéutico">
  <meta name="keywords" content="admin, panel, usuarios, profesionales, El Espejo">
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
    .single-page-header {
      background: linear-gradient(135deg, rgba(53, 131, 196, 0.72), rgba(242, 137, 21, 0.62)), url("images/slider/fachada/fachada1.jpeg");
      background-size: cover;
      background-position: center;
      padding: 140px 0 70px;
      position: relative;
      color: #ffffff;
      text-align: center;
      min-height: 340px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .single-page-header .container {
      position: relative;
      z-index: 1;
    }
    .single-page-header h2 {
      color: #ffffff;
      font-size: 48px;
      font-family: 'Dancing Script', cursive;
      margin-bottom: 15px;
      text-shadow: 0 12px 25px rgba(0,0,0,0.32);
    }
    body {
      background: #f5f8fc;
    }
    .single-page-header p {
      color: rgba(255,255,255,0.94);
      font-size: 18px;
      font-family: 'Work Sans', sans-serif;
      max-width: 700px;
      margin: 0 auto;
      text-shadow: 0 8px 18px rgba(0,0,0,0.24);
    }
    .admin-section {
      padding: 70px 0 90px;
      background: transparent;
    }
    .admin-container {
      max-width: 1100px;
      margin: -80px auto 0;
      padding: 50px 45px;
      background: rgba(255,255,255,0.98);
      border-radius: 24px;
      box-shadow: 0 30px 80px rgba(0,0,0,0.08);
      border: 1px solid rgba(53,131,196,0.14);
    }
    .admin-container h2 {
      text-align: left;
      margin-bottom: 25px;
      margin-top: 35px;
      font-size: 32px;
      color: #222222;
      font-family: 'Dancing Script', cursive;
    }
    .admin-container h2:first-child {
      margin-top: 0;
    }
    .form-section {
      margin-bottom: 40px;
      padding-bottom: 40px;
      border-bottom: 1px solid #e4e4e4;
    }
    .form-section:last-child {
      border-bottom: none;
    }
    .admin-container label {
      font-weight: 600;
      color: #333333;
      font-family: 'Work Sans', sans-serif;
      margin-bottom: 8px;
    }
    .admin-container .form-control {
      border-radius: 8px;
      border: 1px solid #e4e4e4;
      padding: 14px 16px;
      box-shadow: none;
      background: #f8fbff;
      font-family: 'Work Sans', sans-serif;
    }
    .admin-container .form-control:focus {
      border-color: #3583C4;
      box-shadow: 0 0 0 3px rgba(53, 131, 196, 0.12);
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .form-row.full {
      grid-template-columns: 1fr;
    }
    .form-group {
      margin-bottom: 16px;
    }
    .custom-checkbox {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 10px;
    }
    .custom-checkbox input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #3583C4;
    }
    .custom-checkbox label {
      margin: 0;
      cursor: pointer;
      font-weight: 500;
      color: #555;
    }
    .btn-primary {
      background: #3583C4;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      padding: 12px 28px;
      color: #ffffff;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      background: #2a6a9e;
      color: #ffffff;
      text-decoration: none;
    }
    .btn-danger {
      background: #d64541;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      padding: 8px 14px;
      color: #ffffff;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.3s ease;
    }
    .btn-danger:hover {
      background: #b93532;
      color: #ffffff;
      text-decoration: none;
    }
    .btn-secondary {
      background: #6c757d;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      padding: 8px 14px;
      color: #ffffff;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.3s ease;
    }
    .btn-secondary:hover {
      background: #5a6268;
      color: #ffffff;
      text-decoration: none;
    }
    .success-message {
      padding: 12px 16px;
      background: #d4edda;
      border: 1px solid #c3e6cb;
      border-left: 4px solid #28a745;
      border-radius: 6px;
      color: #155724;
      font-weight: 600;
      margin-bottom: 24px;
    }
    .error-message {
      padding: 12px 16px;
      background: #fde8e8;
      border: 1px solid #f5c6cb;
      border-left: 4px solid #d64541;
      border-radius: 6px;
      color: #d64541;
      font-weight: 600;
      margin-bottom: 24px;
    }
    .usuarios-list h3 {
      font-family: 'Dancing Script', cursive;
      font-size: 28px;
      color: #222222;
      margin-bottom: 20px;
      margin-top: 10px;
    }
    .usuario-card {
      padding: 16px;
      border: 1px solid rgba(53,131,196,0.18);
      margin-bottom: 12px;
      border-radius: 12px;
      background: #ffffff;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      transition: all 0.2s ease;
    }
    .usuario-card:hover {
      box-shadow: 0 8px 20px rgba(53,131,196,0.12);
      border-color: rgba(53,131,196,0.3);
    }
    .usuario-info {
      flex: 1;
    }
    .usuario-info strong {
      font-weight: 700;
      color: #3a7ca5;
      font-family: 'Work Sans', sans-serif;
    }
    .usuario-info .name {
      font-weight: 600;
      color: #222222;
      margin-bottom: 4px;
      display: block;
    }
    .usuario-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 16px;
      font-size: 11px;
      font-weight: 700;
      background: #e8f4f8;
      color: #3583C4;
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .usuario-badge.admin {
      background: #fff3cd;
      color: #856404;
    }
    .usuario-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }
    .logout-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      padding: 10px 20px;
      background: #d64541;
      color: #fff;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
    }
    .logout-btn:hover {
      background: #b93532;
      text-decoration: none;
      color: #fff;
    }
    .edit-tooltip {
      position: absolute;
      top: 100%;
      right: 0;
      width: 380px;
      margin-top: 12px;
      padding: 18px;
      background: #fff;
      border: 1px solid rgba(53,131,196,0.18);
      border-radius: 14px;
      box-shadow: 0 15px 40px rgba(0,0,0,0.08);
      z-index: 10;
    }
    .edit-tooltip label {
      font-size: 13px;
      color: #555;
      margin-bottom: 6px;
      display: block;
      font-weight: 600;
    }
    .edit-tooltip input,
    .edit-tooltip .custom-checkbox {
      margin-bottom: 12px;
    }
    .edit-tooltip input {
      width: 100%;
      border-radius: 8px;
      border: 1px solid #e4e4e4;
      padding: 10px 12px;
      background: #f8fbff;
      font-size: 13px;
    }
    .edit-tooltip .button-group {
      display: flex;
      gap: 8px;
      margin-top: 14px;
    }
    .edit-tooltip .btn {
      flex: 1;
      padding: 10px 12px;
      font-size: 13px;
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
      z-index: 999;
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
      .form-row {
        grid-template-columns: 1fr;
      }
      .usuario-card {
        flex-direction: column;
        align-items: flex-start;
      }
      .usuario-actions {
        width: 100%;
      }
      .edit-tooltip {
        width: 100%;
        right: auto;
        left: 0;
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
  <div class="page-wrapper">

    <!--header top-->
    <div class="header-top">
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-12 col-md-6">
            <div class="top-left text-center text-md-left">
              <h6 style="text-align: center">Horario: <strong> Lunes a Viernes, de 8 a 19</strong></h6>
            </div>
          </div>
          <div class="col-md-6">
            <div class="top-right text-center text-md-right">
              <ul class="social-links">
                <li>
                  <a href="https://www.facebook.com/El-Espejo-Espacio-Psicoterap%C3%A9utico-274649443003355/" target="_blank" rel="noopener noreferrer" aria-label="facebook">
                    <i class="fab fa-facebook-f"></i>
                  </a>
                </li>
                <li>
                  <a href="https://instagram.com/el.espejo.rio4?igshid=11por4zixnxsk" target="_blank" rel="noopener noreferrer" aria-label="instagram">
                    <i class="fab fa-instagram"></i>
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!--header top-->

    <!--Header Upper-->
    <section class="header-uper">
      <div class="container col-xl-12 row justify-content-center">
        <div class="row align-items-center">
          <div class="col-xl-12 col-lg-3">
          </div>
          <div class="col-xl-12 col-lg-9">
            <div class="right-side">
              <ul class="contact-info pl-0 mb-4 mb-md-0" style="margin-top: 10px; margin-bottom: 10px;">
                <li class="item text-left logo">
                  <a href="index.html" style="display: inline-block; text-decoration: none;">
                    <img loading="lazy" class="img-fluid" src="images/logo/EL_ESPEJO_ISOLOGO_H_320px.png" style="margin-top: -30px" alt="logo">
                  </a>
                </li>
                <li class="item text-left">
                  <div class="icon-box">
                    <i class="far fa-envelope"></i>
                  </div>
                  <strong>Mail</strong>
                  <br>
                  <a href="mailto:info@centroelespejo.com.ar">
                    <span>info@centroelespejo.com.ar</span>
                  </a>
                </li>
                <li class="item text-left">
                  <div class="icon-box">
                    <i class="fas fa-phone"></i>
                  </div>
                  <strong>Teléfono</strong>
                  <br>
                  <span><a href="tel:+543585185508">(3585) 185508</a></span>
                </li>
                <li class="item text-left">
                  <div class="icon-box">
                    <i class="fab fa-whatsapp"></i>
                  </div>
                  <strong>Escribinos</strong>
                  <br>
                  <span><a href="https://wa.me/5493585185508/">Contacto directo!</a> </span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Page Header -->
    <section class="single-page-header">
      <a href="logout.php" class="logout-btn" title="Cerrar Sesión">Cerrar Sesión</a>
      <div class="container">
        <h2>Panel de Administración</h2>
        <p>Gestiona los usuarios del sistema</p>
      </div>
    </section>

    <!-- Admin Section -->
    <section class="admin-section">
      <div class="container">
        <div class="admin-container">
          <?php if (!empty($success)): ?>
            <div class="success-message">✓ <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if (!empty($error)): ?>
            <div class="error-message">✕ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <!-- Crear Usuario -->
          <div class="form-section">
            <h2>Crear Nuevo Usuario</h2>
            <form action="admin.php" method="post">
              <input type="hidden" name="accion" value="crear">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
              
              <div class="form-row">
                <div class="form-group">
                  <label for="username">Nombre de Usuario</label>
                  <input type="text" class="form-control" id="username" name="username" placeholder="ej: juan_perez" required minlength="3">
                  <small style="color: #999;">Mínimo 3 caracteres</small>
                </div>
                <div class="form-group">
                  <label for="name">Nombre Completo</label>
                  <input type="text" class="form-control" id="name" name="name" placeholder="ej: Dr. Juan Pérez" required>
                </div>
              </div>

              <div class="form-row full">
                <div class="form-group">
                  <label for="password">Contraseña</label>
                  <input type="password" class="form-control" id="password" name="password" placeholder="Mínimo 6 caracteres" required minlength="6">
                  <small style="color: #999;">Mínimo 6 caracteres</small>
                </div>
              </div>

              <div class="form-group">
                <div class="custom-checkbox">
                  <input type="checkbox" id="is_admin" name="is_admin" value="1">
                  <label for="is_admin">¿Es administrador?</label>
                </div>
              </div>

              <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </form>
          </div>

          <!-- Listar Usuarios -->
          <div class="usuarios-list">
            <h3>Usuarios del Sistema</h3>
            <?php if (empty($usuarios)): ?>
              <p style="text-align: center; color: #999; padding: 20px;">No hay usuarios registrados.</p>
            <?php else: ?>
              <?php foreach ($usuarios as $index => $user): ?>
                <div class="usuario-card">
                  <div class="usuario-info">
                    <span class="name"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if ($user['is_admin'] ?? false): ?>
                      <span class="usuario-badge admin">Administrador</span>
                    <?php else: ?>
                      <span class="usuario-badge">Usuario</span>
                    <?php endif; ?>
                  </div>
                  <div class="usuario-actions">
                    <button type="button" class="btn btn-secondary" onclick="openEditTooltip(event, <?php echo $index; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $user['is_admin'] ? 'true' : 'false'; ?>)" title="Editar usuario">Editar</button>
                    <?php if ($index !== $currentUserIndex): ?>
                      <form action="admin.php" method="post" style="display: inline;">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="delete_index" value="<?php echo $index; ?>">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Deseas eliminar a <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>?')" title="Eliminar usuario">Eliminar</button>
                      </form>
                    <?php else: ?>
                      <span style="color: #999; font-size: 12px; padding: 6px 8px;">Tu usuario</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </section>

  </div>

  <!-- jQuery -->
  <script src="plugins/jquery.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="plugins/bootstrap/bootstrap.min.js"></script>

  <script>
    // Función para abrir tooltip de edición
    function openEditTooltip(event, index, username, name, isAdmin) {
      event.preventDefault();
      
      // Cerrar cualquier tooltip abierto
      document.querySelectorAll('.edit-tooltip').forEach(el => el.remove());
      
      // Crear el HTML del tooltip
      const tooltip = document.createElement('div');
      tooltip.className = 'edit-tooltip';
      tooltip.innerHTML = `
        <form action="admin.php" method="post" id="editForm_${index}">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="edit_index" value="${index}">
          
          <label>Nombre Completo</label>
          <input type="text" name="edit_name" value="${name}" required>
          
          <label>Nueva Contraseña (opcional)</label>
          <input type="password" name="edit_password" placeholder="Dejar vacío para no cambiar" minlength="6">
          <small style="color: #999; display: block; margin-top: -8px;">Mínimo 6 caracteres si la cambias</small>
          
          <div class="custom-checkbox" style="margin-top: 12px;">
            <input type="checkbox" id="edit_is_admin_${index}" name="edit_is_admin" value="1" ${isAdmin ? 'checked' : ''}>
            <label for="edit_is_admin_${index}" style="margin: 0;">¿Es administrador?</label>
          </div>
          
          <div class="button-group">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <button type="button" class="btn btn-secondary" onclick="document.querySelector('.edit-tooltip').remove()">Cancelar</button>
          </div>
        </form>
      `;
      
      // Insertar después del botón Editar
      event.target.parentElement.insertAdjacentElement('afterend', tooltip);
    }
    
    // Cerrar tooltip al hacer click fuera
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.usuario-card') && !event.target.closest('.edit-tooltip')) {
        document.querySelectorAll('.edit-tooltip').forEach(el => el.remove());
      }
    });
  </script>

</body>
</html>