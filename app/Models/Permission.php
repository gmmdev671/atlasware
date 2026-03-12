<?php
namespace App\Models;

use PDO;

class Permission {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Lista todas as permissões, trazendo o nome da aba vinculada.
     */
    public function findAll(): array {
        $sql = "
            SELECT 
                p.id, 
                p.name, 
                p.tab_id,
                t.nome AS tab_name
            FROM tb_permissions p
            LEFT JOIN tb_tabs t ON t.id = p.tab_id
            ORDER BY t.nome ASC, p.name ASC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $sql = "
            SELECT p.id, p.name, p.tab_id 
            FROM tb_permissions p 
            WHERE p.id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);
        return $perm ?: null;
    }

    public function findByName(string $name): ?array {
        $sql = "SELECT id, name, tab_id FROM tb_permissions WHERE name = :name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, name, tab_id FROM tb_permissions WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_map('intval', $ids));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByTab(int $tabId): array {
        $sql = "SELECT id, name, tab_id FROM tb_permissions WHERE tab_id = :tab_id ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tab_id' => $tabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Métodos para o CRUD que será criado no Controller
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO tb_permissions (name, tab_id) 
            VALUES (:name, :tab_id)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':tab_id' => $data['tab_id'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE tb_permissions 
            SET name = :name, tab_id = :tab_id 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name' => $data['name'],
            ':tab_id' => $data['tab_id'] ?? null,
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tb_permissions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Verifica referências da permissão em tabelas relacionadas.
     * Retorna array associativo ['tb_user_permissions' => 2, ...] (vazio se sem refs).
     */
    public function findReferences(int $permissionId): array {
        $refs = [];

        $tables = [
            'tb_user_permissions' => 'permission_id',
            'tb_user_team_permissions' => 'permission_id',
            'tb_team_permissions' => 'permission_id',
            'tb_role_permissions' => 'permission_id'
        ];

        foreach ($tables as $table => $column) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :pid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':pid' => $permissionId]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) {
                $refs[$table] = $count;
            }
        }

        return $refs;
    }

    /**
     * Retorna os nomes das permissões diretas de um usuário (tb_user_permissions).
     */
    public function getDirectPermissionsByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT p.name
            FROM tb_permissions p
            INNER JOIN tb_user_permissions up ON up.permission_id = p.id
            WHERE up.user_id = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}