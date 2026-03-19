<?php
/**
 * Mercado Pago Webhook (IPN) - WosKaraoke Billing
 * 
 * Recebe notificações do Mercado Pago sobre status de pagamentos
 * 
 * POST /api/billing/webhook.php
 * 
 * Tipos de notificação:
 * - payment: pagamento criado/atualizado
 * - merchant_order: pedido do vendedor
 * 
 * Documentação: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../config/mercadopago.php';

// Token do Mercado Pago (centralizado)
$mpAccessToken = getMPAccessToken();

// Log de webhooks para debug
function logWebhook(string $message, array $data = []): void
{
    $logFile = __DIR__ . '/../../logs/mp_webhook.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | $message | " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    // Mercado Pago envia POST com dados da notificação
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // GET usado para validação inicial
        http_response_code(200);
        echo 'OK';
        exit;
    }
    
    $pdo = getDatabase();
    
    // Lê o corpo da requisição
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    // Parâmetros da query string (notificação antiga)
    $topic = $_GET['topic'] ?? $data['type'] ?? '';
    $id = $_GET['id'] ?? $data['data']['id'] ?? '';
    
    logWebhook('Webhook received', ['topic' => $topic, 'id' => $id, 'body' => $data]);
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID não informado']);
        exit;
    }
    
    // Processa baseado no tipo de notificação
    if ($topic === 'payment' || $data['type'] === 'payment') {
        processPayment($pdo, $id);
    } elseif ($topic === 'merchant_order') {
        processMerchantOrder($pdo, $id);
    } else {
        logWebhook('Unknown topic', ['topic' => $topic]);
    }
    
    // Sempre responde 200 OK para o Mercado Pago
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    logWebhook('Webhook Error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Processa notificação de pagamento
 */
function processPayment(PDO $pdo, string $paymentId): void
{
    // Busca detalhes do pagamento na API do Mercado Pago
    $payment = getMercadoPagoPayment($paymentId);
    
    if (!$payment || isset($payment['error'])) {
        logWebhook('Failed to get payment', ['id' => $paymentId, 'response' => $payment]);
        return;
    }
    
    logWebhook('Payment details', $payment);
    
    $status = $payment['status'] ?? '';
    $externalReference = $payment['external_reference'] ?? '';
    $preferenceId = $payment['preference_id'] ?? '';
    
    // Decodifica referência externa (contém dados da assinatura)
    $reference = json_decode($externalReference, true);
    
    if (!$reference || empty($reference['establishment_id'])) {
        logWebhook('Invalid external reference', ['ref' => $externalReference]);
        return;
    }
    
    $establishmentId = (int) $reference['establishment_id'];
    $planCode = $reference['plan_code'] ?? '';
    $billingCycle = $reference['billing_cycle'] ?? 'monthly';
    
    // Atualiza fatura correspondente
    $stmt = $pdo->prepare("
        UPDATE invoices 
        SET status = ?, 
            external_id = ?,
            payment_method = ?,
            paid_at = CASE WHEN ? = 'paid' THEN CURRENT_TIMESTAMP ELSE paid_at END
        WHERE establishment_id = ? AND status = 'pending'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    
    $invoiceStatus = mapPaymentStatus($status);
    $stmt->execute([
        $invoiceStatus,
        $paymentId,
        $payment['payment_method_id'] ?? null,
        $invoiceStatus,
        $establishmentId
    ]);
    
    // Se pagamento aprovado, ativa a assinatura
    if ($status === 'approved') {
        activateSubscription($pdo, $establishmentId, $planCode, $billingCycle);
        logWebhook('Subscription activated', [
            'establishment_id' => $establishmentId,
            'plan' => $planCode
        ]);
    }
}

/**
 * Processa notificação de merchant order
 */
function processMerchantOrder(PDO $pdo, string $orderId): void
{
    $order = getMercadoPagoMerchantOrder($orderId);
    
    if (!$order || isset($order['error'])) {
        logWebhook('Failed to get merchant order', ['id' => $orderId]);
        return;
    }
    
    logWebhook('Merchant order details', $order);
    
    // Verifica se todos os pagamentos estão aprovados
    $payments = $order['payments'] ?? [];
    $allApproved = true;
    $totalPaid = 0;
    
    foreach ($payments as $payment) {
        if ($payment['status'] !== 'approved') {
            $allApproved = false;
        } else {
            $totalPaid += $payment['transaction_amount'];
        }
    }
    
    if ($allApproved && $totalPaid >= ($order['total_amount'] ?? 0)) {
        // Pedido totalmente pago
        $reference = json_decode($order['external_reference'] ?? '', true);
        
        if ($reference && !empty($reference['establishment_id'])) {
            activateSubscription(
                $pdo,
                (int) $reference['establishment_id'],
                $reference['plan_code'] ?? '',
                $reference['billing_cycle'] ?? 'monthly'
            );
        }
    }
}

/**
 * Busca detalhes de pagamento na API do Mercado Pago
 */
function getMercadoPagoPayment(string $paymentId): ?array
{
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments/$paymentId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getMPAccessToken()
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}

/**
 * Busca detalhes de merchant order na API do Mercado Pago
 */
function getMercadoPagoMerchantOrder(string $orderId): ?array
{
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.mercadopago.com/merchant_orders/$orderId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getMPAccessToken()
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}

/**
 * Mapeia status do MP para status da invoice
 */
function mapPaymentStatus(string $mpStatus): string
{
    $map = [
        'approved' => 'paid',
        'pending' => 'pending',
        'in_process' => 'pending',
        'rejected' => 'failed',
        'cancelled' => 'failed',
        'refunded' => 'refunded',
        'charged_back' => 'refunded'
    ];
    
    return $map[$mpStatus] ?? 'pending';
}

/**
 * Ativa assinatura após pagamento aprovado
 */
function activateSubscription(PDO $pdo, int $establishmentId, string $planCode, string $billingCycle): void
{
    // Busca o plano
    $stmt = $pdo->prepare("SELECT id, code FROM plans WHERE code = ?");
    $stmt->execute([$planCode]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        logWebhook('Plan not found', ['code' => $planCode]);
        return;
    }
    
    // Cancela assinaturas anteriores
    $pdo->prepare("UPDATE subscriptions SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE establishment_id = ? AND status IN ('active', 'trial')")
        ->execute([$establishmentId]);
    
    // Calcula período
    $now = new DateTime();
    $periodStart = $now->format('Y-m-d');
    $periodEnd = $billingCycle === 'yearly' 
        ? (clone $now)->modify('+1 year')->format('Y-m-d')
        : (clone $now)->modify('+1 month')->format('Y-m-d');
    
    // Cria nova assinatura
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (establishment_id, plan_id, status, billing_cycle, current_period_start, current_period_end)
        VALUES (?, ?, 'active', ?, ?, ?)
    ");
    $stmt->execute([$establishmentId, $plan['id'], $billingCycle, $periodStart, $periodEnd]);
    
    // Atualiza estabelecimento
    $pdo->prepare("UPDATE establishments SET subscription_plan = ?, subscription_expires_at = ? WHERE id = ?")
        ->execute([$planCode, $periodEnd, $establishmentId]);
    
    logWebhook('Subscription created', [
        'establishment_id' => $establishmentId,
        'plan' => $planCode,
        'expires' => $periodEnd
    ]);
}
