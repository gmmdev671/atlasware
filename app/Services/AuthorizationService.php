<?php
namespace App\Services;

use PDO;

class AuthorizationService
{
    private PDO $db;
    private array $teamAncestorsCache = [];

    public function __construct()
    {
        $this->db = \getDbConnection(); // função global vinda do bootstrap
    }

    /**
     * Obtém o ID do usuário logado a partir da sessão.
     */
    private function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Verifica se o usuário possui subordinados (tb_users.id_lider).
     */
    public function hasSubordinates(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM tb_users
            WHERE id_lider = :lider_id
        ");
        $stmt->execute([':lider_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row) && (int)$row['total'] > 0;
    }

    /**
     * Motor de permissão atualizado:
     * 1) Permissão direta (tb_user_permissions)
     * 2) Permissão via cargo global (tb_user_roles + tb_role_permissions)
     * 3) Permissão concedida no escopo do time (tb_user_team_permissions)
     * 4) Permissão via Time (tb_team_permissions) - Varre o time e seus ancestrais
     */
    public function can(int $userId, string $permissionName, ?int $teamId = null): bool
    {
        // ATALHO MASTER: se o usuário for Master (min level == 1) -> tem tudo
        $userLevel = $this->getUserMinRoleLevel($userId);
        if ($userLevel !== null && $userLevel === 1) {
            return true;
        }

        // 1) Obter ID da permissão
        $stmt = $this->db->prepare("SELECT id FROM tb_permissions WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $permissionName]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$perm) {
            return false;
        }
        $permissionId = (int)$perm['id'];

        // 2) Permissão direta do usuário (tb_user_permissions)
        $stmt = $this->db->prepare("SELECT 1 FROM tb_user_permissions WHERE user_id = :u AND permission_id = :p LIMIT 1");
        $stmt->execute([':u' => $userId, ':p' => $permissionId]);
        if ($stmt->fetch()) {
            return true;
        }

        // 3) Permissão via cargos globais (tb_user_roles -> tb_role_permissions)
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tb_user_roles ur
            JOIN tb_role_permissions rp ON ur.role_id = rp.role_id
            WHERE ur.user_id = :u AND rp.permission_id = :p
            LIMIT 1
        ");
        $stmt->execute([':u' => $userId, ':p' => $permissionId]);
        if ($stmt->fetch()) {
            return true;
        }

        // 3.5) Permissão concedida especificamente para este usuário neste time (tb_user_team_permissions)
        if ($teamId !== null) {
            $stmt = $this->db->prepare("
                SELECT 1
                FROM tb_user_team_permissions
                WHERE user_id = :u AND team_id = :t AND permission_id = :p
                LIMIT 1
            ");
            $stmt->execute([':u' => $userId, ':t' => $teamId, ':p' => $permissionId]);
            if ($stmt->fetch()) {
                return true;
            }
        }

        // 4) Permissões definidas no time / hierarquia de times (tb_team_permissions)
        if ($teamId !== null) {
            $ancestors = $this->getTeamAncestors($teamId);      // [teamId, parent, ...]
            // $userLevel já calculado acima (pode ser null)
            $stmtTeamPerm = $this->db->prepare("
                SELECT allowed_till_role_id
                FROM tb_team_permissions
                WHERE team_id = :t AND permission_id = :p
                LIMIT 1
            ");
            $stmtRoleLevel = $this->db->prepare("SELECT level FROM tb_roles WHERE id = :rid LIMIT 1");

            foreach ($ancestors as $tId) {
                $stmtTeamPerm->execute([':t' => $tId, ':p' => $permissionId]);
                $teamPerm = $stmtTeamPerm->fetch(PDO::FETCH_ASSOC);

                if (!$teamPerm) {
                    continue;
                }

                // Se a permissão do time não tem restrição de nível, concede
                if ($teamPerm['allowed_till_role_id'] === null) {
                    return true;
                }

                // Há restrição: verificar o level do role limite
                $stmtRoleLevel->execute([':rid' => (int)$teamPerm['allowed_till_role_id']]);
                $limitRow = $stmtRoleLevel->fetch(PDO::FETCH_ASSOC);
                if (!$limitRow) {
                    continue;
                }

                $limitLevel = (int)$limitRow['level'];

                // Só concede se o usuário tiver algum cargo global e seu level for <= limitLevel
                if ($userLevel !== null && $userLevel <= $limitLevel) {
                    return true;
                }
            }
        }

        // Sem permissão encontrada
        return false;
    }

    /**
     * Versão do can() já usando o usuário logado.
     */
    public function canCurrent(string $permissionName, ?int $teamId = null): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->can($userId, $permissionName, $teamId);
    }

    // ============================================================
    //          MÉTODOS DE IDENTIFICAÇÃO DE CARGO
    // ============================================================

    /**
     * Verifica se o usuário tem um cargo específico (por nome) globalmente.
     * Usa LIKE para casar substrings (ex: "Master" casa com "01.00 Master").
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tb_user_roles ur
            JOIN tb_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id 
            AND LOWER(r.name) LIKE :pattern
            LIMIT 1
        ");
        $pattern = '%' . mb_strtolower($roleName) . '%';
        $stmt->execute([
            ':user_id'   => $userId,
            ':pattern'   => $pattern,
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * Verifica se o usuário logado tem um cargo específico.
     */
    public function hasRoleCurrent(string $roleName): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->hasRole($userId, $roleName);
    }

    /**
     * Atalho para verificar se o usuário é Master (Level 1).
     * Master sempre tem level 1 no nosso sistema.
     */
    public function isMaster(int $userId): bool
    {
        $level = $this->getUserMinRoleLevel($userId);
        return $level === 1;
    }

    /**
     * Verifica se o usuário é Master ou Coordenador (Baseado em nomes ou levels baixos).
     */
    public function isMasterOrCoordinator(int $userId): bool
    {
        // 1) Level-based shortcut: Master = level 1
        $level = $this->getUserMinRoleLevel($userId);
        if ($level !== null && $level === 1) {
            return true;
        }

        // 2) Substring match (ex: "Coordenador" presente no nome)
        if ($this->hasRole($userId, 'Coordenador')) {
            return true;
        }

        // 3) Caso queira considerar "coordenador" por permissão específica:
        if ($this->can($userId, 'manage_teams') || $this->can($userId, 'manage_users')) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se o usuário logado é Master ou Coordenador.
     */
    public function isMasterOrCoordinatorCurrent(): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->isMasterOrCoordinator($userId);
    }

    /**
     * Verifica se o usuário é Gerente.
     */
    public function isManager(int $userId): bool
    {
        return $this->hasRole($userId, 'Gerente');
    }

    /**
     * Verifica se o usuário logado é Gerente.
     */
    public function isManagerCurrent(): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->isManager($userId);
    }

    // ============================================================
    //          MÉTODOS DE VERIFICAÇÃO DE MEMBROS DE TIME
    // ============================================================

    /**
     * Verifica se o usuário é membro de um time específico.
     */
    public function isMemberOfTeam(int $userId, int $teamId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tb_user_teams
            WHERE user_id = :user_id 
              AND team_id = :team_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'  => $userId,
            ':team_id'  => $teamId,
        ]);

