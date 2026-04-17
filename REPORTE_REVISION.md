# 🔍 Reporte de Revisión - El Espejo Sitio Web

**Fecha:** 17 de Abril de 2026  
**Proyecto:** El Espejo - Espacio Psicoterapéutico  
**Estado:** Se encontraron **20+ problemas** (5 Críticos, 10 Importantes, 5+ Mejoras)

---

## 🚨 PROBLEMAS CRÍTICOS (Requieren acción inmediata)

### 1. **CREDENCIALES DE TWITTER EXPUESTAS** ⚠️ CRÍTICO
**Archivo:** `inc/twitter/index.php` (líneas 3-12, 48-51)  
**Problema:** Las credenciales reales de Twitter están hardcodeadas en el código:
- Consumer Key: `O1MpyNUKaV6m5eI3zBozh4xhh`
- Consumer Secret: `H8gRYxPwypaPPDYFG6CfnhH0ioiOznOkKGxXhXbvNcpvwIBFkY`
- Access Token: `858711673-faHBplLJ8q1I5LnVItfkxo9I9Rx3z4bU6ewaIvHj`
- Token Secret: `XCpfOrvi4bwCeSSNxhJlbfPb5tgWkexSj5ZtrZluWTJco`

**Riesgo:** Cualquiera que tenga acceso al código puede usar estas credenciales para publicar en Twitter de El Espejo.

**Recomendación:**
- [ ] Regenerar TODAS las credenciales de Twitter inmediatamente
- [ ] Mover credenciales a archivo `.env` (no versionado)
- [ ] Cargar credenciales desde variables de entorno

```php
// ❌ ACTUAL (INSEGURO)
$consumer_key = 'O1MpyNUKaV6m5eI3zBozh4xhh';

// ✅ MEJOR
$consumer_key = getenv('TWITTER_CONSUMER_KEY');
```

---

### 2. **SIN PROTECCIÓN CSRF** ⚠️ CRÍTICO
**Archivos:** `login.php`, `agenda.php`, `admin_api.php`  
**Problema:** No hay tokens CSRF (Cross-Site Request Forgery) en formularios POST.

**Riesgo:** Un atacante puede hacer que usuarios autenticados realicen acciones sin su conocimiento.

**Recomendación:**
```php
// Generar token en sesión
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// En formularios HTML
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// Validar en POST
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token inválido');
}
```

---

### 3. **Sin Autenticación en admin_api.php** ⚠️ CRÍTICO
**Archivo:** `admin_api.php`  
**Problema:** El archivo no valida si el usuario está autenticado antes de permitir operaciones.

**Riesgo:** Cualquiera puede crear, editar o eliminar usuarios.

**Recomendación:**
```php
<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
?>
```

---

### 4. **Sin Sanitización de Entrada** ⚠️ CRÍTICO
**Archivos:** `login.php`, `agenda.php`, `admin_api.php`  
**Problema:** No hay sanitización de datos POST/GET.

**Riesgo:** Posibles ataques XSS o inyección de código.

**Recomendación:**
```php
$username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
$name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
```

---

### 5. **Archivos de Sesión en Directorio Temporal** ⚠️ CRÍTICO
**Archivo:** `login.php`, `logout.php`, `admin_api.php`, `agenda.php`  
**Problema:** `ini_set('session.save_path', sys_get_temp_dir());`

**Riesgo:** Las sesiones se guardan en directorio temporal, pueden ser sobrescritas o comprometidas.

**Recomendación:**
```php
// Crear directorio seguro en el proyecto
$session_dir = __DIR__ . '/sessions';
if (!is_dir($session_dir)) {
    mkdir($session_dir, 0700, true);
}
ini_set('session.save_path', $session_dir);

// Agregar a .gitignore si versionan el código
echo "/sessions/" >> .gitignore
```

---

## ⚠️ PROBLEMAS IMPORTANTES

### 6. **Sin Validación de Método HTTP**
**Archivo:** `agenda.php`  
**Línea:** 14, 25, 34

```php
// ❌ ACTUAL - Mezcla GET y POST
if (isset($_GET['delete'])) { ... }
if ($_SERVER['REQUEST_METHOD'] == 'POST') { ... }

// ✅ MEJOR - Separar claramente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) { ... }
```

---

