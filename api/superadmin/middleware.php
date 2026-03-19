<?php
/**
 * Super Admin Middleware - Session & Authentication
 * Use este arquivo para incluir em outros endpoints que precisam de autenticação
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Session para Super Admin
if (session_status() === PHP_SESSION_NONE) {
    session_name('SUPERADMIN_SESSION');
    session_start();
}

/**
 * Middleware para verificar autenticação
 * Retorna dados do admin logado ou encerra com erro 401
 */
function requireSuperAdmin(): array
{
    if (empty($_SESSION['super_admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    return [
        'id' => $_SESSION['super_admin_id'],
        'name' => $_SESSION['super_admin_name'] ?? '',
        'username' => $_SESSION['super_admin_username'] ?? ''
    ];
}

/**
 * Retorna admin logado ou null (não encerra)
 */
function getLoggedSuperAdmin(): ?array
{
    if (empty($_SESSION['super_admin_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['super_admin_id'],
        'name' => $_SESSION['super_admin_name'] ?? '',
        'username' => $_SESSION['super_admin_username'] ?? ''
    ];
}
