<?php
/**
 * Cache - Sistema de cache simples baseado em arquivos
 * 
 * Para produção, considere usar Redis ou APCu
 * 
 * @author WosKaraoke
 * @version 1.0
 */

declare(strict_types=1);

namespace WosKaraoke;

class Cache
{
    private string $cacheDir;
    private int $defaultTTL = 300; // 5 minutos

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__) . '/cache';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Obtém valor do cache
     * 
     * @param string $key Chave do cache
     * @return mixed|null Valor ou null se não existir/expirado
     */
    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($data === null) {
            return null;
        }

        // Verifica expiração
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'] ?? null;
    }

    /**
     * Salva valor no cache
     * 
     * @param string $key Chave do cache
     * @param mixed $value Valor a cachear
     * @param int|null $ttl Tempo de vida em segundos (null = padrão)
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTTL;
        $file = $this->getFilePath($key);

        $data = [
            'key' => $key,
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
        ];

        return file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    /**
     * Remove item do cache
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Verifica se chave existe e não está expirada
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Obtém ou define valor (callback se não existir)
     * 
     * @param string $key Chave do cache
     * @param callable $callback Função que retorna valor se não existir
     * @param int|null $ttl Tempo de vida
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Limpa todo o cache
     */
    public function clear(): void
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Remove itens expirados (garbage collection)
     */
    public function cleanup(): int
    {
        $files = glob($this->cacheDir . '/*.cache');
        $removed = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['expires_at']) && $data['expires_at'] < time()) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Obtém estatísticas do cache
     */
    public function stats(): array
    {
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['expires_at'])) {
                if ($data['expires_at'] >= time()) {
                    $validCount++;
                } else {
                    $expiredCount++;
                }
            }
        }

        return [
            'total_files' => count($files),
            'valid_items' => $validCount,
            'expired_items' => $expiredCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        ];
    }

    /**
     * Define TTL padrão
     */
    public function setDefaultTTL(int $seconds): void
    {
        $this->defaultTTL = $seconds;
    }

    // ============================================
    // MÉTODOS DE CACHE ESPECÍFICOS
    // ============================================

    /**
     * Cache de configurações de evento
     */
    public function getEventSettings(\PDO $pdo, int $eventId): ?array
    {
        return $this->remember("event_settings_{$eventId}", function() use ($pdo, $eventId) {
            $stmt = $pdo->prepare("SELECT * FROM event_settings WHERE id = ?");
            $stmt->execute([$eventId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }, 60); // 1 minuto
    }

    /**
     * Cache de estatísticas do evento
     */
    public function getEventStats(\PDO $pdo, int $eventId): array
    {
        return $this->remember("event_stats_{$eventId}", function() use ($pdo, $eventId) {
            // Músicas na fila
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue WHERE event_id = ? AND status = 'waiting'");
            $stmt->execute([$eventId]);
            $queueCount = (int) $stmt->fetchColumn();

            // Músicas cantadas hoje
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM queue 
                WHERE event_id = ? AND status = 'finished' AND DATE(finished_at) = CURDATE()
            ");
            $stmt->execute([$eventId]);
            $sungToday = (int) $stmt->fetchColumn();

            return [
                'queue_count' => $queueCount,
                'sung_today' => $sungToday,
            ];
        }, 30); // 30 segundos
    }

    /**
     * Invalida cache de evento
     */
    public function invalidateEvent(int $eventId): void
    {
        $this->delete("event_settings_{$eventId}");
        $this->delete("event_stats_{$eventId}");
    }

    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    private function getFilePath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
    }
}
