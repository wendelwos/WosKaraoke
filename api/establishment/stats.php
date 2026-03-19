<?php
/**
 * API de Estatísticas para Estabelecimentos
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

$pdo = getDatabase();
$establishment = requireEstablishment();
$establishmentId = $establishment['id'];

// Contar KJs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE establishment_id = ?");
$stmt->execute([$establishmentId]);
$totalKjs = $stmt->fetchColumn();

// KJs ativos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE establishment_id = ? AND is_active = 1");
$stmt->execute([$establishmentId]);
$activeKjs = $stmt->fetchColumn();

// Eventos ativos
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM event_settings 
    WHERE establishment_id = ? AND is_open = 1
");
$stmt->execute([$establishmentId]);
$activeEvents = $stmt->fetchColumn();

// Total de eventos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM event_settings WHERE establishment_id = ?");
$stmt->execute([$establishmentId]);
$totalEvents = $stmt->fetchColumn();

// Músicas hoje (de todos os KJs do estabelecimento)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM queue q
    JOIN event_settings e ON q.event_id = e.id
    WHERE e.establishment_id = ? AND DATE(q.added_at) = CURRENT_DATE
");
$stmt->execute([$establishmentId]);
$songsToday = $stmt->fetchColumn();

// Total de músicas
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM queue q
    JOIN event_settings e ON q.event_id = e.id
    WHERE e.establishment_id = ?
");
$stmt->execute([$establishmentId]);
$totalSongs = $stmt->fetchColumn();

// Últimos KJs
$stmt = $pdo->prepare("
    SELECT id, name, username, is_active, last_login
    FROM admins 
    WHERE establishment_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$establishmentId]);
$recentKjs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Eventos recentes
$stmt = $pdo->prepare("
    SELECT e.id, e.event_name, e.event_code, e.is_open, a.name as kj_name
    FROM event_settings e
    LEFT JOIN admins a ON e.admin_id = a.id
    WHERE e.establishment_id = ?
    ORDER BY e.created_at DESC
    LIMIT 5
");
$stmt->execute([$establishmentId]);
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse([
    'success' => true,
    'stats' => [
        'total_kjs' => (int)$totalKjs,
        'active_kjs' => (int)$activeKjs,
        'total_events' => (int)$totalEvents,
        'active_events' => (int)$activeEvents,
        'songs_today' => (int)$songsToday,
        'total_songs' => (int)$totalSongs
    ],
    'recent_kjs' => $recentKjs,
    'recent_events' => $recentEvents
]);
