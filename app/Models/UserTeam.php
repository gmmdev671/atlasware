<?php
namespace App\Models;

use PDO;

class UserTeam
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \getDbConnection();
    }

    /**
     * Lista membros de um time com nome do usuário e da role.
     */
    public function getMembersByTeam(int $teamId): array
    {
        $sql = "
            SELECT 
                ut.user_id,
                ut.team_id,
                ut.role_id,
                u.name AS user_name,
                u.email AS user_email,
                r.name AS role_name
            FROM tb_user_teams ut
            INNER JOIN tb_users u ON u.id = ut.user_id
            INNER JOIN tb_roles r ON r.id = ut.role_id
            WHERE ut.team_id = :team_id
            ORDER BY u.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Adiciona (ou atualiza) um membro do time.
     */
    public function addOrUpdateMember(int $teamId, int $userId, int $roleId): void
    {
        // Se já existe registro, atualiza; senão, insere
        $sql = "
            INSERT INTO tb_user_teams (user_id, team_id, role_id)
            VALUES (:user_id, :team_id, :role_id)
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':team_id' => $teamId,
            ':role_id' => $roleId,
        ]);
    }

    /**
     * Remove um membro do time.
     */
    public function removeMember(int $teamId, int $userId): void
    {
        $sql = "DELETE FROM tb_user_teams WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $userId,
        ]);
    }

    /**
     * Retorna times em que o usuário participa, com nome do time e cargo no time.
     */
    public function getTeamsByUser(int $userId): array
    {
        $sql = "
            SELECT 
                ut.user_id,
                ut.team_id,
                ut.role_id,
                t.name AS team_name,
                r.name AS role_name
            FROM tb_user_teams ut
            INNER JOIN tb_teams t ON t.id = ut.team_id
            INNER JOIN tb_roles r ON r.id = ut.role_id
            WHERE ut.user_id = :user_id
            ORDER BY t.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}