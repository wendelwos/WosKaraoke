<?php
/**
 * API de Ranking & Gamificação - WosKaraoke
 * 
 * GET  /api/ranking.php?event_id=X          - Ranking semanal do evento
 * GET  /api/ranking.php?token=X             - Pontos e posição do usuário
 * GET  /api/ranking.php?action=global       - Ranking global (todos os eventos)
 * POST /api/ranking.php?action=add_points   - Adiciona pontos (uso interno)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Constantes de pontuação
const POINTS_SING_SONG = 5;
const POINTS_FIRST_OF_DAY = 2;
const POINTS_BATTLE_WIN = 7;

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if (!empty($_GET['token'])) {
                getUserRanking($pdo);
            } elseif (!empty($_GET['event_id'])) {
                getEventRanking($pdo);
            } elseif ($action === 'global') {
                getGlobalRanking($pdo);
            } else {
                errorResponse('Parâmetro obrigatório: token, event_id ou action=global', 400);
            }
            break;
            
        case 'POST':
            if ($action === 'add_points') {
                addPoints($pdo);
            } else {
                errorResponse('Ação não reconhecida', 400);
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Ranking API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Retorna ranking semanal de um evento
 */
function getEventRanking(PDO $pdo): void
{
    $eventId = (int) ($_GET['event_id'] ?? 0);
    
    if ($eventId <= 0) {
        errorResponse('event_id inválido', 400);
    }
    
    // Início da semana atual (segunda-feira)
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Top 10 da semana por pontos
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.avatar_color,
            p.avatar_url,
            COALESCE(SUM(up.points), 0) as week_points,
            p.total_points
        FROM profiles p
        LEFT JOIN user_points up ON p.id = up.profile_id 
            AND up.event_id = ? 
            AND DATE(up.created_at) >= ?
        GROUP BY p.id, p.name, p.avatar_color, p.avatar_url, p.total_points
        HAVING week_points > 0
        ORDER BY week_points DESC
        LIMIT 10
    ");
    $stmt->execute([$eventId, $weekStart]);
    $ranking = $stmt->fetchAll();
    
    // Adiciona posição
    $position = 1;
    foreach ($ranking as &$row) {
        $row['position'] = $position++;
        $row['week_points'] = (int) $row['week_points'];
        $row['total_points'] = (int) $row['total_points'];
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'event_id' => $eventId,
            'week_start' => $weekStart,
            'ranking' => $ranking
        ]
    ]);
}

/**
 * Retorna ranking global (todos os eventos)
 */
function getGlobalRanking(PDO $pdo): void
{
    // Início da semana atual
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Top 10 global da semana
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.avatar_color,
            p.avatar_url,
            COALESCE(SUM(up.points), 0) as week_points,
            p.total_points
        FROM profiles p
        LEFT JOIN user_points up ON p.id = up.profile_id 
            AND DATE(up.created_at) >= ?
        GROUP BY p.id, p.name, p.avatar_color, p.avatar_url, p.total_points
        HAVING week_points > 0
        ORDER BY week_points DESC
        LIMIT 10
    ");
    $stmt->execute([$weekStart]);
    $ranking = $stmt->fetchAll();
    
    // Adiciona posição
    $position = 1;
    foreach ($ranking as &$row) {
        $row['position'] = $position++;
        $row['week_points'] = (int) $row['week_points'];
        $row['total_points'] = (int) $row['total_points'];
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'week_start' => $weekStart,
            'ranking' => $ranking
        ]
    ]);
}

/**
 * Retorna pontuação e posição do usuário
 */
