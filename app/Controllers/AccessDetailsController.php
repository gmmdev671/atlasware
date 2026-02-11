<?php
namespace App\Controllers;

use App\Controllers\ObraController;
use App\Controllers\CidadeController;
use App\Controllers\TabsController;

class AccessDetailsController
{
    /**
     * Retorna detalhes completos de um usuário para exibição no card expandido
     *
     * @param int $userId ID do usuário
     * @return array Dados formatados para o frontend
     */
    public static function getUserDetails($userId)
    {
        header('Content-Type: application/json; charset=utf-8');

        error_log("DEBUG getUserDetails: userId = " . var_export($userId, true));

        try {
            $db = getDbConnection();

            $stmt = $db->prepare("
                SELECT
                    id,
                    nome,
                    login,
                    status,
                    nivel_acesso,
                    obra,
                    cidade,
                    acesso_login,
                    detalhe_acesso,
                    obs_user,
                    -- campos de permissão (se estão na mesma tabela usuarios)
                    editarss, editarRH, editarAlmox, editarLancamento, editarEquipamento,
                    admEquipamento, equipMaster, equipAC, cadEquipamento, editarCTE,
                    editarEquipe, editarCargo, editarnota, editarNum, criarNum,
                    editarConc, aprovarConc, aprovarFE, aprovarLOC, editarCons,
                    editarDataC, editarRHDoc, editarRHSit, excluirAnexo, editarAnexo,
                    editarLibFunc, editarDtRetSS, alterarEqRH, editarTST, aprovarNFZ,
                    equipSit, cadNF, abrirNF, relAtestado, editarStatusEmp, editarEmpresa,
                    editarImovel, editarLic, cadCT, orcLic, propriosPlaca
                FROM usuarios
                WHERE id = ?
            ");
            $stmt->execute([(int)$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                exit;
            }

            $payload = [
                'success' => true,
                'user' => self::formatUser($user),
                'obras' => ObraController::getObrasForUser((int)$user['id']),
                'acessos' => self::parseAcessos($user),
                'abas' => self::parseAbas($user['nivel_acesso'] ?? ''),
                'cidades' => CidadeController::getCidadesForUser((int)$user['id']),
                'permissoes' => self::parsePermissoes($user),
            ];

            echo json_encode($payload);
            exit;

        } catch (\Throwable $e) {
            error_log("getUserDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()]);
            exit;
        }
    }

    /**
     * Formata dados básicos do usuário
     */
    private static function formatUser($user)
    {
        return [
            'id' => (int)$user['id'],
            'nome' => $user['nome'],
            'login' => $user['login'],
            'status' => (int)$user['status'],
            'nivel_acesso' => $user['nivel_acesso'] ?? 'N/A'
        ];
    }

    /**
     * Parse dos campos de acesso (acesso_login, detalhe_acesso, obs_user, nivel_acesso)
     */
    private static function parseAcessos($user)
    {
        $acessos = [];

        // Montar array com os campos de acesso disponíveis
        if (!empty($user['acesso_login'])) {
            $acessos[] = [
                'tipo' => 'acesso_login',
                'valor' => $user['acesso_login']
            ];
        }

        if (!empty($user['detalhe_acesso'])) {
            $acessos[] = [
                'tipo' => 'detalhe_acesso',
                'valor' => $user['detalhe_acesso']
            ];
        }

        if (!empty($user['obs_user'])) {
            $acessos[] = [
                'tipo' => 'obs_user',
                'valor' => $user['obs_user']
            ];
        }

        if (!empty($user['nivel_acesso'])) {
            $acessos[] = [
                'tipo' => 'nivel_acesso',
                'valor' => $user['nivel_acesso']
            ];
        }

        return $acessos;
    }

    /**
     * Parse das abas usando o TabsController para obter a lista completa
     * e identificar quais estão ativas com base no nivel_acesso.
     */
    private static function parseAbas($nivelAcessoString)
    {
        // obtém a conexão do mesmo jeito que seus outros controllers fazem
        $db = getDbConnection(); // mesma função usada em ObraController, por exemplo

        if (!$db) {
            error_log('AccessDetailsController::parseAbas - DB connection not available');
            return [];
        }

        $tabsCtrl = new TabsController($db);
        // retorna lista completa com flag 'active' (id, nome, active)
        return $tabsCtrl->getTabsForNivel((string)$nivelAcessoString);
    }

    /**
     * Monta arrays de permissões ativas/inativas a partir dos campos do registro do usuário.
     * Recebe o array $user (linha fetch) para evitar nova consulta.
     */
    private static function parsePermissoes($user)
    {
        $permissionFields = [
            'editarss','editarRH','editarAlmox','editarLancamento','editarEquipamento',
            'admEquipamento','equipMaster','equipAC','cadEquipamento','editarCTE',
            'editarEquipe','editarCargo','editarnota','editarNum','criarNum',
            'editarConc','aprovarConc','aprovarFE','aprovarLOC','editarCons',
            'editarDataC','editarRHDoc','editarRHSit','excluirAnexo','editarAnexo',
            'editarLibFunc','editarDtRetSS','alterarEqRH','editarTST','aprovarNFZ',
            'equipSit','cadNF','abrirNF','relAtestado','editarStatusEmp','editarEmpresa',
            'editarImovel','editarLic','cadCT','orcLic','propriosPlaca'
        ];

        $ativas = [];
        $inativas = [];

        foreach ($permissionFields as $field) {
            if (!array_key_exists($field, $user)) continue;

            $value = (int)$user[$field];

            // ✅ só considera flags 0/1
            if ($value !== 0 && $value !== 1) continue;

            $permissao = [
                'campo' => $field,
                'nome_exibicao' => self::formatPermissionName($field),
                'valor' => $value
            ];

            if ($value === 0) $ativas[] = $permissao;
            else $inativas[] = $permissao;
        }

        return ['ativas' => $ativas, 'inativas' => $inativas];
    }

    /**
     * Formata o nome do campo de permissão para exibição legível
     */
    private static function formatPermissionName($field)
    {
        // Converter camelCase para espaços e maiúsculas
        $formatted = preg_replace('/([a-z])([A-Z])/', '$1 $2', $field);
        return ucfirst($formatted);
    }

    /**
     * Ativa permissões fornecidas para um usuário (define o campo = 0).
     * Espera JSON POST: { "userId": 123, "permissions": ["editarEquipe", "editarnota", ...] }
     */
    public static function activatePermissions()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $db = getDbConnection();

            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                echo json_encode(['success' => false, 'message' => 'Payload inválido']);
                return;
            }

            $userId = isset($input['userId']) ? (int)$input['userId'] : 0;
            $permissions = isset($input['permissions']) && is_array($input['permissions']) ? $input['permissions'] : [];

            if ($userId <= 0 || empty($permissions)) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros ausentes.']);
                return;
            }

            $allowedFields = [
                'editarss', 'editarRH', 'editarAlmox', 'editarLancamento',
                'editarEquipamento', 'admEquipamento', 'equipMaster', 'equipAC',
                'cadEquipamento', 'editarCTE', 'editarEquipe', 'editarCargo',
                'editarnota', 'editarNum', 'criarNum', 'editarConc',
                'aprovarConc', 'aprovarFE', 'aprovarLOC', 'editarCons',
                'editarDataC', 'editarRHDoc', 'editarRHSit', 'excluirAnexo',
                'editarAnexo', 'editarLibFunc', 'editarDtRetSS', 'alterarEqRH',
                'editarTST', 'aprovarNFZ', 'equipSit', 'cadNF',
                'abrirNF', 'relAtestado', 'editarStatusEmp', 'editarEmpresa',
                'editarImovel', 'editarLic', 'cadCT', 'orcLic',
                'propriosPlaca', 'user_edicao'
            ];

            $validPermissions = array_values(array_intersect($permissions, $allowedFields));
            if (empty($validPermissions)) {
                echo json_encode(['success' => false, 'message' => 'Nenhuma permissão válida encontrada']);
                return;
            }

            // Buscar estado antigo
            $fieldsStr = implode(', ', array_map(fn($f) => "`$f`", $validPermissions));
            $stmtOld = $db->prepare("SELECT $fieldsStr FROM usuarios WHERE id = :userId");
            $stmtOld->execute([':userId' => $userId]);
            $oldValues = $stmtOld->fetch(\PDO::FETCH_ASSOC);

            if ($oldValues === false) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                return;
            }

