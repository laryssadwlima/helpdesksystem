<?php
session_start();
require 'db.php'; // Arquivo de conexão com o banco de dados
include 'config.php'; // Ajuste o caminho conforme necessário

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Você precisa estar logado para avaliar o atendimento.";
    header('Location: index.php');
    exit();
}

// Obtenha o ID do usuário logado
$user_id = $_SESSION['user_id'];

// Busque os dados do usuário no banco de dados
$stmt_users = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt_users->execute([$user_id]);
$users = $stmt_users->fetch(PDO::FETCH_ASSOC);

// Obtenha o ID do chamado a partir da URL
$chamado_id = $_GET['id'] ?? null;

// Verifique se o ID do chamado foi fornecido
if (!$chamado_id) {
    $_SESSION['error'] = "Atualize a página.";
    header('Location: index.php'); // Ajuste o redirecionamento conforme necessário
    exit();
}

// Verifique se o chamado pertence ao usuário logado
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = ? AND user_id = ?');
$stmt->execute([$chamado_id, $user_id]);
$chamado = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifique se o chamado foi encontrado e pertence ao usuário
if (!$chamado) {
    $_SESSION['error'] = "Chamado não encontrado ou você não tem permissão para avaliá-lo.";
    header('Location: index.php');
    exit();
}

// Inicializa variáveis
$nota = null;
$comentario = '';

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $nota = $_POST['nota'] ?? null;
    $comentario = trim($_POST['comentario'] ?? '');

    // Verificação de campos obrigatórios
    if (is_null($nota)) {
        $_SESSION['error'] = "Você deve selecionar uma nota.";
    } else {
        try {
            // Preparar a consulta INSERT
            $stmt_insert = $pdo->prepare('
                INSERT INTO avaliacoes (chamado_id, user_id, nota, comentario)
                VALUES (?, ?, ?, ?)
            ');

            // Execute a inserção
            $stmt_insert->execute([$chamado_id, $user_id, $nota, $comentario]);

            // Verifique se a inserção foi bem-sucedida
            if ($stmt_insert->rowCount() > 0) {
                $_SESSION['success'] = "Avaliação enviada com sucesso.";
                header('Location: visualizar_chamado.php'); // Ajuste o redirecionamento conforme necessário
                exit();
            } else {
                $_SESSION['error'] = "Falha ao enviar a avaliação.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erro ao processar a avaliação: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Avaliar Atendimento</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"> <!-- Adicione o CSS do Bootstrap -->
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
        <h1>Avalie o Atendimento</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form action="avaliacao.php?id=<?php echo htmlspecialchars($chamado_id); ?>" method="POST">
            <div class="form-group">
                <label for="nota">Nota:</label>
                <select name="nota" id="nota" class="form-control" required>
                    <option value="">Selecione uma nota</option>
                    <option value="1">1 Estrela</option>
                    <option value="2">2 Estrelas</option>
                    <option value="3">3 Estrelas</option>
                    <option value="4">4 Estrelas</option>
                    <option value="5">5 Estrelas</option>
                </select>
            </div>

            <div class="form-group">
                <label for="comentario">Comentário:</label>
                <textarea name="comentario" id="comentario" class="form-control" rows="4" placeholder="Deixe seu comentário aqui..."><?php echo htmlspecialchars($comentario); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
            <button type="button" onclick="history.back()" class="btn btn-secondary ">Voltar</button>
        </form>

    
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
