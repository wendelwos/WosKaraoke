<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id {$intPK} PRIMARY KEY {$autoInc},
            user_type VARCHAR(20) NOT NULL,
            user_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            target_table VARCHAR(50) DEFAULT NULL,
            target_id INT DEFAULT NULL,
            old_data JSON DEFAULT NULL,
            new_data JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_audit_user', 'audit_logs', 'user_type, user_id');
    Helpers::createIndex($pdo, 'idx_audit_action', 'audit_logs', 'action');
    Helpers::createIndex($pdo, 'idx_audit_date', 'audit_logs', 'created_at');
};
