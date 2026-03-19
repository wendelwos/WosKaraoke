<?php
/**
 * ============================================
 * INSTALADOR DO BANCO DE DADOS - Karaoke Show
 * ============================================
 * 
 * Execute este script UMA VEZ para:
 * 1. Criar o banco de dados (se não existir)
 * 2. Criar todas as tabelas
 * 3. Inserir dados iniciais (planos, super admin)
 * 4. Importar músicas
 * 
 * Uso: php database/install.php
 * Ou acesse via browser: http://localhost/WosKaraoke/database/install.php
 */

// Configurações do banco de dados local (XAMPP)
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';  // XAMPP não tem senha por padrão
$DB_NAME = 'woskaraoke';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <title>Instalação - Karaoke Show</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a2e; color: #eee; }
        h1 { color: #6366f1; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        .step { margin: 15px 0; padding: 10px; background: #2a2a4a; border-radius: 8px; }
        .step-title { font-weight: bold; margin-bottom: 5px; }
        pre { background: #0a0a1a; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
<h1>🎤 Karaoke Show - Instalação do Banco de Dados</h1>
";

try {
    // ============================================
    // PASSO 1: Conectar ao MySQL (sem banco específico)
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-title'>1. Conectando ao MySQL...</div>";
    
    $pdo = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<span class='success'>✅ Conexão bem-sucedida!</span>";
    echo "</div>";
    
    // ============================================
    // PASSO 2: Criar banco de dados
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-title'>2. Criando banco de dados '$DB_NAME'...</div>";
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$DB_NAME`");
    
    echo "<span class='success'>✅ Banco de dados criado/selecionado!</span>";
    echo "</div>";
    
    // ============================================
    // PASSO 3: Executar script de criação de tabelas
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-title'>3. Criando tabelas...</div>";
    
    $sqlFile = __DIR__ . '/01_create_tables.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Remove comentários de linha simples
        $sql = preg_replace('/^--.*$/m', '', $sql);
        
        // Divide por ponto e vírgula (respeitando strings)
        $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/', $sql)));
        
        $tablesCreated = 0;
        foreach ($statements as $statement) {
            if (!empty($statement) && strtoupper(substr($statement, 0, 6)) !== 'SELECT') {
                try {
                    $pdo->exec($statement);
                    if (stripos($statement, 'CREATE TABLE') !== false) {
                        $tablesCreated++;
                    }
                } catch (PDOException $e) {
                    // Ignora erros de "já existe"
                    if (strpos($e->getMessage(), 'already exists') === false && 
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        echo "<br><span class='error'>⚠️ " . htmlspecialchars($e->getMessage()) . "</span>";
                    }
                }
            }
        }
        
        echo "<span class='success'>✅ $tablesCreated tabelas processadas!</span>";
    } else {
        echo "<span class='error'>❌ Arquivo 01_create_tables.sql não encontrado!</span>";
    }
    echo "</div>";
    
    // ============================================
    // PASSO 4: Verificar se há músicas
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-title'>4. Verificando músicas...</div>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM songs");
    $result = $stmt->fetch();
    $totalSongs = $result['total'];
    
    if ($totalSongs == 0) {
        echo "<span class='info'>ℹ️ Nenhuma música encontrada. Importando...</span><br>";
        
        $sqlFile = __DIR__ . '/02_import_songs.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            
            // Executa statements um por um
            $statements = array_filter(array_map('trim', preg_split('/;[\r\n]+/', $sql)));
            $imported = 0;
            
            foreach ($statements as $statement) {
                if (!empty($statement) && stripos($statement, 'INSERT') !== false) {
                    try {
                        $pdo->exec($statement);
                        $imported++;
                    } catch (PDOException $e) {
                        // Ignora duplicatas
                    }
                }
            }
            
            // Verifica novamente
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM songs");
            $result = $stmt->fetch();
            
            echo "<span class='success'>✅ {$result['total']} músicas importadas!</span>";
        } else {
            echo "<span class='error'>❌ Arquivo 02_import_songs.sql não encontrado!</span>";
        }
    } else {
        echo "<span class='success'>✅ $totalSongs músicas já cadastradas!</span>";
    }
    echo "</div>";
    
    // ============================================
    // PASSO 5: Corrigir Super Admin
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-title'>5. Configurando Super Admin...</div>";
    
    // Gera hash correto para 'admin123'
    $novoHash = password_hash('admin123', PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE super_admins SET password_hash = ? WHERE username = 'superadmin'");
    $stmt->execute([$novoHash]);
    
    // Verifica quantos super admins existem
    $stmt = $pdo->query("SELECT id, username, email, name FROM super_admins");
    $admins = $stmt->fetchAll();
    
    if (count($admins) > 0) {
        echo "<span class='success'>✅ " . count($admins) . " Super Admin(s) configurado(s)!</span><br>";
        echo "<pre>";
        foreach ($admins as $admin) {
            echo "ID: {$admin['id']} | Usuário: {$admin['username']} | Email: {$admin['email']}\n";
        }
        echo "</pre>";
    }
    echo "</div>";
    
    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "<div class='step' style='background: #1e3a1e;'>";
    echo "<div class='step-title'>🎉 Instalação Concluída!</div>";
    echo "<br><strong>Credenciais do Super Admin:</strong><br>";
    echo "👤 Usuário: <code>superadmin</code><br>";
    echo "🔑 Senha: <code>admin123</code><br>";
    echo "<br><a href='/WosKaraoke/superadmin/' style='color: #6366f1;'>➡️ Acessar Painel Super Admin</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='step' style='background: #3a1e1e;'>";
    echo "<div class='step-title'>❌ Erro na Instalação</div>";
    echo "<span class='error'>" . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<br><br><strong>Verifique:</strong><br>";
    echo "1. O MySQL/MariaDB está rodando no XAMPP?<br>";
    echo "2. As credenciais estão corretas?<br>";
    echo "</div>";
}

echo "</body></html>";
