<?php
/**
 * API de Favoritos - WosKaraoke
 * 
 * GET /api/favorites.php?token=XXX - Listar favoritos do perfil
 * POST /api/favorites.php - Adicionar favorito (body: {token, song_code, song_title, song_artist})
 * DELETE /api/favorites.php?token=XXX&song_code=YYY - Remover favorito
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

// Rate limiting para favoritos: 30 por minuto
$rateLimiter = new WosKaraoke\RateLimiter();
if (!$rateLimiter->check('favorites')) {
    $rateLimiter->tooManyRequests('favorites');
}
$rateLimiter->addHeaders('favorites');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDatabase();

    switch ($method) {
        case 'GET':
            // Listar favoritos
            $token = $_GET['token'] ?? '';
            
            if (empty($token)) {
                errorResponse('Token é obrigatório');
            }

            // Buscar perfil
            $profile = getProfileByToken($pdo, $token);
            if (!$profile) {
                errorResponse('Perfil não encontrado', 404);
            }

            $stmt = $pdo->prepare("
                SELECT id, song_code, song_title, song_artist, created_at
                FROM favorites
                WHERE profile_id = :profile_id
                ORDER BY created_at DESC
            ");
            $stmt->execute(['profile_id' => $profile['id']]);
            $favorites = $stmt->fetchAll();

            jsonResponse([
                'success' => true,
                'data' => $favorites
            ]);
            break;

        case 'POST':
            // Adicionar favorito
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['token'])) {
                errorResponse('Token é obrigatório');
            }
            if (empty($input['song_code'])) {
                errorResponse('Código da música é obrigatório');
            }

            $profile = getProfileByToken($pdo, $input['token']);
            if (!$profile) {
                errorResponse('Perfil não encontrado', 404);
            }

            // Verificar se já existe
            $stmt = $pdo->prepare("
                SELECT id FROM favorites 
                WHERE profile_id = :profile_id AND song_code = :song_code
            ");
            $stmt->execute([
                'profile_id' => $profile['id'],
                'song_code' => $input['song_code']
            ]);
            
            if ($stmt->fetch()) {
                errorResponse('Música já está nos favoritos');
            }

            // Inserir favorito
            $stmt = $pdo->prepare("
                INSERT INTO favorites (profile_id, song_code, song_title, song_artist)
                VALUES (:profile_id, :song_code, :song_title, :song_artist)
            ");
            $stmt->execute([
                'profile_id' => $profile['id'],
                'song_code' => $input['song_code'],
                'song_title' => $input['song_title'] ?? '',
                'song_artist' => $input['song_artist'] ?? ''
            ]);

            jsonResponse([
                'success' => true,
                'message' => 'Favorito adicionado',
                'data' => [
                    'id' => (int) $pdo->lastInsertId(),
                    'song_code' => $input['song_code']
                ]
            ], 201);
            break;

        case 'DELETE':
            // Remover favorito
            $token = $_GET['token'] ?? '';
            $songCode = $_GET['song_code'] ?? '';
            
            if (empty($token) || empty($songCode)) {
                errorResponse('Token e código da música são obrigatórios');
            }

            $profile = getProfileByToken($pdo, $token);
            if (!$profile) {
                errorResponse('Perfil não encontrado', 404);
            }

            $stmt = $pdo->prepare("
                DELETE FROM favorites
                WHERE profile_id = :profile_id AND song_code = :song_code
            ");
            $stmt->execute([
                'profile_id' => $profile['id'],
                'song_code' => $songCode
            ]);

            if ($stmt->rowCount() === 0) {
                errorResponse('Favorito não encontrado', 404);
            }

            jsonResponse([
                'success' => true,
                'message' => 'Favorito removido'
            ]);
            break;

        default:
            errorResponse('Método não permitido', 405);
    }

} catch (Throwable $e) {
    error_log("Favorites API Error: " . $e->getMessage());
    errorResponse('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Busca perfil pelo token
 */
function getProfileByToken(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare("SELECT id, name FROM profiles WHERE token = :token");
    $stmt->execute(['token' => $token]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}
