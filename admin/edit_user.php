<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Conectar ao banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Verifica se o ID do usuário foi passado
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

// Obter informações do usuário
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_GET['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o usuário existe
if (!$user) {
    header('Location: users.php');
    exit();
}

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $setor = $_POST['setor'];
    $telefone = $_POST['telefone'];
    $password = $_POST['password'];

    // Verifica se a nova senha foi fornecida e faz a hash
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        // Atualiza usuário com nova senha
        $stmt = $pdo->prepare('UPDATE users SET username = :username, email = :email, role = :role, telefone = :telefone, password = :password, setor = :setor WHERE id = :id');
        $stmt->execute(['username' => $username, 'email' => $email, 'role' => $role, 'telefone' => $telefone, 'password' => $hashedPassword, 'setor' => $setor, 'id' => $_GET['id']]);
    } else {
        // Atualiza usuário sem alterar a senha
        $stmt = $pdo->prepare('UPDATE users SET username = :username, email = :email, role = :role, telefone = :telefone, setor = :setor WHERE id = :id');
        $stmt->execute(['username' => $username, 'email' => $email, 'role' => $role, 'telefone' => $telefone, 'setor' => $setor, 'id' => $_GET['id']]);
    }

    header('Location: users.php');
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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuário</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
</head>
<body>
<div class="sidebar">
    <ul class="nav flex-column">
        <!-- Itens visíveis para todos -->
        <li class="nav-item"><a class="nav-link" href="..\dashboard.php"><i class="fas fa-tachometer-alt"></i> Painel</a></li>
        <li class="nav-item"><a class="nav-link" href="..\editar_perfil.php"><i class="fas fa-user"></i> Editar Perfil</a></li>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Itens exclusivos para administradores -->
            <li class="nav-item"><a class="nav-link" href="..\chamados.php"><i class="fas fa-eye"></i> Visualizar Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="..\meuschamados.php"><i class="fas fa-headset"></i> Meus Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users"></i> Usuários</a></li>
            <li class="nav-item"><a class="nav-link" href="inventory.php"><i class="fas fa-box"></i> Inventário</a></li>
            <li class="nav-item"><a class="nav-link" href="relatorio.php"><i class="fas fa-chart-line"></i> Relatórios</a></li>
        <?php endif; ?>
    </ul>

    <!-- Botões visíveis para todos -->
    <a href="..\abrir_chamado.php" class="btn btn-primary btn-large abrir-chamado-btn">Abrir Chamado</a>
    <a href="..\sair.php" class="btn btn-primary btn-large sair-btn">Sair</a>
</div>

<div class="profile-section">
    <div class="toggle-sidebar">
        <img src="../CSS/foto/f.png" alt="Logo" class="logo">
    </div>

    <div class="right-icons">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="..\configuracoes.php"><i class="settings-icon fas fa-cog"></i></a>
        <?php endif; ?>
        <div class="theme-toggle">
            <input type="checkbox" class="checkbox" id="chk" />
            <label class="label" for="chk">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
                <div class="ball"></div>
            </label>
        </div>
        <script src="..\script.js"></script>
        <script src="https://kit.fontawesome.com/998c60ef77.js" crossorigin="anonymous"></script>
    </div>

    <div class="divider"></div>

    <div class="profile-photo">
        <?php if (!empty($users['photo']) && file_exists('../' . $users['photo'])): ?>
            <img src="../<?php echo htmlspecialchars($users['photo']); ?>" alt="Foto de Perfil" class="img-fluid rounded-circle">
        <?php else: ?>
            <i class="fas fa-user fa-2x"></i>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <a href="editar_perfil.php"><?php echo htmlspecialchars($users['username'] ?? ''); ?></a>
    </div>
</div>    

<div class="content">
    
    <h2>Editar Usuário</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label>Nome</label>
            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="form-group">
            <label>Função</label>
            <select class="form-control" name="role">
                <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Usuário</option>
                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="form-group">
            <label>Setor</label>
            <input type="text" class="form-control" name="setor" value="<?php echo htmlspecialchars($user['setor']); ?>" required>
        </div>
        <div class="form-group">
            <label>Telefone</label>
            <input type="text" class="form-control" name="telefone" value="<?php echo htmlspecialchars($user['telefone']); ?>" required>
        </div>
        <div class="form-group">
            <label>Nova Senha (deixe em branco para manter a atual)</label>
            <input type="password" class="form-control" name="password" placeholder="Digite uma nova senha">
        </div>
        <button type="submit" class="btn btn-primary">Atualizar</button>
        <button class="btn btn-secondary" onclick="goBack()">Voltar</button>
    </form>
    <script>
        function goBack() {
            window.history.back(); // ou use window.history.go(-1);
        }
    </script>  
</div>
</body>
</html>

