<?php
/**
 * Super Admin - Password Reset API
 * 
 * POST /api/superadmin/password_reset.php
 * Body: { type: "establishment"|"kj", id: number }
 * 
 * Gera token de reset e envia email
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

try {
    $pdo = getDatabase();
    
    // Verificar autenticação SuperAdmin
    requireSuperAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Método não permitido', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? '';
    $id = (int)($input['id'] ?? 0);
    
    if (!in_array($type, ['establishment', 'kj'])) {
        errorResponse('Tipo inválido. Use "establishment" ou "kj"');
    }
    
    if ($id <= 0) {
        errorResponse('ID inválido');
    }
    
    // Buscar dados do usuário
    if ($type === 'establishment') {
        $stmt = $pdo->prepare("SELECT id, name, email FROM establishments WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $resetTable = 'establishment';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $resetTable = 'admin';
    }
    
    if (!$user) {
        errorResponse('Usuário não encontrado', 404);
    }
    
    if (empty($user['email'])) {
        errorResponse('Este usuário não possui email cadastrado');
    }
    
    // Gerar token de reset
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Salvar token na tabela password_resets
    // Primeiro, invalidar tokens anteriores
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE admin_id = ? AND used = 0");
    $stmt->execute([$id]);
    
    // Inserir novo token (usando admin_id para ambos os tipos por simplicidade)
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (admin_id, token, expires_at, used) 
        VALUES (?, ?, ?, 0)
    ");
    $stmt->execute([$id, $token, $expiresAt]);
    
    // Montar URL de reset
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $baseUrl .= '://' . $_SERVER['HTTP_HOST'];
    
    if ($type === 'establishment') {
        $resetUrl = $baseUrl . '/WosKaraoke/establishment/reset-password.php?token=' . $token;
    } else {
        $resetUrl = $baseUrl . '/WosKaraoke/admin/reset-password.php?token=' . $token;
    }
    
    // Enviar email
    $emailSent = sendPasswordResetEmail($user['email'], $user['name'], $resetUrl);
    
    if ($emailSent) {
        jsonResponse([
            'success' => true,
            'message' => 'Email de reset enviado para ' . maskEmail($user['email']),
            'data' => [
                'email_masked' => maskEmail($user['email']),
                'expires_at' => $expiresAt
            ]
        ]);
    } else {
        // Se não conseguir enviar email, retornar o link diretamente (para desenvolvimento)
        jsonResponse([
            'success' => true,
            'message' => 'Token gerado (email não configurado)',
            'data' => [
                'reset_url' => $resetUrl,
                'token' => $token,
                'expires_at' => $expiresAt,
                'note' => 'Configure SMTP para enviar emails em produção'
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Password Reset API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Envia email de reset de senha
 */
function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool
{
    // Verificar se PHPMailer está disponível
    $mailerPath = __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    
    if (!file_exists($mailerPath)) {
        // Tentar usar mail() nativo
        $subject = 'Reset de Senha - Karaoke Show';
        $message = "
        <html>
        <head><title>Reset de Senha</title></head>
        <body>
            <h2>Olá, {$name}!</h2>
            <p>Recebemos uma solicitação para resetar sua senha.</p>
            <p>Clique no link abaixo para criar uma nova senha:</p>
            <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
            <p>Este link expira em 24 horas.</p>
            <p>Se você não solicitou este reset, ignore este email.</p>
            <br>
            <p>Atenciosamente,<br>Equipe Karaoke Show</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Karaoke Show <noreply@karaokeshow.com>\r\n";
        
        return @mail($email, $subject, $message, $headers);
    }
    
    // Se PHPMailer estiver disponível, usar
    // (deixar para implementação futura com SMTP)
    return false;
}

/**
 * Mascara email para exibição
 */
function maskEmail(string $email): string
{
    $parts = explode('@', $email);
    if (count($parts) !== 2) return '***@***.***';
    
    $name = $parts[0];
    $domain = $parts[1];
    
    $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
    
    return $maskedName . '@' . $domain;
}
