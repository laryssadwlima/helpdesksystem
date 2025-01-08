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

// Processar o cadastro de setores
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setor_nome'])) {
    $setor_nome = trim($_POST['setor_nome']);
    $setor_descricao = trim($_POST['setor_descricao']);

    try {
        $stmt_insert = $pdo->prepare("INSERT INTO setores (nome, descricao) VALUES (:nome, :descricao)");
        $stmt_insert->execute(['nome' => $setor_nome, 'descricao' => $setor_descricao]);
        $_SESSION['success'] = "Setor cadastrado com sucesso!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erro ao cadastrar setor: " . $e->getMessage();
    }
}

// Carregar setores existentes
$setores = [];
try {
    $stmt_select = $pdo->prepare("SELECT * FROM setores ORDER BY criado_em DESC");
    $stmt_select->execute();
    $setores = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erro ao carregar setores: " . $e->getMessage();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Setores</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <link rel="stylesheet" href="CSS/style.css">
</head>
<body>
<div class="sidebar">
   <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Painel</a></li>
        <li class="nav-item"><a class="nav-link" href="chamados.php"><i class="fas fa-eye"></i> Visualizar Chamados</a></li>
        <li class="nav-item"><a class="nav-link" href="admin/users.php"><i class="fas fa-users"></i> Usuários</a></li>
        <li class="nav-item"><a class="nav-link" href="admin/inventory.php"><i class="fas fa-box"></i> Inventário</a></li>
        <li class="nav-item"><a class="nav-link" href="admin/relatorio.php"><i class="fas fa-chart-line"></i> Relatórios</a></li>
    </ul>
    <a href="abrir_chamado.php" class="btn btn-primary btn-large abrir-chamado-btn">Abrir Chamado</a>
    <a href="sair.php" class="btn btn-primary btn-large sair-btn">Sair</a>
</div>

<div class="profile-section">
    <div class="toggle-sidebar">
        <img src="CSS/foto/f.png" alt="Logo" class="logo">
    </div>
    <div class="right-icons">
        <a href="configuracoes.php"><i class="settings-icon fas fa-cog"></i></a>

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
        <h1>Cadastro de Setores</h1>

        <!-- Mensagens de sucesso ou erro -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <div class="form-group">
                <label for="setor_nome">Nome do Setor:</label>
                <input type="text" name="setor_nome" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="setor_descricao">Descrição:</label>
                <textarea name="setor_descricao" class="form-control"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Cadastrar Setor</button>
            <a href="configuracoes.php" class="btn btn-secondary ">Voltar</a>
        </form>

        <h2 class="mt-5">Setores Cadastrados</h2>
        <table class="table table-bordered mt-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Criado Em</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($setores) > 0): ?>
                    <?php foreach ($setores as $setor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($setor['id']); ?></td>
                            <td><?php echo htmlspecialchars($setor['nome']); ?></td>
                            <td><?php echo htmlspecialchars($setor['descricao']); ?></td>
                            <td><?php echo htmlspecialchars($setor['criado_em']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">Nenhum setor cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
