<?php
/**
 * API de Analytics - Dashboard do KJ
 * 
 * GET /api/admin/analytics.php?event_id=X - Estatísticas do evento
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Configuração de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica autenticação admin
if (!isset($_SESSION['admin_id'])) {
    errorResponse('Acesso não autorizado', 401);
}

try {
    $pdo = getDatabase();
    // event_id ignorado - a tabela queue atual não tem essa coluna
    $period = $_GET['period'] ?? 'today'; // today, week, month, all

    // Define filtro de data (queue usa added_at, não created_at)
    $dateFilter = match($period) {
        'today' => "DATE(q.added_at) = CURDATE()",
        'week' => "q.added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        'month' => "q.added_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        default => "1=1"
    };

    // ============================================
    // 1. ESTATÍSTICAS GERAIS
    // ============================================
    $stats = [];

    // Total de músicas cantadas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM queue q 
        WHERE q.status = 'finished' AND $dateFilter
    ");
    $stmt->execute();
    $stats['total_songs'] = (int) $stmt->fetchColumn();

    // Pessoas únicas
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT profile_id) as total 
        FROM queue q 
        WHERE $dateFilter
    ");
    $stmt->execute();
    $stats['unique_singers'] = (int) $stmt->fetchColumn();

    // Na fila agora
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM queue 
        WHERE status = 'waiting'
    ");
    $stmt->execute();
    $stats['in_queue'] = (int) $stmt->fetchColumn();

    // Tempo médio na fila (minutos)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, q.added_at, q.finished_at)) as avg_wait
        FROM queue q 
        WHERE q.status = 'finished' AND q.finished_at IS NOT NULL AND $dateFilter
    ");
    $stmt->execute();
    $stats['avg_wait_minutes'] = round((float) ($stmt->fetchColumn() ?: 0), 1);

    // ============================================
    // 2. TOP 10 MÚSICAS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            song_code,
            song_title,
            song_artist,
            COUNT(*) as play_count
        FROM queue q
        WHERE $dateFilter
        GROUP BY song_code, song_title, song_artist
        ORDER BY play_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topSongs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // 3. CANTORES FREQUENTES
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.avatar_color,
            COUNT(q.id) as song_count
        FROM queue q
        JOIN profiles p ON q.profile_id = p.id
        WHERE $dateFilter
        GROUP BY p.id, p.name, p.avatar_color
        ORDER BY song_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topSingers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona iniciais
    foreach ($topSingers as &$singer) {
        $singer['initials'] = getInitials($singer['name']);
    }

    // ============================================
    // 4. HORÁRIOS DE PICO (últimos 7 dias)
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(added_at) as hour,
            COUNT(*) as count
        FROM queue
        WHERE added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(added_at)
        ORDER BY hour
    ");
    $stmt->execute();
    $hourlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preenche horas faltantes
    $peakHours = array_fill(0, 24, 0);
    foreach ($hourlyData as $row) {
        $peakHours[(int) $row['hour']] = (int) $row['count'];
    }

    // ============================================
    // 5. ÚLTIMOS 7 DIAS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            DATE(added_at) as date,
            COUNT(*) as count
        FROM queue
        WHERE added_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(added_at)
        ORDER BY date
    ");
    $stmt->execute();
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'top_songs' => $topSongs,
            'top_singers' => $topSingers,
            'peak_hours' => $peakHours,
            'daily_trend' => $dailyData,
            'period' => $period
        ]
    ]);

} catch (Exception $e) {
    error_log("Analytics API Error: " . $e->getMessage());
    errorResponse('Erro ao buscar estatísticas: ' . $e->getMessage(), 500);
}

// Nota: getInitials() é definida em config.php

