<?php
/**
 * Helpers - Funções utilitárias compartilhadas
 */

declare(strict_types=1);

namespace WosKaraoke;

class Helpers
{
    /**
     * Obtém o IP real do cliente, considerando proxies e CDNs
     */
    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }

        return '127.0.0.1';
    }

    /**
     * Detecta se o banco é MySQL (vs SQLite)
     */
    public static function isMySQL(): bool
    {
        return defined('DB_TYPE') && DB_TYPE === 'mysql';
    }

    /**
     * Retorna fragmento SQL de auto-increment conforme o banco
     */
    public static function autoIncrement(): string
    {
        return self::isMySQL() ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
    }

    /**
     * Retorna tipo INT para PK conforme o banco
     */
    public static function intPK(): string
    {
        return self::isMySQL() ? 'INT' : 'INTEGER';
    }

    /**
     * Retorna sufixo de ENGINE para MySQL
     */
    public static function engineSuffix(): string
    {
        return self::isMySQL() ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    }

    /**
     * Cria um index de forma segura (sem erro se já existir)
     */
    public static function createIndex(\PDO $pdo, string $name, string $table, string $columns, bool $unique = false): void
    {
        $type = $unique ? 'UNIQUE INDEX' : 'INDEX';

        if (self::isMySQL()) {
            try {
                $pdo->exec("CREATE {$type} {$name} ON {$table}({$columns})");
            } catch (\Exception $e) {
                // Index already exists
            }
        } else {
            $pdo->exec("CREATE {$type} IF NOT EXISTS {$name} ON {$table}({$columns})");
        }
    }

    /**
     * Adiciona coluna de forma segura (sem erro se já existir)
     */
    public static function addColumn(\PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Exception $e) {
            // Column already exists
        }
    }
}
