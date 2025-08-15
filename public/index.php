<?php
    session_start();
    var_dump(($_SESSION['usuario_id']));
    if(!isset($_SESSION['usuario_id'])) {
        header("Location: auth.php");
        exit;
    }

    include '../includes/header.php';

    include_once __DIR__ . '/../core/endpoints.php';
?>
    
<div class="container mt-5">
    <h1>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h1>
    <a href="logout.php" class="btn btn-danger">Sair</a>
</div>
<?php include "../includes/footer.php"; ?>
