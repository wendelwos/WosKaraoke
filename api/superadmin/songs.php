<?php
/**
 * API SuperAdmin - Músicas
 * 
 * GET ?action=stats - Estatísticas de músicas
 * GET ?action=lists - Listar listas de músicas
 * GET ?action=songs&list_id=X - Listar músicas de uma lista
 * POST - Importar nova lista (futuro)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

// Verifica autenticação SuperAdmin
requireSuperAdmin();

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'stats';

    switch ($method) {
        case 'GET':
            if ($action === 'stats') {
                getSongStats($pdo);
            } elseif ($action === 'lists') {
                getSongLists($pdo);
            } elseif ($action === 'songs') {
                getSongs($pdo);
            } else {
                getSongStats($pdo);
            }
            break;

        case 'POST':
            // Futuro: importar lista
            errorResponse('Importação de lista ainda não implementada', 501);
            break;

        case 'DELETE':
            // Futuro: deletar lista
            errorResponse('Remoção de lista ainda não implementada', 501);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Throwable $e) {
    error_log("SuperAdmin Songs API Error: " . $e->getMessage());
    errorResponse('Erro: ' . $e->getMessage(), 500);
}

/**
 * Estatísticas de músicas
 */
function getSongStats(PDO $pdo): void
{
    // Total de músicas
    $stmt = $pdo->query("SELECT COUNT(*) FROM songs");
    $totalSongs = (int) $stmt->fetchColumn();

    // Total de listas (se tabela existir)
    $totalLists = 1;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM song_lists");
        $totalLists = (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        // Tabela pode não existir ainda
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'total_songs' => $totalSongs,
            'total_lists' => $totalLists,
            'searches_today' => '-' // Futuro: implementar contagem
        ]
    ]);
}

/**
 * Listar listas de músicas
 */
function getSongLists(PDO $pdo): void
{
    $lists = [];
    
    // Tentar buscar da tabela song_lists
    try {
        $stmt = $pdo->query("
            SELECT id, name, description, is_default, is_active, song_count, created_at
            FROM song_lists
            ORDER BY is_default DESC, name
        ");
        $lists = $stmt->fetchAll();
    } catch (Exception $e) {
        // Se tabela não existir, retorna lista padrão simulada
        $stmt = $pdo->query("SELECT COUNT(*) FROM songs");
        $count = (int) $stmt->fetchColumn();
        
        $lists = [[
            'id' => 1,
            'name' => 'Lista Padrão',
            'description' => 'Repertório principal',
            'is_default' => 1,
            'is_active' => 1,
            'song_count' => $count,
            'created_at' => date('Y-m-d H:i:s')
        ]];
    }

    jsonResponse([
        'success' => true,
        'data' => $lists
    ]);
}

/**
 * Buscar músicas
 */
function getSongs(PDO $pdo): void
{
    $listId = (int) ($_GET['list_id'] ?? 0);
    $search = trim($_GET['search'] ?? '');
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    $params = [];
    $where = [];

    if (!empty($search)) {
        $where[] = "(code LIKE :search1 OR title LIKE :search2 OR artist LIKE :search3)";
        $params['search1'] = "%{$search}%";
        $params['search2'] = "%{$search}%";
        $params['search3'] = "%{$search}%";
    }

    $whereClause = '';
    if (!empty($where)) {
        $whereClause = 'WHERE ' . implode(' AND ', $where);
    }

    // Contar total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM songs {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Buscar
    $sql = "SELECT id, code, title, artist, category FROM songs {$whereClause} ORDER BY title LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue(":{$key}", $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $songs = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => $songs,
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}
