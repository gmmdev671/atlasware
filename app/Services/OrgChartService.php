<?php
// app/Services/OrgChartService.php
namespace App\Services;

class OrgChartService {

    public static function buildHumanHierarchy()
    {
        global $pdo;

        // Certifique-se de selecionar todos os campos necessários
        $stmt = $pdo->query("
            SELECT 
                id, 
                nome, 
                login, 
                COALESCE(status, 1) AS status, 
                COALESCE(id_lider, 0) AS id_lider, 
                nivel_acesso, 
                ord_user 
            FROM usuarios 
            ORDER BY id_lider ASC, ord_user ASC
        ");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar por ID
        $usersById = [];
        foreach ($rows as $row) {
            // Garantir que todos os campos esperados existam
            $usersById[$row['id']] = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'login' => $row['login'],
                'status' => $row['status'],
                'id_lider' => $row['id_lider'],
                'nivel_acesso' => $row['nivel_acesso'],
                'ord_user' => $row['ord_user'],
                'subordinates' => []
            ];
        }

        // Agrupar filhos
        $roots = [];
        foreach ($usersById as $id => $user) {
            $parentId = (int)$user['id_lider'];
            if ($parentId === 0 || !isset($usersById[$parentId])) {
                $roots[] = &$usersById[$id];
            } else {
                $usersById[$parentId]['subordinates'][] = &$usersById[$id];
            }
        }

        return $roots;
    }

    public static function buildHumanHierarchyFromUsuarios(): array
    {
        $db = getDbConnection();

        // Buscamos apenas os ativos (status = 0)
        // Selecionamos os campos necessários para o card e para a árvore
        $sql = "SELECT id, nome, login, status, id_lider, nivel_acesso, ord_user 
                FROM usuarios 
                WHERE status = 0 
                ORDER BY id_lider ASC, ord_user ASC, nome ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $usersById = [];
        foreach ($rows as $r) {
            $usersById[(int)$r['id']] = [
                'id' => (int)$r['id'],
                'nome' => $r['nome'],
                'login' => $r['login'],
                'status' => (int)$r['status'],
                'id_lider' => (int)$r['id_lider'],
                'nivel_acesso' => $r['nivel_acesso'] ?? '',
                'subordinates' => []
            ];
        }

        $roots = [];
        foreach ($usersById as $id => &$user) {
            $parentId = (int)$user['id_lider'];
            // Se o pai não existe na lista de ativos ou é 0, ele é uma raiz
            if ($parentId === 0 || !isset($usersById[$parentId])) {
                $roots[] = &$user;
            } else {
                $usersById[$parentId]['subordinates'][] = &$user;
            }
        }
        unset($user);

        return $roots;
    }
}