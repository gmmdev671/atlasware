<?php
namespace App\Models;

use PDO;

class User {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection(); // getDbConnection() vem do app/Config/database.php carregado no bootstrap
    }

    /**
     * Find user by login (from tb_users). Returns single user row or null.
     */
    public function findByLogin(string $login) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.login, u.password_hash,
                   u.id_lider, u.status, u.obras, u.cidades,
                   r.id AS role_id, r.name AS role_name
            FROM tb_users u
            LEFT JOIN tb_user_roles ur ON ur.user_id = u.id
            LEFT JOIN tb_roles r ON r.id = ur.role_id
            WHERE u.login = :key OR u.email = :key
        ");
        $stmt->execute([':key' => $login]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: null;
    }

    public function getUserRoleNames(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT r.name FROM tb_user_roles ur
            JOIN tb_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getUserTeamsWithRoleNames(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT t.id as id, t.name as name, r.name as role
            FROM tb_user_teams ut
            JOIN tb_teams t ON ut.team_id = t.id
            JOIN tb_roles r ON ut.role_id = r.id
            WHERE ut.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Full login flow:
     * - try tb_users
     * - if not found, try legacy "usuarios", validate password, insert into tb_users
     * - after found, validate password and return array similar to your API response
     */
    public function login(string $login, string $senha): array {
        $login = trim($login);
        $senha = trim($senha);

        // 1) Busca em tb_users
        $resultado = $this->findByLogin($login);

        // 2) Se encontrou na tb_users, verifica se a senha está NULL/vazia (Pré-migrado pelo chefe)
        if ($resultado && isset($resultado[0])) {
            $row0 = $resultado[0];

            // checagem explícita para NULL ou string vazia
            if ($row0['password_hash'] === null || $row0['password_hash'] === '') {
                return [
                    'requires_password_setup' => true,
                    'id' => (int)$row0['id'],
                    'nome' => $row0['name'],
                    'login' => $row0['login']
                ];
            }

            // Se tem hash, valida normalmente
            if (!password_verify($senha, $row0['password_hash'])) {
                return ['error' => 'Usuário ou senha inválidos.'];
            }

            // login OK → montar resposta completa
            return $this->buildFullResponse($resultado);
        }

        // 3) Se não encontrou na tb_users, tenta o legado (Migração automática no login)
        $oldUser = $this->findInLegacyTable($login);
        if (!$oldUser) {
            return ['error' => 'Usuário não encontrado.'];
        }

        // Valida a senha contra o hash antigo (MD5) — detectar formato MD5
        $legacyHash = $oldUser['senha'] ?? '';
        $okLegacy = false;
        if ($legacyHash !== null && strlen($legacyHash) === 32 && ctype_xdigit($legacyHash)) {
            // provável MD5 hex
            $okLegacy = (md5($senha) === $legacyHash);
        } else {
            // fallback: tenta password_verify (caso legado tenha outro formato)
            $okLegacy = password_verify($senha, $legacyHash);
        }

        if (!$okLegacy) {
            return ['error' => 'Usuário ou senha inválidos.'];
        }

        // MIGRAR: Insere na tb_users com password_hash = NULL para forçar troca
        $newId = $this->migrateUser($oldUser);
        if ($newId === null) {
            return ['error' => 'Erro ao migrar usuário.'];
        }

        return [
            'requires_password_setup' => true,
            'id' => $newId,
            'nome' => $oldUser['nome'],
            'login' => $oldUser['login']
        ];
    }

    /**
     * Constrói a resposta completa do usuário (token, roles, teams, etc)
     * $rows = resultado de findByLogin() (pode ser múltiplas linhas por causa do join em roles)
     * $extraData = array opcional com campos suplementares (id_lider, status, obras, cidades)
     */
    private function buildFullResponse(array $rows, array $extraData = []): array
    {
        $row0 = $rows[0];
        $idDb = (int)$row0['id'];
        $nameDb = $row0['name'] ?? '';
        $emailDb = $row0['email'] ?? null;
        $loginDb = $row0['login'] ?? '';

        // roles: tenta extrair das linhas (se vierem) senão consulta via método
        $roles = [];
        foreach ($rows as $r) {
            if (!empty($r['role_name'])) $roles[] = $r['role_name'];
        }
        $roles = array_values(array_unique($roles));
        if (empty($roles)) {
            $roles = $this->getUserRoleNames($idDb);
        }

        // teams (usa o método já existente)
        $teams = $this->getUserTeamsWithRoleNames($idDb);

        // Gera JWT se a lib estiver disponível (se não, token = null)
        $nextWeek = time() + (7 * 24 * 60 * 60);
        $token = null;
        if (class_exists('\Firebase\JWT\JWT')) {
            try {
                $payload = [
                    'id' => $idDb,
                    'nome' => $nameDb,
                    'roles' => $roles,
                    'exp' => $nextWeek
                ];
                $token = \Firebase\JWT\JWT::encode($payload, $GLOBALS['secretJWT'] ?? '', 'HS256');
            } catch (\Throwable $e) {
                $token = null;
            }
        }

        $response = [
            'token'      => $token,
            'id'         => $idDb,
            'nome'       => $nameDb,
            'login'      => $loginDb,
            'email'      => $emailDb,
            'roles'      => $roles,
            'teams'      => $teams,
            'expira_em'  => date('Y-m-d H:i:s', $nextWeek),
            'id_lider'   => $row0['id_lider'] ?? ($extraData['id_lider'] ?? null),
            'status'     => $row0['status'] ?? ($extraData['status'] ?? null),
            'obras'      => !empty($row0['obras']) ? explode(',', $row0['obras']) : ($extraData['obras'] ?? []),
            'cidades'    => !empty($row0['cidades']) ? explode(',', $row0['cidades']) : ($extraData['cidades'] ?? [])
        ];

        return $response;
    }

    /**
     * Retorna todos os usuários ativos (status = 1) ordenados por nome.
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email 
            FROM tb_users 
            WHERE status = 0 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findAllWithRoles(): array {
        $sql = "
            SELECT 
                u.id,
                u.name,
                GROUP_CONCAT(DISTINCT ur.role_id) AS role_ids,
                GROUP_CONCAT(DISTINCT t.name SEPARATOR '||') AS teams_info
            FROM tb_users u
            LEFT JOIN tb_user_roles ur ON ur.user_id = u.id
            LEFT JOIN tb_user_teams ut ON ut.user_id = u.id
            LEFT JOIN tb_teams t ON t.id = ut.team_id
            LEFT JOIN tb_roles r ON r.id = ut.role_id
            GROUP BY u.id, u.name
            ORDER BY u.name
        ";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            // Converte IDs de roles em array
            $row['role_ids'] = $row['role_ids']
                ? array_map('intval', explode(',', $row['role_ids']))
                : [];
                
            // Converte a string de times em um array amigável
            $row['teams'] = $row['teams_info'] 
                ? explode('||', $row['teams_info']) 
                : [];
        }

        return $rows;
    }

    /**
     * Retorna um usuário com os cargos (roles) associados.
     */
    public function findWithRoles(int $userId): ?array {
        $sql = "
            SELECT 
                u.id,
                u.name,
                u.email,
                GROUP_CONCAT(ur.role_id) AS role_ids
            FROM tb_users u
            LEFT JOIN tb_user_roles ur ON ur.user_id = u.id
            WHERE u.id = :id
            GROUP BY u.id, u.name, u.email
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['role_ids'] = $row['role_ids']
            ? array_map('intval', explode(',', $row['role_ids']))
            : [];

        return $row;
    }

    /**
     * Atualiza os roles de um usuário (tb_user_roles).
     * Estratégia: apaga tudo e grava de novo.
     */
    public function setRoles(int $userId, array $roleIds): void {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare("DELETE FROM tb_user_roles WHERE user_id = :user_id");
            $del->execute([':user_id' => $userId]);

            if (!empty($roleIds)) {
                $ins = $this->db->prepare("
                    INSERT INTO tb_user_roles (user_id, role_id)
                    VALUES (:user_id, :role_id)
                ");
                foreach ($roleIds as $rid) {
                    $ins->execute([
                        ':user_id' => $userId,
                        ':role_id' => (int)$rid
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
     * Lista básica de usuários (id, name, email).
     */
    public function findAllBasic(): array {
        $sql = "SELECT id, name, email FROM tb_users ORDER BY name";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca usuário na tabela legada 'usuarios'
     */
    public function findInLegacyTable(string $login): ?array {
        $stmt = $this->db->prepare("
            SELECT id, nome, login, senha, id_lider, status, obra, cidade
            FROM usuarios
            WHERE login = :login
            LIMIT 1
        ");
        $stmt->execute([':login' => $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Migrar usuário do legado para tb_users com senha vazia
     */
    public function migrateFromLegacy(array $legacyUser): bool {
        $newId = $this->migrateUser($legacyUser);
        return $newId !== null;
    }

    private function buildUserResponse(array $rows): array {
        $row0 = $rows[0];
        $idDb = (int)$row0['id'];
        $nameDb = $row0['name'] ?? '';
        $emailDb = $row0['email'] ?? null;
        $loginDb = $row0['login'] ?? '';

        // Roles
        $roles = [];
        foreach ($rows as $r) {
            if (!empty($r['role_name'])) $roles[] = $r['role_name'];
        }
        $roles = array_values(array_unique($roles));

        // Teams
        $teams = $this->getUserTeamsWithRoleNames($idDb);

        // Token JWT (se aplicável)
        $nextWeek = time() + (7 * 24 * 60 * 60);
        $token = null;
        if (class_exists('\Firebase\JWT\JWT')) {
            try {
                $payload = [
                    'id' => $idDb,
                    'nome' => $nameDb,
                    'roles' => $roles,
                    'exp' => $nextWeek
                ];
                $token = \Firebase\JWT\JWT::encode($payload, $GLOBALS['secretJWT'], 'HS256');
            } catch (\Throwable $e) {
                $token = null;
            }
        }

        return [
            'token'      => $token,
            'id'         => $idDb,
            'nome'       => $nameDb,
            'login'      => $loginDb,
            'email'      => $emailDb,
            'roles'      => $roles,
            'teams'      => $teams,
            'expira_em'  => date('Y-m-d H:i:s', $nextWeek),
            'id_lider'   => $row0['id_lider'] ?? null,
            'status'     => $row0['status'] ?? null,
            'obras'      => !empty($row0['obras']) ? explode(',', $row0['obras']) : [],
            'cidades'    => !empty($row0['cidades']) ? explode(',', $row0['cidades']) : []
        ];
    }

    /**
     * Migra um único usuário legado (array vindo de SELECT na tabela `usuarios`)
     * para tb_users. Não mantém a senha legada (grava NULL em password_hash).
     * Retorna o id do usuário em tb_users (int) ou null em caso de erro.
     *
     * $oldUser deve conter pelo menos: ['id', 'login', 'nome', 'email'?, 'senha'?, 'id_lider'?, 'status'?, 'obra'?, 'cidade'?]
     */
    public function migrateUser(array $oldUser): ?int
    {
        try {
            $oldId   = $oldUser['id'] ?? null;
            $login   = $oldUser['login'] ?? null;
            $name    = $oldUser['nome'] ?? '';
            $email   = $oldUser['email'] ?? '';
            // Não manter a senha legada — gravamos NULL para forçar reset
            $pwLegacy = null;
            $idLider = $oldUser['id_lider'] ?? null;
            // default para legacy: 0 = ativo
            $status  = $oldUser['status'] ?? 0;
            $obras   = $oldUser['obra'] ?? null;
            $cidades = $oldUser['cidade'] ?? null;

            $sql = "
                INSERT INTO tb_users
                    (old_id, login, name, email, password_hash, id_lider, status, obras, cidades, created_at)
                VALUES
                    (:old_id, :login, :name, :email, :password_hash, :id_lider, :status, :obras, :cidades, NOW())
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    name = VALUES(name),
                    email = VALUES(email),
                    password_hash = COALESCE(VALUES(password_hash), password_hash),
                    id_lider = VALUES(id_lider),
                    status = VALUES(status),
                    obras = VALUES(obras),
                    cidades = VALUES(cidades)
            ";

            $stmt = $this->db->prepare($sql);

            // bindValue para passar NULL corretamente
            $stmt->bindValue(':old_id', $oldId, $oldId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':login', $login);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':password_hash', $pwLegacy, \PDO::PARAM_NULL);
            $stmt->bindValue(':id_lider', $idLider, $idLider === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':obras', $obras);
            $stmt->bindValue(':cidades', $cidades);

            $ok = $stmt->execute();
            if (!$ok) return null;

            $newId = (int)$this->db->lastInsertId();
            return $newId > 0 ? $newId : null;
        } catch (\PDOException $e) {
            error_log("migrateUser error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca o usuário legado por id e chama migrateUser.
     */
    public function migrateLegacyById(int $oldId): ?int
    {
        $stmt = $this->db->prepare("SELECT id, nome, login, senha, email, id_lider, status, obra, cidade FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $oldId]);
        $oldUser = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$oldUser) return null;
        return $this->migrateUser($oldUser);
    }

    /**
     * Migração em massa a partir de uma lista de legacy IDs.
     * Retorna array com relatórios por id:
     *  ['old_id' => X, 'status' => 'ok', 'new_id' => Y] ou
     *  ['old_id' => X, 'status' => 'error', 'message' => '...']
     *
     * $atomic = true -> tudo ou nada (rollback em qualquer erro)
     */
    public function migrateUsersByLegacyIds(array $oldIds, bool $atomic = false): array
    {
        $results = [];
        if (empty($oldIds)) return $results;

        if ($atomic) $this->db->beginTransaction();

        try {
            $selectStmt = $this->db->prepare("
                SELECT id, nome, login, senha, email, id_lider, status, obra, cidade
                FROM usuarios
                WHERE id = :id
                LIMIT 1
            ");

            $insertSql = "
                INSERT INTO tb_users
                    (old_id, login, name, email, password_hash, id_lider, status, obras, cidades, created_at)
                VALUES
                    (:old_id, :login, :name, :email, :password_hash, :id_lider, :status, :obras, :cidades, NOW())
                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id),
                    name = VALUES(name),
                    email = VALUES(email),
                    password_hash = COALESCE(VALUES(password_hash), password_hash),
                    id_lider = VALUES(id_lider),
                    status = VALUES(status),
                    obras = VALUES(obras),
                    cidades = VALUES(cidades)
            ";
            $insertStmt = $this->db->prepare($insertSql);

            foreach ($oldIds as $oldId) {
                try {
                    $selectStmt->execute([':id' => $oldId]);
                    $oldUser = $selectStmt->fetch(\PDO::FETCH_ASSOC);

                    if (!$oldUser) {
                        $results[] = ['old_id' => $oldId, 'status' => 'error', 'message' => 'Usuário legado não encontrado'];
                        if ($atomic) throw new \RuntimeException("Legacy user not found: $oldId");
                        continue;
                    }

                    // Bind e execute do insert (password_hash = NULL)
                    $insertStmt->bindValue(':old_id', $oldUser['id'], \PDO::PARAM_INT);
                    $insertStmt->bindValue(':login', $oldUser['login']);
                    $insertStmt->bindValue(':name', $oldUser['nome']);
                    $insertStmt->bindValue(':email', $oldUser['email'] ?? '');
                    $insertStmt->bindValue(':password_hash', null, \PDO::PARAM_NULL);
                    $insertStmt->bindValue(':id_lider', $oldUser['id_lider'] ?? null, ($oldUser['id_lider'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                    $insertStmt->bindValue(':status', $oldUser['status'] ?? 0);
                    $insertStmt->bindValue(':obras', $oldUser['obra'] ?? null);
                    $insertStmt->bindValue(':cidades', $oldUser['cidade'] ?? null);

                    $ok = $insertStmt->execute();
                    if (!$ok) {
                        $results[] = ['old_id' => $oldId, 'status' => 'error', 'message' => 'Falha no insert'];
                        if ($atomic) throw new \RuntimeException("Insert failed for legacy id: $oldId");
                        continue;
                    }

                    $newId = (int)$this->db->lastInsertId();
                    $results[] = ['old_id' => $oldId, 'status' => 'ok', 'new_id' => $newId > 0 ? $newId : null];
                } catch (\Throwable $eItem) {
                    $results[] = ['old_id' => $oldId, 'status' => 'error', 'message' => $eItem->getMessage()];
                    if ($atomic) throw $eItem;
                }
            }

            if ($atomic) $this->db->commit();
        } catch (\Throwable $e) {
            if ($atomic && $this->db->inTransaction()) $this->db->rollBack();
            $results[] = ['old_id' => null, 'status' => 'error', 'message' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Opcional: retorna lista de usuários legados ativos (útil para a UI do chefe).
     * Ajuste a condição 'status' conforme sua definição (1 = ativo? no seu esquema tem 1 como default).
     */
    public function findLegacyActive(int $status = 0): array
    {
        $stmt = $this->db->prepare("SELECT id, nome, login, email, id_lider, status, obra, cidade FROM usuarios WHERE status = :status ORDER BY nome ASC");
        $stmt->execute([':status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualiza a senha de um usuário (usa password_hash).
     * Retorna true se OK, false caso contrário.
     */
    public function setPassword(int $userId, string $newPassword): bool
    {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE tb_users SET password_hash = :hash WHERE id = :id");
            return (bool)$stmt->execute([
                ':hash' => $hash,
                ':id'   => $userId
            ]);
        } catch (\PDOException $e) {
            error_log("setPassword error: " . $e->getMessage());
            return false;
        }
    }
}