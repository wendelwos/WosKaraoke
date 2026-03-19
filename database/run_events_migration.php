<?php
/**
 * Migração: Adicionar novos campos em event_settings
 * Execute via browser: http://localhost/WosKaraoke/database/run_events_migration.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Migração: Eventos</h2>";

try {
    // Carregar configurações
    require_once __DIR__ . '/../api/config.php';
    $pdo = getDatabase();
    
    echo "<p>✅ Conexão com banco de dados OK</p>";
    
    $migrations = [
        "ADD status" => "ALTER TABLE event_settings ADD COLUMN status VARCHAR(20) DEFAULT 'closed'",
        "ADD starts_at" => "ALTER TABLE event_settings ADD COLUMN starts_at DATETIME DEFAULT NULL",
        "ADD ends_at" => "ALTER TABLE event_settings ADD COLUMN ends_at DATETIME DEFAULT NULL",
        "ADD max_songs_per_person" => "ALTER TABLE event_settings ADD COLUMN max_songs_per_person INT DEFAULT 3",
        "ADD is_template" => "ALTER TABLE event_settings ADD COLUMN is_template TINYINT DEFAULT 0",
    ];
    
    // Verificar quais colunas existem
    $stmt = $pdo->query("DESCRIBE event_settings");
    $existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $columnsToAdd = [
        'status' => "ADD status",
        'starts_at' => "ADD starts_at",
        'ends_at' => "ADD ends_at",
        'max_songs_per_person' => "ADD max_songs_per_person",
        'is_template' => "ADD is_template"
    ];
    
    foreach ($columnsToAdd as $col => $migKey) {
        if (!in_array($col, $existingColumns)) {
            try {
                $pdo->exec($migrations[$migKey]);
                echo "<p>✅ Coluna <code>$col</code> adicionada</p>";
            } catch (Exception $e) {
                echo "<p>⚠️ <code>$col</code>: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>✓ Coluna <code>$col</code> já existe</p>";
        }
    }
    
    // Migrar dados de is_open para status
    $stmt = $pdo->query("SELECT COUNT(*) FROM event_settings WHERE (status IS NULL OR status = '') AND is_open IS NOT NULL");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $pdo->exec("UPDATE event_settings SET status = CASE WHEN is_open = 1 THEN 'open' ELSE 'closed' END WHERE status IS NULL OR status = ''");
        echo "<p>✅ Migrados $count registros de is_open para status</p>";
    }
    
    echo "<h3>✅ Migração concluída!</h3>";
    echo "<p><a href='../superadmin/events.php'>Ir para Eventos (SuperAdmin)</a></p>";
    echo "<p><a href='../establishment/events.php'>Ir para Eventos (Estabelecimento)</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
