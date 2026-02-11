<?php
namespace App\Models;

use PDO;

class AuditLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \getDbConnection();
    }

    /**
     * Registra um evento no log.
     */
    public function log(string $action, ?string $table = null, ?int $recordId = null, $old = null, $new = null): void
    {
        $user = \App\Core\SessionManager::getUser();
        $userId = $user['id'] ?? null;

        $sql = "INSERT INTO tb_audit_logs 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (:u, :a, :t, :r, :old, :new, :ip, :ua)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':u'   => $userId,
            ':a'   => $action,
            ':t'   => $table,
            ':r'   => $recordId,
            ':old' => $old ? json_encode($old) : null,
            ':new' => $new ? json_encode($new) : null,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Lista os logs mais recentes com nome do usuário.
     */
    public function findAll(int $limit = 100): array
    {
        $sql = "SELECT l.*, u.name as user_name 
                FROM tb_audit_logs l 
                LEFT JOIN tb_users u ON u.id = l.user_id 
                ORDER BY l.created_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um log específico por ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT l.*, u.name as user_name 
                FROM tb_audit_logs l 
                LEFT JOIN tb_users u ON u.id = l.user_id 
                WHERE l.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
}