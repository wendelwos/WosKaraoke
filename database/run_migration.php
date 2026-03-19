<?php
/**
 * Executa migrações de banco de dados
 * 
 * php database/run_migration.php
 */

require_once __DIR__ . '/../api/config.php';

echo "=== Karaoke Show Database Migration ===\n\n";

try {
    $pdo = getDatabase();
    
    // 1. Adiciona coluna trial_ends_at
    echo "1. Adicionando coluna trial_ends_at...\n";
    try {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN trial_ends_at DATE NULL");
        echo "   ✓ Coluna adicionada!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "   - Coluna já existe, pulando...\n";
        } else {
            throw $e;
        }
    }
    
    // 2. Cria tabela notifications
    echo "2. Criando tabela notifications...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            establishment_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            action_url VARCHAR(500),
            is_read TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX idx_establishment (establishment_id),
            INDEX idx_type (type),
            FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "   ✓ Tabela criada!\n";
    
    echo "\n=== Migração concluída com sucesso! ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
