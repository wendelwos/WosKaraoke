<?php
/**
 * Super Admin - Impersonate API
 * 
 * POST /api/superadmin/impersonate.php
 * Body: { type: "establishment"|"kj", id: number }
 * 
 * Permite ao SuperAdmin logar como outro usuário
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

try {
    $pdo = getDatabase();
    
    // Verificar autenticação SuperAdmin
    $superAdmin = requireSuperAdmin();
    
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
    
    // Buscar dados do usuário alvo
    if ($type === 'establishment') {
        $stmt = $pdo->prepare("SELECT id, name, email, slug FROM establishments WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        
        if (!$target) {
            errorResponse('Estabelecimento não encontrado', 404);
        }
        
        // Criar sessão de estabelecimento
        session_start();
        
        // Salvar sessão atual do SuperAdmin para poder voltar
        $_SESSION['original_super_admin'] = [
            'id' => $superAdmin['id'],
            'name' => $superAdmin['name'],
            'return_url' => '/WosKaraoke/superadmin/establishments.php'
        ];
        
        // Criar sessão do estabelecimento
        $_SESSION['establishment_id'] = $target['id'];
        $_SESSION['establishment_name'] = $target['name'];
        $_SESSION['establishment_email'] = $target['email'];
        $_SESSION['impersonating'] = true;
        
        $redirectUrl = '/WosKaraoke/establishment/index.php';
        
    } else {
        // KJ/Admin
        $stmt = $pdo->prepare("
            SELECT a.id, a.name, a.username, a.email, a.establishment_id, e.name as establishment_name
            FROM admins a
            LEFT JOIN establishments e ON a.establishment_id = e.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        
        if (!$target) {
            errorResponse('KJ não encontrado', 404);
        }
        
        // Criar sessão de admin/KJ
        session_start();
        
        // Salvar sessão atual do SuperAdmin para poder voltar
        $_SESSION['original_super_admin'] = [
            'id' => $superAdmin['id'],
            'name' => $superAdmin['name'],
            'return_url' => '/WosKaraoke/superadmin/kjs.php'
        ];
        
        // Criar sessão do admin/KJ
        $_SESSION['admin_id'] = $target['id'];
        $_SESSION['admin_name'] = $target['name'];
        $_SESSION['admin_username'] = $target['username'];
        $_SESSION['establishment_id'] = $target['establishment_id'];
        $_SESSION['impersonating'] = true;
        
        $redirectUrl = '/WosKaraoke/admin/index.php';
    }
    
    // Log de auditoria
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES ('super_admin', ?, 'impersonate', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $superAdmin['id'],
            $type,
            $id,
            json_encode(['target_name' => $target['name']]),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Ignora se tabela de auditoria não existir
        error_log("Audit log failed: " . $e->getMessage());
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Você está acessando como ' . $target['name'],
        'data' => [
            'redirect_url' => $redirectUrl,
            'target_name' => $target['name'],
            'type' => $type
        ]
    ]);

} catch (Exception $e) {
    error_log("Impersonate API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}
