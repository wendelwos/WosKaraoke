<?php
/**
 * Configuracao central do sistema WosKaraoke
 * Suporta MySQL (producao) e SQLite (desenvolvimento)
 *
 * @version 3.0 - Migracao via MigrationRunner (nao roda DDL a cada request)
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('America/Sao_Paulo');

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Env.php';
require_once __DIR__ . '/../includes/Helpers.php';
require_once __DIR__ . '/../includes/MigrationRunner.php';

WosKaraoke\Env::load();

// Constantes do sistema
define('BASE_PATH', dirname(__DIR__));
define('DATA_PATH', BASE_PATH . '/data');
define('REPERTORIO_PATH', BASE_PATH . '/repertorio');
define('CACHE_FILE', DATA_PATH . '/songs_cache.json');

// Banco de dados (via .env)
define('DB_TYPE', WosKaraoke\Env::get('DB_TYPE', 'sqlite'));
define('DB_HOST', WosKaraoke\Env::get('DB_HOST', 'localhost'));
define('DB_NAME', WosKaraoke\Env::get('DB_NAME', 'woskaraoke'));
define('DB_USER', WosKaraoke\Env::get('DB_USER', 'root'));
define('DB_PASS', WosKaraoke\Env::get('DB_PASS', ''));
define('DB_FILE', DATA_PATH . '/karaoke.db');

// Credenciais externas (via .env)
define('GOOGLE_CLIENT_ID', WosKaraoke\Env::get('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', WosKaraoke\Env::get('GOOGLE_CLIENT_SECRET', ''));

define('PUSHER_APP_ID', WosKaraoke\Env::get('PUSHER_APP_ID', ''));
define('PUSHER_KEY', WosKaraoke\Env::get('PUSHER_KEY', ''));
define('PUSHER_SECRET', WosKaraoke\Env::get('PUSHER_SECRET', ''));
define('PUSHER_CLUSTER', WosKaraoke\Env::get('PUSHER_CLUSTER', 'sa1'));

define('APP_DEBUG', WosKaraoke\Env::bool('APP_DEBUG', false));

// Headers CORS para API
header('Content-Type: application/json; charset=utf-8');

$allowedOrigins = array_filter(array_map('trim', explode(',', WosKaraoke\Env::get('CORS_ORIGINS', '*'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Conexao com banco de dados.
 * Na primeira chamada, executa migracoes pendentes (custo: 1 SELECT se nada pendente).
 */
function getDatabase(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

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

    // Run pending migrations (lightweight check: 1 SELECT when nothing pending)
    try {
        $runner = new WosKaraoke\MigrationRunner($pdo);
        $runner->runPending();
    } catch (Throwable $e) {
        error_log('Migration error: ' . $e->getMessage());
        if (APP_DEBUG) {
            throw $e;
        }
    }

    return $pdo;
}

/**
 * Resposta JSON padronizada
 */
function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Resposta de erro
 */
function errorResponse(string $message, int $code = 400): never
{
    jsonResponse(['success' => false, 'error' => $message], $code);
}

/**
 * Validacao de entrada
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Gera token unico para perfil
 */
function generateToken(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Gera cor aleatoria para avatar
 */
function getRandomAvatarColor(): string
{
    $colors = [
        '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
        '#ec4899', '#f43f5e', '#ef4444', '#f97316',
        '#f59e0b', '#eab308', '#84cc16', '#22c55e',
        '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
        '#3b82f6',
    ];
    return $colors[array_rand($colors)];
}

/**
 * Obtem iniciais do nome
 */
function getInitials(string $name): string
{
    $words = explode(' ', trim($name));
    $initials = '';

    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }

    return $initials ?: 'U';
}
