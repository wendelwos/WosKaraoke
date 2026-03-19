<?php
/**
 * API de Evento - WosKaraoke
 * Gerencia código de acesso e status do evento
 * 
 * GET /api/admin/event.php - Obter configurações (público)
 * POST /api/admin/event.php?action=update - Atualizar configurações (admin)
 * POST /api/admin/event.php?action=toggle - Abrir/fechar fila (admin)
 * POST /api/admin/event.php?action=new_code - Gerar novo código (admin)
 * POST /api/admin/event.php?action=verify - Verificar código de acesso (público)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();

    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                requireAdmin();
                listEvents($pdo);
            } elseif ($action === 'current') {
                requireAdmin();
                getCurrentEvent($pdo);
            } elseif ($action === 'public') {
                // Lista eventos abertos para clientes (público)
                listPublicEvents($pdo);
            } else {
                getEventSettings($pdo);
            }
            break;
            
        case 'POST':
            if ($action === 'verify') {
                verifyEventCode($pdo);
            } elseif ($action === 'create') {
                requireAdmin();
                createEvent($pdo);
            } elseif ($action === 'update') {
                requireAdmin();
                updateEventSettings($pdo);
            } elseif ($action === 'toggle') {
                requireAdmin();
                toggleEvent($pdo);
            } elseif ($action === 'new_code') {
                requireAdmin();
                generateNewCode($pdo);
            } else {
                errorResponse('Ação inválida');
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Throwable $e) {
    error_log("Event API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Verifica se é admin logado
 */
function requireAdmin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_httponly', '1');
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        errorResponse('Acesso não autorizado', 401);
    }
}

/**
 * Lista eventos do KJ logado
 */
function listEvents(PDO $pdo): void
{
    $adminId = $_SESSION['admin_id'];
    
    // Busca eventos do admin ou todos se não tiver admin_id (legado)
    $stmt = $pdo->prepare("
        SELECT id, event_name, event_code, is_open, created_at, updated_at
        FROM event_settings 
        WHERE admin_id = ? OR admin_id IS NULL
        ORDER BY created_at DESC
    ");
    $stmt->execute([$adminId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'data' => $events
    ]);
}

/**
 * Obtém evento atual do KJ
 */
function getCurrentEvent(PDO $pdo): void
{
    $adminId = $_SESSION['admin_id'];
    
    // Tenta pegar evento do admin ou o padrão (id=1)
    $stmt = $pdo->prepare("
        SELECT id, event_name, event_code, is_open
        FROM event_settings 
        WHERE admin_id = ? OR id = 1
        ORDER BY admin_id DESC, id ASC
        LIMIT 1
    ");
    $stmt->execute([$adminId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'data' => $event
    ]);
}

/**
 * Cria novo evento
 */
function createEvent(PDO $pdo): void
{
    $adminId = $_SESSION['admin_id'];
    $establishmentId = $_SESSION['establishment_id'] ?? null;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $eventName = trim($input['event_name'] ?? '');
    $eventCode = strtoupper(trim($input['event_code'] ?? ''));
    $isOpen = isset($input['is_open']) ? (int)$input['is_open'] : 0;
    
    if (empty($eventName)) {
        errorResponse('Nome do evento é obrigatório');
    }
    
    // Gera código se não fornecido
    if (empty($eventCode)) {
        $eventCode = strtoupper(substr(md5(uniqid()), 0, 4));
    }
    
    // Verifica código único
    $stmt = $pdo->prepare("SELECT id FROM event_settings WHERE event_code = ?");
    $stmt->execute([$eventCode]);
    if ($stmt->fetch()) {
        // Gera novo código se já existe
        $eventCode = strtoupper(substr(md5(uniqid() . time()), 0, 4));
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO event_settings (event_name, event_code, is_open, admin_id, establishment_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$eventName, $eventCode, $isOpen, $adminId, $establishmentId]);
    
    $id = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Evento criado com sucesso',
        'data' => [
            'id' => (int)$id,
            'event_name' => $eventName,
            'event_code' => $eventCode,
            'is_open' => (bool)$isOpen
        ]
    ], 201);
}

/**
 * Obtém configurações do evento (público)
 */
function getEventSettings(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT * FROM event_settings WHERE id = 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        $settings = [
            'event_code' => '1234',
            'is_open' => 1,
            'event_name' => 'Karaokê'
        ];
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'event_name' => $settings['event_name'],
            'is_open' => (bool) $settings['is_open'],
            // Não expõe o código completo publicamente
            'code_hint' => substr($settings['event_code'], 0, 1) . '***'
        ]
    ]);
}

