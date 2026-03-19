<?php
/**
 * Establishment - Events CRUD API
 * 
 * GET /api/establishment/events.php - Listar eventos do estabelecimento
 * GET /api/establishment/events.php?id=X - Obter um evento
 * POST /api/establishment/events.php - Criar evento
 * PUT /api/establishment/events.php?id=X - Atualizar evento
 * DELETE /api/establishment/events.php?id=X - Deletar evento
 * POST /api/establishment/events.php?action=toggle&id=X - Alternar status
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDatabase();
    
    // Verificar autenticação do estabelecimento
    $establishment = requireEstablishment();
    $establishmentId = $establishment['id'];

    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getOne($pdo, (int)$_GET['id'], $establishmentId);
            } elseif (isset($_GET['kjs'])) {
                getKJs($pdo, $establishmentId);
            } else {
                getAll($pdo, $establishmentId);
            }
            break;
            
        case 'POST':
            if ($action === 'toggle' && isset($_GET['id'])) {
                toggleStatus($pdo, (int)$_GET['id'], $establishmentId);
            } else {
                create($pdo, $establishmentId);
            }
            break;
            
        case 'PUT':
            if (!isset($_GET['id'])) {
                errorResponse('ID é obrigatório', 400);
            }
            update($pdo, (int)$_GET['id'], $establishmentId);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                errorResponse('ID é obrigatório', 400);
            }
            delete($pdo, (int)$_GET['id'], $establishmentId);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Establishment Events API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Verificar se é estabelecimento logado
 */
function requireEstablishment(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }
    
    if (empty($_SESSION['establishment_id'])) {
        errorResponse('Acesso não autorizado', 401);
    }
    
    return [
        'id' => $_SESSION['establishment_id'],
        'name' => $_SESSION['establishment_name'] ?? 'Estabelecimento'
    ];
}

/**
 * Listar eventos do estabelecimento
 */
function getAll(PDO $pdo, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            a.name as kj_name,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id) as total_songs,
            (SELECT COUNT(DISTINCT profile_name) FROM queue WHERE event_id = e.id) as unique_singers,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id AND status = 'sung') as songs_completed
        FROM event_settings e
        LEFT JOIN admins a ON e.admin_id = a.id
        WHERE e.establishment_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$establishmentId]);
    $events = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $events
    ]);
}

/**
 * Obter um evento
 */
function getOne(PDO $pdo, int $id, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            a.name as kj_name,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id) as total_songs,
            (SELECT COUNT(DISTINCT profile_name) FROM queue WHERE event_id = e.id) as unique_singers,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id AND status = 'sung') as songs_completed
        FROM event_settings e
        LEFT JOIN admins a ON e.admin_id = a.id
        WHERE e.id = ? AND e.establishment_id = ?
    ");
    $stmt->execute([$id, $establishmentId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        errorResponse('Evento não encontrado', 404);
    }
    
    // Buscar músicas mais pedidas do evento
    $stmt = $pdo->prepare("
        SELECT song_title, artist, COUNT(*) as count
        FROM queue
        WHERE event_id = ?
        GROUP BY song_title, artist
        ORDER BY count DESC
        LIMIT 5
    ");
    $stmt->execute([$id]);
    $event['top_songs'] = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $event
    ]);
}

/**
 * Listar KJs do estabelecimento
 */
function getKJs(PDO $pdo, int $establishmentId): void
{
    $stmt = $pdo->prepare("
        SELECT id, name, username, is_active
        FROM admins
        WHERE establishment_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$establishmentId]);
    $kjs = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $kjs
    ]);
}

/**
 * Criar evento
 */
