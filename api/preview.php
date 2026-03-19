<?php
/**
 * API Endpoint: Deezer Preview
 * 
 * Busca preview de 30 segundos de músicas via Deezer API (gratuita)
 * 
 * GET /api/preview.php?artist=ARTISTA&title=TITULO
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Parâmetros
$artist = $_GET['artist'] ?? '';
$title = $_GET['title'] ?? '';

if (empty($artist) && empty($title)) {
    echo json_encode([
        'success' => false,
        'error' => 'Parâmetros artist ou title são obrigatórios'
    ]);
    exit;
}

// Construir query de busca
$query = trim("$artist $title");
$query = urlencode($query);

// URL da API Deezer (gratuita, sem autenticação)
$url = "https://api.deezer.com/search?q=$query&limit=1";

// Fazer requisição
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'WosKaraoke/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode([
        'success' => false,
        'error' => "Erro de conexão: $error"
    ]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'error' => "Deezer API retornou código $httpCode"
    ]);
    exit;
}

$data = json_decode($response, true);

if (!$data || empty($data['data'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Música não encontrada no Deezer'
    ]);
    exit;
}

// Pegar primeiro resultado
$track = $data['data'][0];

echo json_encode([
    'success' => true,
    'preview' => [
        'title' => $track['title'] ?? '',
        'artist' => $track['artist']['name'] ?? '',
        'album' => $track['album']['title'] ?? '',
        'preview_url' => $track['preview'] ?? null,
        'cover' => $track['album']['cover_medium'] ?? null,
        'duration' => $track['duration'] ?? 0,
        'deezer_id' => $track['id'] ?? null
    ]
]);