function verifyEventCode(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $code = trim($input['code'] ?? '');
    
    if (empty($code)) {
        errorResponse('Código é obrigatório');
    }
    
    // Busca por QUALQUER evento com este código (case insensitive se DB permitir, mas forçamos UPPER no insert/busca)
    // Nota: Em SQLite 'LIKE' é case-insensitive por padrão para ASCII, mas para garantir, vamos buscar exato.
    // O frontend já manda UPPERCASE.
    
    $stmt = $pdo->prepare("SELECT id, event_name, is_open FROM event_settings WHERE event_code = ?");
    $stmt->execute([strtoupper($code)]);
    $event = $stmt->fetch();
    
    if (!$event) {
        // Tenta buscar na tabela de configurações padrão se não achar (fallback legado ID 1)
        // Isso mantem compatibilidade se o admin mudou o codigo do evento 1
        // Mas idealmente o SELECT acima já pega se o código bater.
        jsonResponse([
            'success' => false,
            'valid' => false,
            'error' => 'Código inválido ou evento não encontrado'
        ]);
    }
    
    if (!$event['is_open']) {
        jsonResponse([
            'success' => false,
            'valid' => false,
            'error' => "O evento '{$event['event_name']}' está fechado no momento"
        ]);
    }
    
    jsonResponse([
        'success' => true,
        'valid' => true,
        'message' => "Bem-vindo ao {$event['event_name']}!",
        'event_id' => (int)$event['id'], // Retorna o ID do evento encontrado
        'event_name' => $event['event_name']
    ]);
}

/**
 * Atualiza configurações do evento (admin)
 */
function updateEventSettings(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $updates = [];
    $params = [];
    
    if (isset($input['event_code'])) {
        $code = strtoupper(trim($input['event_code']));
        if (strlen($code) < 4) {
            errorResponse('Código deve ter pelo menos 4 caracteres');
        }
        $updates[] = 'event_code = ?';
        $params[] = $code;
    }
    
    if (isset($input['event_name'])) {
        $updates[] = 'event_name = ?';
        $params[] = trim($input['event_name']);
    }
    
    if (isset($input['is_open'])) {
        $updates[] = 'is_open = ?';
        $params[] = $input['is_open'] ? 1 : 0;
    }
    
    if (empty($updates)) {
        errorResponse('Nenhum campo para atualizar');
    }
    
    $updates[] = 'updated_at = CURRENT_TIMESTAMP';
    $params[] = 1; // WHERE id = 1
    
    $sql = "UPDATE event_settings SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Retorna configurações atualizadas
    $stmt = $pdo->query("SELECT * FROM event_settings WHERE id = 1");
    $settings = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => 'Configurações atualizadas',
        'data' => [
            'event_code' => $settings['event_code'],
            'event_name' => $settings['event_name'],
            'is_open' => (bool) $settings['is_open']
        ]
    ]);
}

/**
 * Abre ou fecha o evento (toggle)
 */
function toggleEvent(PDO $pdo): void
{
    $pdo->exec("UPDATE event_settings SET is_open = NOT is_open, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
    
    $stmt = $pdo->query("SELECT is_open FROM event_settings WHERE id = 1");
    $settings = $stmt->fetch();
    
    $isOpen = (bool) $settings['is_open'];
    
    jsonResponse([
        'success' => true,
        'is_open' => $isOpen,
        'message' => $isOpen ? '🟢 Evento aberto! Fila liberada.' : '🔴 Evento fechado. Fila bloqueada.'
    ]);
}

/**
 * Gera um novo código aleatório
 */
function generateNewCode(PDO $pdo): void
{
    // Gera código de 4 dígitos
    $newCode = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    
    $stmt = $pdo->prepare("UPDATE event_settings SET event_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1");
    $stmt->execute([$newCode]);
    
    jsonResponse([
        'success' => true,
        'code' => $newCode,
        'message' => "Novo código gerado: $newCode"
    ]);
}

/**
 * Lista eventos abertos para clientes (público)
 * Não expõe o código, apenas nome e ID do evento
 */
function listPublicEvents(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT id, event_name, created_at
        FROM event_settings 
        WHERE is_open = 1
        ORDER BY event_name ASC
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'data' => $events
    ]);
}
