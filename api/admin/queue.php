<?php
/**
 * API de Fila Global - Gerenciamento da fila de karaokê
 * 
 * GET              - Lista fila ordenada por prioridade
 * POST             - Adiciona música à fila
 * POST ?action=next    - Avança para próxima música
 * POST ?action=skip    - Pula pessoa ausente
 * DELETE           - Remove item da fila
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../pusher_helper.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabase();
    
    switch ($method) {
        case 'GET':
            getQueue($pdo);
            break;
            
        case 'POST':
            if ($action === 'next') {
                requireAdmin();
                nextSong($pdo);
            } elseif ($action === 'skip') {
                requireAdmin();
                skipSong($pdo);
            } elseif ($action === 'clear') {
                requireAdmin();
                clearQueue($pdo);
            } elseif ($action === 'reorder') {
                reorderQueue($pdo);
            } elseif ($action === 'play') {
                requireAdmin();
                playNow($pdo);
            } else {
                addToQueue($pdo);
            }
            break;
            
        case 'DELETE':
            removeFromQueue($pdo);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}

/**
 * Verifica se há admin logado
 */
function requireAdmin(): void
{
    if (!isset($_SESSION['admin_id'])) {
        errorResponse('Acesso não autorizado - apenas administradores', 401);
    }
}

/**
 * Retorna a fila ordenada por prioridade
 */
/**
 * Retorna a fila ordenada por prioridade (filtrada por evento)
 */
function getQueue(PDO $pdo): void
{
    $today = date('Y-m-d');
    $eventId = (int)($_GET['event_id'] ?? 1); // Default to 1 (Legacy/Global)
    
    // Busca música atual (status = 'singing')
    $stmt = $pdo->prepare("
        SELECT q.*, 
               COALESCE(s.songs_sung, 0) as songs_sung_today
        FROM queue q
        LEFT JOIN session_stats s ON q.profile_id = s.profile_id AND s.session_date = ?
        WHERE q.status = 'singing' AND q.event_id = ?
        LIMIT 1
    ");
    $stmt->execute([$today, $eventId]);
    $current = $stmt->fetch() ?: null;
    
    // Busca músicas aguardando (status = 'waiting')
    // Ordena por prioridade: tempo de espera - (músicas cantadas * 10 minutos)
    $isMySQL = DB_TYPE === 'mysql';
    
    if ($isMySQL) {
        $waitMinutesSQL = "TIMESTAMPDIFF(MINUTE, q.added_at, NOW())";
    } else {
        $waitMinutesSQL = "CAST((julianday('now') - julianday(q.added_at)) * 24 * 60 AS INTEGER)";
    }
    
    $stmt = $pdo->prepare("
        SELECT q.*,
               COALESCE(s.songs_sung, 0) as songs_sung_today,
               COALESCE(s.last_sung_at, '1970-01-01') as last_sung_at,
               ($waitMinutesSQL) as wait_minutes,
                (($waitMinutesSQL) - COALESCE(s.songs_sung, 0) * 10) as priority,
                p.songs_sung_count
        FROM queue q
        LEFT JOIN session_stats s ON q.profile_id = s.profile_id AND s.session_date = ?
        LEFT JOIN profiles p ON q.profile_id = p.id
        WHERE q.status = 'waiting' AND q.event_id = ?
        ORDER BY priority DESC, q.added_at ASC
    ");
    $stmt->execute([$today, $eventId]);
    $waiting = $stmt->fetchAll();
    
    // Process levels
    foreach ($waiting as &$item) {
        $count = (int)($item['songs_sung_count'] ?? 0);
        
        if ($count < 5) {
            $item['level'] = 1;
            $item['level_title'] = 'Iniciante';
            $item['level_icon'] = '🎤';
        } elseif ($count < 20) {
            $item['level'] = 2;
            $item['level_title'] = 'Cantor de Chuveiro';
            $item['level_icon'] = '🚿';
        } else {
            $item['level'] = 3;
            $item['level_title'] = 'Ídolo da Galera';
            $item['level_icon'] = '🌟';
        }
    }
    unset($item); // Break reference
    
    // Aplica regra: mesma pessoa não pode cantar duas seguidas
    $waiting = applyNoRepeatRule($waiting, $current);
    
    // Estatísticas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE status = 'waiting' AND event_id = ?");
    $stmt->execute([$eventId]);
    $waitingCount = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE status = 'done' AND event_id = ? AND DATE(finished_at) = ?");
    $stmt->execute([$eventId, $today]);
    $doneToday = $stmt->fetchColumn();
    
    // Pessoas únicas na fila
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT profile_id) FROM queue WHERE status = 'waiting' AND event_id = ?");
    $stmt->execute([$eventId]);
    $uniquePeople = $stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'event_id' => $eventId,
        'data' => [
            'current' => $current,
            'waiting' => $waiting,
            'stats' => [
                'waiting_count' => (int) $waitingCount,
                'done_today' => (int) $doneToday,
                'unique_people' => (int) $uniquePeople
            ]
        ]
    ]);
}

