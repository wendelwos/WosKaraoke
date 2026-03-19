<?php
/**
 * API de Badges - WosKaraoke
 * 
 * GET  /api/badges.php?token=X        - Lista badges do usuário
 * GET  /api/badges.php?action=all     - Lista todas as badges disponíveis
 * POST /api/badges.php?action=check   - Verifica e concede novas badges
 * POST /api/badges.php?action=grant   - Concede badge manualmente (admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Definição de todas as badges disponíveis
const BADGES = [
    'starter' => [
        'code' => 'starter',
        'name' => 'Estreante',
        'icon' => '🎤',
        'description' => 'Cantou a primeira música',
        'condition' => 'Cantar 1 música'
    ],
    'veteran' => [
        'code' => 'veteran',
        'name' => 'Veterano',
        'icon' => '🎵',
        'description' => 'Cantou 50 ou mais músicas',
        'condition' => 'Cantar 50 músicas'
    ],
    'gladiator' => [
        'code' => 'gladiator',
        'name' => 'Gladiador',
        'icon' => '⚔️',
        'description' => 'Venceu uma batalha',
        'condition' => 'Vencer 1 batalha'
    ],
    'top10' => [
        'code' => 'top10',
        'name' => 'Top 10',
        'icon' => '🔥',
        'description' => 'Entrou no ranking semanal',
        'condition' => 'Estar no Top 10 da semana'
    ],
    'champion' => [
        'code' => 'champion',
        'name' => 'Campeão',
        'icon' => '👑',
        'description' => 'Primeiro lugar da semana',
        'condition' => 'Ser o Top 1 da semana'
    ]
];

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            if ($action === 'all') {
                getAllBadges();
            } elseif (!empty($_GET['token'])) {
                getUserBadges($pdo);
            } else {
                errorResponse('Parâmetro obrigatório: token ou action=all', 400);
            }
            break;
            
        case 'POST':
            if ($action === 'check') {
                checkBadges($pdo);
            } elseif ($action === 'grant') {
                grantBadge($pdo);
            } else {
                errorResponse('Ação não reconhecida', 400);
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Badges API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Retorna todas as badges disponíveis no sistema
 */
function getAllBadges(): void
{
    $badges = array_values(BADGES);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'badges' => $badges,
            'total' => count($badges)
        ]
    ]);
}

/**
 * Retorna badges do usuário
 */
