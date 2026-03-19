<?php
/**
 * Super Admin Authentication API
 * 
 * POST /api/superadmin/auth.php - Login
 * POST /api/superadmin/auth.php?action=logout - Logout
 * GET /api/superadmin/auth.php?action=check - Verificar sessão
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Session para Super Admin
session_name('SUPERADMIN_SESSION');
session_start();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();

    switch ($method) {
        case 'POST':
            if ($action === 'logout') {
                logout();
            } else {
                login($pdo);
            }
            break;
            
        case 'GET':
            if ($action === 'check') {
                checkSession($pdo);
            } else {
                errorResponse('Ação inválida', 400);
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Super Admin Auth Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Login de Super Admin
 */
function login(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        errorResponse('Usuário e senha são obrigatórios');
    }
    
    // Busca super admin
    $stmt = $pdo->prepare("
        SELECT * FROM super_admins 
        WHERE (username = ? OR email = ?) AND is_active = 1
    ");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        errorResponse('Credenciais inválidas', 401);
    }
    
    if (!password_verify($password, $admin['password_hash'])) {
        errorResponse('Credenciais inválidas', 401);
    }
    
    // Atualiza último login
    $stmt = $pdo->prepare("UPDATE super_admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Cria sessão
    $_SESSION['super_admin_id'] = $admin['id'];
    $_SESSION['super_admin_name'] = $admin['name'];
    $_SESSION['super_admin_username'] = $admin['username'];
    $_SESSION['logged_in_at'] = time();
    
    // Gera token para API calls (opcional)
    $token = bin2hex(random_bytes(32));
    $_SESSION['api_token'] = $token;
    
    jsonResponse([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'data' => [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'token' => $token
        ]
    ]);
}

/**
 * Logout
 */
function logout(): void
{
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'Logout realizado'
    ]);
}

/**
 * Verificar se sessão é válida
 */
function checkSession(PDO $pdo): void
{
    if (empty($_SESSION['super_admin_id'])) {
        jsonResponse([
            'success' => false,
            'authenticated' => false
        ]);
        return;
    }
    
    // Verifica se admin ainda existe e está ativo
    $stmt = $pdo->prepare("SELECT id, name, username, email FROM super_admins WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['super_admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        session_destroy();
        jsonResponse([
            'success' => false,
            'authenticated' => false
        ]);
        return;
    }
    
    jsonResponse([
        'success' => true,
        'authenticated' => true,
        'data' => [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'username' => $admin['username'],
            'email' => $admin['email']
        ]
    ]);
}

/**
 * Middleware para verificar autenticação (usar em outros endpoints)
 */
function requireSuperAdmin(): array
{
    if (empty($_SESSION['super_admin_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    return [
        'id' => $_SESSION['super_admin_id'],
        'name' => $_SESSION['super_admin_name'],
        'username' => $_SESSION['super_admin_username']
    ];
}
