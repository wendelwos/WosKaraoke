<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();

    // Profiles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profiles (
            id {$intPK} PRIMARY KEY {$autoInc},
            name VARCHAR(255) NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            password_hash TEXT DEFAULT NULL,
            avatar_color VARCHAR(20) DEFAULT '#6366f1',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");

    // Favorites
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS favorites (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            song_code VARCHAR(50) NOT NULL,
            song_title VARCHAR(255) NOT NULL,
            song_artist VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(profile_id, song_code)
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_favorites_profile', 'favorites', 'profile_id');

    // Admins
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id {$intPK} PRIMARY KEY {$autoInc},
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            password_hash TEXT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        ){$engine}
    ");

    // Password Resets
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id {$intPK} PRIMARY KEY {$autoInc},
            admin_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            used INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_password_resets_token', 'password_resets', 'token');

    // Queue
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            profile_name VARCHAR(255) NOT NULL,
            song_code VARCHAR(50) NOT NULL,
            song_title VARCHAR(255) NOT NULL,
            song_artist VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'waiting',
            message TEXT DEFAULT NULL,
            table_number VARCHAR(10) DEFAULT NULL,
            event_id INT DEFAULT 1,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            finished_at DATETIME
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_queue_status', 'queue', 'status');
    Helpers::createIndex($pdo, 'idx_queue_profile', 'queue', 'profile_id');
    Helpers::createIndex($pdo, 'idx_queue_event', 'queue', 'event_id');

    // Session Stats
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS session_stats (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            session_date DATE NOT NULL,
            songs_sung INT DEFAULT 0,
            last_sung_at DATETIME,
            UNIQUE(profile_id, session_date)
        ){$engine}
    ");

    // Event Settings
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_settings (
            id {$intPK} PRIMARY KEY {$autoInc},
            event_code VARCHAR(20) DEFAULT '1234',
            is_open TINYINT DEFAULT 1,
            event_name VARCHAR(255) DEFAULT 'Karaoke',
            admin_id INT DEFAULT NULL,
            establishment_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_events_admin', 'event_settings', 'admin_id');
    Helpers::createIndex($pdo, 'idx_events_establishment', 'event_settings', 'establishment_id');

    // Default event
    $count = (int) $pdo->query("SELECT COUNT(*) FROM event_settings")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO event_settings (id, event_code, is_open, event_name) VALUES (1, '1234', 1, 'Karaoke')");
    }
};
