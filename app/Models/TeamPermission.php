<?php
namespace App\Models;

use PDO;

/**
 * app/Models/TeamPermission.php
 *
 * Extensões ao model original para suportar:
 * - consultas com detalhes (nome da permissão, aba)
 * - retorno de todas as permissões indicando se estão atribuídas ao time
 * - possibilidade de salvar permissões com allowed_till_role_id por permissão
 *
 * Mantive o estilo PDO / getDbConnection() do projeto.
 */
class TeamPermission {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Retorna todas as permissões associadas a um time (raw).
     * Mantido para compatibilidade com código existente.
     *
     * @param int $teamId
     * @return array
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
     * Retorna as permissões vinculadas ao time com detalhes (permission name, tab name,
     * allowed_till_role_id e allowed_till_role_level quando existirem).
     *
     * Útil para exibir a listagem do time com o contexto das abas.
     *
     * @param int $teamId
     * @return array
     */
    public function getPermissionsByTeamWithDetails(int $teamId): array {
        $sql = "
            SELECT
                tp.team_id,
                tp.permission_id,
                tp.allowed_till_role_id,
                p.name AS permission_name,
                p.tab_id,
                t.nome AS tab_name,
                r.level AS allowed_till_role_level,
                r.name AS allowed_till_role_name
            FROM tb_team_permissions tp
            JOIN tb_permissions p ON p.id = tp.permission_id
            LEFT JOIN tb_tabs t ON t.id = p.tab_id
            LEFT JOIN tb_roles r ON r.id = tp.allowed_till_role_id
            WHERE tp.team_id = :team_id
            ORDER BY COALESCE(t.nome, 'ZZZ'), p.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todas as permissões do sistema com indicação se já estão atribuídas ao time.
     * Cada item contém:
     *  - id, name, tab_id, tab_name
     *  - assigned (0/1)
     *  - allowed_till_role_id (se atribuído)
     *
     * Útil para montar a tela agrupada por Aba e marcar checkboxes.
     *
     * @param int $teamId
     * @return array
     */
    public function getAllPermissionsWithAssignment(int $teamId): array {
        $sql = "
            SELECT
                p.id AS permission_id,
                p.name AS permission_name,
                p.tab_id,
                t.nome AS tab_name,
                CASE WHEN tp.permission_id IS NULL THEN 0 ELSE 1 END AS assigned,
                tp.allowed_till_role_id
            FROM tb_permissions p
            LEFT JOIN tb_tabs t ON t.id = p.tab_id
            LEFT JOIN tb_team_permissions tp ON tp.permission_id = p.id AND tp.team_id = :team_id
            ORDER BY COALESCE(t.nome, 'ZZZ'), p.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Salva (substitui) as permissões do time. Aqui aceitamos um mapa associativo
     * $permissionsMap onde a chave é permission_id e o valor é allowed_till_role_id|null.
     *
     * Exemplo:
     *   [
     *     10 => 3,    // permission_id 10 com allowed_till_role_id = 3
     *     11 => null, // permission_id 11 sem limite (NULL)
     *   ]
     *
     * Estratégia: delete all + insert (em transação).
     *
     * @param int $teamId
     * @param array $permissionsMap
     * @throws \Throwable
     * @return void
     */
    public function setPermissionsForTeamAssociative(int $teamId, array $permissionsMap): void {
        $this->db->beginTransaction();
        try {
            // Remove todas as permissões antigas do time
            $del = $this->db->prepare("DELETE FROM tb_team_permissions WHERE team_id = :team_id");
            $del->execute([':team_id' => $teamId]);

            if (!empty($permissionsMap)) {
                $ins = $this->db->prepare("
                    INSERT INTO tb_team_permissions (team_id, permission_id, allowed_till_role_id)
                    VALUES (:team_id, :permission_id, :allowed_till_role_id)
                ");

                foreach ($permissionsMap as $pid => $allowedTillRoleId) {
                    $ins->execute([
                        ':team_id' => $teamId,
                        ':permission_id' => (int)$pid,
                        ':allowed_till_role_id' => $allowedTillRoleId !== null ? (int)$allowedTillRoleId : null
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Helper: retorna allowed_till_role_id para uma permissão específica do time.
     *
     * @param int $teamId
     * @param int $permissionId
     * @return int|null
     */
    public function findAllowedTillForPermission(int $teamId, int $permissionId): ?int {
        $stmt = $this->db->prepare("
            SELECT allowed_till_role_id
            FROM tb_team_permissions
            WHERE team_id = :team_id AND permission_id = :permission_id
            LIMIT 1
        ");
        $stmt->execute([':team_id' => $teamId, ':permission_id' => $permissionId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : ($val === null ? null : (int)$val);
    }
}