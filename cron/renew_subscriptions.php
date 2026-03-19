<?php
/**
 * Cron: Renovação Automática de Assinaturas
 * 
 * Este script deve ser executado diariamente via cron:
 * 0 6 * * * php /path/to/cron/renew_subscriptions.php
 * 
 * Processa renovações para assinaturas com pagamento recorrente.
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../config/mercadopago.php';

echo "=== WosKaraoke Subscription Renewal ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDatabase();
    
    // Busca assinaturas que expiram hoje e precisam renovar
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            e.name as establishment_name,
            e.email as establishment_email,
            p.code as plan_code,
            p.name as plan_name,
            p.price_monthly,
            p.price_yearly
        FROM subscriptions s
        INNER JOIN establishments e ON e.id = s.establishment_id
        INNER JOIN plans p ON p.id = s.plan_id
        WHERE s.current_period_end = CURDATE()
        AND s.status = 'active'
        AND p.price_monthly > 0
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll();
    
    echo "Encontradas " . count($subscriptions) . " assinatura(s) para renovar.\n\n";
    
    $renewed = 0;
    $created_invoices = 0;
    
    foreach ($subscriptions as $sub) {
        echo "Processando: {$sub['establishment_name']} ({$sub['plan_name']})...\n";
        
        // Calcula próximo período
        $billingCycle = $sub['billing_cycle'] ?? 'monthly';
        $price = $billingCycle === 'yearly' && $sub['price_yearly']
            ? (float) $sub['price_yearly']
            : (float) $sub['price_monthly'];
            
        $nextPeriodEnd = $billingCycle === 'yearly'
            ? date('Y-m-d', strtotime('+1 year'))
            : date('Y-m-d', strtotime('+1 month'));
        
        // Cria nova fatura para cobrança
        $invoiceStmt = $pdo->prepare("
            INSERT INTO invoices 
            (subscription_id, establishment_id, amount, status, due_date, notes)
            VALUES (?, ?, ?, 'pending', ?, ?)
        ");
        $invoiceStmt->execute([
            $sub['id'],
            $sub['establishment_id'],
            $price,
            date('Y-m-d', strtotime('+3 days')), // Vencimento em 3 dias
            "Renovação automática - {$sub['plan_name']} ({$billingCycle})"
        ]);
        
        $invoiceId = $pdo->lastInsertId();
        $created_invoices++;
        
        // Estende temporariamente a assinatura (período de graça de 7 dias)
        $gracePeriodEnd = date('Y-m-d', strtotime('+7 days'));
        
        $updateStmt = $pdo->prepare("
            UPDATE subscriptions 
            SET current_period_end = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$gracePeriodEnd, $sub['id']]);
        
        // Cria notificação
        $notifyStmt = $pdo->prepare("
            INSERT INTO notifications (establishment_id, type, title, message, action_url)
            VALUES (?, 'payment_due', ?, ?, '/establishment/billing.php')
        ");
        $notifyStmt->execute([
            $sub['establishment_id'],
            "💳 Renovação pendente",
            "Sua assinatura do plano {$sub['plan_name']} precisa ser renovada. Valor: R$ " . number_format($price, 2, ',', '.') . ". Efetue o pagamento para continuar usando todos os recursos."
        ]);
        
        echo "  ✓ Fatura #{$invoiceId} criada (R$ " . number_format($price, 2, ',', '.') . ")\n";
        echo "  ✓ Período de graça até {$gracePeriodEnd}\n";
        
        $renewed++;
    }
    
    echo "\n=== Resumo ===\n";
    echo "Assinaturas processadas: {$renewed}\n";
    echo "Faturas criadas: {$created_invoices}\n";
    echo "=== Concluído ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
