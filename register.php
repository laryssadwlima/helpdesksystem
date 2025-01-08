<?php
// Conectar ao banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Inicializa variáveis para armazenar mensagens de erro ou sucesso
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obter dados do formulário com verificação se as chaves existem
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    $telefone = isset($_POST['telefone']) ? $_POST['telefone'] : '';
    $setor = isset($_POST['setor']) ? $_POST['setor'] : '';
    $nomeCompleto = isset($_POST['nomeCompleto']) ? $_POST['nomeCompleto'] : '';

    // Verificar se as senhas correspondem
    if ($password !== $confirmPassword) {
        $error = 'As senhas não correspondem!';
    } else {
        // Verificar se o usuário já existe
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'Usuário já existe!';
        } else {
            // Hash da senha
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Inserir novo usuário
            $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, telefone, setor, nomeCompleto, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            if ($stmt->execute([$username, $email, $hashedPassword, 'user', $telefone, $setor, $nomeCompleto])) {
                $success = 'Usuário registrado com sucesso!';
            } else {
                $error = 'Erro ao registrar usuário!';
            }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Helpdesk</title>
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
     <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
        }

        .card {
            width: 100%;
            max-width: 500px;
            margin: auto; /* Centraliza o card na tela */
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px; /* Adiciona bordas arredondadas */
        }

        @media (max-width: 576px) { /* Para telas menores que 576px */
            .card {
              padding: 15px; /* Reduz o padding */
            }
        }
        .card h3 {
            text-align: center;
        }

        .logo {
            display: block;
            margin: 0 auto;
            width: 50%; /* Ajuste a largura conforme necessário */
            height: auto; /* Mantém a proporção da imagem */
        }

        .toggle-password {
            float: right;
            margin-right: 10px;
            margin-top: -30px;
            position: relative;
            cursor: pointer;
        }

    </style>
</head>
<body>
    <div class="card">
        <img src="CSS/foto/logo.png" alt="Logo" class="logo">
        <h3>Registrar Novo Usuário</h3>
        <?php if ($success) { echo "<div class='alert alert-success'>$success</div>"; } ?>
        <?php if ($error) { echo "<div class='alert alert-danger'>$error</div>"; } ?>
        <form method="post">
            <div class="form-group">
                <label for="nomeCompleto">Nome</label>
                <input type="text" class="form-control" placeholder="Informe seu nome completo" id="nomeCompleto" name="nomeCompleto" required>
            </div>
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" class="form-control" id="username" placeholder="Informe um nome de usuário para login" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" placeholder="Informe seu email @.com.br" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" placeholder="Senha" class="form-control" id="password" required>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirmar Senha</label>
                <input type="password" name="confirmPassword" placeholder="Confirmar senha" class="form-control" id="confirmPassword" required>
            </div>
            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" class="form-control" placeholder="Informe seu número com DDD" id="telefone" name="telefone" required>
            </div>
            <div class="form-group">
                <label for="setor">Setor</label>
                <select class="form-control" id="setor" name="setor" required>
                    <option value="" disabled selected>Selecione seu setor</option>
                    <?php foreach ($setores as $setor): ?>
                        <option value="<?php echo htmlspecialchars($setor); ?>"><?php echo htmlspecialchars($setor); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Registrar</button>
            <button type="button" onclick="history.back()" class="btn btn-secondary btn-block">Voltar</button>
        </form>
    </div>
    <script>
        function goBack() {
            window.history.back(); 
        }
    </script>    
</body>
</html>
