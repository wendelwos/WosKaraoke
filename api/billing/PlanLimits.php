<?php
/**
 * PlanLimits - Verificação de Limites do Plano
 * 
 * Classe helper para verificar se um estabelecimento pode executar
 * determinadas ações baseado nos limites do seu plano.
 */

declare(strict_types=1);

class PlanLimits
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Obtém os limites do plano de um estabelecimento
     */
    public function getPlanLimits(int $establishmentId): array
    {
        // Tenta buscar assinatura ativa
        $stmt = $this->pdo->prepare("
            SELECT p.*
            FROM subscriptions s
            JOIN plans p ON s.plan_id = p.id
            WHERE s.establishment_id = ? AND s.status IN ('active', 'trial')
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$establishmentId]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            // Retorna limites do plano Free
            $stmt = $this->pdo->prepare("SELECT * FROM plans WHERE code = 'free'");
            $stmt->execute();
            $plan = $stmt->fetch();
            
            if (!$plan) {
                // Fallback se não houver plano free
                return [
                    'code' => 'free',
                    'max_events' => 1,
                    'max_songs_per_day' => 30,
                    'max_kjs' => 1,
                    'features' => ['watermark' => true]
                ];
            }
        }
        
        return [
            'code' => $plan['code'],
            'max_events' => (int) $plan['max_events'],
            'max_songs_per_day' => (int) $plan['max_songs_per_day'],
            'max_kjs' => (int) $plan['max_kjs'],
            'features' => json_decode($plan['features'] ?? '{}', true)
        ];
    }
    
    /**
     * Obtém o uso atual do dia
     */
    public function getUsageToday(int $establishmentId): array
    {
        $today = date('Y-m-d');
        
        $stmt = $this->pdo->prepare("
            SELECT songs_played, events_created 
            FROM usage_logs 
            WHERE establishment_id = ? AND usage_date = ?
        ");
        $stmt->execute([$establishmentId, $today]);
        $usage = $stmt->fetch();
        
        // Conta KJs ativos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE establishment_id = ? AND is_active = 1");
        $stmt->execute([$establishmentId]);
        $kjCount = (int) $stmt->fetchColumn();
        
        // Conta eventos ativos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM event_settings WHERE establishment_id = ? AND is_open = 1");
        $stmt->execute([$establishmentId]);
        $activeEvents = (int) $stmt->fetchColumn();
        
        return [
            'songs_today' => (int) ($usage['songs_played'] ?? 0),
            'events_today' => (int) ($usage['events_created'] ?? 0),
            'active_kjs' => $kjCount,
            'active_events' => $activeEvents
        ];
    }
    
    /**
     * Incrementa contador de músicas do dia
     */
    public function incrementSongsPlayed(int $establishmentId): bool
    {
        $today = date('Y-m-d');
        $isMySQL = DB_TYPE === 'mysql';
        
        if ($isMySQL) {
            $this->pdo->prepare("
                INSERT INTO usage_logs (establishment_id, usage_date, songs_played) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE songs_played = songs_played + 1
            ")->execute([$establishmentId, $today]);
        } else {
            // SQLite
            $this->pdo->prepare("
                INSERT INTO usage_logs (establishment_id, usage_date, songs_played) 
                VALUES (?, ?, 1)
                ON CONFLICT(establishment_id, usage_date) 
                DO UPDATE SET songs_played = songs_played + 1
            ")->execute([$establishmentId, $today]);
        }
        
        return true;
    }
    
    /**
     * Incrementa contador de eventos criados no dia
     */
    public function incrementEventsCreated(int $establishmentId): bool
    {
        $today = date('Y-m-d');
        $isMySQL = DB_TYPE === 'mysql';
        
        if ($isMySQL) {
            $this->pdo->prepare("
                INSERT INTO usage_logs (establishment_id, usage_date, events_created) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE events_created = events_created + 1
            ")->execute([$establishmentId, $today]);
        } else {
            $this->pdo->prepare("
                INSERT INTO usage_logs (establishment_id, usage_date, events_created) 
                VALUES (?, ?, 1)
                ON CONFLICT(establishment_id, usage_date) 
                DO UPDATE SET events_created = events_created + 1
            ")->execute([$establishmentId, $today]);
        }
        
        return true;
    }
    
    /**
     * Verifica se pode tocar mais uma música hoje
     */
    public function canPlaySong(int $establishmentId): array
    {
        $limits = $this->getPlanLimits($establishmentId);
        $usage = $this->getUsageToday($establishmentId);
        
        $canPlay = $usage['songs_today'] < $limits['max_songs_per_day'];
        
        return [
            'allowed' => $canPlay,
            'current' => $usage['songs_today'],
            'limit' => $limits['max_songs_per_day'],
            'remaining' => max(0, $limits['max_songs_per_day'] - $usage['songs_today']),
            'message' => $canPlay 
                ? null 
                : "Limite de {$limits['max_songs_per_day']} músicas/dia atingido. Faça upgrade do seu plano!"
        ];
    }
    
    /**
     * Verifica se pode criar mais eventos
     */
    public function canCreateEvent(int $establishmentId): array
    {
        $limits = $this->getPlanLimits($establishmentId);
        $usage = $this->getUsageToday($establishmentId);
        
        $canCreate = $usage['active_events'] < $limits['max_events'];
        
        return [
            'allowed' => $canCreate,
            'current' => $usage['active_events'],
            'limit' => $limits['max_events'],
            'remaining' => max(0, $limits['max_events'] - $usage['active_events']),
            'message' => $canCreate 
                ? null 
                : "Limite de {$limits['max_events']} eventos ativos atingido. Faça upgrade do seu plano!"
        ];
    }
    
    /**
     * Verifica se pode adicionar mais KJs
     */
    public function canAddKJ(int $establishmentId): array
    {
        $limits = $this->getPlanLimits($establishmentId);
        $usage = $this->getUsageToday($establishmentId);
        
        $canAdd = $usage['active_kjs'] < $limits['max_kjs'];
        
        return [
            'allowed' => $canAdd,
            'current' => $usage['active_kjs'],
            'limit' => $limits['max_kjs'],
            'remaining' => max(0, $limits['max_kjs'] - $usage['active_kjs']),
            'message' => $canAdd 
                ? null 
                : "Limite de {$limits['max_kjs']} KJs atingido. Faça upgrade do seu plano!"
        ];
    }
    
    /**
     * Verifica se uma feature está disponível no plano
     */
    public function hasFeature(int $establishmentId, string $feature): bool
    {
        $limits = $this->getPlanLimits($establishmentId);
        return !empty($limits['features'][$feature]);
    }
    
    /**
     * Retorna se deve mostrar marca d'água
     */
    public function shouldShowWatermark(int $establishmentId): bool
    {
        return $this->hasFeature($establishmentId, 'watermark');
    }
}

/**
 * Função helper global para usar em qualquer lugar
 */
function checkPlanLimit(PDO $pdo, int $establishmentId, string $action): array
{
    $limits = new PlanLimits($pdo);
    
    switch ($action) {
        case 'play_song':
            return $limits->canPlaySong($establishmentId);
        case 'create_event':
            return $limits->canCreateEvent($establishmentId);
        case 'add_kj':
            return $limits->canAddKJ($establishmentId);
        default:
            return ['allowed' => true, 'message' => null];
    }
}
