<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$is_admin = true; // Agora sabemos que o usuário é admin
// Conectar ao banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Obter usuários
$stmt = $pdo->query('SELECT id, username, email, setor, role, last_login FROM users');
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');

// Contar usuários por função
$rolesCount = [];
foreach ($allUsers as $user) {
    $role = $user['role'];
    if (!isset($rolesCount[$role])) {
        $rolesCount[$role] = 0;
    }
    $rolesCount[$role]++;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Usuários</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">    
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <a href="..\editar_perfil.php"><?php echo htmlspecialchars($users['username'] ?? ''); ?></a>
    </div>
</div>    

<div class="content">
  <div class="card">
    <div class="grafico">
        <h2>Gráfico de Funções</h2>
        <canvas id="roleChart"></canvas> 
    </div>
    
    <h2>Usuários</h2>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Setor</th>
                <th>Função</th>
                <th>Último Login</th> <!-- Nova coluna -->
                <th>Ações</th>
            </tr>
        </thead>
        <a href="../register.php" class="btn btn-primary btn-large">Adicionar Usuário</a>
        <tbody>
            <?php foreach ($allUsers as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['setor']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td><?php echo htmlspecialchars($user['last_login'] ? date('d/m/Y H:i:s', strtotime($user['last_login'])) : 'Nunca'); ?></td> <!-- Exibe a data do último login -->
                <td>
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-info">Editar</a>
                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger">Excluir</a>
                </td>
               
            </tr>
            
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
        const ctx = document.getElementById('roleChart').getContext('2d');
        const roleChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($rolesCount)); ?>,
                datasets: [{
                    label: 'Usuários por Tipo',
                    data: <?php echo json_encode(array_values($rolesCount)); ?>,
                    backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Distribuição de Usuários por Tipo'
                    }
                }
            }
        });
    </script>
</div>
</body>
</html>
