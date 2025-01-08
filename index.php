<?php
session_start();

// Se o usuário já estiver logado, redireciona para o dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Inicializa a variável de erro
$error = '';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Conectar ao banco de dados
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
    }

    // Alterado para 'email' em vez de 'username'
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Verificar as credenciais no banco de dados
    $stmt = $pdo->prepare('SELECT id, password, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    date_default_timezone_set('America/Sao_Paulo');

    // Verifica se o usuário foi encontrado e se a senha está correta
    if ($user && password_verify($password, $user['password'])) {
        // Armazenar informações na sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Atualizar o último login
        $lastLogin = date('Y-m-d H:i:s'); // Formato da data
        $updateStmt = $pdo->prepare('UPDATE users SET last_login = :last_login WHERE id = :id');
        $updateStmt->execute(['last_login' => $lastLogin, 'id' => $user['id']]);

        // Redirecionar para o dashboard
        header('Location: dashboard.php');
        exit();
    } else {
        // Exibe mensagem de erro se as credenciais estiverem incorretas
        $error = 'E-mail ou senha inválidos!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk</title>
    <link rel="stylesheet" href="CSS/login.css"> 
    <link rel="icon" type="image/x-icon" href="CSS/foto/.ico">
    <style> 
        input[type="email"], input[type="password"] {
            width: 90%; /* Ajuste a largura conforme necessário */
            padding: 10px;
            margin: 5px 0; /* Removido auto para o alinhamento correto */
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: 'inter', sans-serif; /* Define a fonte como Calibri */
            font-size: 14px;
        }
        .error-message {
            color: red; /* Cor da mensagem de erro */
            margin: 10px 0; /* Espaço em cima e embaixo da mensagem */
            font-size: 14px; /* Tamanho da fonte da mensagem */
        }
        
  /* Media Queries para dispositivos móveis */
@media (max-width: 480px) {
    .form-section {
        width: 50%; /* Largura do card em dispositivos móveis */
        padding: 200px; /* Reduz o padding em dispositivos móveis */
    }

    .logo {
        width: 50%; 
    }

    
.background-image {
    width: 100%;
    height: 100%;
    object-fit: cover; /* Corta a imagem mantendo a proporção */
}

.form-section {
    background: white; /* Fundo branco para o card de login */
    border-radius: 10px; /* Cantos arredondados */
    padding: 50px; /* Espaçamento interno */
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); /* Sombra para o card */
    width: 90%; /* Largura do card */
    max-width: 400px; /* Largura máxima */
}

h2 {
    text-align: center; /* Centraliza o título */
    margin-bottom: 20px; /* Espaçamento inferior */
}

input[type="email"], input[type="password"] {
    width: 100%; /* Largura total dos inputs */
    padding: 10px;
    margin: 10px 0; /* Espaçamento entre os inputs */
    border: 1px solid #ccc;
    border-radius: 5px;
    font-family: 'inter', sans-serif; 
    font-size: 14px;
}

button {
    width: 100%; /* Largura total do botão */
    padding: 10px;
    background-color: #007bff; /* Cor do botão */
    color: white; /* Cor do texto do botão */
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer; /* Muda o cursor para pointer */
}


.forgot-password {
    text-align: center; /* Centraliza o link de recuperação de senha */
    margin-top: 10px; /* Espaçamento acima */
}

.signup-link {
    text-align: center; /* Centraliza o link de registro */
    margin-top: 20px; /* Espaçamento acima */
}


}

    </style>
</head>
<body>
    <div class="container">
        <div class="image-section">
            <img src="CSS/foto/photo.jpg" alt="Imagem de fundo" class="background-image">
        </div>
        <div class="form-section">
            <div class="logo-container">
                <img src="CSS/foto/logo.png" alt="Logo" class="logo"> 
            </div>
            <form method="POST">
                <h2>Login</h2>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div> 
                <?php endif; ?>
                <input type="email" name="email" placeholder="E-mail" required>
                <input type="password" name="password" placeholder="Senha" required>
                <div class="form-footer">
                    <button type="submit">Entrar</button>
                    <div class="forgot-password">
                        <a href="recuperar_senha.php">Esqueceu a senha?</a>
                    </div>
                </div>
            </form>
            <div class="signup-link">
                <p>Não tem uma conta? <a href="register.php">Crie uma</a></p>
            </div>
        </div>
    </div>
    <div class="site-description">
        <p>Sistema de Chamados Tecnologia da Informação - Versão: 1.0</p>
    </div>
</body>
</html>
