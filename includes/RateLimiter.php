<?php
/**
 * Rate Limiter - Proteção contra abuso de API
 * 
 * Implementação baseada em arquivos (sem Redis/APCu)
 * Para produção, considere usar Redis para melhor performance
 * 
 * @author WosKaraoke
 * @version 1.0
 */

declare(strict_types=1);

namespace WosKaraoke;

class RateLimiter
{
    private string $storageDir;
    private array $limits = [
        'login' => ['attempts' => 5, 'window' => 60],      // 5 tentativas/minuto
        'search' => ['attempts' => 60, 'window' => 60],    // 60 buscas/minuto
        'favorites' => ['attempts' => 30, 'window' => 60], // 30 ações/minuto
        'queue' => ['attempts' => 10, 'window' => 60],     // 10 adições/minuto
        'default' => ['attempts' => 100, 'window' => 60],  // 100 req/minuto padrão
    ];

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__) . '/data/rate_limits';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Verifica se o cliente pode fazer uma requisição
     * 
     * @param string $action Tipo de ação (login, search, favorites, queue)
     * @param string|null $identifier IP ou token do usuário
     * @return bool True se permitido, false se bloqueado
     */
    public function check(string $action = 'default', ?string $identifier = null): bool
    {
        $identifier = $identifier ?? $this->getClientIP();
        $limit = $this->limits[$action] ?? $this->limits['default'];
        
        $key = $this->getKey($action, $identifier);
        $data = $this->getData($key);
        
        $now = time();
        $windowStart = $now - $limit['window'];
        
        // Remove timestamps antigos (fora da janela)
        $data['timestamps'] = array_filter(
            $data['timestamps'] ?? [],
            fn($ts) => $ts > $windowStart
        );
        
        // Verifica se excedeu o limite
        if (count($data['timestamps']) >= $limit['attempts']) {
            $this->saveData($key, $data);
            return false;
        }
        
        // Adiciona timestamp atual
        $data['timestamps'][] = $now;
        $this->saveData($key, $data);
        
        return true;
    }

    /**
     * Retorna informações sobre o limite
     */
    public function getInfo(string $action = 'default', ?string $identifier = null): array
    {
        $identifier = $identifier ?? $this->getClientIP();
        $limit = $this->limits[$action] ?? $this->limits['default'];
        
        $key = $this->getKey($action, $identifier);
        $data = $this->getData($key);
        
        $now = time();
        $windowStart = $now - $limit['window'];
        
        $timestamps = array_filter(
            $data['timestamps'] ?? [],
            fn($ts) => $ts > $windowStart
        );
        
        $remaining = max(0, $limit['attempts'] - count($timestamps));
        $resetAt = empty($timestamps) ? $now : min($timestamps) + $limit['window'];
        
        return [
            'limit' => $limit['attempts'],
            'remaining' => $remaining,
            'reset_at' => $resetAt,
            'reset_in' => max(0, $resetAt - $now),
        ];
    }

    /**
     * Adiciona headers de rate limit na resposta
     */
    public function addHeaders(string $action = 'default', ?string $identifier = null): void
    {
        $info = $this->getInfo($action, $identifier);
        
        header('X-RateLimit-Limit: ' . $info['limit']);
        header('X-RateLimit-Remaining: ' . $info['remaining']);
        header('X-RateLimit-Reset: ' . $info['reset_at']);
    }

    /**
     * Responde com erro 429 Too Many Requests
     */
    public function tooManyRequests(string $action = 'default'): never
    {
        $info = $this->getInfo($action);
        
        http_response_code(429);
        header('Retry-After: ' . $info['reset_in']);
        $this->addHeaders($action);
        
        echo json_encode([
            'success' => false,
            'error' => 'Muitas requisições. Tente novamente em ' . $info['reset_in'] . ' segundos.',
            'retry_after' => $info['reset_in'],
        ], JSON_UNESCAPED_UNICODE);
        
        exit;
    }

    /**
     * Define limites customizados
     */
    public function setLimit(string $action, int $attempts, int $window = 60): void
    {
        $this->limits[$action] = ['attempts' => $attempts, 'window' => $window];
    }

    /**
     * Limpa dados antigos de rate limit (garbage collection)
     */
    public function cleanup(): void
    {
        $files = glob($this->storageDir . '/*.json');
        $maxAge = 3600; // 1 hora
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - $maxAge) {
                unlink($file);
            }
        }
    }

    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    private function getClientIP(): string
    {
        return Helpers::getClientIP();
    }

    private function getKey(string $action, string $identifier): string
    {
        return md5($action . ':' . $identifier);
    }

    private function getData(string $key): array
    {
        $file = $this->storageDir . '/' . $key . '.json';
        
        if (!file_exists($file)) {
            return ['timestamps' => []];
        }
        
        $content = file_get_contents($file);
        return json_decode($content, true) ?? ['timestamps' => []];
    }

    private function saveData(string $key, array $data): void
    {
        $file = $this->storageDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
