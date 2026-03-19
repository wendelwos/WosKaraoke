<?php
/**
 * API de Faturas - WosKaraoke Billing
 * 
 * GET  /api/billing/invoices.php?establishment_id=X  - Listar faturas
 * GET  /api/billing/invoices.php?id=X                - Detalhe de uma fatura
 * POST /api/billing/invoices.php                     - Criar fatura (interno)
 * PUT  /api/billing/invoices.php?id=X                - Atualizar status
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (!empty($_GET['id'])) {
                getInvoice($pdo);
            } else {
                listInvoices($pdo);
            }
            break;
            
        case 'POST':
            createInvoice($pdo);
            break;
            
        case 'PUT':
            updateInvoice($pdo);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Invoices API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Lista faturas de um estabelecimento
 */
function listInvoices(PDO $pdo): void
{
    $establishmentId = (int) ($_GET['establishment_id'] ?? 0);
    $status = $_GET['status'] ?? '';
    $limit = (int) ($_GET['limit'] ?? 50);
    
    if ($establishmentId <= 0) {
        errorResponse('ID do estabelecimento obrigatório', 400);
    }
    
    $sql = "
        SELECT i.*, p.code as plan_code, p.name as plan_name
        FROM invoices i
        JOIN subscriptions s ON i.subscription_id = s.id
        JOIN plans p ON s.plan_id = p.id
        WHERE i.establishment_id = ?
    ";
    $params = [$establishmentId];
    
    if ($status) {
        $sql .= " AND i.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY i.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    // Formata valores
    foreach ($invoices as &$invoice) {
        $invoice['amount'] = (float) $invoice['amount'];
        $invoice['id'] = (int) $invoice['id'];
    }
    
    // Estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending
        FROM invoices 
        WHERE establishment_id = ?
    ");
    $stmt->execute([$establishmentId]);
    $stats = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'invoices' => $invoices,
            'stats' => [
                'total_invoices' => (int) $stats['total'],
                'total_paid' => (float) ($stats['total_paid'] ?? 0),
                'total_pending' => (float) ($stats['total_pending'] ?? 0)
            ]
        ]
    ]);
}

/**
 * Detalhe de uma fatura
 */
function getInvoice(PDO $pdo): void
{
    $id = (int) $_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT i.*, p.code as plan_code, p.name as plan_name, e.name as establishment_name
        FROM invoices i
        JOIN subscriptions s ON i.subscription_id = s.id
        JOIN plans p ON s.plan_id = p.id
        JOIN establishments e ON i.establishment_id = e.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        errorResponse('Fatura não encontrada', 404);
    }
    
    $invoice['amount'] = (float) $invoice['amount'];
    
    jsonResponse([
        'success' => true,
        'data' => $invoice
    ]);
}

/**
 * Cria uma nova fatura (geralmente chamado pelo sistema de pagamento)
 */
function createInvoice(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $subscriptionId = (int) ($input['subscription_id'] ?? 0);
    $establishmentId = (int) ($input['establishment_id'] ?? 0);
    $amount = (float) ($input['amount'] ?? 0);
    $dueDate = $input['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes = $input['notes'] ?? null;
    
    if ($subscriptionId <= 0 || $establishmentId <= 0 || $amount <= 0) {
        errorResponse('Dados inválidos', 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO invoices (subscription_id, establishment_id, amount, due_date, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$subscriptionId, $establishmentId, $amount, $dueDate, $notes]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Fatura criada',
        'data' => ['id' => $pdo->lastInsertId()]
    ], 201);
}

/**
 * Atualiza status de uma fatura
 */
function updateInvoice(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        errorResponse('ID da fatura obrigatório', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $allowedFields = ['status', 'paid_at', 'payment_method', 'external_id', 'pdf_url', 'notes'];
    $fields = [];
    $values = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $values[] = $input[$field];
        }
    }
    
    // Se status for 'paid' e não tiver paid_at, define agora
    if (isset($input['status']) && $input['status'] === 'paid' && !isset($input['paid_at'])) {
        $fields[] = "paid_at = CURRENT_TIMESTAMP";
    }
    
    if (empty($fields)) {
        errorResponse('Nenhum campo para atualizar', 400);
    }
    
    $values[] = $id;
    $sql = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    jsonResponse([
        'success' => true,
        'message' => 'Fatura atualizada'
    ]);
}