        return (bool)$stmt->fetch();
    }

    /**
     * Verifica se o usuário logado é membro de um time específico.
     */
    public function isMemberOfTeamCurrent(int $teamId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->isMemberOfTeam($userId, $teamId);
    }

    /**
     * Retorna todos os IDs de times dos quais o usuário é membro.
     */
    public function getUserTeamIds(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT team_id
            FROM tb_user_teams
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Retorna os IDs dos times que o usuário pode gerenciar usuários.
     */
    public function getManageableUserTeamIds(int $userId): array
    {
        if (!$userId) {
            return [];
        }

        // Master e Coordenador: todos os times
        if ($this->isMasterOrCoordinator($userId)) {
            $stmt = $this->db->query("SELECT id FROM tb_teams");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Gerente: apenas times onde é membro
        if ($this->isManager($userId)) {
            return $this->getUserTeamIds($userId);
        }

        return [];
    }

    /**
     * Retorna todos os IDs de times do usuário logado.
     */
    public function getCurrentUserTeamIds(): array
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return [];
        }

        return $this->getUserTeamIds($userId);
    }

    // ============================================================
    //          MÉTODOS DE ESCOPO: PERMISSÃO + TIME
    // ============================================================

    /**
     * Verifica se o usuário pode gerenciar times em um time específico.
     * 
     * Regra:
     * - Master/Coordenador com manage_teams: todos os times
     * - Gerente com manage_teams: apenas times onde é membro
     * - Outros: não
     */
    public function canManageTeamsInTeam(int $teamId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        // Precisa ter a permissão base
        if (!$this->can($userId, 'manage_teams')) {
            return false;
        }

        // Master/Coordenador: acesso total
        if ($this->isMasterOrCoordinator($userId)) {
            return true;
        }

        // Gerente: apenas se for membro do time
        if ($this->isManager($userId)) {
            return $this->isMemberOfTeam($userId, $teamId);
        }

        return false;
    }

    /**
     * Verifica se o usuário pode gerenciar usuários em um time específico.
     * 
     * Regra:
     * - Master/Coordenador com manage_users: todos os times
     * - Gerente com manage_users: apenas times onde é membro
     * - Outros: não
     */
    public function canManageUsersInTeam(int $teamId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        // Master/Coordenador => acesso total (independente de tb_role_permissions)
        if ($this->isMasterOrCoordinator($userId)) {
            return true;
        }

        // Precisa ter a permissão base
        if (!$this->can($userId, 'manage_users')) {
            return false;
        }

        // Gerente: apenas se for membro do time
        if ($this->isManager($userId)) {
            return $this->isMemberOfTeam($userId, $teamId);
        }

        return false;
    }

    /**
     * Verifica se o usuário pode gerenciar cargos em um time específico.
     * 
     * Regra:
     * - Master/Coordenador com manage_roles: todos os times
     * - Gerente com manage_roles: apenas times onde é membro
     * - Outros: não
     */
    public function canManageRolesInTeam(int $teamId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        // Precisa ter a permissão base
        if (!$this->can($userId, 'manage_roles')) {
            return false;
        }

        // Master/Coordenador: acesso total
        if ($this->isMasterOrCoordinator($userId)) {
            return true;
        }

        // Gerente: apenas se for membro do time
        if ($this->isManager($userId)) {
            return $this->isMemberOfTeam($userId, $teamId);
        }

        return false;
    }

    /**
     * Retorna todos os IDs de times que o usuário pode gerenciar.
     * Útil para filtrar listagens.
     */
    public function getManageableTeamIds(string $permissionName): array
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return [];
        }

        // Master/Coordenador: todos os times, não precisa ter o permissionName mapeado
        if ($this->isMasterOrCoordinator($userId)) {
            $stmt = $this->db->query("SELECT id FROM tb_teams");
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        // Precisa ter a permissão base
        if (!$this->can($userId, $permissionName)) {
            return [];
        }

        // Gerente: apenas seus times
        if ($this->isManager($userId)) {
            return $this->getUserTeamIds($userId);
        }

        return [];
    }

    /**
     * Retorna array de roles (id, name, level) do usuário — útil para debugging.
     */
    public function getUserRolesInfo(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name, r.level
            FROM tb_user_roles ur
            JOIN tb_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :uid
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    //          ATALHOS ESPECÍFICOS PARA O NOSSO DOMÍNIO
    // ============================================================

    /**
     * Quem pode ver a visão geral de acesso (4 colunas)?
     * -> Master, Coordenador, Gerente (via tb_role_permissions)
     */
    public function canViewAccessOverview(): bool
    {
        return $this->canCurrent('view_access_overview');
    }

    /**
     * Quem pode gerenciar times (globalmente)?
     * -> Master, Coordenador (via tb_role_permissions)
     */
    public function canManageTeams(): bool
    {
        return $this->canCurrent('manage_teams');
    }

    /**
     * Quem pode gerenciar cargos (globalmente)?
     * -> Master, Coordenador (via tb_role_permissions)
     */
    public function canManageRoles(): bool
    {
        return $this->canCurrent('manage_roles');
    }

    /**
     * Quem pode gerenciar usuários (globalmente)?
     * -> RH (quando você criar a role e mapear em tb_role_permissions)
     */
    public function canManageUsers(): bool
    {
        return $this->canCurrent('manage_users');
    }

    /**
     * Quem pode ver/atualizar permissões?
     * -> Regra dinâmica: quem tiver subordinados (id_lider)
     *    (e opcionalmente, você pode colocar Master como sempre true)
     */
    public function canManagePermissions(): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        // Master ou Coordenador sempre podem gerenciar permissões
        if ($this->isMasterOrCoordinator($userId)) {
            return true;
        }

        // Caso contrário, checar se tem subordinados (regra original)
        return $this->hasSubordinates($userId);
    }
    /**
     * Verifica se o Ator pode gerenciar o Alvo.
     * Regra: Menor level global = Mais poder.
     * O Ator deve ter um level estritamente menor que o Alvo.
     */
    public function canManageUser(int $actorId, int $targetId): bool
    {
        $actorLevel = $this->getUserMinRoleLevel($actorId);
        $targetLevel = $this->getUserMinRoleLevel($targetId);

        if ($actorLevel === null || $targetLevel === null) {
            return false;
        }
        
        // Master (level 1) sempre pode gerenciar qualquer um abaixo dele
        if ($actorLevel === 1 && $targetLevel > 1) {
            return true;
        }

        // Regra: Quem tem número menor (ex: 10) manda em quem tem número maior (ex: 20)
        return $actorLevel < $targetLevel;
    }

    /**
     * Verifica se o Ator pode gerenciar o Alvo dentro do contexto de um time.
     * Além do level, verifica se o Ator tem autoridade sobre o time do Alvo.
     */
    public function canManageUserInTeam(int $actorId, int $targetId, int $teamId): bool
    {
        // 1. Primeiro checa a hierarquia de poder (Level Global)
        if (!$this->canManageUser($actorId, $targetId)) {
            return false;
        }

        // 2. Verifica se o Alvo realmente pertence ao time informado
        if (!$this->isMemberOfTeam($targetId, $teamId)) {
            return false;
        }

        // 3. O Ator deve ser membro do time ou de um time ancestral (Pai)
        $ancestors = $this->getTeamAncestors($teamId);
        foreach ($ancestors as $tId) {
            if ($this->isMemberOfTeam($actorId, $tId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Versão usando o usuário logado como ator.
     * 
     * @param int $targetId ID do usuário alvo
     * @param int $teamId   ID do time
     * @return bool
     */
    public function canManageUserInTeamCurrent(int $targetId, int $teamId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return false;
        }

        return $this->canManageUserInTeam($userId, $targetId, $teamId);
    }

    /**
     * Verifica se o Grantor (Líder) pode conceder uma permissão a um Grantee (Subordinado).
     * Regras do Chefe:
     * 1. O Líder deve possuir a permissão que deseja conceder no contexto do time.
     * 2. O Líder deve ter um level global MENOR (mais poder) que o Subordinado.
     * 3. O Líder deve ser membro do time ou de um time ancestral (autoridade sobre o escopo).
     */
    public function canGrantPermission(int $grantorId, int $granteeId, string $permissionName, int $teamId): bool
    {
        // 1. O Líder tem a permissão que quer dar?
        if (!$this->can($grantorId, $permissionName, $teamId)) {
            return false;
        }

        // 2. O Líder tem poder hierárquico sobre o subordinado? (Level Global)
        if (!$this->canManageUser($grantorId, $granteeId)) {
            return false;
        }

        // 3. O Líder tem autoridade sobre o time em questão?
        $isAuthority = false;
        $ancestors = $this->getTeamAncestors($teamId);
        foreach ($ancestors as $tId) {
            if ($this->isMemberOfTeam($grantorId, $tId)) {
                $isAuthority = true;
                break;
            }
        }

        return $isAuthority;
    }

    /**
     * Concede uma permissão ao usuário no escopo de um time e registra auditoria.
     * Retorna true se a permissão foi criada ou já existia; false em erro/negado.
     */
    public function grantPermission(int $grantorId, int $granteeId, string $permissionName, int $teamId, ?string $comment = null): bool
    {
        // 0) Valida existência da permissão
        $stmt = $this->db->prepare("SELECT id FROM tb_permissions WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $permissionName]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$perm) {
            return false; // permissão desconhecida
        }
        $permissionId = (int)$perm['id'];

        // 1) Verifica regra: grantor pode conceder?
        if (!$this->canGrantPermission($grantorId, $granteeId, $permissionName, $teamId)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // 2) Insere idempotente em tb_user_team_permissions
            $insertSql = "
                INSERT INTO tb_user_team_permissions (user_id, team_id, permission_id, granted_by)
                VALUES (:user_id, :team_id, :permission_id, :granted_by)
                ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), granted_at = CURRENT_TIMESTAMP
            ";
            $stmtIns = $this->db->prepare($insertSql);
            $stmtIns->execute([
                ':user_id'       => $granteeId,
                ':team_id'       => $teamId,
                ':permission_id' => $permissionId,
                ':granted_by'    => $grantorId,
            ]);

            // 3) Registrar auditoria em tb_permission_audit
            $auditSql = "
                INSERT INTO tb_permission_audit (user_id, permission_id, team_id, action, performed_by, comment)
                VALUES (:user_id, :permission_id, :team_id, 'granted', :performed_by, :comment)
            ";
            $stmtAudit = $this->db->prepare($auditSql);
            $stmtAudit->execute([
                ':user_id'       => $granteeId,
                ':permission_id' => $permissionId,
                ':team_id'       => $teamId,
                ':performed_by'  => $grantorId,
                ':comment'       => $comment,
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("grantPermission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoga uma permissão de um usuário em um time e registra na auditoria.
     */
    public function revokePermission(int $performerId, int $targetUserId, string $permissionName, int $teamId, ?string $comment = null): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM tb_permissions WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $permissionName]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$perm) return false;
        $permissionId = (int)$perm['id'];

        // Regra: Quem revoga deve ter poder para gerenciar o alvo no time
        if (!$this->canManageUserInTeam($performerId, $targetUserId, $teamId)) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Remove a permissão específica
            $stmtDel = $this->db->prepare("DELETE FROM tb_user_team_permissions WHERE user_id = :u AND team_id = :t AND permission_id = :p");
            $stmtDel->execute([':u' => $targetUserId, ':t' => $teamId, ':p' => $permissionId]);

            // Auditoria
            $stmtAudit = $this->db->prepare("
                INSERT INTO tb_permission_audit (user_id, permission_id, team_id, action, performed_by, comment)
                VALUES (:u, :p, :t, 'revoked', :pb, :c)
            ");
            $stmtAudit->execute([':u' => $targetUserId, ':p' => $permissionId, ':t' => $teamId, ':pb' => $performerId, ':c' => $comment]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Retorna array de IDs do team começando pelo próprio e subindo até a raiz.
     * Usa cache em memória para esta instância do AuthorizationService.
     */
    private function getTeamAncestors(int $teamId): array
    {
        if (isset($this->teamAncestorsCache[$teamId])) {
            return $this->teamAncestorsCache[$teamId];
        }

        $anc = [];
        $current = $teamId;
        $stmt = $this->db->prepare("SELECT parent_team_id FROM tb_teams WHERE id = :id LIMIT 1");

        while ($current !== null) {
            $anc[] = (int)$current;
            $stmt->execute([':id' => $current]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['parent_team_id'] === null) {
                break;
            }
            $current = (int)$row['parent_team_id'];
        }

        $this->teamAncestorsCache[$teamId] = $anc;
        return $anc;
    }

    /**
     * Retorna o menor valor de `level` entre os cargos globais do usuário.
     * Menor = mais poder. Retorna null se o usuário não tem cargos.
     */
    private function getUserMinRoleLevel(int $userId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT MIN(r.level) AS min_level
            FROM tb_user_roles ur
            JOIN tb_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row && $row['min_level'] !== null) ? (int)$row['min_level'] : null;
    }

    /**
     * Retorna o menor `level` entre os cargos globais do usuário logado.
     * Menor = mais poder. Retorna null se o usuário não tem cargos.
     *
     * Uso: helpers para validações de delegação e checks no controller.
     *
     * @return int|null
     */
    public function getCurrentUserRoleLevel(): ?int
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null) {
            return null;
        }
        return $this->getUserMinRoleLevel($userId);
    }

    /**
     * Retorna o "highest role level" do usuário fornecido.
     * Observação: nome histórico/significado no código: "highest" = cargo com mais autoridade,
     * que no nosso modelo corresponde ao menor valor numérico de `level`.
     *
     * Ex: se o usuário tem cargos com level 1 e 3, retorna 1.
     *
     * @param int $userId
     * @return int|null
     */
    public function getUserHighestRoleLevel(int $userId): ?int
    {
        return $this->getUserMinRoleLevel($userId);
    }
}