<?php
/**
 * Auth - Middleware centralizado de autenticacao
 *
 * Uso em qualquer endpoint:
 *   require_once __DIR__ . '/../includes/Auth.php';
 *   $admin = WosKaraoke\Auth::requireAdmin();
 *   $sa    = WosKaraoke\Auth::requireSuperAdmin();
 *   $estab = WosKaraoke\Auth::requireEstablishment();
 */

declare(strict_types=1);

namespace WosKaraoke;

class Auth
{
    /**
     * Inicia sessao padrao se ainda nao foi iniciada
     */
    public static function startSession(string $name = ''): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if ($name !== '') {
                session_name($name);
            }
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_httponly', '1');
            session_start();
        }
    }

    /**
     * Exige admin logado. Retorna dados do admin ou aborta com 401.
     */
    public static function requireAdmin(): array
    {
        self::startSession();

        if (empty($_SESSION['admin_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Acesso nao autorizado']);
            exit;
        }

        return [
            'id' => (int) $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'] ?? '',
            'name' => $_SESSION['admin_name'] ?? '',
            'establishment_id' => $_SESSION['establishment_id'] ?? null,
            'role' => $_SESSION['admin_role'] ?? 'kj',
        ];
    }

    /**
     * Retorna admin logado ou null (sem abortar)
     */
    public static function getAdmin(): ?array
    {
        self::startSession();

        if (empty($_SESSION['admin_id'])) {
            return null;
        }

        return [
            'id' => (int) $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'] ?? '',
            'name' => $_SESSION['admin_name'] ?? '',
            'establishment_id' => $_SESSION['establishment_id'] ?? null,
            'role' => $_SESSION['admin_role'] ?? 'kj',
        ];
    }

    /**
     * Exige super admin logado.
     */
    public static function requireSuperAdmin(): array
    {
        self::startSession('SUPERADMIN_SESSION');

        if (empty($_SESSION['super_admin_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Nao autenticado']);
            exit;
        }

        return [
            'id' => (int) $_SESSION['super_admin_id'],
            'name' => $_SESSION['super_admin_name'] ?? '',
            'username' => $_SESSION['super_admin_username'] ?? '',
        ];
    }

    /**
     * Exige estabelecimento logado.
     */
    public static function requireEstablishment(): array
    {
        self::startSession('ESTABLISHMENT_SESSION');

        if (empty($_SESSION['establishment_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Nao autenticado']);
            exit;
        }

        return [
            'id' => (int) $_SESSION['establishment_id'],
            'name' => $_SESSION['establishment_name'] ?? '',
            'slug' => $_SESSION['establishment_slug'] ?? '',
        ];
    }
}
