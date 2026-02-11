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

        $extraData = [];

        // 1) Busca em tb_users
        try {
            $resultado = $this->findByLogin($login);
        } catch (\PDOException $e) {
            return ['error' => 'Erro na query de busca em tb_users: ' . $e->getMessage()];
        }

        // 2) Se não encontrou, tenta legacy
        if (!$resultado) {
            try {
                $sqlLegacy = $this->db->prepare("
                    SELECT id, nome, login, senha, id_lider, status, obra, cidade
                    FROM usuarios
                    WHERE login = :key
                    LIMIT 1
                ");
                $sqlLegacy->execute([':key' => $login]);
                $oldUser = $sqlLegacy->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                return ['error' => 'Erro na query de busca em usuarios: ' . $e->getMessage()];
            }

            if (!$oldUser) {
                return ['error' => 'Usuário não encontrado.'];
            }

            if (!password_verify($senha, $oldUser['senha'])) {
                return ['error' => 'Usuário ou senha inválidos.'];
            }

            // Insere na tb_users (mantém a senha como veio do legado)
            try {
                $insert = $this->db->prepare("
                    INSERT INTO tb_users 
                        (old_id, login, name, email, password_hash, id_lider, status, obras, cidades, created_at)
                    VALUES 
                        (:old_id, :login, :name, :email, :password, :id_lider, :status, :obras, :cidades, NOW())
                ");
                $insert->execute([
                    ':old_id'   => $oldUser['id'] ?? null,
                    ':login'    => $oldUser['login'],
                    ':name'     => $oldUser['nome'],
                    ':email'    => $oldUser['email'] ?? '',
                    ':password' => $oldUser['senha'],
                    ':id_lider' => $oldUser['id_lider'] ?? null,
                    ':status'   => $oldUser['status'] ?? 1,
                    ':obras'    => $oldUser['obra'] ?? null,
                    ':cidades'  => $oldUser['cidade'] ?? null
                ]);
                $userId = (int)$this->db->lastInsertId();

                $stmt = $this->db->prepare("SELECT * FROM tb_users WHERE id = :id");
                $stmt->execute([':id' => $userId]);
                $resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                return ['error' => 'Erro na query de insert em tb_users: ' . $e->getMessage()];
            }

            $extraData = [
                'id_lider' => $oldUser['id_lider'] ?? null,
                'status'   => $oldUser['status'] ?? null,
                'obras'    => !empty($oldUser['obra']) ? explode(',', $oldUser['obra']) : [],
                'cidades'  => !empty($oldUser['cidade']) ? explode(',', $oldUser['cidade']) : []
            ];
        }

        // 3) Verifica senha e monta resposta
        if (!$resultado || !isset($resultado[0])) {
            return ['error' => 'Usuário não encontrado após sincronização.'];
        }

        $row0 = $resultado[0];
        $idDb = (int)$row0['id'];
        $nameDb = $row0['name'] ?? '';
        $emailDb = $row0['email'] ?? null;
        $loginDb = $row0['login'] ?? '';
        $passwordDb = $row0['password_hash'] ?? '';

        if (!password_verify($senha, $passwordDb)) {
            return ['error' => 'Usuário ou senha inválidos.'];
        }

        // roles globais
        $roles = [];
        foreach ($resultado as $r) {
            if (!empty($r['role_name'])) $roles[] = $r['role_name'];
        }
        $roles = array_values(array_unique($roles));

        // teams
        try {
            $sqlTeams = $this->db->prepare("
                SELECT t.id AS team_id, t.name AS team_name, r.id AS role_id, r.name AS role_name
                FROM tb_user_teams ut
                JOIN tb_teams t ON ut.team_id = t.id
                JOIN tb_roles r ON ut.role_id = r.id
                WHERE ut.user_id = :user_id
            ");
            $sqlTeams->execute([':user_id' => $idDb]);
            $resultadoTeams = $sqlTeams->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return ['error' => 'Erro na query de times e roles: ' . $e->getMessage()];
        }

        $teams = [];
        foreach ($resultadoTeams as $row) {
            $teams[] = [
                'id' => $row['team_id'],
                'name' => $row['team_name'],
                'role' => $row['role_name']
            ];
        }

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
                $token = \Firebase\JWT\JWT::encode($payload, $GLOBALS['secretJWT'], 'HS256');
            } catch (\Throwable $e) {
                // não interrompe: apenas não retorna token
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
        $db = getDbConnection();
        $stmt = $db->prepare("
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
}