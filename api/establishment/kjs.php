<?php
/**
 * API de Gerenciamento de KJs para Estabelecimentos
 * 
 * Endpoints:
 * - GET ?action=list - Listar KJs do estabelecimento
 * - GET ?action=get&id=X - Obter KJ específico
 * - POST ?action=create - Criar novo KJ
 * - POST ?action=update&id=X - Atualizar KJ
 * - DELETE ?id=X - Excluir KJ
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

$pdo = getDatabase();
$establishment = requireEstablishment();
$establishmentId = $establishment['id'];

$action = $_GET['action'] ?? '';

// Listar KJs do estabelecimento
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT id, username, email, name, role, is_active, created_at, last_login
        FROM admins 
        WHERE establishment_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$establishmentId]);
    $kjs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'data' => $kjs]);
}

// Obter KJ específico
if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT id, username, email, name, role, is_active, created_at, last_login
        FROM admins 
        WHERE id = ? AND establishment_id = ?
    ");
    $stmt->execute([$id, $establishmentId]);
    $kj = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$kj) {
        jsonResponse(['success' => false, 'error' => 'KJ não encontrado'], 404);
    }
    
    jsonResponse(['success' => true, 'data' => $kj]);
}

// Criar KJ
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $name = trim($data['name'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($name) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Username, nome e senha são obrigatórios'], 400);
    }
    
    // Verificar username único
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Username já está em uso'], 400);
    }
    
    // Criar KJ
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO admins (username, email, password_hash, name, establishment_id, role, is_active)
        VALUES (?, ?, ?, ?, ?, 'kj', 1)
    ");
    $stmt->execute([$username, $email, $hash, $name, $establishmentId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'KJ criado com sucesso',
        'id' => $pdo->lastInsertId()
    ]);
}

// Atualizar KJ
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Verificar se pertence ao estabelecimento
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'KJ não encontrado'], 404);
    }
    
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $password = $data['password'] ?? '';
    
    if (empty($name)) {
        jsonResponse(['success' => false, 'error' => 'Nome é obrigatório'], 400);
    }
    
    // Atualizar com ou sem senha
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, is_active = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$name, $email, $isActive, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $email, $isActive, $id]);
    }
    
    jsonResponse(['success' => true, 'message' => 'KJ atualizado']);
}

// Toggle ativo
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT is_active FROM admins WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    $kj = $stmt->fetch();
    
    if (!$kj) {
        jsonResponse(['success' => false, 'error' => 'KJ não encontrado'], 404);
    }
    
    $newStatus = $kj['is_active'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE admins SET is_active = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    jsonResponse(['success' => true, 'is_active' => $newStatus]);
}

// Excluir KJ
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    // Verificar se pertence ao estabelecimento
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'KJ não encontrado'], 404);
    }
    
    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse(['success' => true, 'message' => 'KJ excluído']);
}

jsonResponse(['success' => false, 'error' => 'Ação inválida'], 400);
