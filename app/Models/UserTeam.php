<?php
namespace App\Models;

use PDO;
use PDOException;

class UserTeam
{
    private PDO $db;
    private ?bool $hasRoleIdColumn = null;
    private ?bool $roleIdNullable = null;

    public function __construct()
    {
        $this->db = \getDbConnection();
    }

    /**
     * Checa se a coluna role_id existe em tb_user_teams (cacheado).
     */
    private function checkRoleIdColumn(): void
    {
        if ($this->hasRoleIdColumn !== null) {
            return;
        }

        $sql = "
            SELECT ISNULL( (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'tb_user_teams' 
                              AND COLUMN_NAME = 'role_id') ) AS not_exists
        ";
        try {
            // Query simplificada: se INFORMATION_SCHEMA disponível
            $stmt = $this->db->query("
                SELECT COLUMN_NAME, IS_NULLABLE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'tb_user_teams'
                  AND COLUMN_NAME = 'role_id'
                LIMIT 1
            ");
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($col) {
                $this->hasRoleIdColumn = true;
                $this->roleIdNullable = (isset($col['IS_NULLABLE']) && strtoupper($col['IS_NULLABLE']) === 'YES');
            } else {
                $this->hasRoleIdColumn = false;
                $this->roleIdNullable = null;
            }
        } catch (\Throwable $e) {
            // Em caso de DB que não exponha INFORMATION_SCHEMA por permissões,
            // assumimos que a coluna existe (fallback conservador) e que não é nullable.
            $this->hasRoleIdColumn = true;
            $this->roleIdNullable = false;
        }
    }

    /**
     * Adiciona um usuário ao time (não altera cargo global).
     * Compatível com esquema antigo (role_id pode existir) e novo.
     *
     * Retorna true se inserido/existente, false em erro.
     */
    public function addMember(int $teamId, int $userId): bool
    {
        $this->checkRoleIdColumn();

        try {
            if ($this->hasRoleIdColumn) {
                // Tenta inserir sem role_id (NULL) — se a coluna aceitar NULL, ok.
                // Caso não aceite, fallback para inserir com role_id = 0 (ou manter existente).
                $sql = "INSERT IGNORE INTO tb_user_teams (user_id, team_id" . ($this->roleIdNullable ? ", role_id" : "") . ") VALUES (:uid, :tid" . ($this->roleIdNullable ? ", NULL" : "") . ")";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':uid' => $userId, ':tid' => $teamId]);
                // Se a coluna não for nullable e a primeira tentativa falhar, tentar inserir com role_id = 0
                if (!$this->roleIdNullable) {
                    // Verifica se existiu registro (talvez já exista)
                    $check = $this->db->prepare("SELECT 1 FROM tb_user_teams WHERE user_id = :uid AND team_id = :tid LIMIT 1");
                    $check->execute([':uid' => $userId, ':tid' => $teamId]);
                    if (!$check->fetch()) {
                        // Tenta inserir com role_id = 0 (assumindo que 0 seja um sentinel; melhor migrar DB para NULL)
                        $stmt2 = $this->db->prepare("INSERT IGNORE INTO tb_user_teams (user_id, team_id, role_id) VALUES (:uid, :tid, 0)");
                        $stmt2->execute([':uid' => $userId, ':tid' => $teamId]);
                    }
                }
            } else {
                // Sem coluna role_id — inserção simples
                $stmt = $this->db->prepare("INSERT IGNORE INTO tb_user_teams (user_id, team_id) VALUES (:uid, :tid)");
                $stmt->execute([':uid' => $userId, ':tid' => $teamId]);
            }

            return true;
        } catch (PDOException $e) {
            // Caso a primeira abordagem falhe por constraints, tentar fallback direto
            try {
                $stmt = $this->db->prepare("INSERT IGNORE INTO tb_user_teams (user_id, team_id, role_id) VALUES (:uid, :tid, NULL)");
                $stmt->execute([':uid' => $userId, ':tid' => $teamId]);
                return true;
            } catch (\Throwable $ex) {
                error_log("UserTeam::addMember error: " . $ex->getMessage());
                return false;
            }
        }
    }

    /**
     * Adiciona ou atualiza membro do time com role opcional.
     * Se $roleId === null -> não altera role (ou tenta inserir sem role_id).
     *
     * Mantém compatibilidade com 'addOrUpdateMember' antigo.
     */
    public function addOrUpdateMember(int $teamId, int $userId, ?int $roleId = null): bool
    {
        $this->checkRoleIdColumn();

        try {
            if ($this->hasRoleIdColumn) {
                // Use upsert com role_id (pode ser NULL)
                // Se DB não aceitar NULL, o bind de NULL será convertido e pode falhar; capturamos exceção.
                $sql = "INSERT INTO tb_user_teams (user_id, team_id, role_id) VALUES (:uid, :tid, :rid)
                        ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':tid', $teamId, PDO::PARAM_INT);
                if ($roleId === null) {
                    $stmt->bindValue(':rid', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':rid', $roleId, PDO::PARAM_INT);
                }
                $stmt->execute();
            } else {
                // Sem coluna role_id; apenas garantir existência da relação
                $stmt = $this->db->prepare("INSERT IGNORE INTO tb_user_teams (user_id, team_id) VALUES (:uid, :tid)");
                $stmt->execute([':uid' => $userId, ':tid' => $teamId]);
            }

            return true;
        } catch (PDOException $e) {
            // Fallback simples: tentar inserir sem role_id
            try {
                $stmt = $this->db->prepare("INSERT IGNORE INTO tb_user_teams (user_id, team_id) VALUES (:uid, :tid)");
                $stmt->execute([':uid' => $userId, ':tid' => $teamId]);
                return true;
            } catch (\Throwable $ex) {
                error_log("UserTeam::addOrUpdateMember error: " . $ex->getMessage());
                return false;
            }
        }
    }

    /**
     * Remove um membro do time.
     */
    public function removeMember(int $teamId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM tb_user_teams WHERE team_id = :tid AND user_id = :uid");
            $stmt->execute([':tid' => $teamId, ':uid' => $userId]);
            return true;
        } catch (\Throwable $e) {
            error_log("UserTeam::removeMember error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna membros do time com cargos globais agregados (role_names, min_role_level).
     * Também retorna dados legacy de role por time se existir.
     *
     * Campos retornados por item:
     *  - id (user id)
     *  - name
     *  - email
     *  - min_role_level (int)
     *  - role_names (string|null)  // cargos globais concatenados
     *  - team_role_id (nullable)   // role_id armazenado em tb_user_teams se existir
     *  - team_role_name (nullable)
     */
    public function getMembersByTeam(int $teamId): array
    {
        $sql = "
            SELECT 
                u.id AS id,
                u.name AS name,
                u.email AS email,
                COALESCE(MIN(r.level), 9999) AS min_role_level,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names,
                ut.role_id AS team_role_id,
                tr.name AS team_role_name
            FROM tb_user_teams ut
            JOIN tb_users u ON ut.user_id = u.id
            LEFT JOIN tb_user_roles ur ON u.id = ur.user_id
            LEFT JOIN tb_roles r ON ur.role_id = r.id
            LEFT JOIN tb_roles tr ON ut.role_id = tr.id
            WHERE ut.team_id = :tid
            GROUP BY u.id, ut.role_id
            ORDER BY min_role_level ASC, u.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tid' => $teamId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normaliza tipos
        foreach ($rows as &$row) {
            $row['min_role_level'] = isset($row['min_role_level']) ? (int)$row['min_role_level'] : null;
            $row['team_role_id'] = isset($row['team_role_id']) ? ($row['team_role_id'] === '' ? null : (int)$row['team_role_id']) : null;
            if ($row['role_names'] === null) $row['role_names'] = null;
        }

        return $rows;
    }

    /**
     * Retorna times em que o usuário participa, com cargos globais agregados e info legacy do role por time.
     *
     * Campos retornados por item:
     *  - team_id
     *  - team_name
     *  - team_role_id (nullable)
     *  - team_role_name (nullable)
     *  - min_role_level (int)
     *  - role_names (string|null)
     */
    public function getTeamsByUser(int $userId): array
    {
        $sql = "
            SELECT 
                t.id AS team_id,
                t.name AS team_name,
                ut.role_id AS team_role_id,
                tr.name AS team_role_name,
                COALESCE(MIN(r.level), 9999) AS min_role_level,
                GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names
            FROM tb_user_teams ut
            JOIN tb_teams t ON ut.team_id = t.id
            LEFT JOIN tb_roles tr ON ut.role_id = tr.id
            LEFT JOIN tb_user_roles ur ON ut.user_id = ur.user_id
            LEFT JOIN tb_roles r ON ur.role_id = r.id
            WHERE ut.user_id = :uid
            GROUP BY t.id, ut.role_id
            ORDER BY t.name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['min_role_level'] = isset($row['min_role_level']) ? (int)$row['min_role_level'] : null;
            $row['team_role_id'] = isset($row['team_role_id']) ? ($row['team_role_id'] === '' ? null : (int)$row['team_role_id']) : null;
        }

        return $rows;
    }
}