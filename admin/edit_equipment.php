<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Obter detalhes do equipamento
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo "Equipamento não encontrado.";
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $quantity = $_POST['quantity'];
    $observations = $_POST['observations'];
    $planta = $_POST['planta'];
    $features = $_POST['features'];


    $stmt = $pdo->prepare('UPDATE inventory SET name = ?, type = ?, quantity = ?, observations = ?, planta = ?, features = ? WHERE id = ?');
    $stmt->execute([$name, $type, $quantity, $observations, $planta, $features, $id]);

    header('Location: inventory.php');
    exit();

}
// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Equipamento</title>
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
        <a href="..\editar_perfil.php"><?php echo htmlspecialchars($users['username'] ?? ''); ?></a>
    </div>
</div>    


<div class="content">
    <div class="card">
        <h2>Editar Equipamento</h2>
        <form method="post">
            <div class="form-group">
                <label for="name">Nome</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="type">Tipo</label>
                <input type="text" class="form-control" id="type" name="type" value="<?php echo htmlspecialchars($item['type']); ?>" required>
            </div>
            <div class="form-group">
               <label for="planta">Planta</label>
               <select class="form-control" id="planta" name="planta" required>
                   <option value="P1">P1</option>
                   <option value="P2">P2</option>
               </select>
            </div>
            <div class="form-group">
                <label for="observations">Observações</label>
                <textarea class="form-control" id="observations" name="observations"><?php echo htmlspecialchars($item['observations']); ?></textarea>
            </div>
            <div class="form-group">
               <label for="features">Características</label>
               <textarea class="form-control" id="features" name="features" required><?php echo htmlspecialchars($item['features']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            <button class="btn btn-secondary" onclick="goBack()">Voltar</button>
        </form>
    </div>
</div>
    <script>
        function goBack() {
            window.history.back(); // ou use window.history.go(-1);
        }
    </script>
</body>
</html>