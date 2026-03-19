<?php
/**
 * Audit Logger - Registro de ações administrativas
 * 
 * Registra todas as ações importantes para auditoria e segurança
 * 
 * @author WosKaraoke
 * @version 1.0
 */

declare(strict_types=1);

namespace WosKaraoke;

class AuditLogger
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    /**
     * Registra uma ação de auditoria
     * 
     * @param string $userType Tipo do usuário (admin, superadmin, establishment, profile)
     * @param int|null $userId ID do usuário
     * @param string $action Ação realizada (create, update, delete, login, logout, etc)
     * @param string|null $targetTable Tabela afetada
     * @param int|null $targetId ID do registro afetado
     * @param array|null $oldData Dados antes da alteração
     * @param array|null $newData Dados após a alteração
     */
    public function log(
        string $userType,
        ?int $userId,
        string $action,
        ?string $targetTable = null,
        ?int $targetId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs 
                (user_type, user_id, action, target_table, target_id, old_data, new_data, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userType,
                $userId,
                $action,
                $targetTable,
                $targetId,
                $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Silently fail - não queremos que erro de log quebre a aplicação
            error_log('Audit log error: ' . $e->getMessage());
        }
    }

    // ============================================
    // MÉTODOS AUXILIARES DE LOG
    // ============================================

    /**
     * Log de login bem-sucedido
     */
    public function logLogin(string $userType, int $userId, string $username): void
    {
        $this->log($userType, $userId, 'login', null, null, null, ['username' => $username]);
    }

    /**
     * Log de login falho
     */
    public function logFailedLogin(string $userType, string $username, string $reason = 'invalid_credentials'): void
    {
        $this->log($userType, null, 'login_failed', null, null, null, [
            'username' => $username,
            'reason' => $reason,
        ]);
    }

    /**
     * Log de logout
     */
    public function logLogout(string $userType, int $userId): void
    {
        $this->log($userType, $userId, 'logout');
    }

    /**
     * Log de criação de registro
     */
    public function logCreate(string $userType, ?int $userId, string $table, int $recordId, array $data): void
    {
        // Remove campos sensíveis
        $data = $this->sanitizeData($data);
        $this->log($userType, $userId, 'create', $table, $recordId, null, $data);
    }

    /**
     * Log de atualização de registro
     */
    public function logUpdate(string $userType, ?int $userId, string $table, int $recordId, array $oldData, array $newData): void
    {
        $oldData = $this->sanitizeData($oldData);
        $newData = $this->sanitizeData($newData);
        $this->log($userType, $userId, 'update', $table, $recordId, $oldData, $newData);
    }

    /**
     * Log de exclusão de registro
     */
    public function logDelete(string $userType, ?int $userId, string $table, int $recordId, array $data): void
    {
        $data = $this->sanitizeData($data);
        $this->log($userType, $userId, 'delete', $table, $recordId, $data, null);
    }

    /**
     * Log de ação na fila de músicas
     */
    public function logQueueAction(string $action, int $adminId, int $queueId, array $details = []): void
    {
        $this->log('admin', $adminId, 'queue_' . $action, 'queue', $queueId, null, $details);
    }

    /**
     * Log de ação em batalha
     */
    public function logBattleAction(string $action, int $adminId, int $battleId, array $details = []): void
    {
        $this->log('admin', $adminId, 'battle_' . $action, 'battles', $battleId, null, $details);
    }

    // ============================================
    // CONSULTAS
    // ============================================

    /**
     * Busca logs com filtros
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_type'])) {
            $where[] = 'user_type = ?';
            $params[] = $filters['user_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }

        if (!empty($filters['target_table'])) {
            $where[] = 'target_table = ?';
            $params[] = $filters['target_table'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = ?';
            $params[] = $filters['ip_address'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare("
            SELECT * FROM audit_logs 
            WHERE $whereClause 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");

        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de logs com filtros
     */
    public function count(array $filters = []): int
    {
        // Mesma lógica de where do search
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_type'])) {
            $where[] = 'user_type = ?';
            $params[] = $filters['user_type'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE $whereClause");
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    private function ensureTable(): void
    {
        $intPK = Helpers::intPK();
        $autoInc = Helpers::autoIncrement();
        $engine = Helpers::engineSuffix();

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id {$intPK} PRIMARY KEY {$autoInc},
                    user_type VARCHAR(20) NOT NULL,
                    user_id INT DEFAULT NULL,
                    action VARCHAR(100) NOT NULL,
                    target_table VARCHAR(50) DEFAULT NULL,
                    target_id INT DEFAULT NULL,
                    old_data JSON DEFAULT NULL,
                    new_data JSON DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ){$engine}
            ");

            Helpers::createIndex($this->pdo, 'idx_audit_user', 'audit_logs', 'user_type, user_id');
            Helpers::createIndex($this->pdo, 'idx_audit_action', 'audit_logs', 'action');
            Helpers::createIndex($this->pdo, 'idx_audit_date', 'audit_logs', 'created_at');
        } catch (\Exception $e) {
            // Table already exists
        }
    }

    private function getClientIP(): string
    {
        return Helpers::getClientIP();
    }

    private function sanitizeData(array $data): array
    {
        // Remove campos sensíveis dos logs
        $sensitiveFields = ['password', 'password_hash', 'token', 'secret', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
