<?php
/**
 * Pusher Helper - Enviar eventos em tempo real
 * 
 * Uso: pushEvent('event-1', 'queue_updated', ['action' => 'song_added']);
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Envia um evento via Pusher para clientes conectados
 * 
 * @param string $channel Canal do Pusher (ex: "event-1")
 * @param string $event   Nome do evento (ex: "queue_updated")
 * @param array  $data    Dados a serem enviados
 * @return bool Sucesso ou falha
 */
function pushEvent(string $channel, string $event, array $data = []): bool
{
    // Se Pusher não está configurado, retorna silenciosamente
    if (empty(PUSHER_APP_ID) || empty(PUSHER_KEY) || empty(PUSHER_SECRET)) {
        return false;
    }
    
    try {
        $pusher = new Pusher\Pusher(
            PUSHER_KEY,
            PUSHER_SECRET,
            PUSHER_APP_ID,
            [
                'cluster' => PUSHER_CLUSTER,
                'useTLS' => true
            ]
        );
        
        $pusher->trigger($channel, $event, $data);
        return true;
        
    } catch (Exception $e) {
        error_log("Pusher Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Envia atualização de fila para um evento específico
 */
function pushQueueUpdate(int $eventId, string $action = 'updated'): void
{
    pushEvent("event-{$eventId}", 'queue_updated', [
        'action' => $action,
        'timestamp' => time()
    ]);
}

/**
 * Envia notificação de música atual alterada
 */
function pushCurrentSongChanged(int $eventId, ?array $song = null): void
{
    pushEvent("event-{$eventId}", 'current_song_changed', [
        'song' => $song,
        'timestamp' => time()
    ]);
}

/**
 * Envia anúncio/toast para todos os clientes do evento
 */
function pushAnnouncement(int $eventId, string $message, string $type = 'info'): void
{
    pushEvent("event-{$eventId}", 'announcement', [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ]);
}

/**
 * Envia atualização de batalha
 */
function pushBattleUpdate(int $eventId, string $action, array $data = []): void
{
    pushEvent("event-{$eventId}", 'battle_update', array_merge([
        'action' => $action,
        'timestamp' => time()
    ], $data));
}
