<?php
session_start();

// Redirecionar para a página de login se o usuário não estiver autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Verificar se o usuário é admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Acesso negado. Somente administradores podem visualizar esta página.");
}

$is_admin = true; // Agora sabemos que o usuário é admin

// Conectar ao banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Verificar se o ID ou o unique_id foi passado na URL
$equipmentId = isset($_GET['id']) ? intval($_GET['id']) : null;
$equipmentUniqueId = isset($_GET['unique_id']) ? htmlspecialchars($_GET['unique_id']) : null;

// Inicializar a variável para armazenar o equipamento
$equipment = null;

// Buscar informações do equipamento usando o id ou unique_id
if ($equipmentId) {
    // Buscar pelo ID do equipamento
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE id = ?');
    $stmt->execute([$equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($equipmentUniqueId) {
    // Buscar pelo unique_id do equipamento
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE unique_id = ?');
    $stmt->execute([$equipmentUniqueId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    die("ID ou Unique ID do equipamento não fornecido.");
}

// Verificar se o equipamento foi encontrado
if (!$equipment) {
    die("Equipamento não encontrado.");
}

// Buscar histórico de uso incluindo informações do administrador
$historyStmt = $pdo->prepare('
    SELECT uh.*, u.username AS admin_name 
    FROM usage_history uh
    LEFT JOIN users u ON uh.admin_id = u.id 
    WHERE equipment_id = ? 
    ORDER BY date DESC
');
$historyStmt->execute([$equipment['id']]); // Usando o ID do equipamento encontrado
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);   

// Gerar o caminho do QR Code usando o unique_id do equipamento
$qrCodePath = "../qr_codes/" . htmlspecialchars($equipment['unique_id']) . ".png";

if (!file_exists($qrCodePath)) {
    die("QR Code não encontrado para este equipamento.");
}

date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Detalhes do Equipamento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <style>
        /* Adicione isso ao seu arquivo CSS ou dentro de uma tag <style> no seu HTML */
        .table {
            width: 100%; /* Tabela ocupa 100% da largura do contêiner */
            table-layout: auto; /* Permite que as colunas sejam ajustadas */
            max-width: 100%; /* Limita a largura máxima da tabela */
            overflow-x: auto; /* Permite rolagem horizontal se necessário */
        }

        .table th, .table td {
            white-space: nowrap; /* Impede que o texto quebre em múltiplas linhas */
            overflow: hidden; /* Oculta o texto que transborda */
            text-overflow: ellipsis; /* Adiciona '...' se o texto transbordar */
        }

        .table-container {
            overflow-x: auto; /* Permite rolagem horizontal na tabela */
        } 

    </style>
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


<div class="content">
    <div class="card">
        <h2>Detalhes do Equipamento</h2>
        <div class="card mb-4y">
            <h4 class="card-title"><?php echo htmlspecialchars($equipment['name']); ?></h4>
            <p class="card-text"><strong>Tipo:</strong> <?php echo htmlspecialchars($equipment['type']); ?></p>
            <p class="card-text"><strong>Fabricante:</strong> <?php echo htmlspecialchars($equipment['manufacturer']); ?></p>
            <p class="card-text"><strong>Observações:</strong> <?php echo htmlspecialchars($equipment['observations']); ?></p>
            <p class="card-text"><strong>Características:</strong> <?php echo htmlspecialchars($equipment['features']); ?></p>
            <p class="card-text"><strong>ID Único:</strong> <?php echo htmlspecialchars($equipment['unique_id']); ?></p>
        
        </div>
        <div class="card-body">
            <h3>QR Code do Equipamento</h3>
            <div id="qrCodeContainer">
                <img src="../CSS/foto/qrlogo.png" alt="Logo" style="max-width: 100px; max-height: 100px;"> 
                <img src="<?php echo $qrCodePath; ?>" alt="QR Code" style="max-width: 100px; max-height: 100px;">
            </div>
            <button class="btn btn-info mt-2" onclick="printQRCode()">
                <i class="fas fa-print"></i> Imprimir QR Code
            </button>
        </div>
    </div>
    <div class="card">
        <h3 class="mt-4">Histórico de Uso</h3>
        <div class="table-container">
            <?php if (count($history) > 0): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Destinatário</th>
                            <th>Descrição</th>
                            <th>Admin</th>
                            <th>Termo de Responsabilidade</th>
                            <th>Termo de Devolução</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['date']); ?></td>
                                <td><?php echo htmlspecialchars($entry['status']); ?></td>
                                <td><?php echo htmlspecialchars($entry['recipient']); ?></td>
                                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                <td><?php echo htmlspecialchars($entry['admin_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($entry['arquivo_termo'])): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($entry['arquivo_termo']); ?>" target="_blank">Visualizar Anexo</a>
                                    <?php else: ?>
                                        Sem anexo
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['return_file'])): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($entry['return_file']); ?>" target="_blank">Visualizar Anexo</a>
                                    <?php else: ?>
                                        Sem anexo
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Não há histórico de uso para este equipamento.</p>
            <?php endif; ?>
        </div>
        <button class="btn btn-secondary" onclick="goBack()">Voltar</button>
    </div>
</div>
    <script>
        function goBack() {
            window.history.back(); // ou use window.history.go(-1);
        }
    </script>
</body>
</html>
