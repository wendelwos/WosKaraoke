<?php
/**
 * TrialManager - Gerenciador de Trial Gratuito
 *
 * Gerencia o periodo de trial de 14 dias para novos estabelecimentos.
 */

declare(strict_types=1);

namespace WosKaraoke;

class TrialManager
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Inicia trial gratuito para um estabelecimento
     */
    public function startTrial(int $establishmentId, string $planCode = 'starter'): bool
    {
        if ($this->hasUsedTrial($establishmentId)) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM plans WHERE code = ? AND is_active = 1");
        $stmt->execute([$planCode]);
        $plan = $stmt->fetch();

        if (!$plan) {
            return false;
        }

        $now = new \DateTime();
        $trialDays = MercadoPagoConfig::getInstance()->getTrialDays();
        $periodStart = $now->format('Y-m-d');
        $trialEndsAt = (clone $now)->modify("+{$trialDays} days")->format('Y-m-d');

        $this->pdo->prepare("
            UPDATE subscriptions
            SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP
            WHERE establishment_id = ? AND status IN ('active', 'trial')
        ")->execute([$establishmentId]);

        $stmt = $this->pdo->prepare("
            INSERT INTO subscriptions
            (establishment_id, plan_id, status, billing_cycle, current_period_start, current_period_end, trial_ends_at)
            VALUES (?, ?, 'trial', 'monthly', ?, ?, ?)
        ");
        $stmt->execute([$establishmentId, $plan['id'], $periodStart, $trialEndsAt, $trialEndsAt]);

        $this->pdo->prepare("
            UPDATE establishments
            SET subscription_plan = ?, subscription_expires_at = ?
            WHERE id = ?
        ")->execute([$planCode, $trialEndsAt, $establishmentId]);

        return true;
    }

    /**
     * Retorna quantos dias restam do trial
     */
    public function getTrialDaysRemaining(int $establishmentId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT trial_ends_at
            FROM subscriptions
            WHERE establishment_id = ? AND status = 'trial'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$establishmentId]);
        $subscription = $stmt->fetch();

        if (!$subscription || !$subscription['trial_ends_at']) {
            return 0;
        }

        $now = new \DateTime();
        $trialEnd = new \DateTime($subscription['trial_ends_at']);

        if ($now > $trialEnd) {
            return 0;
        }

        return (int) $now->diff($trialEnd)->days;
    }

    /**
     * Verifica se o estabelecimento esta em periodo de trial
     */
    public function isInTrial(int $establishmentId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id, trial_ends_at
            FROM subscriptions
            WHERE establishment_id = ? AND status = 'trial'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$establishmentId]);
        $subscription = $stmt->fetch();

        if (!$subscription) {
            return false;
        }

        $now = new \DateTime();
        $trialEnd = new \DateTime($subscription['trial_ends_at']);

        return $now <= $trialEnd;
    }

    /**
     * Verifica se o estabelecimento ja usou o trial
     */
    public function hasUsedTrial(int $establishmentId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM subscriptions
            WHERE establishment_id = ? AND trial_ends_at IS NOT NULL
        ");
        $stmt->execute([$establishmentId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Expira trials vencidos
     */
    public function expireTrials(): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE subscriptions
            SET status = 'expired'
            WHERE status = 'trial' AND trial_ends_at < CURDATE()
        ");
        $stmt->execute();

        $count = $stmt->rowCount();

        $this->pdo->exec("
            UPDATE establishments e
            INNER JOIN subscriptions s ON e.id = s.establishment_id
            SET e.subscription_plan = 'free'
            WHERE s.status = 'expired' AND s.trial_ends_at IS NOT NULL
        ");

        return $count;
    }
}