            // Montar SET dinâmico para ativar (0)
            $setParts = [];
            $binds = [':userId' => $userId];
            foreach ($validPermissions as $idx => $field) {
                $ph = ":val_{$idx}";
                $setParts[] = "`$field` = $ph";
                $binds[$ph] = 0;
            }
            $setString = implode(', ', $setParts);

            $stmtUpdate = $db->prepare("UPDATE usuarios SET $setString WHERE id = :userId");
            $stmtUpdate->execute($binds);
            $affected = $stmtUpdate->rowCount();

            // Buscar estado novo
            $stmtNew = $db->prepare("SELECT $fieldsStr FROM usuarios WHERE id = :userId");
            $stmtNew->execute([':userId' => $userId]);
            $newValues = $stmtNew->fetch(\PDO::FETCH_ASSOC);

            // Registrar auditoria
            $audit = new \App\Models\AuditLog();
            $audit->log(
                'ATIVAR_PERMISSOES',
                'usuarios',
                $userId,
                $oldValues,
                $newValues
            );

            echo json_encode(['success' => true, 'updated' => $affected]);
            return;

        } catch (\Throwable $e) {
            error_log("activatePermissions error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            return;
        }
    }

    /**
     * Desativa permissões fornecidas para um usuário (define o campo = 1).
     * Espera JSON POST: { "userId": 123, "permissions": ["editarEquipe", "editarnota", ...] }
     */
    public static function deactivatePermissions()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $db = getDbConnection();

            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                echo json_encode(['success' => false, 'message' => 'Payload inválido']);
                return;
            }

            $userId = isset($input['userId']) ? (int)$input['userId'] : 0;
            $permissions = isset($input['permissions']) && is_array($input['permissions']) ? $input['permissions'] : [];

            if ($userId <= 0 || empty($permissions)) {
                echo json_encode(['success' => false, 'message' => 'Parâmetros ausentes.']);
                return;
            }

            $allowedFields = [
                'editarss', 'editarRH', 'editarAlmox', 'editarLancamento',
                'editarEquipamento', 'admEquipamento', 'equipMaster', 'equipAC',
                'cadEquipamento', 'editarCTE', 'editarEquipe', 'editarCargo',
                'editarnota', 'editarNum', 'criarNum', 'editarConc',
                'aprovarConc', 'aprovarFE', 'aprovarLOC', 'editarCons',
                'editarDataC', 'editarRHDoc', 'editarRHSit', 'excluirAnexo',
                'editarAnexo', 'editarLibFunc', 'editarDtRetSS', 'alterarEqRH',
                'editarTST', 'aprovarNFZ', 'equipSit', 'cadNF',
                'abrirNF', 'relAtestado', 'editarStatusEmp', 'editarEmpresa',
                'editarImovel', 'editarLic', 'cadCT', 'orcLic',
                'propriosPlaca', 'user_edicao'
            ];

            $validPermissions = array_values(array_intersect($permissions, $allowedFields));
            if (empty($validPermissions)) {
                echo json_encode(['success' => false, 'message' => 'Nenhuma permissão válida encontrada']);
                return;
            }

            // Buscar estado antigo
            $fieldsStr = implode(', ', array_map(fn($f) => "`$f`", $validPermissions));
            $stmtOld = $db->prepare("SELECT $fieldsStr FROM usuarios WHERE id = :userId");
            $stmtOld->execute([':userId' => $userId]);
            $oldValues = $stmtOld->fetch(\PDO::FETCH_ASSOC);

            if ($oldValues === false) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                return;
            }

            // Montar SET dinâmico para desativar (1)
            $setParts = [];
            $binds = [':userId' => $userId];
            foreach ($validPermissions as $idx => $field) {
                $ph = ":val_{$idx}";
                $setParts[] = "`$field` = $ph";
                $binds[$ph] = 1;
            }
            $setString = implode(', ', $setParts);

            $stmtUpdate = $db->prepare("UPDATE usuarios SET $setString WHERE id = :userId");
            $stmtUpdate->execute($binds);
            $affected = $stmtUpdate->rowCount();

            // Buscar estado novo
            $stmtNew = $db->prepare("SELECT $fieldsStr FROM usuarios WHERE id = :userId");
            $stmtNew->execute([':userId' => $userId]);
            $newValues = $stmtNew->fetch(\PDO::FETCH_ASSOC);

            // Registrar auditoria
            $audit = new \App\Models\AuditLog();
            $audit->log(
                'DESATIVAR_PERMISSOES',
                'usuarios',
                $userId,
                $oldValues,
                $newValues
            );

            echo json_encode(['success' => true, 'updated' => $affected]);
            return;

        } catch (\Throwable $e) {
            error_log("deactivatePermissions error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
            return;
        }
    }

    /**
     * Placeholder para registrar auditoria — substitua pela sua implementação.
     */
    protected static function logAudit($action, $userId, $details = [])
    {
        // Exemplo simples: grava no log. Substitua por inserção em tabela tb_audit ou similar.
        error_log(sprintf('AUDIT: %s user=%d details=%s', $action, $userId, json_encode($details)));
    }
}