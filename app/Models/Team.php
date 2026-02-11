<?php
namespace App\Models;

use PDO;

class Team {
    private PDO $db;

    public function __construct() {
        $this->db = \getDbConnection();
    }

    /**
     * Lista todos os times, já trazendo o nome do time pai (se houver).
     */
    public function findAll(): array {
        $sql = "
            SELECT 
                t.id,
                t.name,
                t.parent_team_id,
                pt.name AS parent_team_name
            FROM tb_teams t
            LEFT JOIN tb_teams pt ON pt.id = t.parent_team_id
            ORDER BY t.name
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista times filtrados por uma lista de IDs.
     * Útil para Gerentes que só podem ver seus times.
     */
    public function findByIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "
            SELECT 
                t.id,
                t.name,
                t.parent_team_id,
                pt.name AS parent_team_name
            FROM tb_teams t
            LEFT JOIN tb_teams pt ON pt.id = t.parent_team_id
            WHERE t.id IN ($placeholders)
            ORDER BY t.name
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_map('intval', $ids));
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um time por ID, com dados do time pai.
     */
    public function findById(int $id): ?array {
        $sql = "
            SELECT 
                t.id,
                t.name,
                t.parent_team_id,
                pt.name AS parent_team_name
            FROM tb_teams t
            LEFT JOIN tb_teams pt ON pt.id = t.parent_team_id
            WHERE t.id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        return $team ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO tb_teams (name, parent_team_id) 
            VALUES (:name, :parent_team_id)
        ");
        $stmt->execute([
            ':name' => $data['name'],
            ':parent_team_id' => $data['parent_team_id'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE tb_teams 
            SET name = :name, parent_team_id = :parent_team_id 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':name' => $data['name'],
            ':parent_team_id' => $data['parent_team_id'] ?? null,
            ':id' => $id
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tb_teams WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}