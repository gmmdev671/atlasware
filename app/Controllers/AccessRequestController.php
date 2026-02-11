<?php
namespace App\Controllers;

use App\Core\SessionManager;
use App\Models\AccessRequest;
use App\Models\Team;
use App\Models\Role;
use App\Models\UserTeam;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\AuthorizationService;

class AccessRequestController
{
    private AccessRequest $requestModel;
    private AuthorizationService $auth;
    private AuditLog $audit;

    public function __construct()
    {
        $this->requestModel = new AccessRequest();
        $this->auth = new AuthorizationService();
        $this->audit = new AuditLog();
    }

    /**
     * Lista as solicitações do usuário logado.
     */
    public function index(): void
    {
        SessionManager::requireLogin();
        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        $requests = $this->requestModel->findByUser($userId);

        $title = 'Minhas Solicitações';
        ob_start();
        require __DIR__ . '/../Views/requests/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Formulário para criar nova solicitação.
     */
    public function create(): void
    {
        SessionManager::requireLogin();

        $teamModel = new Team();
        $roleModel = new Role();

        $teams = $teamModel->findAll();
        $roles = $roleModel->findAll();

        $title = 'Nova Solicitação de Acesso';
        ob_start();
        require __DIR__ . '/../Views/requests/form.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Salva a solicitação no banco.
     */
    public function store(): void
    {
        SessionManager::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /atlasware/public/requests/create');
            exit;
        }

        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        $type = trim($_POST['type'] ?? '');
        $justification = trim($_POST['justification'] ?? '');

        // Monta o payload baseado no tipo
        $payload = [];
        if ($type === 'team_join') {
            $payload = [
                'team_id' => (int)($_POST['team_id'] ?? 0),
                'role_id' => (int)($_POST['role_id'] ?? 0)
            ];
        } elseif ($type === 'role_change') {
            $payload = [
                'role_id' => (int)($_POST['role_id'] ?? 0)
            ];
        }

        // Validações mínimas
        if ($type === '' || $justification === '') {
            $_SESSION['error'] = 'Preencha todos os campos obrigatórios.';
            header('Location: /atlasware/public/requests/create');
            exit;
        }

        if ($type === 'team_join' && (empty($payload['team_id']) || empty($payload['role_id']))) {
            $_SESSION['error'] = 'Selecione o time e o cargo pretendido.';
            header('Location: /atlasware/public/requests/create');
            exit;
        }

        if ($type === 'role_change' && empty($payload['role_id'])) {
            $_SESSION['error'] = 'Selecione o cargo pretendido.';
            header('Location: /atlasware/public/requests/create');
            exit;
        }

        $data = [
            'user_id' => $userId,
            'requested_by' => $userId,
            'type' => $type,
            'payload' => $payload,
            'justification' => $justification
        ];

        try {
            $requestId = $this->requestModel->create($data); // se seu create() não retorna ID, pode manter sem usar
            $_SESSION['success'] = 'Solicitação enviada com sucesso e aguarda análise.';

            // AUDIT: criação
            $this->audit->log(
                'ACCESS_REQUEST_CREATE',
                'tb_access_requests',
                is_numeric($requestId) ? (int)$requestId : null,
                null,
                [
                    'user_id' => $userId,
                    'type' => $type,
                    'payload' => $payload
                ]
            );

        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao enviar solicitação: ' . $e->getMessage();
        }

        header('Location: /atlasware/public/requests');
        exit;
    }

    /**
     * Área Administrativa: Lista solicitações pendentes.
     */
    public function adminIndex(): void
    {
        SessionManager::requireLogin();

        $user = SessionManager::getUser();
        $userId = (int)$user['id'];

        if (!$this->auth->isMasterOrCoordinator($userId)) {
            $_SESSION['error'] = 'Você não tem permissão para acessar esta área.';
            header('Location: /atlasware/public/dashboard');
            exit;
        }

        $pendingRequests = $this->requestModel->findPending();

        $title = 'Gerenciar Solicitações';
        ob_start();
        require __DIR__ . '/../Views/requests/admin_index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layout/base.php';
    }

    /**
     * Processa a aprovação ou rejeição.
     */
    public function process(int $id): void
    {
        SessionManager::requireLogin();

        $user = SessionManager::getUser();
        $adminId = (int)$user['id'];

        if (!$this->auth->isMasterOrCoordinator($adminId)) {
            $_SESSION['error'] = 'Você não tem permissão para processar solicitações.';
            header('Location: /atlasware/public/dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /atlasware/public/admin/requests');
            exit;
        }

        $action = trim($_POST['action'] ?? ''); // 'approved' ou 'rejected'
        $comment = trim($_POST['review_comment'] ?? '');

        if (!in_array($action, ['approved', 'rejected'], true)) {
            $_SESSION['error'] = 'Ação inválida.';
            header('Location: /atlasware/public/admin/requests');
            exit;
        }

        // Pega estado anterior para auditoria (status/payload)
        $before = $this->requestModel->findById($id);

        $success = $this->requestModel->updateStatus($id, $action, $adminId, $comment);

        if ($success) {
            // AUDIT: decisão
            $this->audit->log(
                $action === 'approved' ? 'ACCESS_REQUEST_APPROVE' : 'ACCESS_REQUEST_REJECT',
                'tb_access_requests',
                $id,
                $before ? [
                    'status' => $before['status'] ?? null,
                    'reviewed_by' => $before['reviewed_by'] ?? null,
                    'review_comment' => $before['review_comment'] ?? null,
                ] : null,
                [
                    'status' => $action,
                    'reviewed_by' => $adminId,
                    'review_comment' => $comment,
                ]
            );

            if ($action === 'approved') {
                // Aplica a mudança de acesso e audita a aplicação
                $this->applyAccessChange($id, $adminId);

                $_SESSION['success'] = 'Solicitação aprovada e acesso concedido.';
            } else {
                $_SESSION['success'] = 'Solicitação rejeitada.';
            }
        } else {
            $_SESSION['error'] = 'Não foi possível processar a solicitação (pode já ter sido processada).';
        }

        header('Location: /atlasware/public/admin/requests');
        exit;
    }

    /**
     * Lógica para aplicar a mudança de acesso após aprovação (com auditoria).
     */
    private function applyAccessChange(int $requestId, int $adminId): void
    {
        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            $this->audit->log(
                'ACCESS_APPLY_FAILED',
                'tb_access_requests',
                $requestId,
                null,
                ['reason' => 'request_not_found']
            );
            return;
        }

        $payload = $this->normalizePayload($request['payload'] ?? null);

        if (($request['type'] ?? '') === 'team_join') {
            $teamId = (int)($payload['team_id'] ?? 0);
            $roleId = (int)($payload['role_id'] ?? 0);
            $targetUserId = (int)($request['user_id'] ?? 0);

            if ($teamId && $roleId && $targetUserId) {
                $userTeamModel = new UserTeam();

                // Opcional: se você tiver como consultar o cargo anterior no time, passe em old_values
                $userTeamModel->addOrUpdateMember($teamId, $targetUserId, $roleId);

                $this->audit->log(
                    'ACCESS_APPLY_TEAM_JOIN',
                    'tb_user_teams',
                    null, // tb_user_teams é chave composta, então record_id não é tão útil
                    null,
                    [
                        'approved_request_id' => $requestId,
                        'approved_by' => $adminId,
                        'user_id' => $targetUserId,
                        'team_id' => $teamId,
                        'role_id' => $roleId,
                    ]
                );
            } else {
                $this->audit->log(
                    'ACCESS_APPLY_FAILED',
                    'tb_access_requests',
                    $requestId,
                    null,
                    ['reason' => 'invalid_payload', 'payload' => $payload]
                );
            }
        }
        elseif (($request['type'] ?? '') === 'role_change') {
            $roleId = (int)($payload['role_id'] ?? 0);
            $targetUserId = (int)($request['user_id'] ?? 0);

            if ($roleId && $targetUserId) {
                $userModel = new User();
                // Ideal: capturar roles antigas antes para old_values (se você tiver um getter)
                $userModel->setRoles($targetUserId, [$roleId]);

                $this->audit->log(
                    'ACCESS_APPLY_ROLE_CHANGE',
                    'tb_user_roles',
                    $targetUserId,
                    null,
                    [
                        'approved_request_id' => $requestId,
                        'approved_by' => $adminId,
                        'user_id' => $targetUserId,
                        'role_id' => $roleId,
                    ]
                );
            } else {
                $this->audit->log(
                    'ACCESS_APPLY_FAILED',
                    'tb_access_requests',
                    $requestId,
                    null,
                    ['reason' => 'invalid_payload', 'payload' => $payload]
                );
            }
        }
        else {
            $this->audit->log(
                'ACCESS_APPLY_FAILED',
                'tb_access_requests',
                $requestId,
                null,
                ['reason' => 'unknown_type', 'type' => $request['type'] ?? null]
            );
        }
    }

    /**
     * Garante que payload esteja em array mesmo se vier JSON.
     */
    private function normalizePayload($payload): array
    {
        if (is_array($payload)) return $payload;

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}