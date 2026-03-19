<?php
/**
 * API de Batalhas/Duelos - WosKaraoke
 * 
 * GET /api/battle.php?event_id=X - Lista batalha ativa
 * POST /api/battle.php?action=vote - Votar (body: {battle_id, vote_for, token})
 * POST /api/battle.php?action=create - Criar batalha (Admin)
 * POST /api/battle.php?action=start - Iniciar batalha (Admin)
 * POST /api/battle.php?action=finish - Finalizar batalha (Admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $pdo = getDatabase();

    switch ($method) {
        case 'GET':
            getActiveBattle($pdo);
            break;
            
        case 'POST':
            if ($action === 'vote') {
                vote($pdo);
            } elseif ($action === 'create') {
                createBattle($pdo);
            } elseif ($action === 'start') {
                startBattle($pdo);
            } elseif ($action === 'finish') {
                finishBattle($pdo);
            } else {
                errorResponse('Ação inválida', 400);
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Battle API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Retorna batalha ativa do evento
 */
function getActiveBattle(PDO $pdo): void
{
    $eventId = (int)($_GET['event_id'] ?? 1);
    
    $stmt = $pdo->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM battle_votes WHERE battle_id = b.id AND voted_for = b.contestant1_id) as votes1,
               (SELECT COUNT(*) FROM battle_votes WHERE battle_id = b.id AND voted_for = b.contestant2_id) as votes2
        FROM battles b
        WHERE b.event_id = ? AND b.status IN ('waiting', 'active')
        ORDER BY b.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $battle = $stmt->fetch();
    
    if (!$battle) {
        jsonResponse([
            'success' => true,
            'has_battle' => false
        ]);
        return;
    }
    
    // Verifica se usuário já votou (se token fornecido)
    $token = $_GET['token'] ?? '';
    $hasVoted = false;
    $votedFor = null;
    
    if ($token) {
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE token = ?");
        $stmt->execute([$token]);
        $profile = $stmt->fetch();
        
        if ($profile) {
            $stmt = $pdo->prepare("SELECT voted_for FROM battle_votes WHERE battle_id = ? AND voter_id = ?");
            $stmt->execute([$battle['id'], $profile['id']]);
            $vote = $stmt->fetch();
            if ($vote) {
                $hasVoted = true;
                $votedFor = (int)$vote['voted_for'];
            }
        }
    }
    
    jsonResponse([
        'success' => true,
        'has_battle' => true,
        'data' => [
            'id' => (int)$battle['id'],
            'status' => $battle['status'],
            'contestant1' => [
                'id' => (int)$battle['contestant1_id'],
                'name' => $battle['contestant1_name'],
                'song' => $battle['contestant1_song'],
                'votes' => (int)$battle['votes1']
            ],
            'contestant2' => [
                'id' => (int)$battle['contestant2_id'],
                'name' => $battle['contestant2_name'],
                'song' => $battle['contestant2_song'],
                'votes' => (int)$battle['votes2']
            ],
            'has_voted' => $hasVoted,
            'voted_for' => $votedFor,
            'created_at' => $battle['created_at'],
            'started_at' => $battle['started_at']
        ]
    ]);
}

/**
 * Votar em um competidor
 */
function vote(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $battleId = (int)($input['battle_id'] ?? 0);
    $voteFor = (int)($input['vote_for'] ?? 0);
    $token = $input['token'] ?? '';
    
    if (!$battleId || !$voteFor || empty($token)) {
        errorResponse('Dados incompletos');
    }
    
    // Busca perfil
    $stmt = $pdo->prepare("SELECT id FROM profiles WHERE token = ?");
    $stmt->execute([$token]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        errorResponse('Perfil não encontrado', 401);
    }
    
    // Verifica se batalha está ativa
    $stmt = $pdo->prepare("SELECT * FROM battles WHERE id = ? AND status = 'active'");
    $stmt->execute([$battleId]);
    $battle = $stmt->fetch();
    
    if (!$battle) {
        errorResponse('Batalha não encontrada ou não está ativa');
    }
    
    // Verifica se vote_for é válido
    if ($voteFor != $battle['contestant1_id'] && $voteFor != $battle['contestant2_id']) {
        errorResponse('Voto inválido');
    }
    
    // Tenta inserir voto (constraint UNIQUE previne duplicatas)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO battle_votes (battle_id, voter_id, voted_for)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$battleId, $profile['id'], $voteFor]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Voto registrado!'
        ]);
    } catch (Exception $e) {
        errorResponse('Você já votou nesta batalha');
    }
}

/**
 * Criar nova batalha (Admin)
 */
