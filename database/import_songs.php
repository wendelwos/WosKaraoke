<?php
/**
 * Script de Importação de Músicas
 * URL: https://seu-dominio.com/database/import_songs.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(300);

// Credenciais do Hostinger
$dbHost = 'localhost';
$dbName = 'u728238878_karaoke';
$dbUser = 'u728238878_admin';
$dbPass = 'w0sK@raoke';

echo "<h1>🎤 Karaoke Show - Importação de Músicas</h1>";
echo "<pre>";

try {
    // Conectar ao banco
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Conectado ao banco de dados\n";

    // Verificar se tabela songs existe e criar se necessário
    $stmt = $pdo->query("SHOW TABLES LIKE 'songs'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE songs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                code VARCHAR(20) UNIQUE,
                title VARCHAR(255) NOT NULL,
                artist VARCHAR(255),
                category VARCHAR(100),
                INDEX idx_code (code),
                INDEX idx_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ Tabela 'songs' criada\n";
    }

    // Verificar se já tem músicas
    $stmt = $pdo->query("SELECT COUNT(*) FROM songs");
    $existingCount = (int) $stmt->fetchColumn();
    
    if ($existingCount > 0) {
        echo "⚠️ Tabela já contém {$existingCount} músicas.\n";
        echo "Para reimportar, execute no phpMyAdmin: TRUNCATE TABLE songs;\n";
        echo "</pre>";
        exit;
    }

    // TENTAR MÚLTIPLOS CAMINHOS para o cache
    $possiblePaths = [
        __DIR__ . '/../data/songs_cache.json',
        $_SERVER['DOCUMENT_ROOT'] . '/data/songs_cache.json',
        dirname(__DIR__) . '/data/songs_cache.json',
        '/home/u728238878/public_html/data/songs_cache.json'
    ];
    
    echo "\n📁 Procurando arquivo de cache...\n";
    
    $cacheFile = null;
    foreach ($possiblePaths as $path) {
        $exists = file_exists($path) ? "✅ ENCONTRADO" : "❌ não existe";
        echo "   - {$path}: {$exists}\n";
        if (file_exists($path) && $cacheFile === null) {
            $cacheFile = $path;
        }
    }
    
    if ($cacheFile === null) {
        throw new Exception("Nenhum arquivo de cache encontrado em nenhum dos caminhos!");
    }
    
    echo "\n📦 Usando: {$cacheFile}\n";
    echo "📊 Tamanho: " . round(filesize($cacheFile) / 1024 / 1024, 2) . " MB\n";

    // Ler arquivo
    $cacheContent = file_get_contents($cacheFile);
    if ($cacheContent === false) {
        throw new Exception("Não foi possível ler o arquivo de cache");
    }
    
    echo "✅ Arquivo lido com sucesso (" . strlen($cacheContent) . " bytes)\n";
    
    // Decodificar JSON
    $cacheData = json_decode($cacheContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
    }
    
    if (!isset($cacheData['songs']) || !is_array($cacheData['songs'])) {
        echo "\n⚠️ Estrutura do arquivo:\n";
        print_r(array_keys($cacheData));
        throw new Exception("Estrutura do JSON inválida - campo 'songs' não encontrado");
    }

    $songs = $cacheData['songs'];
    $totalSongs = count($songs);
    echo "\n📦 {$totalSongs} músicas encontradas no cache\n";
    echo "⏳ Iniciando importação...\n\n";

    // Preparar INSERT
    $stmt = $pdo->prepare("
        INSERT INTO songs (code, title, artist, category) 
        VALUES (:code, :title, :artist, :category)
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");

    $imported = 0;
    $errors = 0;
    
    $pdo->beginTransaction();

    foreach ($songs as $song) {
        try {
            $stmt->execute([
                'code' => $song['code'] ?? ('S' . ($imported + 1)),
                'title' => $song['title'] ?? 'Sem título',
                'artist' => $song['artist'] ?? 'Desconhecido',
                'category' => $song['category'] ?? 'Geral'
            ]);
            $imported++;

            if ($imported % 500 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
                echo "📝 {$imported} músicas importadas...\n";
                flush();
            }

        } catch (Exception $e) {
            $errors++;
        }
    }

    $pdo->commit();

    echo "\n═══════════════════════════════════════════\n";
    echo "✅ IMPORTAÇÃO CONCLUÍDA!\n";
    echo "═══════════════════════════════════════════\n";
    echo "✅ Músicas importadas: {$imported}\n";
    echo "❌ Erros: {$errors}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM songs");
    echo "🎵 Total na tabela: " . $stmt->fetchColumn() . "\n";
    
    echo "\n🗑️ DELETE ESTE ARQUIVO APÓS A IMPORTAÇÃO!\n";

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "</pre>";
?>
<style>
body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 20px; }
h1 { color: #6366f1; }
pre { background: #16213e; padding: 20px; border-radius: 10px; white-space: pre-wrap; }
</style>