### 7. **Uso de onclick en lugar de Event Listeners**
**Archivos:** `login.html`, `admin.html`, `agenda.html`

```html
<!-- ❌ ACTUAL (Obsoleto) -->
<button onclick="createAccount()">Crear cuenta</button>

<!-- ✅ MEJOR -->
<button id="createAccountBtn">Crear cuenta</button>
<script>
document.getElementById('createAccountBtn').addEventListener('click', createAccount);
</script>
```

---

### 8. **Sin Validación de Tipos de Datos**
**Archivo:** `admin_api.php`  
**Problema:** No hay validación de tipos en operaciones CRUD.

```php
// ❌ ACTUAL
$index = (int)$_POST['edit_index'];  // Conversión simple

// ✅ MEJOR
if (!is_numeric($_POST['edit_index']) || (int)$_POST['edit_index'] < 0) {
    response(false, 'Índice inválido');
}
$index = (int)$_POST['edit_index'];
```

---

### 9. **Sin Rate Limiting en Login**
**Archivo:** `login.php`  
**Riesgo:** Sin protección contra ataques de fuerza bruta.

**Recomendación:**
```php
// Guardar intentos fallidos en sesión
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_login_attempt'] = time();
}

// Limitar a 5 intentos por 15 minutos
if ($_SESSION['login_attempts'] > 5 && time() - $_SESSION['last_login_attempt'] < 900) {
    die('Demasiados intentos. Intente más tarde.');
}
```

---

### 10. **Sin Validación de Existencia de Archivo**
**Archivo:** `agenda.php`  
**Línea:** 12

```php
// ❌ ACTUAL
$turnos = json_decode(file_get_contents($turnosFile), true);

// ✅ MEJOR
if (!file_exists($turnosFile)) {
    $turnos = [];
    file_put_contents($turnosFile, json_encode([], JSON_PRETTY_PRINT));
} else {
    $turnos = json_decode(file_get_contents($turnosFile), true) ?? [];
}
```

---

### 11. **URLs Incorrectas en Meta Tags**
**Archivo:** `index.html` (línea 27, 32)  
**Problema:** Canonical y Open Graph apuntan a GitHub Pages en lugar de dominio real.

```html
<!-- ❌ ACTUAL -->
<link rel="canonical" href="https://juanmanuelcantero.github.io/el_espejo/">
<meta property="og:url" content="https://juanmanuelcantero.github.io/el_espejo/">

<!-- ✅ MEJOR -->
<link rel="canonical" href="https://centroelespejo.com.ar/">
<meta property="og:url" content="https://centroelespejo.com.ar/">
```

---

### 12. **Archivo Antiguo No Eliminado**
**Archivo:** `index_old.html`  
**Problema:** Versión antigua del index que puede causar confusión.

**Recomendación:** Eliminar o mover a carpeta `backup/`

---

### 13. **Sin Content Security Policy (CSP)**
**Problema:** No hay headers de seguridad CSP.

**Recomendación:** Agregar en `.htaccess` o configuración del servidor:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://code.jquery.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
```

---

### 14. **Sin Redirección a HTTPS**
**Problema:** El sitio acepta HTTP inseguro.

**Recomendación:** Agregar en `.htaccess`:
```
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

### 15. **admin.html Públicamente Accesible Sin Autenticación**
**Archivo:** `admin.html`  
**Problema:** El archivo HTML está disponible públicamente (aunque la API tenga protección).

**Recomendación:** 
- Generar admin.html solo después de autenticación
- O proteger con `.htaccess`:
```
<Files "admin.html">
    Deny from all
</Files>
```

---

## 💡 MEJORAS SUGERIDAS

### 16. **Agregar Validación de Email**
**Archivos:** `login.html`, `admin.html`

```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    response(false, 'Email inválido');
}
```

---

### 17. **Agregar Sistema de Logs**
Registrar intentos de login fallidos y cambios administrativos:

```php
function logSecurityEvent($event, $details) {
    $log = date('Y-m-d H:i:s') . " - " . $event . " - " . json_encode($details) . "\n";
    file_put_contents('logs/security.log', $log, FILE_APPEND);
}
```

---

### 18. **Mejorar Manejo de Errores**
No exponer errores específicos al usuario:

