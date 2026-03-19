<?php
/**
 * API de Notificações - WosKaraoke
 * 
 * GET /api/notifications.php - Lista notificações do estabelecimento
 * POST /api/notifications.php?action=read&id=X - Marca como lida
 * POST /api/notifications.php?action=read_all - Marca todas como lidas
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabase();
    
    // Verifica autenticação do estabelecimento
    $establishmentId = getAuthenticatedEstablishment();
    
    if (!$establishmentId) {
        errorResponse('Não autorizado', 401);
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            listNotifications($pdo, $establishmentId);
            break;
            
        case 'read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                errorResponse('Método não permitido', 405);
            }
            $notificationId = (int) ($_GET['id'] ?? 0);
            markAsRead($pdo, $establishmentId, $notificationId);
            break;
            
        case 'read_all':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                errorResponse('Método não permitido', 405);
            }
            markAllAsRead($pdo, $establishmentId);
            break;
            
        case 'count':
            countUnread($pdo, $establishmentId);
            break;
            
        default:
            errorResponse('Ação não reconhecida', 400);
    }
    
} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    errorResponse('Erro no servidor', 500);
}

/**
 * Lista notificações do estabelecimento
 */
function listNotifications(PDO $pdo, int $establishmentId): void
{
    $limit = (int) ($_GET['limit'] ?? 20);
    $offset = (int) ($_GET['offset'] ?? 0);
    $unreadOnly = isset($_GET['unread']);
    
    $sql = "SELECT * FROM notifications WHERE establishment_id = ?";
    $params = [$establishmentId];
    
    if ($unreadOnly) {
        $sql .= " AND is_read = 0";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Conta total e não lidas
    $countStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
        FROM notifications 
        WHERE establishment_id = ?
    ");
    $countStmt->execute([$establishmentId]);
    $counts = $countStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'total' => (int) $counts['total'],
            'unread' => (int) $counts['unread']
        ]
    ]);
}

/**
 * Marca uma notificação como lida
 */
function markAsRead(PDO $pdo, int $establishmentId, int $notificationId): void
{
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND establishment_id = ?
    ");
    $stmt->execute([$notificationId, $establishmentId]);
    
    jsonResponse(['success' => true, 'message' => 'Notificação marcada como lida']);
}

/**
 * Marca todas as notificações como lidas
 */
function markAllAsRead(PDO $pdo, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = CURRENT_TIMESTAMP 
        WHERE establishment_id = ? AND is_read = 0
    ");
    $stmt->execute([$establishmentId]);
    
    jsonResponse(['success' => true, 'message' => 'Todas notificações marcadas como lidas']);
}

/**
 * Retorna apenas a contagem de não lidas (para badge)
 */
function countUnread(PDO $pdo, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE establishment_id = ? AND is_read = 0
    ");
    $stmt->execute([$establishmentId]);
    
    jsonResponse([
        'success' => true,
        'data' => ['unread' => (int) $stmt->fetchColumn()]
    ]);
}

/**
 * Obtém o ID do estabelecimento autenticado
 */
function getAuthenticatedEstablishment(): ?int
{
    // Verifica sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['establishment_id'])) {
        return (int) $_SESSION['establishment_id'];
    }
    
    // Verifica header Authorization ou query param
    $establishmentId = $_GET['establishment_id'] ?? $_POST['establishment_id'] ?? null;
    
    if ($establishmentId) {
        return (int) $establishmentId;
    }
    
    return null;
}
