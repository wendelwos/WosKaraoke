<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();

    // User Points
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_points (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            event_id INT DEFAULT NULL,
            points INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_userpoints_profile', 'user_points', 'profile_id');
    Helpers::createIndex($pdo, 'idx_userpoints_event', 'user_points', 'event_id');
    Helpers::createIndex($pdo, 'idx_userpoints_created', 'user_points', 'created_at');

    // User Badges
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_badges (
            id {$intPK} PRIMARY KEY {$autoInc},
            profile_id INT NOT NULL,
            badge_code VARCHAR(50) NOT NULL,
            badge_name VARCHAR(100) NOT NULL,
            badge_icon VARCHAR(10) DEFAULT NULL,
            earned_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_userbadges_unique', 'user_badges', 'profile_id, badge_code', true);
    Helpers::createIndex($pdo, 'idx_userbadges_profile', 'user_badges', 'profile_id');

    // Total points column on profiles
    Helpers::addColumn($pdo, 'profiles', 'total_points', 'INT DEFAULT 0');
};
