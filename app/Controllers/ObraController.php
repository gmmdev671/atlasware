<?php
namespace App\Controllers;

use App\Models\AuditLog;

class ObraController
{
    /**
     * Retorna todas as obras com flag 'associada' para o usuário indicado.
     * Se $search for fornecido, filtra pelo nome/descrição.
     * Retorna array: [ ['id' => int, 'nome_obra' => string, 'associada' => bool], ... ]
     *
     * @param int|null $userId
     * @param string|null $search
     * @return array
     */
    public static function getObrasForUser($userId = null, $search = null)
    {
        $search = $search === null ? '' : trim($search);

        try {
            $db = getDbConnection();

            // Carrega IDs associados ao usuário (string CSV) e transforma em array de ints
            $userObras = [];
            if ($userId !== null && (int)$userId > 0) {
                $stmt = $db->prepare('SELECT obra FROM usuarios WHERE id = :uid LIMIT 1');
                $stmt->execute([':uid' => (int)$userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['obra'])) {
                    $raw = array_filter(array_map('trim', explode(',', $row['obra'])), fn($v) => $v !== '' && is_numeric($v));
                    $userObras = array_map('intval', $raw);
                }
            }

            // Consulta todas as obras (com filtro opcional)
            if ($search !== '') {
                $sql = "SELECT id, COALESCE(descricao, '') AS descricao FROM notas_obras WHERE descricao LIKE :search ORDER BY descricao ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute([':search' => '%' . $search . '%']);
            } else {
                $sql = "SELECT id, COALESCE(descricao, '') AS descricao FROM notas_obras ORDER BY descricao ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute();
            }

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                $result[] = [
                    'id' => $id,
                    'nome_obra' => $r['descricao'] !== '' ? $r['descricao'] : ('Obra ' . $id),
                    'associada' => in_array($id, $userObras, true)
                ];
            }

            // Ordena: associadas primeiro, depois por nome
            usort($result, function($a, $b) {
                if ($a['associada'] === $b['associada']) return strcasecmp($a['nome_obra'], $b['nome_obra']);
                return $a['associada'] ? -1 : 1;
            });

            return $result;

        } catch (\Throwable $e) {
            error_log('ObraController::getObrasForUser error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualiza as obras associadas a um usuário.
     * Recebe array de IDs (inteiros) ou string CSV. Retorna ['success'=>bool, 'message'=>string]
     * Valida IDs existentes em notas_obras e registra auditoria.
     *
     * @param int $userId
     * @param array|string $obraIds
     * @return array
     */
    public static function updateUserObras($userId, $obraIds)
    {
        try {
            $db = getDbConnection();

            $userId = (int)$userId;
            if ($userId <= 0) return ['success' => false, 'message' => 'userId inválido'];

            // Normaliza obraIds para array de inteiros
            if (is_string($obraIds)) {
                $raw = array_filter(array_map('trim', explode(',', $obraIds)), fn($v) => $v !== '' && is_numeric($v));
                $ids = array_map('intval', $raw);
            } elseif (is_array($obraIds)) {
                $ids = array_values(array_filter(array_map('intval', $obraIds), fn($v) => $v > 0));
            } else {
                $ids = [];
            }

            // Valida IDs: mantém apenas os que existem na tabela
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("SELECT id FROM notas_obras WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
                $valid = array_map('intval', $rows ?: []);
            } else {
                $valid = [];
            }

            // Ordena e gera string CSV (sem duplicatas)
            $valid = array_values(array_unique($valid));
            $csv = implode(',', $valid);

            // Buscar estado antigo (para auditoria)
            $stmtOld = $db->prepare('SELECT obra FROM usuarios WHERE id = :uid LIMIT 1');
            $stmtOld->execute([':uid' => $userId]);
            $old = $stmtOld->fetch(\PDO::FETCH_ASSOC);
            $oldValue = $old ? ($old['obra'] ?? '') : null;

            // Atualiza
            $stmtUpdate = $db->prepare('UPDATE usuarios SET obra = :obra WHERE id = :uid');
            $stmtUpdate->execute([':obra' => $csv, ':uid' => $userId]);
            $affected = $stmtUpdate->rowCount();

            // Registrar auditoria (se houver mudança)
            $newValue = $csv;
            if ($newValue !== $oldValue) {
                try {
                    $audit = new AuditLog();
                    $audit->log('ATUALIZAR_OBRAS', 'usuarios', $userId, ['obra' => $oldValue], ['obra' => $newValue]);
                } catch (\Throwable $inner) {
                    error_log('ObraController audit error: ' . $inner->getMessage());
                }
            }

            return ['success' => true, 'updated' => $affected, 'obra' => $csv];

        } catch (\Throwable $e) {
            error_log('ObraController::updateUserObras error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Endpoint helper: lista paginada/filtrada para uso em selects (AJAX)
     * Parâmetros via $_GET: q (query), user_id (opcional), limit, offset
     * Imprime JSON (compatível com select2/simple select)
     */
    public static function ajaxList()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 200;
            $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

            $items = self::getObrasForUser($userId, $q);

            $total = count($items);
            if ($limit > 0) {
                $items = array_slice($items, $offset, $limit);
            }

            echo json_encode(['success' => true, 'total' => $total, 'items' => $items]);
            return;
        } catch (\Throwable $e) {
            error_log('ObraController::ajaxList error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }
    }
}