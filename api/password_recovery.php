<?php
/**
 * API de Recuperação de Senha - Profiles
 * 
 * POST /api/password_recovery.php?action=request - Solicitar recuperação (body: {email})
 * POST /api/password_recovery.php?action=verify - Verificar token (body: {token})
 * POST /api/password_recovery.php?action=reset - Resetar senha (body: {token, password})
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/AuditLogger.php';

// Rate Limiter
$rateLimiter = new WosKaraoke\RateLimiter();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();
    
    $auditLogger = new WosKaraoke\AuditLogger($pdo);
    
    // Apenas POST
    if ($method !== 'POST') {
        errorResponse('Método não permitido', 405);
    }
    
    // Criar tabela se não existir
    ensureTableExists($pdo);
    
    switch ($action) {
        case 'request':
            // Rate limit: 3 por minuto para evitar spam
            if (!$rateLimiter->check('password_recovery', 3, 60)) {
                $rateLimiter->tooManyRequests('password_recovery');
            }
            requestRecovery($pdo, $auditLogger);
            break;
            
        case 'verify':
            verifyToken($pdo);
            break;
            
        case 'reset':
            resetPassword($pdo, $auditLogger);
            break;
            
        default:
            errorResponse('Ação inválida. Use: request, verify, reset');
    }
    
} catch (Throwable $e) {
    error_log("Password Recovery Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Garantir que a tabela existe
 */
function ensureTableExists(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `profile_password_resets` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `profile_id` INT NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `token` VARCHAR(64) UNIQUE NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token` (`token`),
            INDEX `idx_profile` (`profile_id`),
            INDEX `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Solicitar recuperação de senha
 */
function requestRecovery(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Email inválido');
    }
    
    // Buscar perfil com este email
    $stmt = $pdo->prepare("SELECT id, name, email FROM profiles WHERE email = ? AND password_hash IS NOT NULL");
    $stmt->execute([$email]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        // Por segurança, não revelamos se o email existe ou não
        jsonResponse([
            'success' => true,
            'message' => 'Se o email estiver cadastrado, você receberá um link de recuperação.'
        ]);
        return;
    }
    
    // Invalidar tokens anteriores
    $stmt = $pdo->prepare("UPDATE profile_password_resets SET used = 1 WHERE profile_id = ? AND used = 0");
    $stmt->execute([$profile['id']]);
    
    // Gerar novo token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $pdo->prepare("
        INSERT INTO profile_password_resets (profile_id, email, token, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$profile['id'], $email, $token, $expiresAt]);
    
    // Enviar email
    $emailSent = sendRecoveryEmail($profile['name'], $email, $token);
    
    // Log
    $logger->log('profile', (int)$profile['id'], 'password_recovery_requested', 'profiles', (int)$profile['id'], ['email' => $email]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Se o email estiver cadastrado, você receberá um link de recuperação.',
        'debug' => APP_DEBUG ? ['email_sent' => $emailSent, 'token' => $token] : null
    ]);
}

/**
 * Verificar se token é válido
 */
function verifyToken(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    
    if (empty($token)) {
        errorResponse('Token é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        SELECT pr.*, p.name as profile_name
        FROM profile_password_resets pr
        JOIN profiles p ON p.id = pr.profile_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        errorResponse('Token inválido ou expirado', 400);
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'valid' => true,
            'profile_name' => $reset['profile_name'],
            'email' => substr($reset['email'], 0, 3) . '***' . substr($reset['email'], strpos($reset['email'], '@'))
        ]
    ]);
}

/**
 * Resetar a senha
 */
function resetPassword(PDO $pdo, WosKaraoke\AuditLogger $logger): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($token) || empty($password)) {
        errorResponse('Token e nova senha são obrigatórios');
    }
    
    if (strlen($password) < 4) {
        errorResponse('Senha deve ter pelo menos 4 caracteres');
    }
    
    // Buscar token válido
    $stmt = $pdo->prepare("
        SELECT pr.*, p.name as profile_name
        FROM profile_password_resets pr
        JOIN profiles p ON p.id = pr.profile_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        errorResponse('Token inválido ou expirado', 400);
    }
    
    // Atualizar senha
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE profiles SET password_hash = ? WHERE id = ?");
    $stmt->execute([$passwordHash, $reset['profile_id']]);
    
    // Marcar token como usado
    $stmt = $pdo->prepare("UPDATE profile_password_resets SET used = 1 WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    // Log
    $logger->log('profile', (int)$reset['profile_id'], 'password_reset_completed', 'profiles', (int)$reset['profile_id']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Senha alterada com sucesso! Você já pode fazer login.'
    ]);
}

/**
 * Enviar email de recuperação
 */
function sendRecoveryEmail(string $name, string $email, string $token): bool
{
    $baseUrl = getBaseUrl();
    $resetLink = $baseUrl . "/reset-password.php?token=" . $token;
    
    $subject = "Karaoke Show - Recuperação de Senha";
    
    $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; color: #888; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🎤 Karaoke Show</h1>
        </div>
        <div class='content'>
            <h2>Olá, {$name}!</h2>
            <p>Você solicitou a recuperação de senha da sua conta.</p>
            <p>Clique no botão abaixo para criar uma nova senha:</p>
            <center>
                <a href='{$resetLink}' class='button'>Redefinir Senha</a>
            </center>
            <p><small>Ou copie e cole este link no navegador:<br>{$resetLink}</small></p>
            <p><strong>Este link expira em 1 hora.</strong></p>
            <p>Se você não solicitou essa recuperação, ignore este email.</p>
        </div>
        <div class='footer'>
            <p>© " . date('Y') . " Karaoke Show - Todos os direitos reservados</p>
        </div>
    </div>
</body>
</html>
";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Karaoke Show <noreply@karaokeshow.com>',
        'Reply-To: noreply@karaokeshow.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($email, $subject, $body, implode("\r\n", $headers));
}

/**
 * Obter URL base do sistema
 */
function getBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    // Remove /api do path
    $path = preg_replace('/\/api$/', '', $path);
    return $protocol . '://' . $host . $path;
}