```php
// ❌ ACTUAL - Expone detalles
if (!file_exists($file)) {
    throw new Exception("Archivo no encontrado: $file");
}

// ✅ MEJOR - Mensaje genérico
if (!file_exists($file)) {
    logSecurityEvent('file_error', ['file' => $file]);
    response(false, 'Error al procesar solicitud');
}
```

---

### 19. **Agregar Validación de Permisos**
En `admin_api.php`:

```php
if (!isset($_SESSION['user'])) {
    response(false, 'No autenticado', null);
}

// Verificar si es admin (cuando se implemente)
if (!isAdmin($_SESSION['user'])) {
    response(false, 'Permiso denegado', null);
}
```

---

### 20. **Usar Prepared Statements si Se Agrega BD**
Si en el futuro se agrega base de datos:

```php
// ✅ SEGURO
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
$stmt->execute([$username]);

// ❌ INSEGURO
$query = "SELECT * FROM usuarios WHERE username = '$username'";
```

---

### 21. **Mejorar Estructura de Directorios**
```
├── public/              # Archivos públicos
│   ├── index.html
│   ├── login.html
│   └── css/
├── private/             # Archivos privados
│   ├── login.php
│   ├── agenda.php
│   └── admin_api.php
├── data/                # Datos (agregar a .gitignore)
├── logs/                # Logs (agregar a .gitignore)
└── config/              # Configuración (agregar a .gitignore)
    └── env.example
```

---

### 22. **Agregar Archivo .env.example**
```
TWITTER_CONSUMER_KEY=your_key_here
TWITTER_CONSUMER_SECRET=your_secret_here
TWITTER_ACCESS_TOKEN=your_token_here
TWITTER_TOKEN_SECRET=your_token_secret_here
SESSION_PATH=/path/to/sessions
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=el_espejo
```

---

### 23. **Mejorar Validación de Fecha en Agenda**
```php
if (!strtotime($_POST['fecha'])) {
    response(false, 'Formato de fecha inválido');
}

// O mejor aún, usar HTML5 date picker
<input type="date" required>
```

---

### 24. **Agregar Versionado de API**
Cambiar `admin_api.php` a `api/v1/usuarios.php`:
```php
// Mejor organización y escalabilidad
```

---

## 📊 RESUMEN DE PROBLEMAS

| Severidad | Cantidad | Ejemplos |
|-----------|----------|----------|
| 🚨 Crítico | 5 | Credenciales expuestas, Sin CSRF, Sin autenticación |
| ⚠️ Importante | 10 | Rate limiting, Validación de datos, CSP |
| 💡 Mejora | 9+ | Estructura, Logs, Versionado API |

---

## ✅ PLAN DE ACCIÓN (Prioridad)

### Fase 1: INMEDIATA (Hoy)
- [ ] Regenerar credenciales de Twitter
- [ ] Implementar validación de autenticación en `admin_api.php`
- [ ] Mover archivos de sesión a directorio seguro

### Fase 2: ESTA SEMANA
- [ ] Implementar CSRF tokens
- [ ] Agregar sanitización de entrada
- [ ] Implementar Rate limiting en login
- [ ] Agregar redirección HTTPS

### Fase 3: PRÓXIMAS DOS SEMANAS
- [ ] Agregar sistema de logs
- [ ] Implementar CSP headers
- [ ] Mejorar manejo de errores
- [ ] Refactorizar estructura de directorios

### Fase 4: MEJORAS A LARGO PLAZO
- [ ] Implementar base de datos (en lugar de JSON)
- [ ] Agregar API versioning
- [ ] Implementar 2FA (autenticación de dos factores)
- [ ] Agregar tests automáticos

---

## 📝 NOTAS ADICIONALES

1. **Backup de credenciales:** Cambiar todas las credenciales de Twitter antes de cualquier acción.

2. **Testing:** Después de cada cambio, hacer pruebas de seguridad.

3. **Documentación:** Mantener documentación actualizada de cambios de seguridad.

4. **Auditoría:** Considerar una auditoría de seguridad profesional anual.

5. **Monitoreo:** Implementar monitoreo de intentos de acceso no autorizados.

---

## 🔗 Recursos Útiles

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)

---

**Generado:** 17 de Abril de 2026  
**Estado:** Requiere atención inmediata en problemas críticos
