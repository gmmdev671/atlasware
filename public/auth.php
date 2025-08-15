<?php
    session_start();
    require_once '../config/db.php';
    $loginUrl = 'http://localhost:8080/atlasware_api/users/login';

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $login = trim($_POST['login']);
        $senha = trim($_POST['senha']);

        $data = [
            "login" => $login,
            "senha" => $senha
        ];

        $ch = curl_init($loginUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['ERROR'])) {
            // Ação no caso de erro
            echo "<div class='alert alert-danger'>" . htmlspecialchars($result['ERROR']) . "</div>";
        } else {
            // Ação no caso de sucesso
            echo "<div class='alert alert-success'>". htmlspecialchars($result['SUCCESS']) ."</div>";
            $_SESSION['usuario_id'] = $result['USER_ID'];
            $_SESSION['usuario_nome'] = $result['USER_NAME'];
            header("Location: index.php");
            exit();
        }
    }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Atlasware</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            /* Fundo gradiente para simular a imagem */
            background: #232526;  /* fallback for old browsers */
            background: -webkit-linear-gradient(to bottom, #fcfcfcff, #7dd4ffff);  /* Chrome 10-25, Safari 5.1-6 */
            background: linear-gradient(to bottom, #ffffffff, #7dd4ffff); /* W3C, IE 10+/ Edge, Firefox 16+, Chrome 26+, Opera 12+, Safari 7+ */
            height: 100vh;
        }

        .card {
            /* Sombra mais suave para um efeito elevado */
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border: none;
        }

        /* Cor personalizada para o botão do Google */
        .btn-google {
            background-color: #DB4437;
            color: white;
        }
        .btn-google:hover {
            background-color: #c23322;
            color: white;
        }
        
        .btn-twitter {
            background-color: #1DA1F2;
            color: white;
        }
         .btn-twitter:hover {
            background-color: #0c85d0;
            color: white;
        }
    </style>
</head>
<body class="d-{sm,md,lg,xl,xxl}-flex align-items-center justify-content-center min-vh-100 px-auto">

    <div class="card p-4 mx-auto my-4" style="width: 30%">
        <div class="card-body">

            <div class="text-center md-4 pb-4">
                <img src="https://atlasware.com.br/imagens/atlasware-logo2.png" alt="Atlasware logo" style="width: 150px;">
            </div>

            <form action="auth.php" method="POST">

                <div class="mb-3">
                    <label for="login" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="login" name="login" placeholder="Usuário" required>
                </div>

                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" placeholder="Senha" required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="lembrar-senha" name="lembrar-senha">
                    <label class="form-check-label" for="lembrar-senha">Lembrar senha</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Entrar</button>

            </form>

            <hr>

            <div class="text-center">
                <p class="text-muted">Ou entre com:</p>
                <a href="#" class="btn btn-primary mx-1"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="btn btn-twitter mx-1"><i class="fab fa-twitter"></i></a>
                <a href="#" class="btn btn-google mx-1"><i class="fab fa-google"></i></a>
            </div>
        </div>
    </div>
</body>