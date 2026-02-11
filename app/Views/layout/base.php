<?php
use App\Core\SessionManager;

// basePath dinâmico
$scriptName = $_SERVER['SCRIPT_NAME']; // /atlasware/public/index.php
$basePath = str_replace('/index.php', '', $scriptName);

// título padrão
$pageTitle = $title ?? 'Atlasware - Controle de Acesso';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <link rel="stylesheet" href="<?= $basePath ?>/css/base.css">
    <link rel="stylesheet" href="<?= $basePath ?>/css/login.css">
    <link rel="stylesheet" href="<?= $basePath ?>/css/access.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-gradient-atlas">

    <?php
    // Navbar extraída para um partial
    require __DIR__ . '/navbar.php';
    ?>

    <!-- Conteúdo principal -->
    <main class="d-flex align-items-start justify-content-center min-vh-100 p-3 p-md-5">
        <div class="container-fluid" style="max-width: 1200px;">
            <?= $content ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"
    ></script>
</body>
</html>