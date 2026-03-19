<?php
/**
 * API de Administradores - Login, Cadastro e Gerenciamento
 * 
 * GET    - Lista admins (requer admin logado)
 * POST   - Cria admin (primeiro é automático, depois requer admin)
 * POST   ?action=login - Faz login
 * DELETE - Remove admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../includes/RateLimiter.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

// Configuração de sessão para hospedagens compartilhadas
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// Inicializa Rate Limiter
$rateLimiter = new WosKaraoke\RateLimiter();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabase();
    
    // Inicializa Audit Logger
    $auditLogger = new WosKaraoke\AuditLogger($pdo);
    
    switch ($method) {
        case 'GET':
            // Lista admins (requer autenticação)
            requireAdmin();
            listAdmins($pdo);
            break;
            
        case 'POST':
            if ($action === 'login') {
                // Rate limit para login admin: 5 tentativas por minuto
                if (!$rateLimiter->check('login')) {
                    $rateLimiter->tooManyRequests('login');
                }
                $rateLimiter->addHeaders('login');
                loginAdmin($pdo, $auditLogger);
            } elseif ($action === 'logout') {
                logoutAdmin($auditLogger);
            } else {
                createAdmin($pdo, $auditLogger);
            }
            break;
            
        case 'DELETE':
            requireAdmin();
            deleteAdmin($pdo, $auditLogger);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}

/**
 * Verifica se há admin logado
 */
function requireAdmin(): void
{
    if (!isset($_SESSION['admin_id'])) {
        errorResponse('Acesso não autorizado', 401);
    }
}

/**
 * Retorna admin logado ou null
 */
function getLoggedAdmin(): ?array
{
    if (isset($_SESSION['admin_id'])) {
        return [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'name' => $_SESSION['admin_name']
        ];
    }
    return null;
}

/**
 * Login de administrador
 */
function loginAdmin(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        errorResponse('Usuário e senha são obrigatórios');
    }
    
    $stmt = $pdo->prepare("
        SELECT a.*, e.name as establishment_name, e.slug as establishment_slug
        FROM admins a
        LEFT JOIN establishments e ON a.establishment_id = e.id
        WHERE a.username = ?
    ");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        $logger->logFailedLogin('admin', $username, 'invalid_credentials');
        errorResponse('Usuário ou senha incorretos', 401);
    }
    
    // Verifica se está ativo
    if (isset($admin['is_active']) && !$admin['is_active']) {
        $logger->logFailedLogin('admin', $username, 'account_disabled');
        errorResponse('Conta desativada. Contate o administrador.', 403);
    }
    
    // Atualiza último login
    $stmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Inicia sessão
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['establishment_id'] = $admin['establishment_id'];
    $_SESSION['admin_role'] = $admin['role'] ?? 'kj';
    
    // Log de login bem-sucedido
    $logger->logLogin('admin', (int)$admin['id'], $admin['username']);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'name' => $admin['name'],
            'role' => $admin['role'] ?? 'kj',
            'establishment_id' => $admin['establishment_id'],
            'establishment_name' => $admin['establishment_name']
        ]
    ]);
}

/**
 * Logout
 */
function logoutAdmin(WosKaraoke\AuditLogger $logger): void
{
    $adminId = $_SESSION['admin_id'] ?? null;
    if ($adminId) {
        $logger->logLogout('admin', (int)$adminId);
    }
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logout realizado']);
}

/**
 * Cria novo administrador
 */
function createAdmin(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $name = trim($data['name'] ?? '');
    
    if (empty($username) || empty($password) || empty($name)) {
        errorResponse('Usuário, senha e nome são obrigatórios');
    }
    
    if (strlen($password) < 4) {
        errorResponse('Senha deve ter pelo menos 4 caracteres');
    }
    
    // Verifica se já existe algum admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $adminCount = $stmt->fetchColumn();
    
    // Se já existe admin, requer autenticação para criar outro
    if ($adminCount > 0) {
        requireAdmin();
    }
    
    // Verifica se username já existe
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        errorResponse('Este usuário já existe');
    }
    
    // Cria o admin
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, password_hash, name)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$username, $passwordHash, $name]);
    
    $adminId = $pdo->lastInsertId();
    
    // Log de auditoria
    $creatorId = $_SESSION['admin_id'] ?? null;
    $logger->logCreate('admin', $creatorId, 'admins', (int)$adminId, [
        'username' => $username,
        'name' => $name
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Administrador criado com sucesso',
        'data' => [
            'id' => $adminId,
            'username' => $username,
            'name' => $name
        ]
    ], 201);
}

/**
 * Lista administradores
 */
function listAdmins(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT id, username, name, created_at, last_login
        FROM admins
        ORDER BY name
    ");
    
    $admins = $stmt->fetchAll();
    
    // Busca evento do admin logado (primeiro por admin_id, depois fallback para id=1)
    $adminId = $_SESSION['admin_id'] ?? null;
    $eventSettings = null;
    
    if ($adminId) {
        $stmt = $pdo->prepare("SELECT * FROM event_settings WHERE admin_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$adminId]);
        $eventSettings = $stmt->fetch();
    }
    
    // Fallback para evento padrão se não encontrar
    if (!$eventSettings) {
        $stmt = $pdo->query("SELECT * FROM event_settings WHERE id = 1");
        $eventSettings = $stmt->fetch();
    }
    
    jsonResponse([
        'success' => true,
        'data' => $admins,
        'logged' => getLoggedAdmin(),
        'event' => $eventSettings ? [
            'id' => (int) $eventSettings['id'],
            'event_code' => $eventSettings['event_code'],
            'event_name' => $eventSettings['event_name'],
            'is_open' => (bool) $eventSettings['is_open']
        ] : null
    ]);
}

/**
 * Remove administrador
 */
function deleteAdmin(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $adminId = $_GET['id'] ?? null;
    
    if (!$adminId) {
        errorResponse('ID do admin é obrigatório');
    }
    
    // Não pode remover a si mesmo
    if ((int)$adminId === $_SESSION['admin_id']) {
        errorResponse('Você não pode remover a si mesmo');
    }
    
    // Verifica se é o último admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() <= 1) {
        errorResponse('Não é possível remover o único administrador');
    }
    
    // Log antes de deletar
    $stmt = $pdo->prepare("SELECT username, name FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    $deletedAdmin = $stmt->fetch();
    
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->execute([$adminId]);
    
    // Log de auditoria
    $logger->logDelete('admin', $_SESSION['admin_id'], 'admins', (int)$adminId, $deletedAdmin ?: []);
    
    jsonResponse(['success' => true, 'message' => 'Administrador removido']);
}
