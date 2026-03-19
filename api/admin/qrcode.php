<?php
/**
 * API de QR Code - Gera QR codes para mesas
 * 
 * GET /api/admin/qrcode.php?table=5 - Gera QR code para mesa 5
 * GET /api/admin/qrcode.php?action=list - Lista mesas com QR codes
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Configuração de sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica autenticação admin
if (!isset($_SESSION['admin_id'])) {
    errorResponse('Acesso não autorizado', 401);
}

try {
    $action = $_GET['action'] ?? 'generate';
    $pdo = getDatabase();
    
    switch ($action) {
        case 'generate':
            generateQRCode();
            break;
            
        case 'list':
            listTables($pdo);
            break;
            
        case 'batch':
            generateBatchQRCodes();
            break;
            
        default:
            errorResponse('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("QRCode API Error: " . $e->getMessage());
    errorResponse('Erro ao gerar QR Code: ' . $e->getMessage(), 500);
}

/**
 * Gera QR Code para uma mesa específica
 */
function generateQRCode(): void
{
    $table = $_GET['table'] ?? '';
    $eventCode = $_GET['event_code'] ?? '';
    $format = $_GET['format'] ?? 'svg'; // svg ou png
    
    if (empty($table)) {
        errorResponse('Número da mesa é obrigatório');
    }
    
    // Monta URL do karaokê com parâmetros
    $baseUrl = getBaseUrl();
    $params = ['table' => $table];
    
    if ($eventCode) {
        $params['event'] = $eventCode;
    }
    
    $url = $baseUrl . '?' . http_build_query($params);
    
    // Gera QR Code usando API externa (simple, no dependencies)
    $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300',
        'data' => $url,
        'format' => $format,
        'margin' => 10,
        'color' => '6366f1', // Cor do tema
        'bgcolor' => 'ffffff'
    ]);
    
    // Retorna dados do QR code
    jsonResponse([
        'success' => true,
        'data' => [
            'table' => $table,
            'url' => $url,
            'qr_url' => $qrApiUrl,
            'event_code' => $eventCode
        ]
    ]);
}

/**
 * Gera múltiplos QR codes de uma vez
 */
function generateBatchQRCodes(): void
{
    $start = (int) ($_GET['start'] ?? 1);
    $end = (int) ($_GET['end'] ?? 10);
    $eventCode = $_GET['event_code'] ?? '';
    
    if ($start < 1 || $end < $start || $end > 100) {
        errorResponse('Range inválido (max 100 mesas)');
    }
    
    $baseUrl = getBaseUrl();
    $qrCodes = [];
    
    for ($table = $start; $table <= $end; $table++) {
        $params = ['table' => $table];
        if ($eventCode) {
            $params['event'] = $eventCode;
        }
        
        $url = $baseUrl . '?' . http_build_query($params);
        
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '300x300',
            'data' => $url,
            'format' => 'svg',
            'margin' => 10,
            'color' => '6366f1',
            'bgcolor' => 'ffffff'
        ]);
        
        $qrCodes[] = [
            'table' => $table,
            'url' => $url,
            'qr_url' => $qrApiUrl
        ];
    }
    
    jsonResponse([
        'success' => true,
        'data' => $qrCodes,
        'count' => count($qrCodes)
    ]);
}

/**
 * Lista mesas que já foram usadas
 */
function listTables(PDO $pdo): void
{
    $eventId = (int) ($_GET['event_id'] ?? 1);
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            table_number,
            COUNT(*) as song_count
        FROM queue
        WHERE event_id = ? AND table_number IS NOT NULL AND table_number != ''
        GROUP BY table_number
        ORDER BY CAST(table_number AS UNSIGNED)
    ");
    $stmt->execute([$eventId]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'data' => $tables
    ]);
}

/**
 * Obtém URL base do sistema
 */
function getBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detecta o path base
    $scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME'])); // Remove /api/admin
    
    return $protocol . '://' . $host . $scriptPath;
}
