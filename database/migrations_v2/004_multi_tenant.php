<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();
    $isMySQL = Helpers::isMySQL();

    // Establishments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS establishments (
            id {$intPK} PRIMARY KEY {$autoInc},
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(255),
            logo_url TEXT,
            is_active TINYINT DEFAULT 1,
            max_kjs INT DEFAULT 5,
            subscription_plan VARCHAR(50) DEFAULT 'free',
            subscription_expires_at DATE,
            password_hash TEXT DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");

    // Super Admins
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS super_admins (
            id {$intPK} PRIMARY KEY {$autoInc},
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name VARCHAR(255) NOT NULL,
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        ){$engine}
    ");

    // Default super admin
    $count = (int) $pdo->query("SELECT COUNT(*) FROM super_admins")->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO super_admins (username, email, password_hash, name) VALUES (?, ?, ?, ?)")
            ->execute(['superadmin', 'admin@woskaraoke.com', $hash, 'Super Administrador']);
    }

    // Multi-tenant columns on admins
    Helpers::addColumn($pdo, 'admins', 'establishment_id', 'INT DEFAULT NULL');
    Helpers::addColumn($pdo, 'admins', 'role', "VARCHAR(20) DEFAULT 'kj'");
    Helpers::addColumn($pdo, 'admins', 'is_active', 'TINYINT DEFAULT 1');
    Helpers::addColumn($pdo, 'admins', 'avatar_url', 'TEXT DEFAULT NULL');

    Helpers::createIndex($pdo, 'idx_admins_establishment', 'admins', 'establishment_id');
};
