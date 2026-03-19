<?php
/**
 * API de Uso - WosKaraoke Billing
 * 
 * GET /api/billing/usage.php?establishment_id=X  - Retorna uso atual e limites
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PlanLimits.php';

try {
    $pdo = getDatabase();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        errorResponse('Método não permitido', 405);
    }
    
    $establishmentId = (int) ($_GET['establishment_id'] ?? 0);
    
    if ($establishmentId <= 0) {
        errorResponse('ID do estabelecimento obrigatório', 400);
    }
    
    $limits = new PlanLimits($pdo);
    $planLimits = $limits->getPlanLimits($establishmentId);
    $usage = $limits->getUsageToday($establishmentId);
    
    // Calcula porcentagens de uso
    $songsPercent = $planLimits['max_songs_per_day'] > 0 
        ? min(100, ($usage['songs_today'] / $planLimits['max_songs_per_day']) * 100)
        : 0;
    
    $eventsPercent = $planLimits['max_events'] > 0
        ? min(100, ($usage['active_events'] / $planLimits['max_events']) * 100)
        : 0;
    
    $kjsPercent = $planLimits['max_kjs'] > 0
        ? min(100, ($usage['active_kjs'] / $planLimits['max_kjs']) * 100)
        : 0;
    
    // Verifica se está perto do limite (acima de 80%)
    $warnings = [];
    if ($songsPercent >= 80) {
        $warnings[] = "Você usou {$usage['songs_today']} de {$planLimits['max_songs_per_day']} músicas hoje";
    }
    if ($eventsPercent >= 80 && $planLimits['max_events'] < 999) {
        $warnings[] = "Você tem {$usage['active_events']} de {$planLimits['max_events']} eventos ativos";
    }
    if ($kjsPercent >= 80 && $planLimits['max_kjs'] < 999) {
        $warnings[] = "Você tem {$usage['active_kjs']} de {$planLimits['max_kjs']} KJs cadastrados";
    }
    
    // Busca assinatura ativa
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as plan_name, p.price_monthly
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.establishment_id = ? AND s.status IN ('active', 'trial')
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$establishmentId]);
    $subscription = $stmt->fetch();
    
    // Calcula dias restantes
    $daysRemaining = 0;
    $isExpiringSoon = false;
    if ($subscription) {
        $periodEnd = new DateTime($subscription['current_period_end']);
        $now = new DateTime();
        $daysRemaining = max(0, $periodEnd->diff($now)->days);
        $isExpiringSoon = $daysRemaining <= 7;
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'plan' => [
                'code' => $planLimits['code'],
                'name' => $subscription['plan_name'] ?? 'Gratuito',
                'features' => $planLimits['features']
            ],
            'limits' => [
                'songs_per_day' => $planLimits['max_songs_per_day'],
                'max_events' => $planLimits['max_events'],
                'max_kjs' => $planLimits['max_kjs']
            ],
            'usage' => [
                'songs_today' => $usage['songs_today'],
                'active_events' => $usage['active_events'],
                'active_kjs' => $usage['active_kjs']
            ],
            'percentages' => [
                'songs' => round($songsPercent, 1),
                'events' => round($eventsPercent, 1),
                'kjs' => round($kjsPercent, 1)
            ],
            'subscription' => $subscription ? [
                'status' => $subscription['status'],
                'billing_cycle' => $subscription['billing_cycle'],
                'period_end' => $subscription['current_period_end'],
                'days_remaining' => $daysRemaining,
                'is_expiring_soon' => $isExpiringSoon,
                'is_trial' => $subscription['status'] === 'trial'
            ] : null,
            'warnings' => $warnings,
            'should_show_watermark' => $limits->shouldShowWatermark($establishmentId)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Usage API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}
