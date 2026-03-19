<?php

use WosKaraoke\Helpers;

return function (PDO $pdo): void {
    $intPK = Helpers::intPK();
    $autoInc = Helpers::autoIncrement();
    $engine = Helpers::engineSuffix();

    // Battles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS battles (
            id {$intPK} PRIMARY KEY {$autoInc},
            event_id INT NOT NULL,
            contestant1_id INT NOT NULL,
            contestant1_name VARCHAR(255) NOT NULL,
            contestant1_song VARCHAR(255) NOT NULL,
            contestant2_id INT NOT NULL,
            contestant2_name VARCHAR(255) NOT NULL,
            contestant2_song VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'waiting',
            winner_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME DEFAULT NULL,
            finished_at DATETIME DEFAULT NULL
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_battles_event', 'battles', 'event_id');
    Helpers::createIndex($pdo, 'idx_battles_status', 'battles', 'status');

    // Battle Votes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS battle_votes (
            id {$intPK} PRIMARY KEY {$autoInc},
            battle_id INT NOT NULL,
            voter_id INT NOT NULL,
            voted_for INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(battle_id, voter_id)
        ){$engine}
    ");
    Helpers::createIndex($pdo, 'idx_battle_votes', 'battle_votes', 'battle_id');
};
