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

        // Carregar mapa de abas: [NOME_UPPER => id]
        $tabsMap = self::loadTabsMap($db);

        // Buscar usuários ativos com campos de permissão
        $permCols = self::getPermissionColumns();
        $sql = "SELECT id, nome, login, status, id_lider, nivel_acesso, ord_user, $permCols
                FROM usuarios 
                WHERE status = 0 
                ORDER BY id_lider ASC, ord_user ASC, nome ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar usuários por ID
        $usersById = [];
        foreach ($rows as $r) {
            $usersById[(int)$r['id']] = array_merge([
                'id'           => (int)$r['id'],
                'nome'         => $r['nome'],
                'login'        => $r['login'],
                'status'       => (int)$r['status'],
                'id_lider'     => (int)$r['id_lider'],
                'nivel_acesso' => $r['nivel_acesso'] ?? '',
                'subordinates' => []
            ], $r);
        }

        // Enriquecer com abas (IDs) e permissoes (campos ativos)
        self::enrichUsers($usersById, $tabsMap);

        // Construir um conjunto dos IDs que são líderes de alguém
        $leaderIds = [];
        foreach ($usersById as $user) {
            if ($user['id_lider'] !== 0) {
                $leaderIds[$user['id_lider']] = true;
            }
        }

        // Filtrar usuários para manter só os que têm líder ou são líderes
        $filteredUsers = [];
        foreach ($usersById as $id => $user) {
            if ($user['id_lider'] !== 0 || isset($leaderIds[$id])) {
                $filteredUsers[$id] = $user;
            }
        }

        // Montar a árvore com os usuários filtrados
        $roots = [];
        foreach ($filteredUsers as $id => &$user) {
            $parentId = (int)$user['id_lider'];
            if ($parentId === 0 || !isset($filteredUsers[$parentId])) {
                $roots[] = &$user;
            } else {
                $filteredUsers[$parentId]['subordinates'][] = &$user;
            }
        }
        unset($user);

        return $roots;
    }

    public static function buildHumanHierarchyByStatus(string $status = 'active', bool $includeOrphans = false): array
    {
        $db = getDbConnection();

        $where = match($status) {
            'active'   => 'WHERE status = 0',
            'inactive' => 'WHERE status = 1',
            'all'      => '',
            default    => 'WHERE status = 0',
        };

        // Carregar mapa de abas: [NOME_UPPER => id]
        $tabsMap = self::loadTabsMap($db);

        $permCols = self::getPermissionColumns();
        $sql = "SELECT id, nome, login, COALESCE(status, 1) AS status, COALESCE(id_lider, 0) AS id_lider, nivel_acesso, ord_user, $permCols
                FROM usuarios
                $where
                ORDER BY id_lider ASC, ord_user ASC, nome ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar por ID
        $usersById = [];
        foreach ($rows as $row) {
            $usersById[(int)$row['id']] = array_merge([
                'id'           => (int)$row['id'],
                'nome'         => $row['nome'],
                'login'        => $row['login'],
                'status'       => (int)$row['status'],
                'id_lider'     => (int)$row['id_lider'],
                'nivel_acesso' => $row['nivel_acesso'] ?? '',
                'ord_user'     => $row['ord_user'] ?? 0,
                'subordinates' => []
            ], $row);
        }

        // Enriquecer com abas (IDs) e permissoes (campos ativos)
        self::enrichUsers($usersById, $tabsMap);

        // Identificar quais IDs são líderes de alguém
        $leaderIds = [];
        foreach ($usersById as $user) {
            if ($user['id_lider'] !== 0 && isset($usersById[$user['id_lider']])) {
                $leaderIds[$user['id_lider']] = true;
            }
        }

        // Separar quem entra na árvore e quem é órfão
        $treeUsers  = [];
        $orphans    = [];
        foreach ($usersById as $id => $user) {
            $hasLeader = $user['id_lider'] !== 0 && isset($usersById[$user['id_lider']]);
            $isLeader  = isset($leaderIds[$id]);

            if ($hasLeader || $isLeader) {
                $treeUsers[$id] = $user;
            } else {
                $orphans[$id] = $user;
            }
        }

        // Montar a árvore principal
        $roots = [];
        foreach ($treeUsers as $id => &$user) {
            $parentId = (int)$user['id_lider'];
            if ($parentId === 0 || !isset($treeUsers[$parentId])) {
                $roots[] = &$treeUsers[$id];
            } else {
                $treeUsers[$parentId]['subordinates'][] = &$treeUsers[$id];
            }
        }
        unset($user);

        // Se $includeOrphans, adiciona os órfãos como raízes extras no final
        if ($includeOrphans) {
            foreach ($orphans as $id => &$orphan) {
                $roots[] = &$orphans[$id];
            }
            unset($orphan);
        }

        return $roots;
    }

    public static function buildHierarchyFromLeaderByStatus(int $leaderId, string $status = 'active'): array
    {
        $db = getDbConnection();

        $where = match($status) {
            'active'   => 'WHERE status = 0',
            'inactive' => 'WHERE status = 1',
            'all'      => '',
            default    => 'WHERE status = 0',
        };

        // Carregar mapa de abas: [NOME_UPPER => id]
        $tabsMap = self::loadTabsMap($db);

        $permCols = self::getPermissionColumns();
        $sql = "SELECT id, nome, login, COALESCE(status, 1) AS status, COALESCE(id_lider, 0) AS id_lider, nivel_acesso, ord_user, $permCols
                FROM usuarios
                $where
                ORDER BY id_lider ASC, ord_user ASC, nome ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar por ID
        $usersById = [];
        foreach ($rows as $row) {
            $usersById[(int)$row['id']] = array_merge([
                'id'           => (int)$row['id'],
                'nome'         => $row['nome'],
                'login'        => $row['login'],
                'status'       => (int)$row['status'],
                'id_lider'     => (int)$row['id_lider'],
                'nivel_acesso' => $row['nivel_acesso'] ?? '',
                'ord_user'     => (int)($row['ord_user'] ?? 0),
                'subordinates' => []
            ], $row);
        }

        // Enriquecer com abas (IDs) e permissoes (campos ativos)
        self::enrichUsers($usersById, $tabsMap);

        // Se líder não existe no recorte (ex: inativo e status=active), retorna vazio
        if (!isset($usersById[$leaderId])) {
            return [];
        }

        // Monta as relações pai->filho
        foreach ($usersById as $id => &$user) {
            $parentId = (int)$user['id_lider'];
            if ($parentId !== 0 && isset($usersById[$parentId])) {
                $usersById[$parentId]['subordinates'][] = &$usersById[$id];
            }
        }
        unset($user);

        // Retorna somente a subárvore
        return [ $usersById[$leaderId] ];
    }

    // ─────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────

    private static function getPermissionColumns(): string
    {
        return implode(',', [
            'editarss','editarRH','editarAlmox','editarLancamento','editarEquipamento',
            'admEquipamento','equipMaster','equipAC','cadEquipamento','editarCTE',
            'editarEquipe','editarCargo','editarnota','editarNum','criarNum',
            'editarConc','aprovarConc','aprovarFE','aprovarLOC','editarCons',
            'editarDataC','editarRHDoc','editarRHSit','excluirAnexo','editarAnexo',
            'editarLibFunc','editarDtRetSS','alterarEqRH','editarTST','aprovarNFZ',
            'equipSit','cadNF','abrirNF','relAtestado','editarStatusEmp','editarEmpresa',
            'editarImovel','editarLic','cadCT','orcLic','propriosPlaca'
        ]);
    }

    private static function loadTabsMap(\PDO $db): array
    {
        $stmt = $db->query("SELECT id, nome FROM tb_tabs");
        $tabsMap = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $tab) {
            $tabsMap[mb_strtoupper(trim($tab['nome']))] = (int)$tab['id'];
        }
        return $tabsMap;
    }

    private static function enrichUsers(array &$usersById, array $tabsMap): void
    {
        $permFields = array_map('trim', explode(',', self::getPermissionColumns()));

        foreach ($usersById as &$user) {
            // ABAS: converte nomes do nivel_acesso em IDs reais
            $abaIds = [];
            $nivelRaw = trim((string)($user['nivel_acesso'] ?? ''));
            if ($nivelRaw !== '') {
                // cria um conjunto de ids válidos a partir do mapa de abas (para validação)
                $validTabIds = array_map('strval', array_values($tabsMap));

                // detecta se nivelRaw é uma lista de números (ex: "1,2,3") — então usamos direto
                if (preg_match('/^\s*\d+(?:\s*,\s*\d+)*\s*$/', $nivelRaw)) {
                    foreach (explode(',', $nivelRaw) as $id) {
                        $id = trim($id);
                        if ($id === '' || $id === '0') continue; // ignorar 0/vazio
                        // só aceita ids que existem no mapa (evita valores inválidos)
                        if (in_array($id, $validTabIds, true)) {
                            $abaIds[] = (int)$id;
                        }
                    }
                } else {
                    // trata como nomes (comportamento antigo)
                    foreach (explode(',', $nivelRaw) as $nomeAba) {
                        $key = mb_strtoupper(trim($nomeAba));
                        if (isset($tabsMap[$key])) {
                            $abaIds[] = (int)$tabsMap[$key];
                        }
                    }
                }
            }
            $user['abas'] = implode(',', array_unique($abaIds));

            // PERMISSÕES: legado usa 0 = ativo, 1 = inativo
            $activePerms = [];
            foreach ($permFields as $field) {
                if (array_key_exists($field, $user) && (int)$user[$field] === 0) {
                    $activePerms[] = $field;
                }
            }
            $user['permissoes'] = implode(',', $activePerms);
        }
        unset($user);
    }
}