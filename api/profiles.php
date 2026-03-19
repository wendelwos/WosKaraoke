<?php
/**
 * API de Perfis - WosKaraoke
 * 
 * POST /api/profiles.php - Criar perfil (body: {name: "Nome"})
 * POST /api/profiles.php?action=login - Login com senha (body: {name, password})
 * POST /api/profiles.php?action=set_password - Definir senha (body: {token, password})
 * GET /api/profiles.php?token=XXX - Obter perfil por token
 */

// Captura erros antes de qualquer coisa
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Não mostra erros na saída
ini_set('log_errors', '1');

// Handler para capturar erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erro fatal: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/AuditLogger.php';

// Inicializa Rate Limiter
$rateLimiter = new WosKaraoke\RateLimiter();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();
    
    // Inicializa Audit Logger
    $auditLogger = new WosKaraoke\AuditLogger($pdo);

    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                // Rate limit para login: 5 tentativas por minuto
                if (!$rateLimiter->check('login')) {
                    $rateLimiter->tooManyRequests('login');
                }
                $rateLimiter->addHeaders('login');
                loginWithPassword($pdo, $auditLogger);
            } elseif ($action === 'set_password') {
                setPassword($pdo, $auditLogger);
            } elseif ($action === 'google_login') {
                // GOOGLE AUTH DESATIVADO - Reativar quando configurar credenciais
                errorResponse('Login com Google está temporariamente desativado', 503);
                // if (!$rateLimiter->check('login')) {
                //     $rateLimiter->tooManyRequests('login');
                // }
                // loginWithGoogle($pdo, $auditLogger);
            } else {
                // Rate limit para criação de perfil
                if (!$rateLimiter->check('default')) {
                    $rateLimiter->tooManyRequests('default');
                }
                createProfile($pdo, $auditLogger);
            }
            break;

        case 'GET':
            getProfile($pdo);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Throwable $e) {
    error_log("Profiles API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Criar novo perfil (acesso rápido)
 */
function createProfile(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (empty($input['name'])) {
        errorResponse('Nome é obrigatório');
    }

    $name = trim($input['name']);
    $token = generateToken();
    $avatarColor = getRandomAvatarColor();

    $stmt = $pdo->prepare("
        INSERT INTO profiles (name, token, avatar_color)
        VALUES (:name, :token, :color)
    ");
    
    $stmt->execute([
        'name' => $name,
        'token' => $token,
        'color' => $avatarColor
    ]);

    $profileId = (int) $pdo->lastInsertId();

    // Log de auditoria
    $logger->logCreate('profile', $profileId, 'profiles', $profileId, [
        'name' => $name,
        'avatar_color' => $avatarColor
    ]);

    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $profileId,
            'name' => $name,
            'token' => $token,
            'avatar_color' => $avatarColor,
            'initials' => getInitials($name),
            'has_password' => false
        ]
    ], 201);
}

/**
 * Login com senha (para contas fixas)
 */
function loginWithPassword(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($name) || empty($password)) {
        errorResponse('Nome e senha são obrigatórios');
    }
    
    // Busca perfil por nome que tenha senha definida
    $stmt = $pdo->prepare("
        SELECT * FROM profiles 
        WHERE LOWER(name) = LOWER(:name) AND password_hash IS NOT NULL
    ");
    $stmt->execute(['name' => $name]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        $logger->logFailedLogin('profile', $name, 'account_not_found');
        errorResponse('Conta não encontrada ou sem senha definida', 401);
    }
    
    if (!password_verify($password, $profile['password_hash'])) {
        $logger->logFailedLogin('profile', $name, 'wrong_password');
        errorResponse('Senha incorreta', 401);
    }
    
    // Log de login bem-sucedido
    $logger->logLogin('profile', (int)$profile['id'], $profile['name']);
    
    // Contar favoritos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE profile_id = :id");
    $stmt->execute(['id' => $profile['id']]);
    $favoritesCount = (int) $stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => (int) $profile['id'],
            'name' => $profile['name'],
            'token' => $profile['token'],
            'avatar_color' => $profile['avatar_color'],
            'initials' => getInitials($profile['name']),
            'favorites_count' => $favoritesCount,
            'has_password' => true,
            'created_at' => $profile['created_at']
        ]
    ]);
}

/**
 * Definir ou alterar senha
 */
