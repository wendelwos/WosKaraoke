<?php
/**
 * API de Assinaturas - WosKaraoke Billing
 * 
 * GET  /api/billing/subscription.php?establishment_id=X  - Ver assinatura atual
 * POST /api/billing/subscription.php                     - Criar/atualizar assinatura
 * PUT  /api/billing/subscription.php?action=cancel       - Cancelar assinatura
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/PlanLimits.php';

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            getSubscription($pdo);
            break;
            
        case 'POST':
            createOrUpdateSubscription($pdo);
            break;
            
        case 'PUT':
            $action = $_GET['action'] ?? '';
            if ($action === 'cancel') {
                cancelSubscription($pdo);
            } else {
                errorResponse('Ação inválida', 400);
            }
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Subscription API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Obtém assinatura atual de um estabelecimento
 */
function getSubscription(PDO $pdo): void
{
    $establishmentId = (int) ($_GET['establishment_id'] ?? 0);
    
    if ($establishmentId <= 0) {
        errorResponse('ID do estabelecimento obrigatório', 400);
    }
    
    // Busca assinatura ativa
    $stmt = $pdo->prepare("
        SELECT s.*, p.code as plan_code, p.name as plan_name, p.price_monthly, 
               p.max_events, p.max_songs_per_day, p.max_kjs, p.features
        FROM subscriptions s
        JOIN plans p ON s.plan_id = p.id
        WHERE s.establishment_id = ? AND s.status IN ('active', 'trial')
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$establishmentId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        // Retorna plano Free como padrão
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE code = 'free'");
        $stmt->execute();
        $freePlan = $stmt->fetch();
        
        jsonResponse([
            'success' => true,
            'data' => [
                'has_subscription' => false,
                'plan' => [
                    'code' => 'free',
                    'name' => $freePlan['name'] ?? 'Gratuito',
                    'max_events' => (int) ($freePlan['max_events'] ?? 1),
                    'max_songs_per_day' => (int) ($freePlan['max_songs_per_day'] ?? 30),
                    'max_kjs' => (int) ($freePlan['max_kjs'] ?? 1),
                    'features' => json_decode($freePlan['features'] ?? '{}', true)
                ],
                'is_trial' => false,
                'trial_days_left' => 0
            ]
        ]);
        return;
    }
    
    // Calcula dias restantes do trial
    $trialDaysLeft = 0;
    if ($subscription['status'] === 'trial' && $subscription['trial_ends_at']) {
        $trialEnd = new DateTime($subscription['trial_ends_at']);
        $now = new DateTime();
        $trialDaysLeft = max(0, $trialEnd->diff($now)->days);
    }
    
    // Calcula dias até expiração
    $periodEnd = new DateTime($subscription['current_period_end']);
    $now = new DateTime();
    $daysUntilExpiration = $periodEnd->diff($now)->days;
    $isExpired = $periodEnd < $now;
    
    // Busca uso atual
    $limits = new PlanLimits($pdo);
    $usage = $limits->getUsageToday($establishmentId);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'has_subscription' => true,
            'subscription' => [
                'id' => (int) $subscription['id'],
                'status' => $subscription['status'],
                'billing_cycle' => $subscription['billing_cycle'],
                'current_period_start' => $subscription['current_period_start'],
                'current_period_end' => $subscription['current_period_end'],
                'days_until_expiration' => $daysUntilExpiration,
                'is_expired' => $isExpired
            ],
            'plan' => [
                'code' => $subscription['plan_code'],
                'name' => $subscription['plan_name'],
                'price_monthly' => (float) $subscription['price_monthly'],
                'max_events' => (int) $subscription['max_events'],
                'max_songs_per_day' => (int) $subscription['max_songs_per_day'],
                'max_kjs' => (int) $subscription['max_kjs'],
                'features' => json_decode($subscription['features'] ?? '{}', true)
            ],
            'usage' => $usage,
            'is_trial' => $subscription['status'] === 'trial',
            'trial_days_left' => $trialDaysLeft
        ]
    ]);
}

/**
 * Cria ou atualiza assinatura
 */
function createOrUpdateSubscription(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $establishmentId = (int) ($input['establishment_id'] ?? 0);
    $planCode = $input['plan_code'] ?? '';
    $billingCycle = $input['billing_cycle'] ?? 'monthly';
    $isTrial = (bool) ($input['is_trial'] ?? false);
    
    if ($establishmentId <= 0 || empty($planCode)) {
        errorResponse('Estabelecimento e plano são obrigatórios', 400);
    }
    
    // Busca o plano
    $stmt = $pdo->prepare("SELECT id, code FROM plans WHERE code = ? AND is_active = 1");
    $stmt->execute([$planCode]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        errorResponse('Plano não encontrado', 404);
    }
    
    // Cancela assinaturas anteriores
    $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE establishment_id = ? AND status IN ('active', 'trial')")
        ->execute([$establishmentId]);
    
    // Calcula período
    $now = new DateTime();
    $periodStart = $now->format('Y-m-d');
    
    if ($isTrial) {
        $periodEnd = (clone $now)->modify('+14 days')->format('Y-m-d');
        $trialEndsAt = $periodEnd;
        $status = 'trial';
    } else {
        $periodEnd = $billingCycle === 'yearly' 
            ? (clone $now)->modify('+1 year')->format('Y-m-d')
            : (clone $now)->modify('+1 month')->format('Y-m-d');
        $trialEndsAt = null;
        $status = 'active';
    }
    
    // Cria nova assinatura
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (establishment_id, plan_id, status, billing_cycle, current_period_start, current_period_end, trial_ends_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $establishmentId,
        $plan['id'],
        $status,
        $billingCycle,
        $periodStart,
        $periodEnd,
        $trialEndsAt
    ]);
    
    // Atualiza o plano na tabela establishments
    $pdo->prepare("UPDATE establishments SET subscription_plan = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$planCode, $periodEnd, $establishmentId]);
    
    jsonResponse([
        'success' => true,
        'message' => $isTrial ? 'Trial de 14 dias ativado!' : 'Assinatura ativada com sucesso!',
        'data' => [
            'subscription_id' => $pdo->lastInsertId(),
            'plan_code' => $planCode,
            'period_end' => $periodEnd
        ]
    ], 201);
}

/**
 * Cancela uma assinatura
 */
function cancelSubscription(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $establishmentId = (int) ($input['establishment_id'] ?? 0);
    
    if ($establishmentId <= 0) {
        errorResponse('ID do estabelecimento obrigatório', 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE subscriptions 
        SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP 
        WHERE establishment_id = ? AND status IN ('active', 'trial')
    ");
    $stmt->execute([$establishmentId]);
    
    // Volta para plano free
    $pdo->prepare("UPDATE establishments SET subscription_plan = 'free', subscription_expires_at = NULL WHERE id = ?")
        ->execute([$establishmentId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Assinatura cancelada. Você voltou para o plano gratuito.'
    ]);
}
