<?php
session_start();
require_once 'db.php'; // Inclua seu arquivo de conexão aqui

// Carregar os valores existentes da tabela
$sla_values = [];
try {
    $stmt_select = $pdo->prepare("SELECT tipo_solicitacao, tempo_sla FROM configuracoes_sla");
    $stmt_select->execute();
    $sla_values = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao carregar configurações de SLA: " . $e->getMessage() . "</div>";
}

// Processar a atualização das configurações de SLA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Criar um array com todos os tipos de SLA
    $tipos_sla = [
        'sla_duvida' => 'Duvida',
        'sla_erros' => 'Erros',
        'sla_outros' => 'Outros',
        'sla_acessos' => 'Acessos',
        'sla_rede' => 'Rede',
        'sla_licencas' => 'Licencas',
        'sla_instalacao' => 'instalacao',
        'sla_impressora' => 'Impressora',
        'sla_solicitacao_equipamento' => 'solicitacao_equipamento',
        'sla_novo_colaborador' => 'novo_colaborador'
    ];

    // Atualizar os valores na tabela
    try {
        foreach ($tipos_sla as $post_key => $tipo) {
            $tempo_sla = isset($_POST[$post_key]) ? floatval($_POST[$post_key]) : 0; // Atribuir 0 se não estiver setado
            
            // Prepare a instrução de atualização
            $stmt_update = $pdo->prepare("UPDATE configuracoes_sla SET tempo_sla = :tempo_sla WHERE tipo_solicitacao = :tipo");
            $stmt_update->execute(['tempo_sla' => $tempo_sla, 'tipo' => $tipo]);
        }

        $_SESSION['success'] = "Configurações de SLA atualizadas com sucesso!";
        header('Location: configuracao_sla.php'); // Redirecionar após a atualização
        exit();
    } catch (PDOException $e) {
        // Captura de erros com a consulta
        echo "<div class='alert alert-danger'>Erro ao atualizar configurações de SLA: " . $e->getMessage() . "</div>";
    }
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
    <title>Configurações SLA</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
        body {
            padding: 20px;
        }
    </style>
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
        <h1>Configurações de SLA</h1>
        <form method="POST" class="mt-4">
            <?php foreach ($sla_values as $value): ?>
                <div class="form-group">
                    <label for="<?php echo strtolower(str_replace(' ', '_', $value['tipo_solicitacao'])); ?>">
                        SLA - <?php echo htmlspecialchars($value['tipo_solicitacao']); ?>:
                    </label>
                    <input type="number" name="sla_<?php echo strtolower(str_replace(' ', '_', $value['tipo_solicitacao'])); ?>" 
                           class="form-control" value="<?php echo htmlspecialchars($value['tempo_sla']); ?>" required>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">Salvar Configurações</button>
            <a href="configuracoes.php" class="btn btn-secondary ">Voltar</a>
        </form>

        <!-- Exiba mensagens de sucesso ou erro se existirem -->
        <?php
        if (isset($_SESSION['success'])) {
            echo "<div class='alert alert-success mt-3'>{$_SESSION['success']}</div>";
            unset($_SESSION['success']);
        }
        ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
