<?php
/**
 * Super Admin - Statistics/Dashboard API
 * 
 * GET /api/superadmin/stats.php - Estatísticas globais
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/middleware.php';

try {
    $pdo = getDatabase();
    
    // Verificar autenticação
    $admin = requireSuperAdmin();
    
    $today = date('Y-m-d');
    
    // Total de estabelecimentos
    $totalEstablishments = $pdo->query("SELECT COUNT(*) FROM establishments")->fetchColumn();
    $activeEstablishments = $pdo->query("SELECT COUNT(*) FROM establishments WHERE is_active = 1")->fetchColumn();
    
    // Total de KJs
    $totalKjs = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $activeKjs = $pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1")->fetchColumn();
    
    // Eventos ativos (is_open = 1)
    $activeEvents = $pdo->query("SELECT COUNT(*) FROM event_settings WHERE is_open = 1")->fetchColumn();
    
    // Total de usuários (profiles)
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM profiles")->fetchColumn();
    
    // Músicas cantadas hoje
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM song_history WHERE DATE(sung_at) = ?");
    $stmt->execute([$today]);
    $songsToday = $stmt->fetchColumn();
    
    // Músicas cantadas total
    $totalSongs = $pdo->query("SELECT COUNT(*) FROM song_history")->fetchColumn();
    
    // Últimos estabelecimentos criados
    $stmt = $pdo->query("SELECT id, name, slug, is_active, created_at FROM establishments ORDER BY created_at DESC LIMIT 5");
    $recentEstablishments = $stmt->fetchAll();
    
    // Últimos KJs criados
    $stmt = $pdo->query("
        SELECT a.id, a.name, a.username, a.is_active, a.created_at, e.name as establishment_name
        FROM admins a
        LEFT JOIN establishments e ON a.establishment_id = e.id
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $recentKjs = $stmt->fetchAll();
    
    // Planos de assinatura
    $stmt = $pdo->query("
        SELECT subscription_plan, COUNT(*) as count 
        FROM establishments 
        GROUP BY subscription_plan
    ");
    $planStats = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'totals' => [
                'establishments' => (int)$totalEstablishments,
                'establishments_active' => (int)$activeEstablishments,
                'kjs' => (int)$totalKjs,
                'kjs_active' => (int)$activeKjs,
                'events_active' => (int)$activeEvents,
                'users' => (int)$totalUsers,
                'songs_today' => (int)$songsToday,
                'songs_total' => (int)$totalSongs
            ],
            'recent_establishments' => $recentEstablishments,
            'recent_kjs' => $recentKjs,
            'plan_distribution' => $planStats
        ]
    ]);

} catch (Exception $e) {
    error_log("Stats API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}