function getUserBadges(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT id, name FROM profiles WHERE token = ?");
    $stmt->execute([$token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    // Busca badges conquistadas
    $stmt = $pdo->prepare("
        SELECT badge_code, badge_name, badge_icon, earned_at
        FROM user_badges 
        WHERE profile_id = ?
        ORDER BY earned_at DESC
    ");
    $stmt->execute([$profile['id']]);
    $earnedBadges = $stmt->fetchAll();
    
    // Adiciona descrição das badges
    foreach ($earnedBadges as &$badge) {
        if (isset(BADGES[$badge['badge_code']])) {
            $badge['description'] = BADGES[$badge['badge_code']]['description'];
        }
    }
    
    // Badges ainda não conquistadas
    $earnedCodes = array_column($earnedBadges, 'badge_code');
    $lockedBadges = [];
    
    foreach (BADGES as $code => $badge) {
        if (!in_array($code, $earnedCodes)) {
            $lockedBadges[] = [
                'badge_code' => $badge['code'],
                'badge_name' => $badge['name'],
                'badge_icon' => $badge['icon'],
                'description' => $badge['description'],
                'condition' => $badge['condition'],
                'locked' => true
            ];
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'profile_id' => $profile['id'],
            'name' => $profile['name'],
            'earned' => $earnedBadges,
            'locked' => $lockedBadges,
            'total_earned' => count($earnedBadges),
            'total_available' => count(BADGES)
        ]
    ]);
}

/**
 * Verifica e concede badges para um usuário
 */
function checkBadges(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $profileId = (int) ($input['profile_id'] ?? 0);
    
    if ($profileId <= 0 && !empty($input['token'])) {
        // Busca por token
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE token = ?");
        $stmt->execute([$input['token']]);
        $profileId = (int) $stmt->fetchColumn();
    }
    
    if ($profileId <= 0) {
        errorResponse('profile_id ou token obrigatório', 400);
    }
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    $badgesGranted = [];
    
    // Badge: Estreante (primeira música)
    if (($profile['songs_sung_count'] ?? 0) >= 1) {
        if (grantBadgeIfNew($pdo, $profileId, 'starter', 'Estreante', '🎤')) {
            $badgesGranted[] = 'starter';
        }
    }
    
    // Badge: Veterano (50+ músicas)
    if (($profile['songs_sung_count'] ?? 0) >= 50) {
        if (grantBadgeIfNew($pdo, $profileId, 'veteran', 'Veterano', '🎵')) {
            $badgesGranted[] = 'veteran';
        }
    }
    
    // Badge: Gladiador (venceu batalha)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM battles 
        WHERE winner_id = ? AND status = 'finished'
    ");
    $stmt->execute([$profileId]);
    $battleWins = (int) $stmt->fetchColumn();
    
    if ($battleWins > 0) {
        if (grantBadgeIfNew($pdo, $profileId, 'gladiator', 'Gladiador', '⚔️')) {
            $badgesGranted[] = 'gladiator';
        }
    }
    
    // Badge: Top 10 (está no top 10 semanal)
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as position
        FROM (
            SELECT profile_id, SUM(points) as total
            FROM user_points 
            WHERE DATE(created_at) >= ?
            GROUP BY profile_id
            ORDER BY total DESC
            LIMIT 10
        ) as top10
        WHERE profile_id = ?
    ");
    $stmt->execute([$weekStart, $profileId]);
    $inTop10 = (int) $stmt->fetchColumn() > 0;
    
    if ($inTop10) {
        if (grantBadgeIfNew($pdo, $profileId, 'top10', 'Top 10', '🔥')) {
            $badgesGranted[] = 'top10';
        }
    }
    
    // Badge: Campeão (top 1 semanal) - verifica se é o primeiro
    $stmt = $pdo->prepare("
        SELECT profile_id
        FROM user_points 
        WHERE DATE(created_at) >= ?
        GROUP BY profile_id
        ORDER BY SUM(points) DESC
        LIMIT 1
    ");
    $stmt->execute([$weekStart]);
    $topProfile = $stmt->fetchColumn();
    
    if ($topProfile == $profileId) {
        if (grantBadgeIfNew($pdo, $profileId, 'champion', 'Campeão', '👑')) {
            $badgesGranted[] = 'champion';
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => count($badgesGranted) > 0 
            ? 'Novas badges conquistadas: ' . implode(', ', $badgesGranted)
            : 'Nenhuma nova badge conquistada',
        'data' => [
            'profile_id' => $profileId,
            'badges_granted' => $badgesGranted,
            'total_granted' => count($badgesGranted)
        ]
    ]);
}

/**
 * Concede badge manualmente (uso admin)
 */
function grantBadge(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $profileId = (int) ($input['profile_id'] ?? 0);
    $badgeCode = $input['badge_code'] ?? '';
    
    if ($profileId <= 0) {
        errorResponse('profile_id obrigatório', 400);
    }
    
    if (empty($badgeCode) || !isset(BADGES[$badgeCode])) {
        errorResponse('badge_code inválido', 400);
    }
    
    $badge = BADGES[$badgeCode];
    
    if (grantBadgeIfNew($pdo, $profileId, $badge['code'], $badge['name'], $badge['icon'])) {
        jsonResponse([
            'success' => true,
            'message' => "Badge '{$badge['name']}' concedida com sucesso",
            'data' => [
                'profile_id' => $profileId,
                'badge' => $badge
            ]
        ]);
    } else {
        errorResponse('Badge já foi concedida anteriormente', 409);
    }
}

/**
 * Concede badge se ainda não foi concedida
 * @return bool True se foi concedida agora, false se já existia
 */
function grantBadgeIfNew(PDO $pdo, int $profileId, string $code, string $name, string $icon): bool
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_badges (profile_id, badge_code, badge_name, badge_icon)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$profileId, $code, $name, $icon]);
        return true;
    } catch (Exception $e) {
        // Badge já existe
        return false;
    }
}
