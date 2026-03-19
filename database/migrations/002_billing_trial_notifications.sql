-- Karaoke Show - Atualizações de Schema para Billing v2
-- Execute este script no banco de dados

-- 1. Adiciona coluna trial_ends_at na tabela subscriptions (se não existir)
ALTER TABLE subscriptions 
ADD COLUMN IF NOT EXISTS trial_ends_at DATE NULL AFTER current_period_end;

-- 2. Cria tabela de notificações
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    establishment_id INT NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'trial_ending, subscription_expiring, payment_failed, etc',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    action_url VARCHAR(500) COMMENT 'URL para ação (ex: /billing.php)',
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_establishment (establishment_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Adiciona índice para verificação de expiração
CREATE INDEX IF NOT EXISTS idx_subscription_expires 
ON subscriptions(current_period_end, status);

-- 4. Log de execução
SELECT 'Schema atualizado com sucesso!' as message;
