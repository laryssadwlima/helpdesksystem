<?php
session_start();// Conexão com o banco de dados
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Conectar ao banco de dados
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
    }

    // Obter o e-mail do formulário
    $email = $_POST['email'];

    // Verificar se o e-mail está registrado
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Gerar um token único para recuperação
        $token = bin2hex(random_bytes(50));
        $stmt = $pdo->prepare('INSERT INTO password_resets (email, token) VALUES (?, ?)');
        $stmt->execute([$email, $token]);

        // Enviar e-mail de recuperação
        enviarEmailRecuperarSenha($user['username'], $email, $token);
        $success_message = "Um e-mail com instruções para redefinir sua senha foi enviado.";
    } else {
        $error_message = "Esse e-mail não está registrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <style>
        .logo-container {
            display: flex;
            justify-content: center; /* Centraliza horizontalmente */
            align-items: center;    /* Centraliza verticalmente */
            margin-bottom: 20px;    /* Espaçamento inferior */
        }

        .logo1 {
            max-width: 20%;         /* Garante que a logo não ultrapasse a largura do card */
            height: auto;            /* Mantém a proporção da imagem */
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="card">
           <div class="logo-container">
              <img src="CSS/foto/logo.png" alt="Logo" class="logo1"> 
           </div>
        <h2 class="text-center">Recuperar Senha</h2>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" class="form-control" name="email" placeholder="Digite seu e-mail" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Enviar Link de Redefinição de Senha</button>
                    <button type="button" onclick="history.back()" class="btn btn-secondary btn-block">Voltar</button>
                </form>
                <?php if (isset($success_message)) echo "<div class='alert alert-success mt-3'>$success_message</div>"; ?>
                <?php if (isset($error_message)) echo "<div class='alert alert-danger mt-3'>$error_message</div>"; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
