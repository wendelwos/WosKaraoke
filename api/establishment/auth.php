<?php
/**
 * API de Autenticação para Estabelecimentos
 * 
 * Endpoints:
 * - POST ?action=login - Login do estabelecimento
 * - POST ?action=logout - Logout
 * - GET (sem action) - Verificar sessão
 */

require_once __DIR__ . '/../config.php';

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDatabase();
$action = $_GET['action'] ?? '';

// Verificar sessão atual
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION['establishment_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, slug, email, logo_url, subscription_plan FROM establishments WHERE id = ?");
        $stmt->execute([$_SESSION['establishment_id']]);
        $establishment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($establishment) {
            jsonResponse([
                'success' => true,
                'logged' => $establishment
            ]);
        }
    }
    
    jsonResponse(['success' => true, 'logged' => null]);
}

// Login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonResponse(['success' => false, 'error' => 'Email e senha são obrigatórios'], 400);
    }
    
    // Buscar estabelecimento por email
    $stmt = $pdo->prepare("
        SELECT id, name, slug, email, password_hash, logo_url, is_active, subscription_plan 
        FROM establishments 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $establishment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$establishment) {
        jsonResponse(['success' => false, 'error' => 'Email ou senha incorretos'], 401);
    }
    
    if (!$establishment['is_active']) {
        jsonResponse(['success' => false, 'error' => 'Estabelecimento inativo'], 403);
    }
    
    // Verificar se tem senha definida
    if (empty($establishment['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Senha não configurada. Contate o administrador.'], 401);
    }
    
    if (!password_verify($password, $establishment['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Email ou senha incorretos'], 401);
    }
    
    // Atualizar last_login
    $stmt = $pdo->prepare("UPDATE establishments SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$establishment['id']]);
    
    // Criar sessão
    $_SESSION['establishment_id'] = $establishment['id'];
    $_SESSION['establishment_name'] = $establishment['name'];
    $_SESSION['establishment_slug'] = $establishment['slug'];
    
    unset($establishment['password_hash']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'establishment' => $establishment
    ]);
}

// Logout
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['establishment_id']);
    unset($_SESSION['establishment_name']);
    unset($_SESSION['establishment_slug']);
    
    jsonResponse(['success' => true, 'message' => 'Logout realizado']);
}

jsonResponse(['success' => false, 'error' => 'Ação inválida'], 400);
