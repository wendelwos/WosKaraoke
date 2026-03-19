<?php
/**
 * API de Planos - WosKaraoke Billing
 * 
 * GET  /api/billing/plans.php           - Lista planos ativos
 * GET  /api/billing/plans.php?code=X    - Detalhe de um plano
 * POST /api/billing/plans.php           - Criar plano (Super Admin)
 * PUT  /api/billing/plans.php?id=X      - Atualizar plano (Super Admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDatabase();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (!empty($_GET['code'])) {
                getPlanByCode($pdo);
            } else {
                listPlans($pdo);
            }
            break;
            
        case 'POST':
            requireSuperAdmin();
            createPlan($pdo);
            break;
            
        case 'PUT':
            requireSuperAdmin();
            updatePlan($pdo);
            break;
            
        default:
            errorResponse('Método não permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Plans API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Verifica se há super admin logado
 */
function requireSuperAdmin(): void
{
    session_start();
    if (empty($_SESSION['super_admin_id'])) {
        errorResponse('Acesso não autorizado', 401);
    }
}

/**
 * Lista todos os planos ativos
 */
function listPlans(PDO $pdo): void
{
    $showAll = isset($_GET['all']) && $_GET['all'] === '1';
    
    $sql = "SELECT * FROM plans";
    if (!$showAll) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC";
    
    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll();
    
    // Decodifica features JSON
    foreach ($plans as &$plan) {
        $plan['features'] = json_decode($plan['features'] ?? '{}', true);
        $plan['price_monthly'] = (float) $plan['price_monthly'];
        $plan['price_yearly'] = $plan['price_yearly'] ? (float) $plan['price_yearly'] : null;
        $plan['max_events'] = (int) $plan['max_events'];
        $plan['max_songs_per_day'] = (int) $plan['max_songs_per_day'];
        $plan['max_kjs'] = (int) $plan['max_kjs'];
    }
    
    jsonResponse([
        'success' => true,
        'data' => $plans
    ]);
}

/**
 * Detalhe de um plano específico
 */
function getPlanByCode(PDO $pdo): void
{
    $code = $_GET['code'];
    
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE code = ?");
    $stmt->execute([$code]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        errorResponse('Plano não encontrado', 404);
    }
    
    $plan['features'] = json_decode($plan['features'] ?? '{}', true);
    $plan['price_monthly'] = (float) $plan['price_monthly'];
    $plan['price_yearly'] = $plan['price_yearly'] ? (float) $plan['price_yearly'] : null;
    
    jsonResponse([
        'success' => true,
        'data' => $plan
    ]);
}

/**
 * Cria um novo plano (Super Admin)
 */
function createPlan(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    
    $code = strtolower(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $priceMonthly = (float) ($input['price_monthly'] ?? 0);
    $priceYearly = isset($input['price_yearly']) ? (float) $input['price_yearly'] : null;
    $maxEvents = (int) ($input['max_events'] ?? 1);
    $maxSongsPerDay = (int) ($input['max_songs_per_day'] ?? 30);
    $maxKjs = (int) ($input['max_kjs'] ?? 1);
    $features = $input['features'] ?? [];
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    
    if (empty($code) || empty($name)) {
        errorResponse('Código e nome são obrigatórios', 400);
    }
    
    // Verifica se código já existe
    $stmt = $pdo->prepare("SELECT id FROM plans WHERE code = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        errorResponse('Código já existe', 409);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO plans (code, name, price_monthly, price_yearly, max_events, max_songs_per_day, max_kjs, features, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $code,
        $name,
        $priceMonthly,
        $priceYearly,
        $maxEvents,
        $maxSongsPerDay,
        $maxKjs,
        json_encode($features),
        $sortOrder
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Plano criado com sucesso',
        'data' => ['id' => $pdo->lastInsertId()]
    ], 201);
}

/**
 * Atualiza um plano existente (Super Admin)
 */
function updatePlan(PDO $pdo): void
{
    $id = (int) ($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        errorResponse('ID do plano obrigatório', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Campos permitidos para atualização
    $allowedFields = ['name', 'price_monthly', 'price_yearly', 'max_events', 'max_songs_per_day', 'max_kjs', 'features', 'is_active', 'sort_order'];
    
    $fields = [];
    $values = [];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $value = $input[$field];
            
            if ($field === 'features' && is_array($value)) {
                $value = json_encode($value);
            }
            
            $fields[] = "$field = ?";
            $values[] = $value;
        }
    }
    
    if (empty($fields)) {
        errorResponse('Nenhum campo para atualizar', 400);
    }
    
    $values[] = $id;
    $sql = "UPDATE plans SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    jsonResponse([
        'success' => true,
        'message' => 'Plano atualizado com sucesso'
    ]);
}
