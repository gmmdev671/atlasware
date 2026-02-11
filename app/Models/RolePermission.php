<?php
namespace App\Models;

use PDO;

class RolePermission {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Retorna todas as permissões de um cargo
     */
    public function getPermissionsByRole(int $roleId): array {
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.description
            FROM tb_permissions p
            INNER JOIN tb_role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            ORDER BY p.name
        ");
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna IDs das permissões de um cargo
     */
    public function getPermissionIdsByRole(int $roleId): array {
        $stmt = $this->db->prepare("
            SELECT permission_id FROM tb_role_permissions WHERE role_id = :role_id
        ");
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Substitui todas as permissões de um cargo
     */
    public function syncPermissions(int $roleId, array $permissionIds): bool {
        try {
            $this->db->beginTransaction();

            // Remove todas as permissões atuais
            $stmt = $this->db->prepare("DELETE FROM tb_role_permissions WHERE role_id = :role_id");
            $stmt->execute([':role_id' => $roleId]);

            // Insere as novas permissões
            if (!empty($permissionIds)) {
                $stmt = $this->db->prepare("
                    INSERT INTO tb_role_permissions (role_id, permission_id) 
                    VALUES (:role_id, :permission_id)
                ");
                foreach ($permissionIds as $permId) {
                    $stmt->execute([
                        ':role_id' => $roleId,
                        ':permission_id' => $permId
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}