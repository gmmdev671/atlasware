<?php
namespace App\Controllers;

/**
 * app/Controllers/TabsController.php
 *
 * Controlador simples para trabalhar com tb_tabs e o campo legado nivel_acesso.
 * Usa PDO injetado no construtor para facilitar testes e reuse.
 */

class TabsController
{
    private \PDO $pdo;

    /**
     * Simple in-memory cache to reduce queries during single request.
     * Keyed by 'all' or 'nivel:{string}' or 'user:{id}'.
     * @var array
     */
    private array $cache = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todas as abas cadastradas em tb_tabs.
     * Resultado: array of ['id' => int, 'nome' => string]
     *
     * @return array
     */
    public function getAllTabs(): array
    {
        if (isset($this->cache['all'])) {
            return $this->cache['all'];
        }

        $stmt = $this->pdo->query("SELECT id, nome FROM tb_tabs ORDER BY id");
        $all = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        // Normaliza tipos
        foreach ($all as &$t) {
            $t['id'] = (int)$t['id'];
            $t['nome'] = (string)$t['nome'];
        }
        $this->cache['all'] = $all;
        return $all;
    }

    /**
     * Recebe string "1,2,3" (campo nivel_acesso) e devolve a lista completa de abas,
     * cada item contendo: ['id' => string, 'nome' => string, 'active' => bool]
     *
     * Mantém a ordem das abas conforme tb_tabs e marca active se presente no nivel.
     *
     * @param string|null $nivelString
     * @return array
     */
    public function getTabsForNivel(?string $nivelString): array
    {
        $key = 'nivel:' . ($nivelString ?? '');
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $ids = $this->parseNivel((string)($nivelString ?? ''));

        // monta lookup para marcação rápida
        $lookup = [];
        foreach ($ids as $id) {
            $lookup[(string)$id] = true;
        }

        $all = $this->getAllTabs();
        $result = [];
        foreach ($all as $t) {
            $result[] = [
                'id'     => (string)$t['id'],
                'nome'   => $t['nome'],
                'active' => isset($lookup[(string)$t['id']])
            ];
        }

        $this->cache[$key] = $result;
        return $result;
    }

    /**
     * Busca o nivel_acesso do usuário e delega para getTabsForNivel.
     *
     * @param int $userId
     * @return array
     */
    public function getTabsForUser(int $userId): array
    {
        $key = 'user:' . $userId;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->pdo->prepare("SELECT nivel_acesso FROM tb_users WHERE id = ?");
        $stmt->execute([$userId]);
        $nivel = $stmt->fetchColumn();
        $result = $this->getTabsForNivel((string)$nivel);
        $this->cache[$key] = $result;
        return $result;
    }

    /**
     * Parse do campo nivel_acesso (string) para array de ids em string, mantendo ordem e removendo duplicatas.
     *
     * @param string $nivelString
     * @return array  Array of id strings (ex: ['1','2','5'])
     */
    private function parseNivel(string $nivelString): array
    {
        $nivelString = trim($nivelString);
        if ($nivelString === '' || $nivelString === '0') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $nivelString)), function ($v) {
            return $v !== '';
        });

        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            // normaliza para int-string (remove zeros à esquerda) e ignora 0
            $n = (string)intval($p);
            if ($n === '0') continue;
            if (!isset($seen[$n])) {
                $seen[$n] = true;
                $out[] = $n;
            }
        }
        return $out;
    }
}