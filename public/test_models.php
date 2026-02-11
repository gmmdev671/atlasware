<?php
require_once __DIR__ . '/../app/Config/bootstrap.php';

use App\Models\Team;
use App\Models\Role;
use App\Models\Permission;
use App\Models\AccessRequest;
use App\Models\AuditLog;

header('Content-Type: text/plain');

echo "=== Teste dos Models ===\n\n";

try {
    // Teste Team
    $teamModel = new Team();
    echo "Times existentes:\n";
    print_r($teamModel->findAll());

    // Criar um time novo
    $newTeamId = $teamModel->create(['name' => 'Time Teste', 'parent_team_id' => null]);
    echo "Criado time com ID: $newTeamId\n";

    // Atualizar time
    $teamModel->update($newTeamId, ['name' => 'Time Teste Atualizado', 'parent_team_id' => null]);
    echo "Time atualizado:\n";
    print_r($teamModel->findById($newTeamId));

    // Teste Role
    $roleModel = new Role();
    echo "\nRoles existentes:\n";
    print_r($roleModel->findAll());

    // Criar role nova
    $newRoleId = $roleModel->create(['name' => 'Role Teste', 'level' => 50]);
    echo "Criada role com ID: $newRoleId\n";

    // Atualizar role
    $roleModel->update($newRoleId, ['name' => 'Role Teste Atualizada', 'level' => 40]);
    echo "Role atualizada:\n";
    print_r($roleModel->findById($newRoleId));

    // Teste Permission
    $permModel = new Permission();
    echo "\nPermissões existentes:\n";
    print_r($permModel->findAll());

    // Criar permissão nova
    $newPermId = $permModel->create('Permissão Teste');
    echo "Criada permissão com ID: $newPermId\n";

    // Atualizar permissão
    $permModel->update($newPermId, 'Permissão Teste Atualizada');
    echo "Permissão atualizada:\n";
    print_r($permModel->findById($newPermId));

    // Teste AccessRequest
    $accessReqModel = new AccessRequest();
    echo "\nSolicitações de acesso pendentes:\n";
    print_r($accessReqModel->findPending());

    // Criar solicitação de acesso (use um user_id e permission_id válidos do seu BD)
    $newAccessReqId = $accessReqModel->create([
        'user_id' => 1,
        'requested_permission_id' => $newPermId,
        'team_id' => $newTeamId
    ]);
    echo "Criada solicitação de acesso com ID: $newAccessReqId\n";

    // Atualizar status da solicitação
    $accessReqModel->updateStatus($newAccessReqId, 'approved', 1, 'Aprovado para teste');
    echo "Solicitação atualizada:\n";
    print_r($accessReqModel->findByUser(1));

    // Teste AuditLog
    $auditModel = new AuditLog();
    echo "\nLogs de auditoria recentes:\n";
    print_r($auditModel->findAll(5));

    // Criar log de auditoria
    $auditModel->log([
        'user_id' => 1,
        'permission_id' => $newPermId,
        'team_id' => $newTeamId,
        'action' => 'granted',
        'performed_by' => 1,
        'comment' => 'Teste de log'
    ]);
    echo "Log criado.\n";

    echo "\nLogs atualizados:\n";
    print_r($auditModel->findAll(5));

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}