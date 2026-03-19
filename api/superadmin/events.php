<?php
/**
 * Super Admin - Events CRUD API
 * 
 * GET /api/superadmin/events.php - Listar todos os eventos
 * GET /api/superadmin/events.php?id=X - Obter um evento
 * GET /api/superadmin/events.php?establishment_id=X - Filtrar por estabelecimento
 * POST /api/superadmin/events.php - Criar evento
 * PUT /api/superadmin/events.php?id=X - Atualizar evento
 * DELETE /api/superadmin/events.php?id=X - Deletar evento
 * POST /api/superadmin/events.php?action=toggle&id=X - Alternar status
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDatabase();
    
    // Verificar autenticação SuperAdmin
    requireSuperAdmin();

    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getOne($pdo, (int)$_GET['id']);
            } elseif (isset($_GET['stats'])) {
                getStats($pdo);
            } else {
                getAll($pdo);
            }
            break;
            
        case 'POST':
            if ($action === 'toggle' && isset($_GET['id'])) {
                toggleStatus($pdo, (int)$_GET['id']);
            } else {
                create($pdo);
            }
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
    error_log("SuperAdmin Events API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Listar todos os eventos
 */
function getAll(PDO $pdo): void
{
    $sql = "
        SELECT 
            e.*,
            est.name as establishment_name,
            a.name as kj_name,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id) as total_songs,
            (SELECT COUNT(DISTINCT profile_name) FROM queue WHERE event_id = e.id) as unique_singers
        FROM event_settings e
        LEFT JOIN establishments est ON e.establishment_id = est.id
        LEFT JOIN admins a ON e.admin_id = a.id
    ";
    
    $params = [];
    
    // Filtro por estabelecimento
    if (!empty($_GET['establishment_id'])) {
        $sql .= " WHERE e.establishment_id = ?";
        $params[] = (int)$_GET['establishment_id'];
    }
    
    // Filtro por status
    if (!empty($_GET['status'])) {
        $sql .= empty($params) ? " WHERE " : " AND ";
        $sql .= "e.status = ?";
        $params[] = $_GET['status'];
    }
    
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $events
    ]);
}

/**
 * Obter um evento
 */
function getOne(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            est.name as establishment_name,
            a.name as kj_name,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id) as total_songs,
            (SELECT COUNT(DISTINCT profile_name) FROM queue WHERE event_id = e.id) as unique_singers,
            (SELECT COUNT(*) FROM queue WHERE event_id = e.id AND status = 'sung') as songs_completed
        FROM event_settings e
        LEFT JOIN establishments est ON e.establishment_id = est.id
        LEFT JOIN admins a ON e.admin_id = a.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        errorResponse('Evento não encontrado', 404);
    }
    
    // Buscar músicas mais pedidas do evento
    $stmt = $pdo->prepare("
        SELECT song_title, song_artist, COUNT(*) as count
        FROM queue
        WHERE event_id = ?
        GROUP BY song_title, song_artist
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
 * Estatísticas gerais de eventos
 */
function getStats(PDO $pdo): void
{
    $stats = [];
    
    // Total de eventos
    $stmt = $pdo->query("SELECT COUNT(*) FROM event_settings");
    $stats['total_events'] = (int)$stmt->fetchColumn();
    
    // Eventos por status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM event_settings
        GROUP BY status
    ");
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Eventos ativos (abertos)
    $stmt = $pdo->query("SELECT COUNT(*) FROM event_settings WHERE status = 'open'");
    $stats['active_events'] = (int)$stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Criar evento
 */
function create(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $eventName = trim($input['event_name'] ?? '');
    $establishmentId = (int)($input['establishment_id'] ?? 0);
    $adminId = !empty($input['admin_id']) ? (int)$input['admin_id'] : null;
    
    if (empty($eventName)) {
        errorResponse('Nome do evento é obrigatório');
    }
    
    if ($establishmentId <= 0) {
        errorResponse('Estabelecimento é obrigatório');
    }
    
    // Verificar se estabelecimento existe
    $stmt = $pdo->prepare("SELECT id FROM establishments WHERE id = ?");
    $stmt->execute([$establishmentId]);
    if (!$stmt->fetch()) {
        errorResponse('Estabelecimento não encontrado', 404);
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
function update(PDO $pdo, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verifica se existe
    $stmt = $pdo->prepare("SELECT id FROM event_settings WHERE id = ?");
    $stmt->execute([$id]);
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
            
            // Uppercase para event_code
            if ($field === 'event_code') {
                $value = strtoupper(trim($value));
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
function toggleStatus(PDO $pdo, int $id): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $newStatus = $input['status'] ?? null;
    
    // Verifica se existe
    $stmt = $pdo->prepare("SELECT status FROM event_settings WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        errorResponse('Evento não encontrado', 404);
    }
    
    // Se não forneceu status, alterna entre open/closed
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
function delete(PDO $pdo, int $id): void
{
    // Verificar se tem músicas na fila
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE event_id = ?");
    $stmt->execute([$id]);
    $queueCount = (int)$stmt->fetchColumn();
    
    if ($queueCount > 0) {
        // Deleta as músicas da fila
        $stmt = $pdo->prepare("DELETE FROM queue WHERE event_id = ?");
        $stmt->execute([$id]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM event_settings WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        errorResponse('Evento não encontrado', 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Evento deletado' . ($queueCount > 0 ? " (e $queueCount músicas da fila)" : '')
    ]);
}
