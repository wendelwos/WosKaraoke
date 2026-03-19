
/**
 * Login com Google
 */
function loginWithGoogle(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['credential'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token Google é obrigatório');
    }
    
    // 1. Validar Token via Google REST API (Lightweight)
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
    $responseRaw = @file_get_contents($url);
    
    if ($responseRaw === false) {
        errorResponse('Token inválido ou expirado (Google API Error)', 401);
    }
    
    $payload = json_decode($responseRaw, true);
    
    if (empty($payload['aud'])) {
        errorResponse('Resposta inválida do Google', 401);
    }
    
    // Verificar Client ID
    if ($payload['aud'] !== GOOGLE_CLIENT_ID) {
        errorResponse('Token não pertence a este aplicativo', 401);
    }
    
    // Dados do usuário
    $googleId = $payload['sub'];
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? 'Usuário Google';
    $picture = $payload['picture'] ?? '';
    
    // 2. Verificar se usuário existe
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $profile = $stmt->fetch();
    
    if ($profile) {
        // Atualiza dados se necessário
        $stmt = $pdo->prepare("UPDATE profiles SET google_id = ?, avatar_url = ? WHERE id = ?");
        $stmt->execute([$googleId, $picture, $profile['id']]);
        
        // Pega objeto atualizado
        $profile['google_id'] = $googleId;
        $profile['avatar_url'] = $picture;
    } else {
        // Cria novo usuário
        $newToken = generateToken();
        $color = getRandomAvatarColor();
        
        $stmt = $pdo->prepare("
            INSERT INTO profiles (name, token, avatar_color, google_id, email, avatar_url)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $newToken, $color, $googleId, $email, $picture]);
        
        $profileId = $pdo->lastInsertId();
        
        // Reconstrói array do perfil
        $profile = [
            'id' => $profileId,
            'name' => $name,
            'token' => $newToken,
            'avatar_color' => $color,
            'google_id' => $googleId,
            'email' => $email,
            'avatar_url' => $picture,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Contar favoritos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE profile_id = ?");
    $stmt->execute([$profile['id']]);
    $favoritesCount = (int) $stmt->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => (int) $profile['id'],
            'name' => $profile['name'],
            'token' => $profile['token'],
            'avatar_color' => $profile['avatar_color'],
            'avatar_url' => $profile['avatar_url'],
            'initials' => getInitials($profile['name']),
            'favorites_count' => $favoritesCount,
            'is_google' => true,
            'created_at' => $profile['created_at']
        ]
    ]);
}
