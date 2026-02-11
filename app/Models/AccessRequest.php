<?php
namespace App\Models;

use PDO;

class AccessRequest {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Cria uma nova solicitação.
     * $data deve conter: user_id, requested_by, type, payload (array), justification
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO tb_access_requests 
            (user_id, requested_by, type, payload, justification, status, created_at) 
            VALUES (:user_id, :requested_by, :type, :payload, :justification, 'pending', NOW())
        ");
        
        $stmt->execute([
            ':user_id'      => $data['user_id'],
            ':requested_by' => $data['requested_by'] ?? $data['user_id'],
            ':type'         => $data['type'],
            ':payload'      => json_encode($data['payload']),
            ':justification'=> $data['justification'] ?? null
        ]);
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Busca solicitações de um usuário específico (histórico dele).
     */
    public function findByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT ar.*, u.name as user_name 
            FROM tb_access_requests ar
            JOIN tb_users u ON ar.user_id = u.id
            WHERE ar.user_id = :user_id 
            ORDER BY ar.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['payload'] = json_decode($row['payload'], true);
        }
        return $results;
    }

    /**
     * Busca todas as solicitações pendentes para os aprovadores.
     */
    public function findPending(): array {
        $stmt = $this->db->query("
            SELECT ar.*, u.name as user_name, rb.name as requester_name
            FROM tb_access_requests ar
            JOIN tb_users u ON ar.user_id = u.id
            JOIN tb_users rb ON ar.requested_by = rb.id
            WHERE ar.status = 'pending' 
            ORDER BY ar.created_at ASC
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as &$row) {
            $row['payload'] = json_decode($row['payload'], true);
        }
        return $results;
    }

    /**
     * Busca uma solicitação específica por ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT ar.*, u.name as user_name, rb.name as requester_name
            FROM tb_access_requests ar
            JOIN tb_users u ON ar.user_id = u.id
            JOIN tb_users rb ON ar.requested_by = rb.id
            WHERE ar.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($res) {
            $res['payload'] = json_decode($res['payload'], true);
            return $res;
        }
        return null;
    }

    /**
     * Atualiza o status (Aprovar/Rejeitar).
     * Só permite atualizar se o status atual for 'pending'.
     */
    public function updateStatus(int $id, string $status, int $reviewedBy, ?string $comment = null): bool {
        $stmt = $this->db->prepare("
            UPDATE tb_access_requests 
            SET status = :status, 
                reviewed_by = :reviewed_by, 
                reviewed_at = NOW(), 
                review_comment = :comment,
                updated_at = NOW()
            WHERE id = :id AND status = 'pending'
        ");
        
        $stmt->execute([
            ':status'      => $status,
            ':reviewed_by' => $reviewedBy,
            ':comment'     => $comment,
            ':id'          => $id
        ]);
        
        return $stmt->rowCount() > 0;
    }
}