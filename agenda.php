<?php
// Incluir configuración de seguridad centralizada
require_once __DIR__ . '/config.php';

// Verificar autenticación
requireAuth();

$user = $_SESSION['user'];
$name = $_SESSION['name'];

// Archivo JSON para turnos
$turnosFile = "data/{$user}_turnos.json";

// Cargar turnos existentes de forma segura
if (file_exists($turnosFile)) {
    $turnos = json_decode(file_get_contents($turnosFile), true) ?? [];
} else {
    $turnos = [];
}

// Agregar nuevo turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    // Validar CSRF
    if (!validateCSRFToken()) {
        $_SESSION['error'] = 'Token de seguridad inválido';
        header('Location: agenda.php');
        exit;
    }
    
    // Sanitizar y validar entrada
    $fecha = sanitize($_POST['fecha'] ?? '', 'string');
    $hora = sanitize($_POST['hora'] ?? '', 'string');
    $paciente = sanitize($_POST['paciente'] ?? '', 'string');
    
    if (empty($fecha) || empty($hora) || empty($paciente)) {
        $_SESSION['error'] = 'Todos los campos son requeridos';
    } elseif (!strtotime($fecha)) {
        $_SESSION['error'] = 'Formato de fecha inválido';
    } else {
        $nuevoTurno = [
            'fecha' => $fecha,
            'hora' => $hora,
            'paciente' => $paciente,
            'creado' => date('Y-m-d H:i:s')
        ];
        $turnos[] = $nuevoTurno;
        
        if (file_put_contents($turnosFile, json_encode($turnos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $_SESSION['success'] = 'Turno agregado exitosamente';
            logSecurityEvent('TURNO_CREADO', ['user' => $user, 'fecha' => $fecha]);
        } else {
            $_SESSION['error'] = 'Error al guardar turno';
        }
    }
    header('Location: agenda.php');
    exit;
}

// Editar turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    // Validar CSRF
    if (!validateCSRFToken()) {
        $_SESSION['error'] = 'Token de seguridad inválido';
        header('Location: agenda.php');
        exit;
    }
    
    // Validar y sanitizar índice
    if (!isset($_POST['edit_index']) || !is_numeric($_POST['edit_index'])) {
        $_SESSION['error'] = 'Índice inválido';
        header('Location: agenda.php');
        exit;
    }
    
    $index = (int)$_POST['edit_index'];
    
    // Validar que el índice existe
    if (!isset($turnos[$index])) {
        $_SESSION['error'] = 'Turno no encontrado';
        header('Location: agenda.php');
        exit;
    }
    
    // Sanitizar entrada
    $fecha = sanitize($_POST['edit_fecha'] ?? '', 'string');
    $hora = sanitize($_POST['edit_hora'] ?? '', 'string');
    $paciente = sanitize($_POST['edit_paciente'] ?? '', 'string');
    
    if (empty($fecha) || empty($hora) || empty($paciente)) {
        $_SESSION['error'] = 'Todos los campos son requeridos';
    } elseif (!strtotime($fecha)) {
        $_SESSION['error'] = 'Formato de fecha inválido';
    } else {
        $turnos[$index] = [
            'fecha' => $fecha,
            'hora' => $hora,
            'paciente' => $paciente,
            'actualizado' => date('Y-m-d H:i:s')
        ];
        
        if (file_put_contents($turnosFile, json_encode($turnos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $_SESSION['success'] = 'Turno actualizado exitosamente';
            logSecurityEvent('TURNO_EDITADO', ['user' => $user, 'index' => $index]);
        } else {
            $_SESSION['error'] = 'Error al guardar cambios';
        }
    }
    header('Location: agenda.php');
    exit;
}

// Eliminar turno
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $index = (int)$_GET['delete'];
    
    if (!isset($turnos[$index])) {
        $_SESSION['error'] = 'Turno no encontrado';
    } else {
        $deleted_turno = $turnos[$index];
        unset($turnos[$index]);
        $turnos = array_values($turnos); // Reindexar
        
        if (file_put_contents($turnosFile, json_encode($turnos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $_SESSION['success'] = 'Turno eliminado';
            logSecurityEvent('TURNO_ELIMINADO', ['user' => $user, 'fecha' => $deleted_turno['fecha']]);
        } else {
            $_SESSION['error'] = 'Error al eliminar turno';
        }
    }
    header('Location: agenda.php');
    exit;
}

// Generar token CSRF
$csrf_token = generateCSRFToken();
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>

  <!-- ** Basic Page Needs ** -->
  <meta charset="utf-8">
  <title>Agenda - El Espejo | Espacio Psicoterapéutico</title>
  <!-- Css fuentes -->
  <link rel="stylesheet" href="css/fonts.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">

  <!-- ** SEO & Meta ** -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="description" content="Agenda para profesionales - El Espejo Espacio Psicoterapéutico">
  <meta name="keywords" content="agenda, turnos, profesionales, El Espejo">
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
  <!-- jquery-ui -->
  <link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.css">
  <!-- timePicker -->
  <link rel="stylesheet" href="plugins/timePicker/timePicker.css">
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
    .agenda-section {
      padding: 70px 0 90px;
      background: transparent;
    }
    .agenda-container {
      max-width: 900px;
      margin: -80px auto 0;
      padding: 50px 45px;
      background: rgba(255,255,255,0.98);
      border-radius: 24px;
      box-shadow: 0 30px 80px rgba(0,0,0,0.08);
      border: 1px solid rgba(53,131,196,0.14);
    }
    .agenda-container h2 {
      text-align: center;
      margin-bottom: 25px;
      font-size: 38px;
      color: #222222;
      font-family: 'Dancing Script', cursive;
    }
    .agenda-container label {
      font-weight: 600;
      color: #333333;
      font-family: 'Work Sans', sans-serif;
    }
    .agenda-container .form-control {
      border-radius: 8px;
      border: 1px solid #e4e4e4;
      padding: 14px 16px;
      box-shadow: none;
      background: #f8fbff;
    }
    .agenda-container .form-control:focus {
      border-color: #3583C4;
      box-shadow: 0 0 0 3px rgba(53, 131, 196, 0.12);
    }
    .agenda-container button {
      margin-top: 10px;
    }
    .turnos-list {
      margin-top: 30px;
    }
    .turnos-list h3 {
      font-family: 'Dancing Script', cursive;
      color: #222222;
      margin-bottom: 18px;
      font-size: 28px;
    }
    .turno-item {
      padding: 16px;
      border: 1px solid rgba(53,131,196,0.18);
      margin-bottom: 14px;
      border-radius: 12px;
      background: #ffffff;
      position: relative;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }
    .turno-item strong {
      font-weight: 600;
      color: #3a7ca5;
      font-family: 'Work Sans', sans-serif;
    }
    .turno-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }
    .success-message {
      margin-bottom: 20px;
      padding: 12px;
      background: #d4edda;
      border: 1px solid #c3e6cb;
      border-radius: 6px;
      color: #155724;
      font-weight: 600;
    }
    .error-message {
      margin-bottom: 20px;
      padding: 12px;
      background: #fde8e8;
      border: 1px solid #f5c6cb;
      border-radius: 6px;
      color: #d64541;
      font-weight: 600;
      border-left: 4px solid #d64541;
    }
    .edit-tooltip {
      position: absolute;
      top: 100%;
      right: 0;
      width: 320px;
      margin-top: 12px;
      padding: 14px;
      background: #fff;
      border: 1px solid rgba(53,131,196,0.18);
      border-radius: 14px;
      box-shadow: 0 15px 40px rgba(0,0,0,0.08);
      z-index: 3;
    }
    .edit-tooltip label {
      font-size: 13px;
      color: #555;
      margin-bottom: 6px;
      display: block;
      font-weight: 600;
    }
    .edit-tooltip input {
      width: 100%;
      border-radius: 8px;
      border: 1px solid #e4e4e4;
      padding: 10px 12px;
      margin-bottom: 10px;
      background: #f8fbff;
    }
    .edit-tooltip .btn {
      font-size: 13px;
      padding: 8px 12px;
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
    }
    .logout-btn:hover {
      background: #b93532;
      text-decoration: none;
      color: #fff;
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
    <!--Header Upper-->

    <!-- Page Header -->
    <section class="single-page-header">
      <a href="logout.php" class="logout-btn" title="Cerrar Sesión">Cerrar Sesión</a>
      <div class="container">
        <h2>Mi Agenda</h2>
        <p>Bienvenido/a, <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    </section>

    <!-- Agenda Section -->
    <section class="agenda-section">
      <div class="container">
        <div class="agenda-container">
          <?php if (!empty($success)): ?>
            <div class="success-message">✓ <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
          <?php if (!empty($error)): ?>
            <div class="error-message">✕ <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <h2>Agregar Nuevo Turno</h2>
          <form action="agenda.php" method="post">
            <!-- Token CSRF para seguridad -->
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-group">
              <label for="fecha">Fecha</label>
              <input type="date" class="form-control" id="fecha" name="fecha" required>
            </div>
            <div class="form-group">
              <label for="hora">Hora</label>
              <input type="time" class="form-control" id="hora" name="hora" required>
            </div>
            <div class="form-group">
              <label for="paciente">Paciente</label>
              <input type="text" class="form-control" id="paciente" name="paciente" placeholder="Nombre del paciente" required>
            </div>
            <button type="submit" class="btn btn-primary">Agregar Turno</button>
          </form>

          <div class="turnos-list">
            <h3>Mis Turnos</h3>
            <?php if (empty($turnos)): ?>
              <p style="text-align: center; color: #999; padding: 20px;">No hay turnos agendados aún.</p>
            <?php else: ?>
              <?php foreach ($turnos as $index => $turno): ?>
                <div class="turno-item">
                  <div>
                    <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($turno['fecha'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <br>
                    <span style="color: #666;">Paciente: <?php echo htmlspecialchars($turno['paciente'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <br>
                    <span style="color: #999; font-size: 13px;">Hora: <?php echo htmlspecialchars($turno['hora'], ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <div class="turno-actions">
                    <form action="agenda.php" method="post" style="display: inline;">
                      <input type="hidden" name="accion" value="editar">
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="edit_index" value="<?php echo $index; ?>">
                      <input type="hidden" name="edit_fecha" id="edit_fecha_<?php echo $index; ?>" value="<?php echo htmlspecialchars($turno['fecha'], ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="edit_hora" id="edit_hora_<?php echo $index; ?>" value="<?php echo htmlspecialchars($turno['hora'], ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="edit_paciente" id="edit_paciente_<?php echo $index; ?>" value="<?php echo htmlspecialchars($turno['paciente'], ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="button" class="btn btn-primary btn-sm" onclick="openEditTooltip(<?php echo $index; ?>)" style="margin-bottom: 0;">Editar</button>
                    </form>
                    <a href="agenda.php?delete=<?php echo $index; ?>" class="btn btn-danger btn-sm" style="margin-bottom: 0;" onclick="return confirm('¿Deseas eliminar este turno?')">Eliminar</a>
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
  <!-- jQuery UI -->
  <script src="plugins/jquery-ui/jquery-ui.js"></script>
  <!-- timePicker -->
  <script src="plugins/timePicker/timePicker.js"></script>

  <script>
    // Funciones para editar turnos con tooltip
    function openEditTooltip(index) {
      // Cerramos cualquier tooltip abierto
      document.querySelectorAll('.edit-tooltip').forEach(el => el.remove());
      
      // Obtenemos los datos del turno
      const fecha = document.querySelector('#edit_fecha_' + index).value;
      const hora = document.querySelector('#edit_hora_' + index).value;
      const paciente = document.querySelector('#edit_paciente_' + index).value;
      
      // Creamos el HTML del tooltip
      const tooltip = document.createElement('div');
      tooltip.className = 'edit-tooltip';
      tooltip.innerHTML = `
        <form action="agenda.php" method="post" id="editForm_${index}">
          <input type="hidden" name="accion" value="editar">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="edit_index" value="${index}">
          
          <label>Fecha</label>
          <input type="date" name="edit_fecha" value="${fecha}" required>
          
          <label>Hora</label>
          <input type="time" name="edit_hora" value="${hora}" required>
          
          <label>Paciente</label>
          <input type="text" name="edit_paciente" value="${paciente}" required>
          
          <div style="display: flex; gap: 8px; margin-top: 10px;">
            <button type="submit" class="btn btn-primary btn-sm" style="flex: 1;">Guardar</button>
            <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="document.querySelector('.edit-tooltip').remove()">Cancelar</button>
          </div>
        </form>
      `;
      
      // Insertamos el tooltip después del botón Editar
      const editBtn = event.target;
      editBtn.parentElement.insertAdjacentElement('afterend', tooltip);
    }
    
    // Cerrar tooltip al hacer click fuera
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.turno-item') && !event.target.closest('.edit-tooltip')) {
        document.querySelectorAll('.edit-tooltip').forEach(el => el.remove());
      }
    });

    // Validar fechas (no permitir fechas pasadas)
    const fechaInput = document.querySelector('#fecha');
    if (fechaInput) {
      const today = new Date().toISOString().split('T')[0];
      fechaInput.setAttribute('min', today);
      fechaInput.value = today;
    }
  </script>

</body>
</html>