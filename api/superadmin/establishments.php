<?php
/**
 * Super Admin - Establishments CRUD API
 * 
 * GET /api/superadmin/establishments.php - Listar todos
 * GET /api/superadmin/establishments.php?id=X - Obter um
 * POST /api/superadmin/establishments.php - Criar
 * PUT /api/superadmin/establishments.php?id=X - Atualizar
 * DELETE /api/superadmin/establishments.php?id=X - Deletar
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
    error_log("Establishments API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Listar todos os estabelecimentos
 */
function getAll(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM admins WHERE establishment_id = e.id) as kj_count,
               (SELECT COUNT(*) FROM event_settings WHERE establishment_id = e.id) as event_count
        FROM establishments e
        ORDER BY e.created_at DESC
    ");
    $establishments = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $establishments
    ]);
}

/**
 * Obter um estabelecimento
 */
function getOne(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM admins WHERE establishment_id = e.id) as kj_count,
               (SELECT COUNT(*) FROM event_settings WHERE establishment_id = e.id) as event_count
        FROM establishments e
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $establishment = $stmt->fetch();
    
    if (!$establishment) {
        errorResponse('Estabelecimento não encontrado', 404);
    }
    
    // Busca KJs vinculados
    $stmt = $pdo->prepare("SELECT id, username, name, email, role, is_active, created_at FROM admins WHERE establishment_id = ?");
    $stmt->execute([$id]);
    $establishment['kjs'] = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $establishment
    ]);
}

/**
 * Criar estabelecimento
 */
function create(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $slug = trim($input['slug'] ?? '');
    
    if (empty($name)) {
        errorResponse('Nome é obrigatório');
    }
    
    // Gera slug se não fornecido
    if (empty($slug)) {
        $slug = generateSlug($name);
    }
    
    // Verifica slug único
    $stmt = $pdo->prepare("SELECT id FROM establishments WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }
    
    // Hash da senha se fornecida
    $passwordHash = null;
    if (!empty($input['password'])) {
        $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO establishments (name, slug, address, phone, email, logo_url, max_kjs, subscription_plan, password_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $slug,
        $input['address'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $input['logo_url'] ?? null,
        (int)($input['max_kjs'] ?? 5),
        $input['subscription_plan'] ?? 'free',
        $passwordHash
    ]);
    
    $id = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Estabelecimento criado com sucesso',
        'data' => ['id' => (int)$id, 'slug' => $slug]
    ], 201);
}

/**
 * Atualizar estabelecimento
 */
function update(PDO $pdo, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verifica se existe
    $stmt = $pdo->prepare("SELECT id FROM establishments WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        errorResponse('Estabelecimento não encontrado', 404);
    }
    
    $fields = [];
    $values = [];
    
    $allowedFields = ['name', 'address', 'phone', 'email', 'logo_url', 'is_active', 'max_kjs', 'subscription_plan', 'subscription_expires_at'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    // Atualizar senha se fornecida
    if (!empty($input['password'])) {
        $fields[] = "password_hash = ?";
        $values[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($fields)) {
        errorResponse('Nenhum campo para atualizar');
    }
    
    $values[] = $id;
    
    $sql = "UPDATE establishments SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    jsonResponse([
        'success' => true,
        'message' => 'Estabelecimento atualizado'
    ]);
}

/**
 * Deletar estabelecimento
 */
function delete(PDO $pdo, int $id): void
{
    // Verifica se existe e não tem KJs
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE establishment_id = ?");
    $stmt->execute([$id]);
    $kjCount = $stmt->fetchColumn();
    
    if ($kjCount > 0) {
        errorResponse('Não é possível deletar: existem KJs vinculados');
    }
    
    $stmt = $pdo->prepare("DELETE FROM establishments WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        errorResponse('Estabelecimento não encontrado', 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Estabelecimento deletado'
    ]);
}

/**
 * Gera slug a partir do nome
 */
function generateSlug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[áàãâä]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[íìîï]/u', 'i', $text);
    $text = preg_replace('/[óòõôö]/u', 'o', $text);
    $text = preg_replace('/[úùûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}
