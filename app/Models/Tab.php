<?php
namespace App\Models;

use PDO;

class Tab {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    public function findAll(): array {
        $sql = "SELECT id, nome, descricao, key_name, created_at FROM tb_tabs ORDER BY nome";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array {
        $sql = "SELECT id, nome, descricao, key_name, created_at FROM tb_tabs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByKeyName(string $keyName): ?array {
        $sql = "SELECT id, nome, descricao, key_name, created_at FROM tb_tabs WHERE key_name = :key_name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':key_name' => $keyName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByNome(string $nome): ?array {
        $sql = "SELECT id, nome, descricao, key_name, created_at FROM tb_tabs WHERE nome = :nome LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nome' => $nome]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Verifica referências à tab em outras tabelas (ex: tb_permissions.tab_id).
     * Retorna array associativo ['tb_permissions' => 3] ou [] se não houver referências.
     */
    public function findReferences(int $tabId): array {
        $refs = [];
        $tables = [
            'tb_permissions' => 'tab_id'
        ];

        foreach ($tables as $table => $col) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$col} = :tid";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tid' => $tabId]);
            $count = (int)$stmt->fetchColumn();
            if ($count > 0) $refs[$table] = $count;
        }

        return $refs;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO tb_tabs (nome, descricao, key_name)
            VALUES (:nome, :descricao, :key_name)
        ");
        $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':key_name' => $data['key_name'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE tb_tabs
            SET nome = :nome, descricao = :descricao, key_name = :key_name
            WHERE id = :id
        ");
        return $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':key_name' => $data['key_name'] ?? null,
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tb_tabs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}