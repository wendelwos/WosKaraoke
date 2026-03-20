<?php
/**
 * Carregador de Variáveis de Ambiente (.env)
 * 
 * Implementação simples sem dependências externas
 * 
 * @author WosKaraoke
 * @version 1.0
 */

declare(strict_types=1);

namespace WosKaraoke;

class Env
{
    private static bool $loaded = false;
    private static array $variables = [];

    /**
     * Carrega variáveis do arquivo .env
     */
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $path ?? dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            // Tenta carregar .env.example como fallback em desenvolvimento
            $exampleFile = dirname(__DIR__) . '/.env.example';
            if (file_exists($exampleFile)) {
                $envFile = $exampleFile;
            } else {
                return; // Nenhum arquivo .env encontrado
            }
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignora comentários
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove aspas se presentes
                $value = trim($value, '"\'');
                
                // Só define se a variável não existir no ambiente
                if (getenv($key) === false) {
                    // Armazena internamente
                    self::$variables[$key] = $value;
                    
                    // Define no ambiente
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtém uma variável de ambiente
     * 
     * @param string $key Nome da variável
     * @param mixed $default Valor padrão se não existir
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Primeiro tenta do cache interno
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        // Depois tenta do $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Por fim tenta getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Verifica se uma variável existe
     */
    public static function has(string $key): bool
    {
        return isset(self::$variables[$key]) 
            || isset($_ENV[$key]) 
            || getenv($key) !== false;
    }

    /**
     * Obtém uma variável como booleano
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Obtém uma variável como inteiro
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        
        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Verifica se está em modo debug
     */
    public static function isDebug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }

    /**
     * Verifica se está em produção
     */
    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}
