<?php
// Incluir configuración de seguridad centralizada
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// ✓ VERIFICACIÓN DE AUTENTICACIÓN - CRÍTICA PARA SEGURIDAD
requireAuthAPI();

$usuariosFile = 'data/usuarios.json';

// Crear archivo si no existe
if (!file_exists($usuariosFile)) {
    $defaultUsuarios = [
        [
            'username' => 'juan',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Dr. Juan Pérez',
            'is_admin' => false
        ],
        [
            'username' => 'maria',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Dra. María García',
            'is_admin' => false
        ],
        [
            'username' => 'ana',
            'password' => password_hash('pass123', PASSWORD_DEFAULT),
            'name' => 'Lic. Ana López',
            'is_admin' => false
        ]
    ];
    file_put_contents($usuariosFile, json_encode($defaultUsuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? '';

function getUsuarios() {
    global $usuariosFile;
    return json_decode(file_get_contents($usuariosFile), true);
}

function saveUsuarios($usuarios) {
    global $usuariosFile;
    return file_put_contents($usuariosFile, json_encode($usuarios, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function response($success, $message = '', $data = null) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response = array_merge($response, $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Obtener datos POST/PUT de forma segura
function getRequestData() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case 'list':
        $usuarios = getUsuarios();
        $usuariosClean = array_map(function($u) {
            return ['username' => $u['username'], 'name' => $u['name'], 'is_admin' => $u['is_admin'] ?? false];
        }, $usuarios);
        response(true, '', ['usuarios' => $usuariosClean]);
        break;

    case 'create':
        $data = getRequestData();
        $username = sanitize($data['username'] ?? '', 'string');
        $password = $data['password'] ?? '';
        $name = sanitize($data['name'] ?? '', 'string');

        // Validar campos
        if (empty($username) || empty($password) || empty($name)) {
            logSecurityEvent('USER_CREATE_FAILED', ['reason' => 'missing_fields']);
            response(false, 'Todos los campos son requeridos');
        }
        
        // Validar longitud de contraseña
        if (strllogSecurityEvent('USER_CREATE_FAILED', ['reason' => 'username_exists', 'username' => $username]);
                response(false, 'El usuario ya existe');
            }
        }

        $usuarios[] = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'is_admin' => false
        ];

        if (saveUsuarios($usuarios)) {
            logSecurityEvent('USER_CREATED', ['username' => $username]);
            response(true, 'Usuario creado exitosamente');
        } else {
            response(false, 'Error al guardar usuario');
        }
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => $name,
            'is_admin' => false
        ];

        saveUsuagetRequestData();
        $username = sanitize($data['username'] ?? '', 'string');
        $password = $data['password'] ?? '';
        $name = sanitize($data['name'] ?? '', 'string');

        if (empty($username) || empty($name)) {
            logSecurityEvent('USER_UPDATE_FAILED', ['reason' => 'missing_fields']);
            response(false, 'Usuario y nombre son requeridos');
        }
        
        if (!empty($password) && strlen($password) < 6) {
            response(false, 'La contraseña debe tener al menos 6 caractere
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';
!empty($password)) {
                    $u['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            logSecurityEvent('USER_UPDATE_FAILED', ['reason' => 'user_not_found', 'username' => $username]);
            response(false, 'Usuario no encontrado');
        }

        if (saveUsuarios($usuarios)) {
            logSecurityEvent('USER_UPDATED', ['username' => $username]);
            response(true, 'Usuario actualizado');
        } else {
            response(false, 'Error al guardar cambios');
        }
                break;
            }
        }
getRequestData();
        $username = sanitize($data['username'] ?? '', 'string');

        if (empty($username)) {
            logSecurityEvent('USER_DELETE_FAILED', ['reason' => 'missing_username']);
            response(false, 'Usuario requerido');
        }

        $usuarios = getUsuarios();
        $initialCount = count($usuarios);
        $usuarios = array_filter($usuarios, function($u) use ($username) {
            return $u['username'] !== $username;
        });
        $usuarios = array_values($usuarios);

        if (count($usuarios) === $initialCount) {
            logSecurityEvent('USER_DELETE_FAILED', ['reason' => 'user_not_found', 'username' => $username]);
            response(false, 'Usuario no encontrado');
        }

        if (saveUsuarios($usuarios)) {
            logSecurityEvent('USER_DELETED', ['username' => $username]);
            response(true, 'Usuario eliminado');
        } else {
            response(false, 'Error al eliminar usuario');
        }do');
        }

        $usuarios = getUsuarios();
        $usuarios = array_filter($usuarios, function($u) use ($username) {
            return $u['username'] !== $username;
        });
        $usuarios = array_values($usuarios);

        saveUsuarios($usuarios);
        response(true, 'Usuario eliminado');
        break;

    default:
        response(false, 'Acción no válida');
}
?>
