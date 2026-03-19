<?php
/**
 * Super Admin - KJs (Karaoke Jockeys) CRUD API
 * 
 * GET /api/superadmin/kjs.php - Listar todos
 * GET /api/superadmin/kjs.php?id=X - Obter um
 * GET /api/superadmin/kjs.php?establishment_id=X - Listar por estabelecimento
 * POST /api/superadmin/kjs.php - Criar
 * PUT /api/superadmin/kjs.php?id=X - Atualizar
 * DELETE /api/superadmin/kjs.php?id=X - Deletar
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDatabase();
    
    // Verificar autenticação
    $admin = requireSuperAdmin();

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getOne($pdo, (int)$_GET['id']);
            } elseif (isset($_GET['establishment_id'])) {
                getByEstablishment($pdo, (int)$_GET['establishment_id']);
            } else {
                getAll($pdo);
            }
            break;
            
        case 'POST':
            create($pdo);
            break;
            
        case 'PUT':
            if (!isset($_GET['id'])) {
                errorResponse('ID é obrigatório', 400);
            }
            update($pdo, (int)$_GET['id']);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                errorResponse('ID é obrigatório', 400);
            }
            delete($pdo, (int)$_GET['id']);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("KJs API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Listar todos os KJs
 */
function getAll(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT a.id, a.username, a.name, a.email, a.role, a.is_active, a.created_at, a.last_login,
               a.establishment_id,
               e.name as establishment_name
        FROM admins a
        LEFT JOIN establishments e ON a.establishment_id = e.id
        ORDER BY a.created_at DESC
    ");
    $kjs = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $kjs
    ]);
}

/**
 * Listar KJs por estabelecimento
 */
function getByEstablishment(PDO $pdo, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        SELECT id, username, name, email, role, is_active, created_at, last_login
        FROM admins
        WHERE establishment_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$establishmentId]);
    $kjs = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $kjs
    ]);
}

/**
 * Obter um KJ
 */
function getOne(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.username, a.name, a.email, a.role, a.is_active, a.created_at, a.last_login,
               a.establishment_id, a.avatar_url,
               e.name as establishment_name
        FROM admins a
        LEFT JOIN establishments e ON a.establishment_id = e.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $kj = $stmt->fetch();
    
    if (!$kj) {
        errorResponse('KJ não encontrado', 404);
    }
    
    // Conta eventos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_settings WHERE admin_id = ?");
    $stmt->execute([$id]);
    $kj['event_count'] = (int)$stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => $kj
    ]);
}

/**
 * Criar KJ
 */
function create(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $establishmentId = isset($input['establishment_id']) ? (int)$input['establishment_id'] : null;
    
    if (empty($username) || empty($name) || empty($password)) {
        errorResponse('Username, nome e senha são obrigatórios');
    }
    
    if (strlen($password) < 4) {
        errorResponse('Senha deve ter pelo menos 4 caracteres');
    }
    
    // Verifica username único
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        errorResponse('Username já existe');
    }
    
    // Verifica se estabelecimento existe (se fornecido)
    if ($establishmentId) {
        $stmt = $pdo->prepare("SELECT id, max_kjs FROM establishments WHERE id = ?");
        $stmt->execute([$establishmentId]);
        $establishment = $stmt->fetch();
        
        if (!$establishment) {
            errorResponse('Estabelecimento não encontrado');
        }
        
        // Verifica limite de KJs
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE establishment_id = ?");
        $stmt->execute([$establishmentId]);
        $currentKjs = $stmt->fetchColumn();
        
        if ($currentKjs >= $establishment['max_kjs']) {
            errorResponse('Limite de KJs atingido para este estabelecimento');
        }
    }
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, name, email, password_hash, establishment_id, role, is_active)
        VALUES (?, ?, ?, ?, ?, 'kj', 1)
    ");
    $stmt->execute([
        $username,
        $name,
        $email ?: null,
        $passwordHash,
        $establishmentId
    ]);
    
    $id = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'KJ criado com sucesso',
        'data' => ['id' => (int)$id]
    ], 201);
}

/**
 * Atualizar KJ
 */
function update(PDO $pdo, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verifica se existe
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        errorResponse('KJ não encontrado', 404);
    }
    
    $fields = [];
    $values = [];
    
    // Campos permitidos
    if (isset($input['name'])) {
        $fields[] = "name = ?";
        $values[] = trim($input['name']);
    }
    if (isset($input['email'])) {
        $fields[] = "email = ?";
        $values[] = trim($input['email']) ?: null;
    }
    if (isset($input['is_active'])) {
        $fields[] = "is_active = ?";
        $values[] = $input['is_active'] ? 1 : 0;
    }
    if (isset($input['role'])) {
        $fields[] = "role = ?";
        $values[] = $input['role'];
    }
    if (isset($input['establishment_id'])) {
        $fields[] = "establishment_id = ?";
        $values[] = $input['establishment_id'] ?: null;
    }
    
    // Reset de senha
    if (!empty($input['password'])) {
        if (strlen($input['password']) < 4) {
            errorResponse('Senha deve ter pelo menos 4 caracteres');
        }
        $fields[] = "password_hash = ?";
        $values[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($fields)) {
        errorResponse('Nenhum campo para atualizar');
    }
    
    $values[] = $id;
    
    $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    jsonResponse([
        'success' => true,
        'message' => 'KJ atualizado'
    ]);
}

/**
 * Deletar KJ
 */
function delete(PDO $pdo, int $id): void
{
    // Desvincular eventos primeiro
    $stmt = $pdo->prepare("UPDATE event_settings SET admin_id = NULL WHERE admin_id = ?");
    $stmt->execute([$id]);
    
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        errorResponse('KJ não encontrado', 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'KJ deletado'
    ]);
}
