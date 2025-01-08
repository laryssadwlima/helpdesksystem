<?php
require 'db.php'; // Inclua sua conexão com o banco de dados
include 'config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Definindo o timezone para São Paulo
date_default_timezone_set('America/Sao_Paulo');

// Obter informações do administrador logado
$stmt_admin = $pdo->prepare("SELECT * FROM users WHERE id = :admin_id");
$stmt_admin->execute(['admin_id' => $_SESSION['user_id']]);
$admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "Admin não encontrado.";
    exit();
}

// Armazene as informações do administrador na sessão, se necessário
$_SESSION['admin_nome'] = $admin['nomeCompleto']; // Nome completo do admin
$_SESSION['role'] = 'admin'; // Role de admi

// Definindo a função formatarStatus
function formatarStatus($status) {
    switch ($status) {
        case 'aberto':
            return 'Aberto';
        case 'em_atendimento':
            return 'Em Atendimento';
        case 'concluido':
            return 'Concluído';
        default:
            return 'Status Desconhecido';
    }
}

// Verifica se o usuário está logado e é um administrador
$is_admin = $_SESSION['role'] === 'admin';

// Verifica se um ID de ticket foi fornecido
$ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : (isset($_GET['id']) ? $_GET['id'] : null);

if ($ticket_id === null || !is_numeric($ticket_id)) {
    echo "Ticket não especificado ou ID inválido.";
    exit();
}

// Consulta o ticket pelo ID
$query = $pdo->prepare("
    SELECT c.*, u.nomeCompleto AS usuario_nome, a.nomeCompleto AS admin_nome
    FROM chamados c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN users a ON c.finalizado_por = a.id
    WHERE c.id = :ticket_id
");
$query->execute(['ticket_id' => $ticket_id]);
$ticket = $query->fetch(PDO::FETCH_ASSOC);

// Verifica se o ticket foi encontrado
if (!$ticket) {
    echo "Ticket não encontrado.";
    exit();
}

// Processar a mudança de status e prioridade se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'concluir') {
    // Captura os dados do formulário
    $novo_status = $_POST['status'];
    $nova_prioridade = $_POST['prioridade'];

    // Atualiza o ticket com o novo status e prioridade
    $data_conclusao = null;
    $sla_atendimento = null; // Variável para armazenar o SLA em horas

    // Verifica se o status é "concluído"
    if ($novo_status === 'concluido') {
        $data_conclusao = date('Y-m-d H:i:s'); // Captura a data e hora atual para conclusão

        // Calcula o SLA em horas
        $data_abertura = strtotime($ticket['data_abertura']); // Converte a data de abertura para timestamp
        $data_conclusao_timestamp = strtotime($data_conclusao); // Converte a data de conclusão para timestamp
        $sla_atendimento = ($data_conclusao_timestamp - $data_abertura) / 3600; // Diferença em horas
    }

    $updateQuery = $pdo->prepare("
        UPDATE chamados 
        SET status = :status, 
            prioridade = :prioridade, 
            data_conclusao = :data_conclusao, 
            finalizado_por = :finalizado_por,
            sla_atendimento = :sla_atendimento
        WHERE id = :ticket_id
    ");
    
    $updateQuery->execute([
        'status' => $novo_status,
        'prioridade' => $nova_prioridade,
        'data_conclusao' => $data_conclusao,
        'finalizado_por' => $_SESSION['user_id'],
        'sla_atendimento' => $sla_atendimento, // Armazena o SLA calculado
        'ticket_id' => $ticket_id
    ]);

    // Atualiza o ticket localmente
    $ticket['status'] = $novo_status;
    $ticket['prioridade'] = $nova_prioridade;
    $ticket['data_conclusao'] = $data_conclusao;
    $ticket['sla_atendimento'] = $sla_atendimento;

    // Enviar emails dependendo do novo status
    if ($novo_status === 'em_atendimento') {
        enviarEmailChamadoEmAtendimento($ticket_id, $ticket['usuario_nome'], $_SESSION['admin_nome'], $ticket['email_utilizado']);
    } elseif ($novo_status === 'concluido') {
        enviarEmailChamadoConcluido($ticket_id, $ticket['usuario_nome'], $ticket['email_utilizado']);
    }
}

// Obter o nome do administrador que concluiu, se aplicável
$finalizado_por_nome = '';
if ($ticket['finalizado_por']) {
    $stmt_admin = $pdo->prepare("SELECT nomeCompleto FROM users WHERE id = :admin_id");
    $stmt_admin->execute(['admin_id' => $ticket['finalizado_por']]);
    $admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    $finalizado_por_nome = $admin ? $admin['nomeCompleto'] : 'Admin Desconhecido';
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
    <title>Atender Ticket</title>
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
        <h1>Atender Ticket #<?php echo htmlspecialchars($ticket['id']); ?></h1>
        <p class="font-weight-bold">Status: <?php echo formatarStatus(htmlspecialchars($ticket['status'] ?? '')); ?><?php if ($ticket['status'] === 'concluido') echo " (Concluído por " . htmlspecialchars($finalizado_por_nome ?? '') . ")"; ?></p>
        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($ticket['descricao'] ?? ''); ?></p>


        <!-- Formulário para alterar status e prioridade -->
        <form method="POST" class="mt-3">
            <input type="hidden" name="action" value="concluir">
            <div class="form-group">
                <label for="status">Alterar Status</label>
                <select name="status" id="status" class="form-control" required <?php echo $ticket['status'] === 'concluido' ? 'disabled' : ''; ?>>
                    <option value="em_atendimento" <?php echo $ticket['status'] === 'em_atendimento' ? 'selected' : ''; ?>>Em Atendimento</option>
                    <option value="concluido" <?php echo $ticket['status'] === 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                </select>
            </div>
            <div class="form-group">
                <label for="prioridade">Prioridade</label>
                <select name="prioridade" id="prioridade" class="form-control" required>
                    <option value="baixa" <?php echo $ticket['prioridade'] === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                    <option value="media" <?php echo $ticket['prioridade'] === 'media' ? 'selected' : ''; ?>>Média</option>
                    <option value="alta" <?php echo $ticket['prioridade'] === 'alta' ? 'selected' : ''; ?>>Alta</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <a href="index.php" class="btn btn-secondary">Voltar</a>
        </form>

    </div>
</body>
</html>

