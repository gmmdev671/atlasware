<?php
    session_start();
    if(!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }

    include '../includes/header.php';
?>

<div class="container mt-5">
    <h1>Bem-vindo, <?= $_SESSION['usuario_nome'] ?>!</h1>
    <a href="logout.php" class="btn btn-danger">Sair</a>
</div>
<?php include "../includes/footer.php"; ?>
