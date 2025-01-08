<?php
session_start();
require 'db.php';

// Verificar se os campos 'username' e 'password' estão definidos
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Proteção contra senha vazia ou nula e uso de md5
    if (!empty($password)) {
        $hashed_password = md5($password); // Supondo que você ainda queira usar md5

        // Verificar se o usuário existe na tabela 'users'
        $query = $pdo->prepare('SELECT * FROM users WHERE username = :username AND password = :password');
        $query->execute([
            'username' => $username,
            'password' => $hashed_password
        ]);

        $user = $query->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Armazenar informações do usuário na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role']; // O campo 'role' vem da tabela `users`
            
            // Redirecionar para a página de administração ou dashboard
            header('Location: dashboard.php');
            exit();
        } else {
            echo "Login inválido!";
        }
    } else {
        echo "A senha não pode estar vazia!";
    }
} else {
    echo "Por favor, preencha o formulário de login corretamente!";
}

// Carregar configurações existentes do banco de dados
$query_config = $pdo->prepare('SELECT * FROM configuracoes WHERE id = 1');
$query_config->execute();
$config = $query_config->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_habilitado = isset($_POST['email_habilitado']) ? 1 : 0;
    $utiliza_smtp = isset($_POST['utiliza_smtp']) ? 1 : 0;
    $endereco_smtp = trim($_POST['endereco_smtp']);
    $porta_smtp = intval($_POST['porta_smtp']);
    $usuario_smtp = trim($_POST['usuario_smtp']);
    $email_remetente = trim($_POST['email_remetente']);
    $nome_remetente = trim($_POST['nome_remetente']);
    
    // Atualizar configurações no banco de dados
    $stmt_update = $pdo->prepare("UPDATE configuracoes SET 
        email_habilitado = ?, utiliza_smtp = ?, endereco_smtp = ?, porta_smtp = ?, usuario_smtp = ?, 
        email_remetente = ?, nome_remetente = ? WHERE id = 1");
    $stmt_update->execute([$email_habilitado, $utiliza_smtp, $endereco_smtp, $porta_smtp, $usuario_smtp, $email_remetente, $nome_remetente]);

    $_SESSION['success'] = "Configurações de e-mail atualizadas com sucesso!";
    header('Location: configuracao_email.php');
    exit();
}
// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Configuração de E-mails</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
</head>
<body>
<div class="sidebar">
    <ul class="nav flex-column">
        <!-- Itens visíveis para todos -->
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Painel</a></li>
        <li class="nav-item"><a class="nav-link" href="editar_perfil.php"><i class="fas fa-user"></i> Editar Perfil</a></li>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Itens exclusivos para administradores -->
            <li class="nav-item"><a class="nav-link" href="chamados.php"><i class="fas fa-eye"></i> Visualizar Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="meuschamados.php"><i class="fas fa-headset"></i> Meus Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/users.php"><i class="fas fa-users"></i> Usuários</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/inventory.php"><i class="fas fa-box"></i> Inventário</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/relatorio.php"><i class="fas fa-chart-line"></i> Relatórios</a></li>
        <?php endif; ?>
    </ul>

    <!-- Botões visíveis para todos -->
    <a href="abrir_chamado.php" class="btn btn-primary btn-large abrir-chamado-btn">Abrir Chamado</a>
    <a href="sair.php" class="btn btn-primary btn-large sair-btn">Sair</a>
</div>

<div class="profile-section">
    <div class="toggle-sidebar">
        <img src="CSS/foto/f.png" alt="Logo" class="logo">
    </div>

    <div class="right-icons">
         <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="configuracoes.php"><i class="settings-icon fas fa-cog"></i></a>
         <?php endif; ?>
        <div class="theme-toggle">
            <input type="checkbox" class="checkbox" id="chk" />
            <label class="label" for="chk">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
                <div class="ball"></div>
            </label>
        </div>
        <script src="script.js"></script>
        <script src="https://kit.fontawesome.com/998c60ef77.js" crossorigin="anonymous"></script>
    </div>
    <div class="divider"></div>
    <div class="profile-photo">
        <?php if (!empty($users['photo'])): ?>
            <img src="<?php echo htmlspecialchars($users['photo']); ?>" alt="Foto de Perfil">
        <?php else: ?>
            <i class="fas fa-user"></i>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <a href="editar_perfil.php"><?php echo htmlspecialchars($users['username']); ?></a>
    </div>
</div>
    <div class="content">
        <h1>Configuração de E-mails</h1>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="email_habilitado">E-mail Habilitado:</label>
                <input type="checkbox" name="email_habilitado" <?= $config['email_habilitado'] ? 'checked' : '' ?>>
            </div>
            <div class="form-group">
                <label for="utiliza_smtp">Utiliza SMTP:</label>
                <input type="checkbox" name="utiliza_smtp" <?= $config['utiliza_smtp'] ? 'checked' : '' ?>>
            </div>
            <div class="form-group">
                <label for="endereco_smtp">Endereço SMTP:</label>
                <input type="text" name="endereco_smtp" value="<?= htmlspecialchars($config['endereco_smtp']) ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="porta_smtp">Porta SMTP:</label>
                <input type="number" name="porta_smtp" value="<?= htmlspecialchars($config['porta_smtp']) ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="usuario_smtp">Usuário SMTP:</label>
                <input type="text" name="usuario_smtp" value="<?= htmlspecialchars($config['usuario_smtp']) ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="email_remetente">E-mail Remetente:</label>
                <input type="email" name="email_remetente" value="<?= htmlspecialchars($config['email_remetente']) ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="nome_remetente">Nome Remetente:</label>
                <input type="text" name="nome_remetente" value="<?= htmlspecialchars($config['nome_remetente']) ?>" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            <a href="configuracoes.php" class="btn btn-secondary ">Voltar</a>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