function createBattle(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    // TODO: Verificar autenticação de admin
    
    $eventId = (int)($input['event_id'] ?? 1);
    $c1Id = (int)($input['contestant1_id'] ?? 0);
    $c1Name = trim($input['contestant1_name'] ?? '');
    $c1Song = trim($input['contestant1_song'] ?? '');
    $c2Id = (int)($input['contestant2_id'] ?? 0);
    $c2Name = trim($input['contestant2_name'] ?? '');
    $c2Song = trim($input['contestant2_song'] ?? '');
    
    if (!$c1Name || !$c2Name) {
        errorResponse('Nomes dos competidores são obrigatórios');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO battles (event_id, contestant1_id, contestant1_name, contestant1_song, contestant2_id, contestant2_name, contestant2_song)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$eventId, $c1Id, $c1Name, $c1Song, $c2Id, $c2Name, $c2Song]);
    
    $battleId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'battle_id' => (int)$battleId,
        'message' => 'Batalha criada!'
    ], 201);
}

/**
 * Iniciar batalha (Admin)
 */
function startBattle(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $battleId = (int)($input['battle_id'] ?? 0);
    
    if (!$battleId) {
        errorResponse('ID da batalha é obrigatório');
    }
    
    $stmt = $pdo->prepare("
        UPDATE battles 
        SET status = 'active', started_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND status = 'waiting'
    ");
    $stmt->execute([$battleId]);
    
    if ($stmt->rowCount() === 0) {
        errorResponse('Batalha não encontrada ou já iniciada');
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Batalha iniciada! Votação aberta.'
    ]);
}

/**
 * Finalizar batalha e declarar vencedor (Admin)
 */
function finishBattle(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $battleId = (int)($input['battle_id'] ?? 0);
    
    if (!$battleId) {
        errorResponse('ID da batalha é obrigatório');
    }
    
    // Conta votos
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM battle_votes WHERE battle_id = ? AND voted_for = b.contestant1_id) as votes1,
            (SELECT COUNT(*) FROM battle_votes WHERE battle_id = ? AND voted_for = b.contestant2_id) as votes2,
            b.contestant1_id, b.contestant1_name,
            b.contestant2_id, b.contestant2_name
        FROM battles b WHERE b.id = ?
    ");
    $stmt->execute([$battleId, $battleId, $battleId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        errorResponse('Batalha não encontrada');
    }
    
    // Determina vencedor
    $winnerId = null;
    $winnerName = '';
    if ($result['votes1'] > $result['votes2']) {
        $winnerId = $result['contestant1_id'];
        $winnerName = $result['contestant1_name'];
    } elseif ($result['votes2'] > $result['votes1']) {
        $winnerId = $result['contestant2_id'];
        $winnerName = $result['contestant2_name'];
    }
    
    // Atualiza batalha
    $stmt = $pdo->prepare("
        UPDATE battles 
        SET status = 'finished', winner_id = ?, finished_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->execute([$winnerId, $battleId]);
    
    // ==========================================
    // SISTEMA DE PONTOS - Vitória em Batalha
    // ==========================================
    if ($winnerId) {
        // Busca event_id da batalha
        $stmtEvent = $pdo->prepare("SELECT event_id FROM battles WHERE id = ?");
        $stmtEvent->execute([$battleId]);
        $eventId = $stmtEvent->fetchColumn();
        
        // Adiciona pontos (+7 por vitória)
        $pointsToAdd = 7;
        try {
            $stmtPoints = $pdo->prepare("
                INSERT INTO user_points (profile_id, event_id, points, action_type, description)
                VALUES (?, ?, ?, 'battle_win', ?)
            ");
            $stmtPoints->execute([
                $winnerId,
                $eventId ?: null,
                $pointsToAdd,
                "🏆 Venceu batalha contra " . ($winnerId == $result['contestant1_id'] ? $result['contestant2_name'] : $result['contestant1_name'])
            ]);
            
            // Atualiza total de pontos
            $pdo->exec("UPDATE profiles SET total_points = COALESCE(total_points, 0) + $pointsToAdd WHERE id = " . (int)$winnerId);
            
            // Concede badge Gladiador
            try {
                $pdo->prepare("INSERT INTO user_badges (profile_id, badge_code, badge_name, badge_icon) VALUES (?, 'gladiator', 'Gladiador', '⚔️')")
                    ->execute([$winnerId]);
            } catch (Exception $e) { /* Badge já existe */ }
            
        } catch (Exception $e) {
            error_log("Battle Points Error: " . $e->getMessage());
        }
    }
    
    jsonResponse([
        'success' => true,
        'winner_id' => $winnerId,
        'winner_name' => $winnerName ?: 'Empate!',
        'votes1' => (int)$result['votes1'],
        'votes2' => (int)$result['votes2'],
        'message' => $winnerId ? "🏆 Vencedor: {$winnerName}! (+7 pontos)" : '🤝 Empate!'
    ]);
}