/**
 * Aplica regra: mesma pessoa não pode cantar duas vezes seguidas
 */
function applyNoRepeatRule(array $waiting, ?array $current): array
{
    if (!$current || count($waiting) < 2) {
        return $waiting;
    }
    
    $currentProfileId = $current['profile_id'];
    
    // Se o primeiro da fila é a mesma pessoa que está cantando, move para depois
    if (!empty($waiting) && $waiting[0]['profile_id'] == $currentProfileId) {
        // Encontra a próxima pessoa diferente
        for ($i = 1; $i < count($waiting); $i++) {
            if ($waiting[$i]['profile_id'] != $currentProfileId) {
                // Troca de posição
                $temp = $waiting[0];
                $waiting[0] = $waiting[$i];
                $waiting[$i] = $temp;
                break;
            }
        }
    }
    
    return $waiting;
}

/**
 * Adiciona música à fila
 */
/**
 * Adiciona música à fila
 */
function addToQueue(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    $token = $data['token'] ?? '';
    $songCode = $data['song_code'] ?? '';
    $songTitle = $data['song_title'] ?? '';
    $songArtist = $data['song_artist'] ?? '';
    $eventId = (int)($data['event_id'] ?? 1); // Default to 1
    
    if (empty($token) || empty($songCode)) {
        errorResponse('Token e código da música são obrigatórios');
    }
    
    // Verifica se evento existe e está aberto
    $stmt = $pdo->prepare("SELECT is_open FROM event_settings WHERE id = ?");
    $stmt->execute([$eventId]);
    $eventSettings = $stmt->fetch();
    
    if (!$eventSettings) {
        errorResponse('Evento não encontrado', 404);
    }

    if (!$eventSettings['is_open']) {
        errorResponse('O evento está fechado. Não é possível adicionar músicas à fila no momento.', 403);
    }
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT id, name FROM profiles WHERE token = ?");
    $stmt->execute([$token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 404);
    }
    
    // Verifica se já tem esta música na fila (waiting) deste evento
    $stmt = $pdo->prepare("
        SELECT id FROM queue 
        WHERE profile_id = ? AND song_code = ? AND status = 'waiting' AND event_id = ?
    ");
    $stmt->execute([$profile['id'], $songCode, $eventId]);
    if ($stmt->fetch()) {
        errorResponse('Esta música já está na sua fila');
    }
    
    // Adiciona à fila
    $stmt = $pdo->prepare("
        INSERT INTO queue (profile_id, profile_name, song_code, song_title, song_artist, message, table_number, event_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $profile['id'],
        $profile['name'],
        $songCode,
        $songTitle,
        $songArtist,
        $data['message'] ?? null,
        $data['table_number'] ?? null,
        $eventId
    ]);
    
    // Conta posição na fila deste evento
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE status = 'waiting' AND event_id = ?");
    $stmt->execute([$eventId]);
    $position = $stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'message' => "Adicionado à fila! Posição: #$position",
        'data' => [
            'queue_id' => $pdo->lastInsertId(),
            'position' => (int) $position,
            'event_id' => $eventId
        ]
    ], 201);
}

