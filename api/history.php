<?php
/**
 * API de Histórico e Perfil - WosKaraoke
 * 
 * GET /api/history.php?token=XXX - Lista histórico de músicas e estatísticas
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabase();
    
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token obrigatório', 401);
    }
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE token = ?");
    $stmt->execute([$token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    // Busca histórico de músicas
    // Busca histórico de músicas com nome do evento
    $stmt = $pdo->prepare("
        SELECT 
            h.id, h.song_code, h.song_title, h.song_artist, h.sung_at,
            COALESCE(e.event_name, 'Evento Antigo') as event_name
        FROM song_history h
        LEFT JOIN event_settings e ON h.event_id = e.id
        WHERE h.profile_id = ? 
        ORDER BY h.sung_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$profile['id']]);
    $history = $stmt->fetchAll();
    
    // Estatísticas Extras
    $level = (int)($profile['level'] ?? 1);
    $songsCount = (int)($profile['songs_sung_count'] ?? 0);
    
    // Calcula progresso para próximo nível (Exemplo simples)
    // Nível 1: 0-5
    // Nível 2: 6-20
    // Nível 3: 20+
    $nextLevelThreshold = 1000;
    $progress = 0;
    $levelTitle = 'Iniciante';
    
    if ($songsCount < 5) {
        $level = 1;
        $nextLevelThreshold = 5;
        $levelTitle = 'Iniciante 🎤';
    } elseif ($songsCount < 20) {
        $level = 2;
        $nextLevelThreshold = 20;
        $levelTitle = 'Cantor de Chuveiro 🚿';
    } elseif ($songsCount < 50) {
        $level = 3;
        $nextLevelThreshold = 50;
        $levelTitle = 'Veterano 🎵';
    } else {
        $level = 4;
        $nextLevelThreshold = 100;
        $levelTitle = 'Ídolo da Galera 🌟';
    }
    
    if ($songsCount < $nextLevelThreshold) {
        $progress = ($songsCount / $nextLevelThreshold) * 100;
    } else {
        $progress = 100;
    }
    
    // Busca total de pontos
    $totalPoints = (int) ($profile['total_points'] ?? 0);
    
    // Busca badges conquistadas
    $stmtBadges = $pdo->prepare("
        SELECT badge_code, badge_name, badge_icon, earned_at
        FROM user_badges 
        WHERE profile_id = ?
        ORDER BY earned_at DESC
    ");
    $stmtBadges->execute([$profile['id']]);
    $badges = $stmtBadges->fetchAll();
    
    // Busca posição no ranking semanal
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmtRank = $pdo->prepare("
        SELECT COUNT(*) + 1 as position
        FROM (
            SELECT profile_id, SUM(points) as total
            FROM user_points 
            WHERE DATE(created_at) >= ?
            GROUP BY profile_id
            HAVING total > (
                SELECT COALESCE(SUM(points), 0)
                FROM user_points 
                WHERE profile_id = ? AND DATE(created_at) >= ?
            )
        ) as better_profiles
    ");
    $stmtRank->execute([$weekStart, $profile['id'], $weekStart]);
    $rankPosition = (int) $stmtRank->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'history' => $history,
            'stats' => [
                'total_songs' => $songsCount,
                'total_points' => $totalPoints,
                'rank_position' => $rankPosition,
                'level' => $level,
                'level_title' => $levelTitle,
                'next_level_at' => $nextLevelThreshold,
                'progress' => $progress
            ],
            'badges' => $badges
        ]
    ]);

} catch (Exception $e) {
    errorResponse('Erro ao buscar histórico: ' . $e->getMessage(), 500);
}
