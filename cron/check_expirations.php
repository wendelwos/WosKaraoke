<?php
/**
 * Cron: Verificar Expiração de Assinaturas
 * 
 * Este script deve ser executado diariamente via cron:
 * 0 8 * * * php /path/to/cron/check_expirations.php
 * 
 * Cria notificações para assinaturas que estão expirando em 7, 3 e 1 dia.
 */

require_once __DIR__ . '/../api/config.php';

echo "=== WosKaraoke Expiration Check ===\n";
echo date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDatabase();
    
    // Dias para alertar antes da expiração
    $alertDays = [7, 3, 1];
    $notificationsCreated = 0;
    
    foreach ($alertDays as $days) {
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        
        echo "Verificando assinaturas expirando em {$days} dia(s) ({$targetDate})...\n";
        
        // Busca assinaturas expirando nesta data
        $stmt = $pdo->prepare("
            SELECT 
                s.id as subscription_id,
                s.establishment_id,
                s.current_period_end,
                s.status,
                e.name as establishment_name,
                p.name as plan_name
            FROM subscriptions s
            INNER JOIN establishments e ON e.id = s.establishment_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE s.current_period_end = ?
            AND s.status IN ('active', 'trial')
        ");
        $stmt->execute([$targetDate]);
        $subscriptions = $stmt->fetchAll();
        
        foreach ($subscriptions as $sub) {
            // Verifica se já existe notificação para este alerta
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE establishment_id = ? 
                AND type = 'subscription_expiring'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                AND message LIKE ?
            ");
            $checkStmt->execute([
                $sub['establishment_id'],
                "%{$days} dia%"
            ]);
            
            if ($checkStmt->fetchColumn() > 0) {
                echo "  - Notificação já existe para {$sub['establishment_name']}, pulando...\n";
                continue;
            }
            
            // Cria notificação
            $title = $days === 1 
                ? "⚠️ Sua assinatura expira amanhã!"
                : "⏰ Sua assinatura expira em {$days} dias";
                
            $message = $sub['status'] === 'trial'
                ? "Seu período de teste do plano {$sub['plan_name']} termina em {$days} dia(s). Faça a assinatura para continuar usando todos os recursos."
                : "Sua assinatura do plano {$sub['plan_name']} expira em {$days} dia(s). Renove agora para não perder o acesso.";
            
            $insertStmt = $pdo->prepare("
                INSERT INTO notifications (establishment_id, type, title, message, action_url)
                VALUES (?, 'subscription_expiring', ?, ?, '/establishment/billing.php')
            ");
            $insertStmt->execute([
                $sub['establishment_id'],
                $title,
                $message
            ]);
            
            $notificationsCreated++;
            echo "  ✓ Notificação criada para {$sub['establishment_name']}\n";
        }
    }
    
    // Expira assinaturas vencidas
    echo "\nExpirando assinaturas vencidas...\n";
    $expireStmt = $pdo->prepare("
        UPDATE subscriptions 
        SET status = 'expired' 
        WHERE current_period_end < CURDATE() 
        AND status IN ('active', 'trial')
    ");
    $expireStmt->execute();
    $expired = $expireStmt->rowCount();
    
    if ($expired > 0) {
        echo "  ✓ {$expired} assinatura(s) expirada(s)\n";
        
        // Reverte para plano free
        $pdo->exec("
            UPDATE establishments e
            INNER JOIN subscriptions s ON e.id = s.establishment_id
            SET e.subscription_plan = 'free', e.subscription_expires_at = NULL
            WHERE s.status = 'expired' 
            AND s.current_period_end < CURDATE()
        ");
    }
    
    echo "\n=== Resumo ===\n";
    echo "Notificações criadas: {$notificationsCreated}\n";
    echo "Assinaturas expiradas: {$expired}\n";
    echo "=== Concluído ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