function create(PDO $pdo, int $establishmentId): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $eventName = trim($input['event_name'] ?? '');
    $adminId = !empty($input['admin_id']) ? (int)$input['admin_id'] : null;
    
    if (empty($eventName)) {
        errorResponse('Nome do evento é obrigatório');
    }
    
    // Se forneceu KJ, verifica se pertence ao estabelecimento
    if ($adminId) {
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND establishment_id = ?");
        $stmt->execute([$adminId, $establishmentId]);
        if (!$stmt->fetch()) {
            errorResponse('KJ não encontrado ou não pertence ao estabelecimento');
        }
    }
    
    // Gera código se não fornecido
    $eventCode = strtoupper(trim($input['event_code'] ?? ''));
    if (empty($eventCode)) {
        $eventCode = strtoupper(substr(md5(uniqid()), 0, 4));
    }
    
    // Verifica código único
    $stmt = $pdo->prepare("SELECT id FROM event_settings WHERE event_code = ?");
    $stmt->execute([$eventCode]);
    if ($stmt->fetch()) {
        $eventCode = strtoupper(substr(md5(uniqid() . time()), 0, 4));
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO event_settings (
            event_name, event_code, establishment_id, admin_id,
            status, starts_at, ends_at, max_songs_per_person,
            is_template, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $eventName,
        $eventCode,
        $establishmentId,
        $adminId,
        $input['status'] ?? 'closed',
        $input['starts_at'] ?? null,
        $input['ends_at'] ?? null,
        (int)($input['max_songs_per_person'] ?? 3),
        (int)($input['is_template'] ?? 0)
    ]);
    
    $id = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Evento criado com sucesso',
        'data' => [
            'id' => (int)$id,
            'event_name' => $eventName,
            'event_code' => $eventCode
        ]
    ], 201);
}

/**
 * Atualizar evento
 */
function update(PDO $pdo, int $id, int $establishmentId): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verifica se existe e pertence ao estabelecimento
    $stmt = $pdo->prepare("SELECT id FROM event_settings WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    if (!$stmt->fetch()) {
        errorResponse('Evento não encontrado', 404);
    }
    
    $fields = [];
    $values = [];
    
    $allowedFields = [
        'event_name', 'event_code', 'status', 'admin_id',
        'starts_at', 'ends_at', 'max_songs_per_person', 'is_template'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $value = $input[$field];
            
            if ($field === 'event_code') {
                $value = strtoupper(trim($value));
            }
            
            // Se mudar o KJ, verificar se pertence ao estabelecimento
            if ($field === 'admin_id' && $value) {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND establishment_id = ?");
                $stmt->execute([$value, $establishmentId]);
                if (!$stmt->fetch()) {
                    errorResponse('KJ não encontrado ou não pertence ao estabelecimento');
                }
            }
            
            $values[] = $value;
        }
    }
    
    if (empty($fields)) {
        errorResponse('Nenhum campo para atualizar');
    }
    
    $values[] = $id;
    
    $sql = "UPDATE event_settings SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    jsonResponse([
        'success' => true,
        'message' => 'Evento atualizado'
    ]);
}

/**
 * Alternar status do evento
 */
function toggleStatus(PDO $pdo, int $id, int $establishmentId): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $newStatus = $input['status'] ?? null;
    
    // Verifica se existe e pertence ao estabelecimento
    $stmt = $pdo->prepare("SELECT status FROM event_settings WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        errorResponse('Evento não encontrado', 404);
    }
    
    if (!$newStatus) {
        $newStatus = $event['status'] === 'open' ? 'closed' : 'open';
    }
    
    $stmt = $pdo->prepare("UPDATE event_settings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    $statusLabels = [
        'open' => '🟢 Aberto',
        'paused' => '🟡 Pausado',
        'closed' => '🔴 Fechado'
    ];
    
    jsonResponse([
        'success' => true,
        'status' => $newStatus,
        'message' => 'Status alterado para: ' . ($statusLabels[$newStatus] ?? $newStatus)
    ]);
}

/**
 * Deletar evento
 */
function delete(PDO $pdo, int $id, int $establishmentId): void
{
    // Verifica se existe e pertence ao estabelecimento
    $stmt = $pdo->prepare("SELECT id FROM event_settings WHERE id = ? AND establishment_id = ?");
    $stmt->execute([$id, $establishmentId]);
    if (!$stmt->fetch()) {
        errorResponse('Evento não encontrado', 404);
    }
    
    // Contar músicas na fila
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE event_id = ?");
    $stmt->execute([$id]);
    $queueCount = (int)$stmt->fetchColumn();
    
    if ($queueCount > 0) {
        $stmt = $pdo->prepare("DELETE FROM queue WHERE event_id = ?");
        $stmt->execute([$id]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM event_settings WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Evento deletado' . ($queueCount > 0 ? " (e $queueCount músicas da fila)" : '')
    ]);
}
