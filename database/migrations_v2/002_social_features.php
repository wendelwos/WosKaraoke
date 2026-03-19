<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();

    // Song History
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS song_history (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            song_code VARCHAR(50) NOT NULL,
            song_title VARCHAR(255) NOT NULL,
            song_artist VARCHAR(255) NOT NULL,
            event_id INT DEFAULT 1,
            sung_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_history_profile', 'song_history', 'profile_id');
    Helpers::createIndex($pdo, 'idx_history_event', 'song_history', 'event_id');

    // Social login & gamification columns on profiles
    Helpers::addColumn($pdo, 'profiles', 'google_id', 'VARCHAR(255) DEFAULT NULL');
    Helpers::addColumn($pdo, 'profiles', 'facebook_id', 'VARCHAR(255) DEFAULT NULL');
    Helpers::addColumn($pdo, 'profiles', 'email', 'VARCHAR(255) DEFAULT NULL');
    Helpers::addColumn($pdo, 'profiles', 'avatar_url', 'TEXT DEFAULT NULL');
    Helpers::addColumn($pdo, 'profiles', 'songs_sung_count', 'INT DEFAULT 0');
    Helpers::addColumn($pdo, 'profiles', 'level', 'INT DEFAULT 1');

    // Announcements
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcements (
            id {$intPK} PRIMARY KEY {$autoInc},
            message TEXT NOT NULL,
            type VARCHAR(20) DEFAULT 'info',
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL
        ){$engine}
    ");
};