function setPassword(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    $currentPassword = $input['current_password'] ?? null;
    
    if (empty($token) || empty($password)) {
        errorResponse('Token e nova senha são obrigatórios');
    }
    
    if (strlen($password) < 4) {
        errorResponse('Senha deve ter pelo menos 4 caracteres');
    }
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE token = :token");
    $stmt->execute(['token' => $token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    // Se já tem senha, precisa da senha atual
    if (!empty($profile['password_hash'])) {
        if (empty($currentPassword)) {
            errorResponse('Senha atual é obrigatória para alterar');
        }
        if (!password_verify($currentPassword, $profile['password_hash'])) {
            errorResponse('Senha atual incorreta', 401);
        }
    }
    
    // Define a nova senha
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE profiles SET password_hash = :hash WHERE id = :id");
    $stmt->execute([
        'hash' => $passwordHash,
        'id' => $profile['id']
    ]);
    
    // Log de auditoria
    $logger->log('profile', (int)$profile['id'], 'password_set', 'profiles', (int)$profile['id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Senha definida com sucesso! Agora você tem uma conta fixa.',
        'data' => [
            'has_password' => true
        ]
    ]);
}

/**
 * Obter perfil por token
 */
function getProfile(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token é obrigatório');
    }

    $stmt = $pdo->prepare("
        SELECT id, name, token, avatar_color, password_hash, created_at
        FROM profiles
        WHERE token = :token
    ");
    $stmt->execute(['token' => $token]);
    $profile = $stmt->fetch();

    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }

    // Contar favoritos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE profile_id = :id");
    $stmt->execute(['id' => $profile['id']]);
    $favoritesCount = (int) $stmt->fetchColumn();

    jsonResponse([
        'success' => true,
        'data' => [
            'id' => (int) $profile['id'],
            'name' => $profile['name'],
            'token' => $profile['token'],
            'avatar_color' => $profile['avatar_color'],
            'initials' => getInitials($profile['name']),
            'favorites_count' => $favoritesCount,
            'has_password' => !empty($profile['password_hash']),
            'created_at' => $profile['created_at']
        ]
    ]);
}

/**
 * Login com Google
 */
function loginWithGoogle(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['credential'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token Google é obrigatório');
    }
    
    // 1. Validar Token via Google REST API (Lightweight)
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
    $responseRaw = @file_get_contents($url);
    
    if ($responseRaw === false) {
        errorResponse('Token inválido ou expirado (Google API Error)', 401);
    }
    
    $payload = json_decode($responseRaw, true);
    
    if (empty($payload['aud'])) {
        errorResponse('Resposta inválida do Google', 401);
    }
    
    // Verificar Client ID
    if ($payload['aud'] !== GOOGLE_CLIENT_ID) {
        errorResponse('Token não pertence a este aplicativo', 401);
    }
    
    // Dados do usuário
    $googleId = $payload['sub'];
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? 'Usuário Google';
    $picture = $payload['picture'] ?? '';
    
    // 2. Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $profile = $stmt->fetch();
    
    if ($profile) {
        // Atualiza dados se necessário
        $stmt = $pdo->prepare("UPDATE profiles SET google_id = ?, avatar_url = ? WHERE id = ?");
        $stmt->execute([$googleId, $picture, $profile['id']]);
        
        $profile['google_id'] = $googleId;
        $profile['avatar_url'] = $picture;
    } else {
        // Cria novo usuário
        $newToken = generateToken();
        $color = getRandomAvatarColor();
        
        $stmt = $pdo->prepare("
            INSERT INTO profiles (name, token, avatar_color, google_id, email, avatar_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $newToken, $color, $googleId, $email, $picture]);
        
        $profileId = $pdo->lastInsertId();
        
        $profile = [
            'id' => $profileId,
            'name' => $name,
            'token' => $newToken,
            'avatar_color' => $color,
            'google_id' => $googleId,
            'email' => $email,
            'avatar_url' => $picture,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Log de auditoria
    $logger->logLogin('profile', (int)$profile['id'], $profile['name'] . ' (Google)');
    
    // Contar favoritos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE profile_id = ?");
    $stmt->execute([$profile['id']]);
    $favoritesCount = (int) $stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => (int) $profile['id'],
            'name' => $profile['name'],
            'token' => $profile['token'],
            'avatar_color' => $profile['avatar_color'],
            'avatar_url' => $profile['avatar_url'] ?? '',
            'initials' => getInitials($profile['name']),
            'favorites_count' => $favoritesCount,
            'is_google' => true,
            'created_at' => $profile['created_at']
        ]
    ]);
}
