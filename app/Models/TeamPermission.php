<?php
namespace App\Models;

use PDO;

class TeamPermission {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Retorna todas as permissões associadas a um time.
     */
    public function getPermissionsByTeam(int $teamId): array {
        $sql = "
            SELECT 
                tp.team_id,
                tp.permission_id,
                tp.allowed_till_role_id
            FROM tb_team_permissions tp
            WHERE tp.team_id = :team_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Seta (substitui) todas as permissões de um time.
     * Estratégia simples: apaga tudo e insere o que veio.
     */
    public function setPermissionsForTeam(int $teamId, array $permissionIds, ?int $allowedTillRoleId = null): void {
        $this->db->beginTransaction();
        try {
            // Remove todas as permissões antigas do time
            $del = $this->db->prepare("DELETE FROM tb_team_permissions WHERE team_id = :team_id");
            $del->execute([':team_id' => $teamId]);

            if (!empty($permissionIds)) {
                $ins = $this->db->prepare("
                    INSERT INTO tb_team_permissions (team_id, permission_id, allowed_till_role_id)
                    VALUES (:team_id, :permission_id, :allowed_till_role_id)
                ");

                foreach ($permissionIds as $pid) {
                    $ins->execute([
                        ':team_id' => $teamId,
                        ':permission_id' => (int)$pid,
                        ':allowed_till_role_id' => $allowedTillRoleId ?: null
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}