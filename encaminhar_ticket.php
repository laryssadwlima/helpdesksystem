<?php
require 'db.php'; // Conexão com o banco de dados
include 'config.php';
session_start();

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Defina o ID do ticket a ser encaminhado
$ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : null;
if (!$ticket_id || !is_numeric($ticket_id)) {
    echo "<div class='alert alert-danger'>ID do ticket inválido.</div>";
    exit();
}

// Obtenha o ticket pelo ID
$stmt = $pdo->prepare("SELECT * FROM chamados WHERE id = :ticket_id");
$stmt->execute(['ticket_id' => $ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    echo "<div class='alert alert-danger'>Ticket não encontrado no banco de dados.</div>";
    exit();
}

// Obtenha a lista de administradores
$stmt_admins = $pdo->query("SELECT id, nomeCompleto, email FROM users WHERE role = 'admin'");
$admins = $stmt_admins->fetchAll(PDO::FETCH_ASSOC);

// Encaminhar o ticket para um admin selecionado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])) {
    $admin_id = $_POST['admin_id'];
    $admin_email = $_POST['admin_email'];
    $admin_nome = $_POST['admin_nome'];
    $usuario = $_SESSION['admin_nome']; // Usuário que está encaminhando o ticket
    $numero_chamado = $ticket['id'];

    // Atualizar o ticket para o status "em_atendimento" e atribuir ao admin
    $stmt_update = $pdo->prepare("
        UPDATE chamados 
        SET status = 'em_atendimento', finalizado_por = :admin_id 
        WHERE id = :ticket_id
    ");
    $stmt_update->execute(['admin_id' => $admin_id, 'ticket_id' => $ticket_id]);

    // Enviar email para o administrador destinatário
    enviarEmailEncaminharChamado($numero_chamado, $usuario, $admin_nome, $admin_email);

    // Enviar email para o usuário informando o encaminhamento
    $usuario_email = $ticket['email_utilizado']; // Campo de email do usuário no chamado
    enviarEmailEncaminharChamadoUsuario($numero_chamado, $usuario, $admin_nome, $usuario_email);

    // Exibir mensagem de sucesso e redirecionar para a página anterior
    echo "<div class='alert alert-success'>Chamado encaminhado com sucesso!</div>";
    header("refresh:3;url=chamados.php"); // Redireciona após 3 segundos
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encaminhar Ticket</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
        <h2>Encaminhar Ticket #<?php echo htmlspecialchars($ticket['id']); ?></h2>
        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($ticket['descricao'] ?? ''); ?></p>
        <form method="POST">
            <div class="form-group">
                <label for="admin_id">Selecione o Técnico que deseja Encaminhar:</label>
                <select name="admin_id" id="admin_id" class="form-control" required>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" data-email="<?php echo htmlspecialchars($admin['email']); ?>" data-nome="<?php echo htmlspecialchars($admin['nomeCompleto']); ?>">
                            <?php echo htmlspecialchars($admin['nomeCompleto']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="admin_email" id="admin_email" value="">
                <input type="hidden" name="admin_nome" id="admin_nome" value="">
            </div>
            <button type="submit" class="btn btn-primary">Encaminhar Chamado</button>
            <button type="button" onclick="history.back()" class="btn btn-secondary">Voltar</button>
        </form>
    </div>

    <script>
        // Atualizar campos hidden "admin_email" e "admin_nome" com os dados do admin selecionado
        document.getElementById('admin_id').addEventListener('change', function() {
            var email = this.options[this.selectedIndex].getAttribute('data-email');
            var nome = this.options[this.selectedIndex].getAttribute('data-nome');
            document.getElementById('admin_email').value = email;
            document.getElementById('admin_nome').value = nome;
        });

        // Acionar o evento de "change" para definir o e-mail e nome na primeira carga
        document.getElementById('admin_id').dispatchEvent(new Event('change'));
   
    
    </script> 
</body>
</html>
