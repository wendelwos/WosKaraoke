<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();
    $isMySQL = Helpers::isMySQL();

    // Plans
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plans (
            id {$intPK} PRIMARY KEY {$autoInc},
            code VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
            price_yearly DECIMAL(10,2) DEFAULT NULL,
            max_events INT DEFAULT 1,
            max_songs_per_day INT DEFAULT 30,
            max_kjs INT DEFAULT 1,
            features TEXT DEFAULT NULL,
            is_active TINYINT DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");

    // Default plans
    $count = (int) $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO plans (code, name, price_monthly, price_yearly, max_events, max_songs_per_day, max_kjs, features, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['free', 'Gratuito', 0, null, 1, 30, 1, '{"analytics":false,"api":false,"support":"community","watermark":true}', 1]);
        $stmt->execute(['starter', 'Starter', 49.00, 470.00, 3, 100, 2, '{"analytics":true,"api":false,"support":"email","watermark":false}', 2]);
        $stmt->execute(['pro', 'Profissional', 99.00, 950.00, 10, 999999, 5, '{"analytics":true,"api":false,"support":"priority","watermark":false}', 3]);
        $stmt->execute(['enterprise', 'Enterprise', 299.00, 2870.00, 999999, 999999, 999999, '{"analytics":true,"api":true,"support":"dedicated","watermark":false,"whitelabel":true}', 4]);
    }

    // Subscriptions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id {$intPK} PRIMARY KEY {$autoInc},
            establishment_id INT NOT NULL,
            plan_id INT NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            billing_cycle VARCHAR(20) DEFAULT 'monthly',
            current_period_start DATE NOT NULL,
            current_period_end DATE NOT NULL,
            trial_ends_at DATE DEFAULT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_subscriptions_establishment', 'subscriptions', 'establishment_id');
    Helpers::createIndex($pdo, 'idx_subscriptions_status', 'subscriptions', 'status');

    // Invoices
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id {$intPK} PRIMARY KEY {$autoInc},
            subscription_id INT NOT NULL,
            establishment_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            due_date DATE NOT NULL,
            paid_at DATETIME DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            pdf_url TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_invoices_establishment', 'invoices', 'establishment_id');
    Helpers::createIndex($pdo, 'idx_invoices_status', 'invoices', 'status');

    // Usage Logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usage_logs (
            id {$intPK} PRIMARY KEY {$autoInc},
            establishment_id INT NOT NULL,
            usage_date DATE NOT NULL,
            songs_played INT DEFAULT 0,
            events_created INT DEFAULT 0
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_usage_establishment_date', 'usage_logs', 'establishment_id, usage_date', true);
};
