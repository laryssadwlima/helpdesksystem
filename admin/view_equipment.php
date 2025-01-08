<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Conectar ao banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Obter ID do equipamento a ser exibido
$equipmentUniqueId = isset($_GET['unique_id']) ? $_GET['unique_id'] : '';

if ($equipmentUniqueId) {
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE unique_id = ?'); // Alterado para buscar pelo unique_id
    $stmt->execute([$equipmentUniqueId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    die("Equipamento não encontrado.");
}

// Verifique se o equipamento foi encontrado
if (!$equipment) {
    die("Equipamento não encontrado.");
}

// Gerar o caminho do QR Code usando o ID único do equipamento
$qrCodePath = "../qr_codes/" . htmlspecialchars($equipment['unique_id']) . ".png";

if (!file_exists($qrCodePath)) {
    die("QR Code não encontrado para este equipamento.");
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
    <title>Visualizar Equipamento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <script>
        function printQRCode() {
            const qrCodeElement = document.getElementById('qrCodeContainer');
            const printWindow = window.open('', '', 'width=600,height=400');
            printWindow.document.write('<html><head><title>Imprimir QR Code</title></head><body>');
            printWindow.document.write(qrCodeElement.innerHTML);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
    </script>
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


    <div class="container">
        <h2>Detalhes do Equipamento</h2>
        
        <p><strong>Nome:</strong> <?php echo htmlspecialchars($equipment['name']); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars($equipment['type']); ?></p>
        <p><strong>Fabricante:</strong> <?php echo htmlspecialchars($equipment['manufacturer']); ?></p>
        <p><strong>Quantidade:</strong> <?php echo htmlspecialchars($equipment['quantity']); ?></p>
        <p><strong>Observações:</strong> <?php echo nl2br(htmlspecialchars($equipment['observations'])); ?></p>
        <p><strong>Características:</strong> <?php echo nl2br(htmlspecialchars($equipment['features'])); ?></p>

        <h3>QR Code do Equipamento</h3>
        <div id="qrCodeContainer">
            <img src="<?php echo $qrCodePath; ?>" alt="QR Code" style="max-width: 300px; max-height: 300px;">
        </div>
        <button class="btn btn-info mt-2" onclick="printQRCode()">
            <i class="fas fa-print"></i> Imprimir QR Code
        </button>

        <a href="inventory.php" class="btn btn-secondary mt-2">Voltar</a>
    </div>
</body>
</html>
