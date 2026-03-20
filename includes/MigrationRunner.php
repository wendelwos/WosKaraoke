<?php
/**
 * MigrationRunner - Sistema de migrações versionadas
 *
 * Executa migrações pendentes uma única vez e registra na tabela _migrations.
 * Substitui o antigo initDatabase() que rodava 30+ DDL statements a cada request.
 */

declare(strict_types=1);

namespace WosKaraoke;

class MigrationRunner
{
    private \PDO $pdo;
    private string $migrationsPath;

    public function __construct(\PDO $pdo, ?string $migrationsPath = null)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__) . '/database/migrations_v2';
    }

    /**
     * Executa migrações pendentes. Chamado uma vez por conexão.
     * Custo quando não há pendências: 1 SELECT + 1 glob.
     */
    public function runPending(): array
    {
        $this->ensureMigrationsTable();

        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $executed = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ran, true)) {
                continue;
            }

            $this->runMigration($file, $name);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Lista migrações e seu status
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $result = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $result[] = [
                'migration' => $name,
                'status' => in_array($name, $ran, true) ? 'ran' : 'pending',
            ];
        }

        return $result;
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS _migrations (
                id INTEGER PRIMARY KEY " . (Helpers::isMySQL() ? 'AUTO_INCREMENT' : 'AUTOINCREMENT') . ",
                migration VARCHAR(255) NOT NULL UNIQUE,
                ran_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )" . Helpers::engineSuffix()
        );
    }

    private function getRanMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM _migrations ORDER BY id");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }

    private function runMigration(string $file, string $name): void
    {
        $migrate = require $file;

        if (!is_callable($migrate)) {
            throw new \RuntimeException("Migration {$name} must return a callable.");
        }

        $this->pdo->beginTransaction();

        try {
            $migrate($this->pdo);

            $stmt = $this->pdo->prepare("INSERT INTO _migrations (migration) VALUES (?)");
            $stmt->execute([$name]);

            try {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
            } catch (\PDOException $commitEx) {
                // MySQL DDL statements implicitly commit transactions. 
                // Ignore "no active transaction" errors.
                if (strpos(strtolower($commitEx->getMessage()), 'active transaction') === false && 
                    strpos($commitEx->getMessage(), '1305') === false) {
                    throw $commitEx;
                }
            }
        } catch (\Throwable $e) {
            try {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            } catch (\PDOException $rollbackEx) {
                // Ignore rollback errors 
            }
            throw new \RuntimeException(
                "Migration {$name} failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
