<?php
// app/Services/AuthorizationService.php
namespace App\Services;

use PDO;

class AuthorizationService
{
    private PDO $db;

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

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return (int)$_SESSION['user_id'];
    }

    /**
     * Regra dinâmica: verifica se o usuário possui subordinados (tb_users.id_lider).
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
     * Motor genérico de permissão:
     * - Permissão direta em tb_user_permissions
     * - Permissão via cargo global (tb_user_roles + tb_role_permissions)
     * - Permissão via papel em time (tb_user_teams + tb_role_permissions), se teamId informado
     */
    public function can(int $userId, string $permissionName, ?int $teamId = null): bool
    {
        // 1) Descobrir o ID da permissão pelo nome
        $stmt = $this->db->prepare("
            SELECT id 
            FROM tb_permissions 
            WHERE name = :name 
            LIMIT 1
        ");
        $stmt->execute([':name' => $permissionName]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$perm) {
            return false;
        }

        $permissionId = (int)$perm['id'];

        // 2) Permissão direta para o usuário (tb_user_permissions)
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM tb_user_permissions 
            WHERE user_id = :user_id 
              AND permission_id = :permission_id 
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'       => $userId,
            ':permission_id' => $permissionId,
        ]);
        if ($stmt->fetch()) {
            return true;
        }

        // 3) Permissão via cargo global (tb_user_roles + tb_role_permissions)
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tb_user_roles ur
            JOIN tb_role_permissions rp ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id 
              AND rp.permission_id = :permission_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'       => $userId,
            ':permission_id' => $permissionId,
        ]);
        if ($stmt->fetch()) {
            return true;
        }

        // 4) Permissão via papel em time (tb_user_teams + tb_role_permissions), se teamId informado
        if ($teamId !== null) {
            $stmt = $this->db->prepare("
                SELECT 1
                FROM tb_user_teams ut
                JOIN tb_role_permissions rp ON ut.role_id = rp.role_id
                WHERE ut.user_id      = :user_id 
                  AND ut.team_id      = :team_id 
                  AND rp.permission_id = :permission_id
                LIMIT 1
            ");
            $stmt->execute([
                ':user_id'       => $userId,
                ':team_id'       => $teamId,
                ':permission_id' => $permissionId,
            ]);
            if ($stmt->fetch()) {
                return true;
            }
        }

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
     * Verifica se o usuário tem um cargo específico (por nome).
     */
    public function hasRole(int $userId, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tb_user_roles ur
            JOIN tb_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id 
              AND LOWER(r.name) = LOWER(:role_name)
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'    => $userId,
            ':role_name'  => $roleName,
        ]);

        return (bool)$stmt->fetch();
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
     * Verifica se o usuário é Master ou Coordenador.
     */
    public function isMasterOrCoordinator(int $userId): bool
    {
        return $this->hasRole($userId, 'Master') || $this->hasRole($userId, 'Coordenador');
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

        // Precisa ter a permissão base
        if (!$this->can($userId, 'manage_users')) {
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

        // Precisa ter a permissão base
        if (!$this->can($userId, $permissionName)) {
            return [];
        }

        // Master/Coordenador: todos os times
        if ($this->isMasterOrCoordinator($userId)) {
            $stmt = $this->db->query("SELECT id FROM tb_teams");
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        // Gerente: apenas seus times
        if ($this->isManager($userId)) {
            return $this->getUserTeamIds($userId);
        }

        return [];
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

        // Regra dinâmica principal:
        return $this->hasSubordinates($userId);
    }

    /**
     * Verifica se o Ator pode gerenciar o Alvo dentro de um time específico.
     * 
     * Regra: Menor level = Mais poder
     * - actorLevel < targetLevel → pode gerenciar
     * - actorLevel >= targetLevel → não pode gerenciar
     * - Master (level 1) sempre pode (se estiver no time)
     * 
     * @param int $actorId  ID do usuário que quer realizar a ação
     * @param int $targetId ID do usuário alvo da ação
     * @param int $teamId   ID do time onde a ação ocorre
     * @return bool
     */
    public function canManageUserInTeam(int $actorId, int $targetId, int $teamId): bool
    {
        // 1. Busca o nível do ator no time
        $actorLevel = $this->getUserLevelInTeam($actorId, $teamId);
        
        // 2. Busca o nível do alvo no time
        $targetLevel = $this->getUserLevelInTeam($targetId, $teamId);

        // Se algum não estiver no time, não pode gerenciar
        if ($actorLevel === null || $targetLevel === null) {
            return false;
        }
        
        // Master (level 1) sempre pode gerenciar qualquer um no time
        if ($actorLevel === 1) {
            return true;
        }

        // Regra: Menor valor = Mais poder
        return $actorLevel < $targetLevel;
    }

    /**
     * Obtém o nível (level) do cargo de um usuário dentro de um time específico.
     * 
     * @param int $userId ID do usuário
     * @param int $teamId ID do time
     * @return int|null Retorna o level ou null se o usuário não estiver no time
     */
    private function getUserLevelInTeam(int $userId, int $teamId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT r.level 
            FROM tb_user_teams ut
            JOIN tb_roles r ON ut.role_id = r.id
            WHERE ut.user_id = :user_id 
            AND ut.team_id = :team_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'  => $userId,
            ':team_id'  => $teamId,
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['level'] : null;
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
}