function getUserRanking(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : null;
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE token = ?");
    $stmt->execute([$token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    
    // Pontos da semana
    if ($eventId) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as week_points
            FROM user_points 
            WHERE profile_id = ? AND event_id = ? AND DATE(created_at) >= ?
        ");
        $stmt->execute([$profile['id'], $eventId, $weekStart]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(points), 0) as week_points
            FROM user_points 
            WHERE profile_id = ? AND DATE(created_at) >= ?
        ");
        $stmt->execute([$profile['id'], $weekStart]);
    }
    $weekPoints = (int) $stmt->fetchColumn();
    
    // Posição no ranking semanal
    if ($eventId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) + 1 as position
            FROM (
                SELECT profile_id, SUM(points) as total
                FROM user_points 
                WHERE event_id = ? AND DATE(created_at) >= ?
                GROUP BY profile_id
                HAVING total > ?
            ) as better_profiles
        ");
        $stmt->execute([$eventId, $weekStart, $weekPoints]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) + 1 as position
            FROM (
                SELECT profile_id, SUM(points) as total
                FROM user_points 
                WHERE DATE(created_at) >= ?
                GROUP BY profile_id
                HAVING total > ?
            ) as better_profiles
        ");
        $stmt->execute([$weekStart, $weekPoints]);
    }
    $position = (int) $stmt->fetchColumn();
    
    // Histórico de pontos recentes (últimos 10)
    $stmt = $pdo->prepare("
        SELECT points, action_type, description, created_at
        FROM user_points 
        WHERE profile_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$profile['id']]);
    $history = $stmt->fetchAll();
    
    // Busca badges do usuário
    $stmt = $pdo->prepare("
        SELECT badge_code, badge_name, badge_icon, earned_at
        FROM user_badges 
        WHERE profile_id = ?
        ORDER BY earned_at DESC
    ");
    $stmt->execute([$profile['id']]);
    $badges = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'profile_id' => $profile['id'],
            'name' => $profile['name'],
            'total_points' => (int) ($profile['total_points'] ?? 0),
            'week_points' => $weekPoints,
            'position' => $position,
            'songs_sung' => (int) ($profile['songs_sung_count'] ?? 0),
            'level' => (int) ($profile['level'] ?? 1),
            'badges' => $badges,
            'recent_points' => $history
        ]
    ]);
}

/**
 * Adiciona pontos a um usuário (uso interno)
 */
function addPoints(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $profileId = (int) ($input['profile_id'] ?? 0);
    $eventId = isset($input['event_id']) ? (int) $input['event_id'] : null;
    $points = (int) ($input['points'] ?? 0);
    $actionType = $input['action_type'] ?? '';
    $description = $input['description'] ?? null;
    
    if ($profileId <= 0) {
        errorResponse('profile_id obrigatório', 400);
    }
    
    if ($points <= 0) {
        errorResponse('points deve ser maior que 0', 400);
    }
    
    if (empty($actionType)) {
        errorResponse('action_type obrigatório', 400);
    }
    
    // Insere registro de pontos
    $stmt = $pdo->prepare("
        INSERT INTO user_points (profile_id, event_id, points, action_type, description)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$profileId, $eventId, $points, $actionType, $description]);
    
    // Atualiza total de pontos no perfil
    $stmt = $pdo->prepare("
        UPDATE profiles 
        SET total_points = COALESCE(total_points, 0) + ?
        WHERE id = ?
    ");
    $stmt->execute([$points, $profileId]);
    
    // Verifica e concede badges
    checkAndGrantBadges($pdo, $profileId, $actionType);
    
    jsonResponse([
        'success' => true,
        'message' => "Adicionados $points pontos",
        'data' => [
            'profile_id' => $profileId,
            'points_added' => $points,
            'action_type' => $actionType
        ]
    ]);
}

/**
 * Verifica e concede badges baseado nas conquistas
 */
function checkAndGrantBadges(PDO $pdo, int $profileId, string $actionType): void
{
    // Busca dados do perfil
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();
    
    if (!$profile) return;
    
    $badges = [];
    
    // Badge: Estreante (primeira música)
    if ($profile['songs_sung_count'] >= 1) {
        $badges[] = ['starter', 'Estreante', '🎤'];
    }
    
    // Badge: Veterano (50+ músicas)
    if ($profile['songs_sung_count'] >= 50) {
        $badges[] = ['veteran', 'Veterano', '🎵'];
    }
    
    // Badge: Gladiador (venceu batalha)
    if ($actionType === 'battle_win') {
        $badges[] = ['gladiator', 'Gladiador', '⚔️'];
    }
    
    // Badge: Top 10 (está no top 10 semanal)
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as better_count
        FROM (
            SELECT profile_id, SUM(points) as total
            FROM user_points 
            WHERE DATE(created_at) >= ?
            GROUP BY profile_id
        ) as rankings
        WHERE total > (
            SELECT COALESCE(SUM(points), 0)
            FROM user_points 
            WHERE profile_id = ? AND DATE(created_at) >= ?
        )
    ");
    $stmt->execute([$weekStart, $profileId, $weekStart]);
    $betterCount = (int) $stmt->fetchColumn();
    
    if ($betterCount < 10) {
        $badges[] = ['top10', 'Top 10', '🔥'];
    }
    
    // Badge: Campeão (top 1 semanal) - só no final da semana
    if ($betterCount === 0 && $profile['total_points'] > 0) {
        $badges[] = ['champion', 'Campeão', '👑'];
    }
    
    // Concede badges que ainda não foram concedidas
    foreach ($badges as $badge) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_badges (profile_id, badge_code, badge_name, badge_icon)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$profileId, $badge[0], $badge[1], $badge[2]]);
        } catch (Exception $e) {
            // Badge já existe, ignora
        }
    }
}
