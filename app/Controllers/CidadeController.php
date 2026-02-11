<?php
namespace App\Controllers;

class CidadeController
{
    /**
     * Retorna todas as cidades com flag de associação para um usuário específico
     */
    public static function getCidadesForUser($userId = null, $search = null)
    {
        try {
            $db = getDbConnection();
            
            // 1. Buscar todas as cidades (com filtro opcional)
            $sql = "SELECT id, nome FROM notas_obras_cidade";
            $params = [];
            
            if ($search) {
                $sql .= " WHERE nome LIKE ?";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY nome ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $allCidades = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Buscar cidades associadas ao usuário
            $userCidades = [];
            if ($userId) {
                $stmtUser = $db->prepare("SELECT cidade FROM usuarios WHERE id = ?");
                $stmtUser->execute([(int)$userId]);
                $userRow = $stmtUser->fetch(\PDO::FETCH_ASSOC);
                
                if ($userRow && !empty($userRow['cidade'])) {
                    $userCidades = array_filter(array_map('trim', explode(',', $userRow['cidade'])));
                }
            }

            // 3. Marcar associações
            foreach ($allCidades as &$cidade) {
                $cidade['associada'] = in_array($cidade['id'], $userCidades);
            }

            // 4. Ordenar: associadas primeiro
            usort($allCidades, function($a, $b) {
                if ($a['associada'] === $b['associada']) {
                    return strcasecmp($a['nome'], $b['nome']);
                }
                return $a['associada'] ? -1 : 1;
            });

            return $allCidades;

        } catch (\Throwable $e) {
            error_log("Error in getCidadesForUser: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Endpoint AJAX para listagem/pesquisa de cidades
     */
    public static function ajaxList()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $search = isset($_GET['q']) ? trim($_GET['q']) : null;

        $items = self::getCidadesForUser($userId, $search);

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
        exit;
    }

    /**
     * Atualiza as cidades associadas a um usuário
     */
    public static function updateUserCidades($userId, $cidadeIds)
    {
        if (!$userId) {
            return ['success' => false, 'message' => 'ID do usuário inválido'];
        }

        try {
            $db = getDbConnection();

            // Validar se os IDs existem (opcional, mas recomendado)
            $cidadeIds = array_filter(array_map('intval', $cidadeIds));
            
            // Buscar estado antigo para auditoria
            $stmtOld = $db->prepare("SELECT cidade FROM usuarios WHERE id = ?");
            $stmtOld->execute([$userId]);
            $oldData = $stmtOld->fetch(\PDO::FETCH_ASSOC);

            // Salvar como string separada por vírgula (padrão do seu banco)
            $cidadesString = implode(',', $cidadeIds);
            
            $stmt = $db->prepare("UPDATE usuarios SET cidade = ? WHERE id = ?");
            $stmt->execute([$cidadesString, $userId]);

            // Registrar no log de auditoria
            $audit = new \App\Models\AuditLog();
            $audit->log(
                'UPDATE_USER_CIDADES',
                'usuarios',
                $userId,
                ['cidade' => $oldData['cidade'] ?? ''],
                ['cidade' => $cidadesString]
            );

            return [
                'success' => true, 
                'message' => 'Cidades atualizadas com sucesso',
                'cidades' => $cidadesString
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()];
        }
    }
}