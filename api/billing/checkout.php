<?php
/**
 * Mercado Pago Checkout - WosKaraoke Billing
 * 
 * Cria preferência de pagamento para assinatura de plano
 * 
 * POST /api/billing/checkout.php
 * Body: { establishment_id, plan_code, billing_cycle }
 * 
 * Documentação: https://www.mercadopago.com.br/developers/pt/reference/preferences/_checkout_preferences/post
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../config/mercadopago.php';

// Configuração carregada de config/mercadopago.php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Método não permitido', 405);
    }
    
    $pdo = getDatabase();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $establishmentId = (int) ($input['establishment_id'] ?? 0);
    $planCode = $input['plan_code'] ?? '';
    $billingCycle = $input['billing_cycle'] ?? 'monthly';
    
    if ($establishmentId <= 0 || empty($planCode)) {
        errorResponse('Estabelecimento e plano são obrigatórios', 400);
    }
    
    // Busca o plano
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE code = ? AND is_active = 1");
    $stmt->execute([$planCode]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        errorResponse('Plano não encontrado', 404);
    }
    
    // Plano gratuito não precisa de checkout
    if ((float) $plan['price_monthly'] <= 0) {
        // Ativa plano gratuito diretamente
        activateFreePlan($pdo, $establishmentId, $plan);
        jsonResponse([
            'success' => true,
            'message' => 'Plano gratuito ativado!',
            'data' => ['redirect' => false]
        ]);
        return;
    }
    
    // Busca dados do estabelecimento
    $stmt = $pdo->prepare("SELECT id, name, email FROM establishments WHERE id = ?");
    $stmt->execute([$establishmentId]);
    $establishment = $stmt->fetch();
    
    if (!$establishment) {
        errorResponse('Estabelecimento não encontrado', 404);
    }
    
    // Calcula valor
    $price = $billingCycle === 'yearly' && $plan['price_yearly'] 
        ? (float) $plan['price_yearly'] 
        : (float) $plan['price_monthly'];
    
    $description = "WosKaraoke - Plano {$plan['name']}";
    if ($billingCycle === 'yearly') {
        $description .= ' (Anual)';
    }
    
    // Cria preferência no Mercado Pago
    $preferenceData = [
        'items' => [
            [
                'id' => $plan['code'],
                'title' => $description,
                'description' => "Assinatura do plano {$plan['name']} para {$establishment['name']}",
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => $price
            ]
        ],
        'payer' => [
            'name' => $establishment['name'],
            'email' => $establishment['email'] ?? 'cliente@woskaraoke.com'
        ],
        'external_reference' => json_encode([
            'establishment_id' => $establishmentId,
            'plan_code' => $planCode,
            'billing_cycle' => $billingCycle
        ]),
        'statement_descriptor' => 'WOSKARAOKE'
    ];
    
    // Adiciona back_urls apenas se não for localhost (MP não aceita localhost)
    $successUrl = getMPCallbackUrl('success');
    if (!empty($successUrl)) {
        $preferenceData['back_urls'] = [
            'success' => $successUrl,
            'failure' => getMPCallbackUrl('failure'),
            'pending' => getMPCallbackUrl('pending')
        ];
        $preferenceData['auto_return'] = 'approved';
        $preferenceData['notification_url'] = getMPCallbackUrl('webhook');
    }

    
    $preference = createMercadoPagoPreference($preferenceData);
    
    if (isset($preference['error'])) {
        error_log("MP Error: " . json_encode($preference));
        errorResponse('Erro ao criar pagamento: ' . ($preference['message'] ?? 'Erro desconhecido'), 500);
    }
    
    // Salva referência na invoice
    $periodEnd = $billingCycle === 'yearly' 
        ? date('Y-m-d', strtotime('+1 year'))
        : date('Y-m-d', strtotime('+1 month'));
    
    $stmt = $pdo->prepare("
        INSERT INTO invoices (subscription_id, establishment_id, amount, status, due_date, external_id, notes)
        VALUES (0, ?, ?, 'pending', ?, ?, ?)
    ");
    $stmt->execute([
        $establishmentId,
        $price,
        date('Y-m-d', strtotime('+7 days')),
        $preference['id'] ?? null,
        "Plano: {$plan['name']} | Ciclo: $billingCycle"
    ]);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'preference_id' => $preference['id'],
            'init_point' => $preference['init_point'],           // URL do checkout
            'sandbox_init_point' => $preference['sandbox_init_point'] ?? null, // URL sandbox
            'redirect' => true
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Checkout API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Cria preferência de pagamento no Mercado Pago
 */
function createMercadoPagoPreference(array $data): array
{
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getMPAccessToken(),
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('wos_', true)
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['error' => true, 'message' => $error];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 400) {
        return [
            'error' => true, 
            'message' => $result['message'] ?? 'Erro HTTP ' . $httpCode,
            'cause' => $result['cause'] ?? []
        ];
    }
    
    return $result;
}

/**
 * Ativa plano gratuito diretamente (sem pagamento)
 */
function activateFreePlan(PDO $pdo, int $establishmentId, array $plan): void
{
    $now = new DateTime();
    $periodStart = $now->format('Y-m-d');
    $periodEnd = (clone $now)->modify('+1 month')->format('Y-m-d');
    
    // Cancela assinaturas anteriores
    $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE establishment_id = ? AND status IN ('active', 'trial')")
        ->execute([$establishmentId]);
    
    // Cria nova assinatura
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (establishment_id, plan_id, status, billing_cycle, current_period_start, current_period_end)
        VALUES (?, ?, 'active', 'monthly', ?, ?)
    ");
    $stmt->execute([$establishmentId, $plan['id'], $periodStart, $periodEnd]);
    
    // Atualiza estabelecimento
    $pdo->prepare("UPDATE establishments SET subscription_plan = 'free', subscription_expires_at = NULL WHERE id = ?")
        ->execute([$establishmentId]);
}
