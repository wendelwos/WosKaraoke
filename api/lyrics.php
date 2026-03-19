<?php
/**
 * API de Letras - WosKaraoke
 * Sistema com Fallback: Lyrics.ovh (primário) → Genius (fallback)
 * 
 * GET /api/lyrics.php?artist=XXX&title=YYY
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Genius API Token
define('GENIUS_ACCESS_TOKEN', 'ezUoKVLGw9b4gU5t61RMtVabIq0ZVWtTtyssvrrRSgfrHt9tfxQRkZpDidUOhA8N');

try {
    $artist = trim($_GET['artist'] ?? '');
    $title = trim($_GET['title'] ?? '');
    
    if (empty($artist) || empty($title)) {
        errorResponse('Artista e título são obrigatórios');
    }
    
    // 1. Tentar Lyrics.ovh primeiro (mais rápido, sem rate limit)
    $result = fetchFromLyricsOvh($artist, $title);
    
    // 2. Genius DESATIVADO - estava trazendo letras erradas
    // Desabilitado temporariamente para teste
    // if (!$result['found']) {
    //     $result = fetchFromGenius($artist, $title);
    // }
    
    jsonResponse($result);

} catch (Exception $e) {
    error_log("Lyrics API Error: " . $e->getMessage());
    errorResponse('Erro ao buscar letra: ' . $e->getMessage(), 500);
}

/**
 * Busca letra no Lyrics.ovh (API gratuita)
 */
function fetchFromLyricsOvh(string $artist, string $title): array
{
    $artistEncoded = rawurlencode($artist);
    $titleEncoded = rawurlencode($title);
    
    $url = "https://api.lyrics.ovh/v1/{$artistEncoded}/{$titleEncoded}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: WosKaraoke/1.0\r\nAccept: application/json\r\n",
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['success' => true, 'found' => false, 'source' => 'lyrics.ovh'];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error']) || empty($data['lyrics'])) {
        return ['success' => true, 'found' => false, 'source' => 'lyrics.ovh'];
    }
    
    // Limpar letra
    $lyrics = trim($data['lyrics']);
    $lyrics = preg_replace('/\n{3,}/', "\n\n", $lyrics);
    
    return [
        'success' => true,
        'found' => true,
        'source' => 'lyrics.ovh',
        'data' => [
            'title' => $title,
            'artist' => $artist,
            'lyrics' => $lyrics
        ]
    ];
}

/**
 * Busca letra no Genius API (melhor cobertura BR)
 */
function fetchFromGenius(string $artist, string $title): array
{
    // Passo 1: Buscar a música no Genius
    $query = rawurlencode("$artist $title");
    $searchUrl = "https://api.genius.com/search?q={$query}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "Authorization: Bearer " . GENIUS_ACCESS_TOKEN . "\r\nUser-Agent: WosKaraoke/1.0\r\n",
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($searchUrl, false, $context);
    
    if ($response === false) {
        return [
            'success' => true,
            'found' => false,
            'source' => 'genius',
            'message' => 'Não foi possível conectar ao Genius'
        ];
    }
    
    $data = json_decode($response, true);
    
    if (empty($data['response']['hits'])) {
        return [
            'success' => true,
            'found' => false,
            'source' => 'genius',
            'message' => 'Letra não encontrada para esta música'
        ];
    }
    
    // Pegar o primeiro resultado
    $hit = $data['response']['hits'][0]['result'];
    $songUrl = $hit['url'];
    $foundTitle = $hit['title'] ?? $title;
    $foundArtist = $hit['primary_artist']['name'] ?? $artist;
    
    // Passo 2: Fazer scraping da página do Genius para pegar a letra
    $lyrics = scrapeGeniusLyrics($songUrl);
    
    if (empty($lyrics)) {
        return [
            'success' => true,
            'found' => true,
            'source' => 'genius',
            'data' => [
                'title' => $foundTitle,
                'artist' => $foundArtist,
                'lyrics' => '',
                'url' => $songUrl,
                'message' => 'Letra disponível no Genius (clique para ver)'
            ]
        ];
    }
    
    return [
        'success' => true,
        'found' => true,
        'source' => 'genius',
        'data' => [
            'title' => $foundTitle,
            'artist' => $foundArtist,
            'lyrics' => $lyrics,
            'url' => $songUrl
        ]
    ];
}

/**
 * Faz scraping da letra no Genius
 */
function scrapeGeniusLyrics(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'ignore_errors' => true
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        return '';
    }
    
    // Padrão para encontrar a letra (Genius usa data-lyrics-container)
    // A estrutura do Genius muda, então tentamos múltiplos padrões
    
    // Padrão 1: data-lyrics-container (mais recente)
    if (preg_match_all('/<div[^>]*data-lyrics-container="true"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
        $lyrics = implode("\n", $matches[1]);
    }
    // Padrão 2: class Lyrics__Container (anterior)
    elseif (preg_match_all('/<div[^>]*class="[^"]*Lyrics__Container[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
        $lyrics = implode("\n", $matches[1]);
    }
    // Padrão 3: id lyrics-root
    elseif (preg_match('/<div[^>]*id="lyrics-root"[^>]*>(.*?)<\/div>/is', $html, $match)) {
        $lyrics = $match[1];
    }
    else {
        return '';
    }
    
    // Limpar HTML
    $lyrics = preg_replace('/<br\s*\/?>/i', "\n", $lyrics);
    $lyrics = preg_replace('/<[^>]+>/', '', $lyrics);
    $lyrics = html_entity_decode($lyrics, ENT_QUOTES, 'UTF-8');
    $lyrics = preg_replace('/\[.*?\]/', '', $lyrics); // Remove [Verse], [Chorus], etc.
    $lyrics = preg_replace('/\n{3,}/', "\n\n", $lyrics);
    $lyrics = trim($lyrics);
    
    return $lyrics;
}
