<?php
namespace App\Models;

use PDO;
use PDOException;

class Role {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM tb_roles ORDER BY level, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tb_roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ?: null;
    }

    public function findByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tb_roles WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return $role ?: null;
    }

    public function existsByName(string $name): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM tb_roles WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Create role. If $returnExisting = true and role exists, returns existing id.
     * Throws exception on DB error otherwise.
     */
    public function create(array $data, bool $returnExisting = true): int {
        $name = $data['name'];
        $level = $data['level'] ?? 99;

        // Se já existe e queremos o id existente, retornar
        if ($returnExisting) {
            $existing = $this->findByName($name);
            if ($existing) {
                return (int)$existing['id'];
            }
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO tb_roles (name, level) VALUES (:name, :level)
            ");
            $stmt->execute([
                ':name' => $name,
                ':level' => $level
            ]);
            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            // Se for erro de duplicidade, podemos retornar id existente (se existir)
            if ($e->errorInfo[1] === 1062) { // MySQL duplicate entry
                $existing = $this->findByName($name);
                if ($existing) {
                    return (int)$existing['id'];
                }
            }
            // Re-throw para tratar em outro nível
            throw $e;
        }
    }

    public function update(int $id, array $data): bool {
        // Se deseja evitar colisão por nome, verificar se outro registro já tem o mesmo nome
        if (isset($data['name'])) {
            $stmt = $this->db->prepare("SELECT id FROM tb_roles WHERE name = :name AND id != :id LIMIT 1");
            $stmt->execute([':name' => $data['name'], ':id' => $id]);
            if ($stmt->fetch()) {
                throw new \Exception("Já existe outra role com o nome '{$data['name']}'.");
            }
        }

        $stmt = $this->db->prepare("
            UPDATE tb_roles SET name = :name, level = :level WHERE id = :id
        ");
        return $stmt->execute([
            ':name' => $data['name'],
            ':level' => $data['level'] ?? 99,
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tb_roles WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}