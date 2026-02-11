<?php
namespace App\Models;

use PDO;

class Permission {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Lista todas as permissões.
     * Se depois você tiver categorias, pode ordenar/agrupá-las aqui.
     */
    public function findAll(): array {
        $sql = "SELECT id, name FROM tb_permissions ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, name FROM tb_permissions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        return $perm ?: null;
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, name FROM tb_permissions WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os nomes das permissões diretas de um usuário (tb_user_permissions).
     */
    public function getDirectPermissionsByUser(int $userId): array
    {
        $db = getDbConnection();
        $stmt = $db->prepare("
            SELECT p.name
            FROM tb_permissions p
            INNER JOIN tb_user_permissions up ON up.permission_id = p.id
            WHERE up.user_id = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}