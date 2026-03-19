<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WosKaraoke\MigrationRunner;

class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;
    private string $migrationsDir;

    protected function setUp(): void
    {
        // In-memory SQLite for isolated tests
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Define DB_TYPE if not yet defined (for Helpers::isMySQL)
        if (!defined('DB_TYPE')) {
            define('DB_TYPE', 'sqlite');
        }

        $this->migrationsDir = sys_get_temp_dir() . '/test_migrations_' . uniqid();
        mkdir($this->migrationsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->migrationsDir . '/*');
        foreach ($files as $f) {
            unlink($f);
        }
        rmdir($this->migrationsDir);
    }

    public function testRunPendingExecutesMigrations(): void
    {
        file_put_contents($this->migrationsDir . '/001_test.php', '<?php return function(PDO $pdo) { $pdo->exec("CREATE TABLE test_table (id INTEGER PRIMARY KEY)"); };');

        $runner = new MigrationRunner($this->pdo, $this->migrationsDir);
        $executed = $runner->runPending();

        $this->assertSame(['001_test'], $executed);

        // Table should exist
        $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_table'")->fetch();
        $this->assertSame('test_table', $result['name']);
    }

    public function testRunPendingSkipsAlreadyRan(): void
    {
        file_put_contents($this->migrationsDir . '/001_test.php', '<?php return function(PDO $pdo) { $pdo->exec("CREATE TABLE test_skip (id INTEGER PRIMARY KEY)"); };');

        $runner = new MigrationRunner($this->pdo, $this->migrationsDir);

        $first = $runner->runPending();
        $this->assertCount(1, $first);

        $second = $runner->runPending();
        $this->assertCount(0, $second);
    }

    public function testStatusShowsCorrectState(): void
    {
        file_put_contents($this->migrationsDir . '/001_a.php', '<?php return function(PDO $pdo) {};');
        file_put_contents($this->migrationsDir . '/002_b.php', '<?php return function(PDO $pdo) {};');

        $runner = new MigrationRunner($this->pdo, $this->migrationsDir);
        $runner->runPending();

        // Add a new migration file
        file_put_contents($this->migrationsDir . '/003_c.php', '<?php return function(PDO $pdo) {};');

        $status = $runner->status();

        $this->assertSame('ran', $status[0]['status']);
        $this->assertSame('ran', $status[1]['status']);
        $this->assertSame('pending', $status[2]['status']);
    }

    public function testFailedMigrationRollsBack(): void
    {
        file_put_contents($this->migrationsDir . '/001_fail.php', '<?php return function(PDO $pdo) { $pdo->exec("INVALID SQL STATEMENT HERE !!!"); };');

        $runner = new MigrationRunner($this->pdo, $this->migrationsDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/001_fail failed/');

        $runner->runPending();
    }
}
