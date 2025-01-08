<?php
session_start();
require 'db.php';

// Verificar se os campos 'username' e 'password' estão definidos
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Proteção contra senha vazia ou nula e uso de md5
    if (!empty($password)) {
        $hashed_password = md5($password); // Supondo que você ainda queira usar md5 (embora seja recomendável usar bcrypt ou password_hash)

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <title>Painel de Configurações</title>
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
        <h1 class="mb-4">Configurações do Sistema</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Configuração de SLA</h5>
                        <a href="configuracao_sla.php" class="btn btn-primary">Editar SLA</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Configuração de E-mails</h5>
                        <a href="configuracao_email.php" class="btn btn-primary">Editar E-mails</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Configuração de Setores</h5>
                        <a href="setores.php" class="btn btn-primary">Editar Setores</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
