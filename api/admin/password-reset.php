<?php
/**
 * API de Recuperação de Senha - WosKaraoke
 * 
 * POST /api/admin/password-reset.php?action=request - Solicita reset (envia e-mail)
 * POST /api/admin/password-reset.php?action=verify  - Verifica token
 * POST /api/admin/password-reset.php?action=reset   - Redefine senha
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();

    if ($method !== 'POST') {
        errorResponse('Método não permitido', 405);
    }

    switch ($action) {
        case 'request':
            requestPasswordReset($pdo);
            break;
        case 'verify':
            verifyToken($pdo);
            break;
        case 'reset':
            resetPassword($pdo);
            break;
        default:
            errorResponse('Ação inválida');
    }

} catch (Throwable $e) {
    error_log("Password Reset API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Solicita recuperação de senha - envia e-mail com link
 */
function requestPasswordReset(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($input['email'] ?? ''));
    
    if (empty($email)) {
        errorResponse('E-mail é obrigatório');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('E-mail inválido');
    }
    
    // Busca admin pelo e-mail
    $stmt = $pdo->prepare("SELECT id, name, email FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    // Sempre retorna sucesso (não revela se e-mail existe)
    if (!$admin) {
        jsonResponse([
            'success' => true,
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.'
        ]);
        return;
    }
    
    // Remove tokens antigos deste admin
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE admin_id = ?");
    $stmt->execute([$admin['id']]);
    
    // Gera token único
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Salva token
    $stmt = $pdo->prepare("INSERT INTO password_resets (admin_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$admin['id'], $token, $expiresAt]);
    
    // Monta link de recuperação
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $resetLink = $baseUrl . dirname(dirname($_SERVER['REQUEST_URI'])) . "/admin?reset=" . $token;
    
    // Envia e-mail
    $sent = sendPasswordResetEmail($admin['email'], $admin['name'], $resetLink);
    
    if (!$sent) {
        error_log("Failed to send password reset email to: " . $admin['email']);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.'
    ]);
}

/**
 * Verifica se token é válido
 */
function verifyToken(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    
    if (empty($token)) {
        errorResponse('Token é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        SELECT pr.*, a.username, a.name 
        FROM password_resets pr
        JOIN admins a ON pr.admin_id = a.id
        WHERE pr.token = ? AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        jsonResponse([
            'success' => false,
            'valid' => false,
            'error' => 'Token inválido ou já utilizado'
        ]);
        return;
    }
    
    // Verifica expiração
    if (strtotime($reset['expires_at']) < time()) {
        jsonResponse([
            'success' => false,
            'valid' => false,
            'error' => 'Token expirado. Solicite uma nova recuperação.'
        ]);
        return;
    }
    
    jsonResponse([
        'success' => true,
        'valid' => true,
        'username' => $reset['username'],
        'name' => $reset['name']
    ]);
}

/**
 * Redefine a senha
 */
function resetPassword(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $newPassword = $input['password'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token é obrigatório');
    }
    
    if (strlen($newPassword) < 4) {
        errorResponse('Senha deve ter pelo menos 4 caracteres');
    }
    
    // Busca e valida token
    $stmt = $pdo->prepare("
        SELECT pr.*, a.id as admin_id 
        FROM password_resets pr
        JOIN admins a ON pr.admin_id = a.id
        WHERE pr.token = ? AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        errorResponse('Token inválido ou já utilizado');
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        errorResponse('Token expirado. Solicite uma nova recuperação.');
    }
    
    // Atualiza senha
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $reset['admin_id']]);
    
    // Marca token como usado
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $stmt->execute([$reset['id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Senha alterada com sucesso! Você já pode fazer login.'
    ]);
}

/**
 * Envia e-mail de recuperação de senha
 */
function sendPasswordResetEmail(string $to, string $name, string $link): bool
{
    $subject = "WosKaraoke - Recuperação de Senha";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 500px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 20px; border-radius: 12px 12px 0 0; text-align: center; }
            .header h1 { color: white; margin: 0; font-size: 24px; }
            .content { background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px; }
            .btn { display: inline-block; background: #6366f1; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎤 WosKaraoke</h1>
            </div>
            <div class='content'>
                <p>Olá <strong>$name</strong>,</p>
                <p>Você solicitou a recuperação de senha da sua conta de administrador.</p>
                <p>Clique no botão abaixo para redefinir sua senha:</p>
                <p style='text-align: center;'>
                    <a href='$link' class='btn'>Redefinir Senha</a>
                </p>
                <p><small>Este link expira em 1 hora.</small></p>
                <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
            </div>
            <div class='footer'>
                <p>WosKaraoke &copy; " . date('Y') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: WosKaraoke <noreply@' . ($_SERVER['HTTP_HOST'] ?? 'woskaraoke.com') . '>',
        'Reply-To: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'woskaraoke.com'),
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}
