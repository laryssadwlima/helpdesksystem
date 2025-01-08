<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Conexão com o banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Obtém os dados do usuário
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomeCompleto = $_POST['nomeCompleto'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $setor = $_POST['setor'];
    $senha = $_POST['senha'];

    // Verifica se foi feito upload de uma foto
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/'; // Diretório para armazenar as imagens
        $uploadFile = $uploadDir . basename($_FILES['photo']['name']);

        // Move o arquivo para o diretório especificado
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadFile)) {
            // Atualiza o caminho da foto no banco de dados
            $updateStmt = $pdo->prepare('UPDATE users SET nomeCompleto = ?, email = ?, telefone = ?, setor = ?, photo = ? WHERE id = ?');
            $updateStmt->execute([$nomeCompleto, $email, $telefone, $setor, $uploadFile, $user_id]);
        } else {
            $_SESSION['error'] = "Erro ao fazer upload da foto.";
        }
    } else {
        // Atualiza os dados do usuário sem alterar a foto
        $updateStmt = $pdo->prepare('UPDATE users SET nomeCompleto = ?, email = ?, telefone = ?, setor = ? WHERE id = ?');
        $updateStmt->execute([$nomeCompleto, $email, $telefone, $setor, $user_id]);
    }

    // Atualiza a senha se fornecida
    if (!empty($senha)) {
        $hashedPassword = password_hash($senha, PASSWORD_BCRYPT);
        $passwordStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $passwordStmt->execute([$hashedPassword, $user_id]);
    }

    $_SESSION['success'] = "Perfil atualizado com sucesso!";
    header('Location: editar_perfil.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Perfil</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
</div>
<div class="content">
        <h2>Editar Perfil</h2>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-5">
                <form action="editar_perfil.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="nomeCompleto">Nome Completo:</label>
                        <input type="text" class="form-control" name="nomeCompleto" id="nomeCompleto" value="<?php echo htmlspecialchars($user['nomeCompleto']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone:</label>
                        <input type="text" class="form-control" name="telefone" id="telefone" value="<?php echo htmlspecialchars($user['telefone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="setor">Setor:</label>
                        <input type="text" class="form-control" name="setor" id="setor" value="<?php echo htmlspecialchars($user['setor']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="photo">Mudar foto de perfil:</label>
                        <input type="file" class="form-control" name="photo" id="photo">
                    </div>
                    <div class="form-group">
                        <label for="senha">Nova Senha:</label>
                        <input type="password" class="form-control" name="senha" id="senha" placeholder="Deixe em branco para não alterar">
                    </div>
                    <button type="submit" class="btn btn-success">Salvar Alterações</button>
                    <button type="button" onclick="history.back()" class="btn btn-secondary">Voltar</button>
                </form>
            </div>
            <div class="col-md-6 foto">
                <h3>Foto de Perfil</h3>
                <?php if (!empty($user['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Foto de Perfil">
                <?php else: ?>
                    <i class="fas fa-user" style="font-size: 150px;"></i> <!-- Ícone de usuário caso não tenha foto -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>    
</body>
</html>
