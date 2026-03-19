<?php
/**
 * CLI para executar migrações
 *
 * Uso:
 *   php database/migrate.php          # executa pendentes
 *   php database/migrate.php status   # mostra status
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Env.php';
require_once __DIR__ . '/../includes/Helpers.php';
require_once __DIR__ . '/../includes/MigrationRunner.php';

WosKaraoke\Env::load(__DIR__ . '/../.env');

// DB constants needed by Helpers::isMySQL()
define('DB_TYPE', WosKaraoke\Env::get('DB_TYPE', 'sqlite'));
define('DB_HOST', WosKaraoke\Env::get('DB_HOST', 'localhost'));
define('DB_NAME', WosKaraoke\Env::get('DB_NAME', 'woskaraoke'));
define('DB_USER', WosKaraoke\Env::get('DB_USER', 'root'));
define('DB_PASS', WosKaraoke\Env::get('DB_PASS', ''));
define('DATA_PATH', dirname(__DIR__) . '/data');
define('DB_FILE', DATA_PATH . '/karaoke.db');

$command = $argv[1] ?? 'run';

// Connect
if (DB_TYPE === 'mysql') {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} else {
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

$runner = new WosKaraoke\MigrationRunner($pdo);

switch ($command) {
    case 'status':
        echo "Migration Status:\n";
        echo str_repeat('-', 50) . "\n";
        foreach ($runner->status() as $m) {
            $icon = $m['status'] === 'ran' ? '[OK]' : '[--]';
            echo "  {$icon} {$m['migration']}\n";
        }
        break;

    case 'run':
    default:
        echo "Running pending migrations...\n";
        try {
            $executed = $runner->runPending();
            if (empty($executed)) {
                echo "Nothing to migrate.\n";
            } else {
                foreach ($executed as $name) {
                    echo "  Migrated: {$name}\n";
                }
                echo "Done. " . count($executed) . " migration(s) executed.\n";
            }
        } catch (Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;
}
