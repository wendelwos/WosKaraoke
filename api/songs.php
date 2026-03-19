<?php
/**
 * API de Músicas - WosKaraoke
 * 
 * GET /api/songs.php - Lista todas as músicas
 * GET /api/songs.php?search=termo - Busca por código, cantor ou trecho
 * GET /api/songs.php?search=termo&limit=20&offset=0 - Com paginação
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

use WosKaraoke\ExcelReader;

// Rate limiting para buscas: 60 por minuto
$rateLimiter = new WosKaraoke\RateLimiter();
if (!$rateLimiter->check('search')) {
    $rateLimiter->tooManyRequests('search');
}
$rateLimiter->addHeaders('search');

try {
    // Encontrar arquivo Excel no diretório repertorio
    $excelFiles = glob(REPERTORIO_PATH . '/*.xls*');
    
    if (empty($excelFiles)) {
        errorResponse('Nenhum arquivo de repertório encontrado', 404);
    }

    $excelPath = $excelFiles[0]; // Usa o primeiro arquivo encontrado
    $reader = new ExcelReader($excelPath, CACHE_FILE);

    // Parâmetros de busca
    $search = $_GET['search'] ?? '';
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    // Realizar busca
    $result = $reader->search($search, $limit, $offset);

    jsonResponse([
        'success' => true,
        'data' => $result['songs'],
        'meta' => [
            'total' => $result['total'],
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
            'hasMore' => ($offset + $limit) < $result['total']
        ]
    ]);

} catch (Throwable $e) {
    error_log("Songs API Error: " . $e->getMessage());
    errorResponse('Erro ao buscar músicas: ' . $e->getMessage(), 500);
}

