<?php
    session_start();
    require_once '../config/db.php';

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $login = trim($_POST['login']);
        $senha = trim($_POST['senha']);

        $sql = "SELECT id, nome, senha FROM usuarios WHERE login = :login LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login);
        $stmt->execute();

        if($stmt->rowCount() == 1){
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if(password_verify($senha, $usuario['senha'])){
                $_SESSION['usuario_id'] = $usuario['id'];
                header("Location: index.php");
                exit();
            } else {
                $erro = "Senha incorreta.";
                echo $erro;
            }
        } else {
            $erro = "Usuário não encontrado.";
                echo $erro;
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

                <?php if(isset($erro)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $erro; ?></div>
                <?php endif; ?>

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