/**
 * Avança para próxima música
 */
function nextSong(PDO $pdo): void
{
    $today = date('Y-m-d');
    $isMySQL = DB_TYPE === 'mysql';
    
    // Verifica se há música atual para finalizar
    $stmt = $pdo->query("SELECT * FROM queue WHERE status = 'singing' LIMIT 1");
    $currentSinger = $stmt->fetch();
    
    if ($currentSinger) {
        // Finaliza música atual
        $pdo->exec("UPDATE queue SET status = 'done', finished_at = CURRENT_TIMESTAMP WHERE status = 'singing'");
        
        // Registrar no Histórico (NOVO)
        try {
            $stmtHist = $pdo->prepare("
                INSERT INTO song_history (profile_id, song_code, song_title, song_artist, event_id, sung_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmtHist->execute([
                $currentSinger['profile_id'], 
                $currentSinger['song_code'], 
                $currentSinger['song_title'], 
                $currentSinger['song_artist'],
                $currentSinger['event_id'] ?? 1
            ]);

            // Incrementa contador no perfil (Gamificação)
            $pdo->exec("UPDATE profiles SET songs_sung_count = songs_sung_count + 1 WHERE id = " . (int)$currentSinger['profile_id']);
            
            // ==========================================
            // SISTEMA DE PONTOS (Gamificação)
            // ==========================================
            $pointsToAdd = 5; // Pontos base por cantar
            $actionDescription = 'Cantou: ' . $currentSinger['song_title'];
            
            // Verifica se é primeira música do dia (bônus +2)
            $stmtFirst = $pdo->prepare("
                SELECT COUNT(*) FROM user_points 
                WHERE profile_id = ? AND action_type = 'song' AND DATE(created_at) = DATE('now')
            ");
            $stmtFirst->execute([$currentSinger['profile_id']]);
            $songsToday = (int) $stmtFirst->fetchColumn();
            
            if ($songsToday === 0) {
                $pointsToAdd += 2; // Bônus primeira música do dia
                $actionDescription .= ' (🌟 Primeira do dia!)';
            }
            
            // Insere pontos
            $stmtPoints = $pdo->prepare("
                INSERT INTO user_points (profile_id, event_id, points, action_type, description)
                VALUES (?, ?, ?, 'song', ?)
            ");
            $stmtPoints->execute([
                $currentSinger['profile_id'],
                $currentSinger['event_id'] ?? 1,
                $pointsToAdd,
                $actionDescription
            ]);
            
            // Atualiza total de pontos no perfil
            $pdo->exec("UPDATE profiles SET total_points = COALESCE(total_points, 0) + $pointsToAdd WHERE id = " . (int)$currentSinger['profile_id']);
            
            // Verifica e concede badges
            try {
                $stmtBadge = $pdo->prepare("SELECT songs_sung_count FROM profiles WHERE id = ?");
                $stmtBadge->execute([$currentSinger['profile_id']]);
                $songCount = (int) $stmtBadge->fetchColumn();
                
                // Badge: Estreante (primeira música)
                if ($songCount >= 1) {
                    try {
                        $pdo->prepare("INSERT INTO user_badges (profile_id, badge_code, badge_name, badge_icon) VALUES (?, 'starter', 'Estreante', '🎤')")
                            ->execute([$currentSinger['profile_id']]);
                    } catch (Exception $e) { /* Badge já existe */ }
                }
                
                // Badge: Veterano (50+ músicas)
                if ($songCount >= 50) {
                    try {
                        $pdo->prepare("INSERT INTO user_badges (profile_id, badge_code, badge_name, badge_icon) VALUES (?, 'veteran', 'Veterano', '🎵')")
                            ->execute([$currentSinger['profile_id']]);
                    } catch (Exception $e) { /* Badge já existe */ }
                }
            } catch (Exception $e) { error_log("Badge Error: " . $e->getMessage()); }
            
            // ==========================================
            // TRACKING DE USO (Limites do Plano)
            // ==========================================
            try {
                // Busca establishment_id do evento
                $stmtEvent = $pdo->prepare("SELECT establishment_id FROM events WHERE id = ?");
                $stmtEvent->execute([$currentSinger['event_id'] ?? 1]);
                $establishmentId = (int) $stmtEvent->fetchColumn();
                
                if ($establishmentId > 0) {
                    $usageDate = date('Y-m-d');
                    $isMySQL = DB_TYPE === 'mysql';
                    
                    if ($isMySQL) {
                        $pdo->prepare("
                            INSERT INTO usage_logs (establishment_id, usage_date, songs_played) 
                            VALUES (?, ?, 1)
                            ON DUPLICATE KEY UPDATE songs_played = songs_played + 1
                        ")->execute([$establishmentId, $usageDate]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO usage_logs (establishment_id, usage_date, songs_played) 
                            VALUES (?, ?, 1)
                            ON CONFLICT(establishment_id, usage_date) 
                            DO UPDATE SET songs_played = songs_played + 1
                        ")->execute([$establishmentId, $usageDate]);
                    }
                }
            } catch (Exception $e) { error_log("Usage Tracking Error: " . $e->getMessage()); }
            
        } catch (Exception $e) { error_log("History Error: " . $e->getMessage()); }

        // Atualiza estatísticas (Lógica existente)
        if ($isMySQL) {
            $stmt = $pdo->prepare("
                INSERT INTO session_stats (profile_id, session_date, songs_sung, last_sung_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE songs_sung = songs_sung + 1, last_sung_at = NOW()
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO session_stats (profile_id, session_date, songs_sung, last_sung_at)
                VALUES (?, ?, 1, CURRENT_TIMESTAMP)
                ON CONFLICT(profile_id, session_date) 
                DO UPDATE SET songs_sung = songs_sung + 1, last_sung_at = CURRENT_TIMESTAMP
            ");
        }
        $stmt->execute([$currentSinger['profile_id'], $today]);
    }
    
    // Busca próximo da fila (ordenado por prioridade)
    $isMySQL = DB_TYPE === 'mysql';
    
    if ($isMySQL) {
        $waitMinutesSQL = "TIMESTAMPDIFF(MINUTE, q.added_at, NOW())";
    } else {
        $waitMinutesSQL = "CAST((julianday('now') - julianday(q.added_at)) * 24 * 60 AS INTEGER)";
    }
    
    $stmt = $pdo->query("
        SELECT q.*,
               COALESCE(s.songs_sung, 0) as songs_sung_today,
               (($waitMinutesSQL) - COALESCE(s.songs_sung, 0) * 10) as priority
        FROM queue q
        LEFT JOIN session_stats s ON q.profile_id = s.profile_id AND s.session_date = '$today'
        WHERE q.status = 'waiting'
        ORDER BY priority DESC, q.added_at ASC
    ");
    $waiting = $stmt->fetchAll();
    
    if (empty($waiting)) {
        jsonResponse([
            'success' => true,
            'message' => 'Fila vazia'
        ]);
        return;
    }
    
    // Determina próximo ID
    $nextId = $waiting[0]['id'];
    
    // Aplica regra de não repetir (somente se tinha cantor anterior E há mais de 1 pessoa)
    if ($currentSinger && count($waiting) > 1) {
        if ($waiting[0]['profile_id'] == $currentSinger['profile_id']) {
            // Busca próxima pessoa diferente
            for ($i = 1; $i < count($waiting); $i++) {
                if ($waiting[$i]['profile_id'] != $currentSinger['profile_id']) {
                    $nextId = $waiting[$i]['id'];
                    break;
                }
            }
        }
    }
    
    // Inicia próxima música
    $stmt = $pdo->prepare("
        UPDATE queue 
        SET status = 'singing', started_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$nextId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Música iniciada!'
    ]);
}

/**
 * Pula pessoa ausente (move para o final)
 */
function skipSong(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $queueId = $data['queue_id'] ?? $_GET['id'] ?? null;
    
    if (!$queueId) {
        // Pula o atual (se existir)
        $stmt = $pdo->query("SELECT id FROM queue WHERE status = 'singing' LIMIT 1");
        $current = $stmt->fetch();
        $queueId = $current['id'] ?? null;
    }
    
    if (!$queueId) {
        errorResponse('Nenhuma música para pular');
    }
    
    // Move para o final da fila resetando added_at
    $stmt = $pdo->prepare("
        UPDATE queue 
        SET added_at = CURRENT_TIMESTAMP, status = 'waiting', started_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$queueId]);
    
    // Se era o atual, inicia próximo
    nextSong($pdo);
}

/**
 * Remove item da fila
 */
function removeFromQueue(PDO $pdo): void
{
    $queueId = $_GET['id'] ?? null;
    $token = $_GET['token'] ?? null;
    
    if (!$queueId) {
        errorResponse('ID da fila é obrigatório');
    }
    
    // Usuário só pode remover suas próprias músicas
    if ($token && !isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE token = ?");
        $stmt->execute([$token]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            errorResponse('Perfil não encontrado', 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM queue WHERE id = ? AND profile_id = ? AND status = 'waiting'");
        $stmt->execute([$queueId, $profile['id']]);
    } else {
        // Admin pode remover qualquer um
        requireAdmin();
        $stmt = $pdo->prepare("DELETE FROM queue WHERE id = ?");
        $stmt->execute([$queueId]);
    }
    
    jsonResponse(['success' => true, 'message' => 'Removido da fila']);
}

/**
 * Limpa toda a fila
 */
function clearQueue(PDO $pdo): void
{
    $pdo->exec("DELETE FROM queue WHERE status IN ('waiting', 'singing')");
    jsonResponse(['success' => true, 'message' => 'Fila limpa']);
}

/**
 * Reordena item na fila (move para nova posição)
 */
function reorderQueue(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    $queueId = $data['queue_id'] ?? $data['id'] ?? null;
    $newPosition = $data['new_position'] ?? null;
    $direction = $data['direction'] ?? null;
    $token = $data['token'] ?? null;
    
    if (!$queueId || ($newPosition === null && !$direction)) {
        errorResponse('ID e nova posição (ou direção) são obrigatórios');
    }
    
    // Verifica permissão (admin ou próprio usuário)
    $isAdmin = isset($_SESSION['admin_id']);
    $profileId = null;
    
    if (!$isAdmin) {
        if (!$token) {
            errorResponse('Token é obrigatório para usuários', 401);
        }
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE token = ?");
        $stmt->execute([$token]);
        $profile = $stmt->fetch();
        if (!$profile) {
            errorResponse('Perfil não encontrado', 404);
        }
        $profileId = $profile['id'];
    }
    
    // Busca o item a ser movido
    $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ? AND status = 'waiting'");
    $stmt->execute([$queueId]);
    $item = $stmt->fetch();
    
    if (!$item) {
        errorResponse('Item não encontrado na fila', 404);
    }
    
    // Verifica se usuário pode mover este item (deve ser dele se não for admin)
    if (!$isAdmin && $item['profile_id'] != $profileId) {
        errorResponse('Você só pode mover suas próprias músicas', 403);
    }
    
    $eventId = $item['event_id'];
    $today = date('Y-m-d');
    
    $isMySQL = DB_TYPE === 'mysql';
    if ($isMySQL) {
        $waitMinutesSQL = "TIMESTAMPDIFF(MINUTE, q.added_at, NOW())";
    } else {
        $waitMinutesSQL = "CAST((julianday('now') - julianday(q.added_at)) * 24 * 60 AS INTEGER)";
    }
    
    // Busca a ordem real atual
    $stmt = $pdo->prepare("
        SELECT q.id, q.added_at, COALESCE(s.songs_sung, 0) as songs_sung
        FROM queue q
        LEFT JOIN session_stats s ON q.profile_id = s.profile_id AND s.session_date = ?
        WHERE q.status = 'waiting' AND q.event_id = ?
        ORDER BY (($waitMinutesSQL) - COALESCE(s.songs_sung, 0) * 10) DESC, q.added_at ASC
    ");
    $stmt->execute([$today, $eventId]);
    $waiting = $stmt->fetchAll();
    
    $currentIndex = array_search($queueId, array_column($waiting, 'id'));
    if ($currentIndex === false) {
        errorResponse('Falha ao encontrar item na fila', 500);
    }
    
    $targetIndex = null;
    if ($direction === 'up') {
        $targetIndex = $currentIndex - 1;
    } elseif ($direction === 'down') {
        $targetIndex = $currentIndex + 1;
    } elseif ($newPosition !== null) {
        $targetIndex = max(0, min((int)$newPosition, count($waiting) - 1));
        if ($targetIndex === $currentIndex) {
            jsonResponse(['success' => true, 'message' => 'Fila inalterada']);
        }
    }
    
    // Mover apenas se target for válido e diferente
    if ($targetIndex !== null && $targetIndex >= 0 && $targetIndex < count($waiting) && $targetIndex !== $currentIndex) {
        $targetItem = $waiting[$targetIndex];
        $mySung = (int) $waiting[$currentIndex]['songs_sung'];
        $targetSung = (int) $targetItem['songs_sung'];
        
        $isMovingUp = $targetIndex < $currentIndex;
        $timeShift = ($mySung - $targetSung) * 10;
        
        if ($isMovingUp) {
            $timeShift += 1; // 1 min older => higher priority
        } else {
            $timeShift -= 1; // 1 min newer => lower priority
        }
        
        $newTime = date('Y-m-d H:i:s', strtotime($targetItem['added_at']) - ($timeShift * 60));
        
        $stmt = $pdo->prepare("UPDATE queue SET added_at = ? WHERE id = ?");
        $stmt->execute([$newTime, $queueId]);
    }
    
    jsonResponse(['success' => true, 'message' => 'Fila reordenada']);
}

/**
 * Inicia uma música específica imediatamente (admin only)
 */
function playNow(PDO $pdo): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    $queueId = $data['queue_id'] ?? $_GET['id'] ?? null;
    
    if (!$queueId) {
        errorResponse('ID da música é obrigatório');
    }
    
    // Verifica se a música existe na fila
    $stmt = $pdo->prepare("SELECT * FROM queue WHERE id = ? AND status = 'waiting'");
    $stmt->execute([$queueId]);
    $song = $stmt->fetch();
    
    if (!$song) {
        errorResponse('Música não encontrada na fila', 404);
    }
    
    $today = date('Y-m-d');
    
    // Para a música atual (se existir)
    $stmt = $pdo->query("SELECT profile_id FROM queue WHERE status = 'singing' LIMIT 1");
    $currentSinger = $stmt->fetch();
    
    if ($currentSinger) {
        // Finaliza música atual
        $pdo->exec("UPDATE queue SET status = 'done', finished_at = CURRENT_TIMESTAMP WHERE status = 'singing'");
        
        // Atualiza estatísticas
        $stmt = $pdo->prepare("
            INSERT INTO session_stats (profile_id, session_date, songs_sung, last_sung_at)
            VALUES (?, ?, 1, CURRENT_TIMESTAMP)
            ON CONFLICT(profile_id, session_date) 
            DO UPDATE SET songs_sung = songs_sung + 1, last_sung_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$currentSinger['profile_id'], $today]);
    }
    
    // Inicia a música selecionada
    $stmt = $pdo->prepare("
        UPDATE queue 
        SET status = 'singing', started_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$queueId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Música iniciada: ' . $song['song_title']
    ]);
}
