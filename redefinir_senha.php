<?php
session_start();
include 'config.php'; // Inclua seu arquivo de configuração

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Conectar ao banco de dados
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
    }

    // Obter o token e a nova senha
    $token = $_POST['token'];
    $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);

    // Verificar se o token existe
    $stmt = $pdo->prepare('SELECT email FROM password_resets WHERE token = ?');
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset) {
        // Atualizar a senha no banco de dados
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->execute([$nova_senha, $reset['email']]);

        // Remover o token usado
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);

        $success_message = "Senha redefinida com sucesso!";
    } else {
        $error_message = "Token inválido ou expirado.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="CSS/style.css">
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
    <div class="container mt-5">
        <div class="card">
           <div class="logo-container">
                <img src="CSS/foto/logo.png" alt="Logo" class="logo1"> 
            </div>
           <h2 class="text-center">Redefinir Senha</h2>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                    <div class="form-group">
                        <label for="nova_senha">Nova Senha</label>
                        <input type="password" class="form-control" name="nova_senha" placeholder="Digite sua nova senha" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Redefinir Senha</button>
                    
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
