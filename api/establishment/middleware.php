<?php
/**
 * Middleware de autenticação para APIs do Estabelecimento
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o estabelecimento está logado e retorna os dados
 */
function requireEstablishment(): array {
    if (!isset($_SESSION['establishment_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    return [
        'id' => $_SESSION['establishment_id'],
        'name' => $_SESSION['establishment_name'] ?? '',
        'slug' => $_SESSION['establishment_slug'] ?? ''
    ];
}

/**
 * Retorna os dados do estabelecimento logado (ou null se não logado)
 */
function getLoggedEstablishment(): ?array {
    if (!isset($_SESSION['establishment_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['establishment_id'],
        'name' => $_SESSION['establishment_name'] ?? '',
        'slug' => $_SESSION['establishment_slug'] ?? ''
    ];
}
