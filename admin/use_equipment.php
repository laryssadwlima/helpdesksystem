<?php 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

// Conectar ao banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Definir modo de erro do PDO

// Processar a pesquisa de equipamentos
$searchId = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $searchId = $_POST['unique_id'];
}

// Obter todos os equipamentos disponíveis no inventário
if ($searchId) {
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE (unique_id LIKE ? OR name LIKE ?) AND status = "disponível"');
    $stmt->execute(['%' . $searchId . '%', '%' . $searchId . '%']);
} else {
    $stmt = $pdo->prepare('SELECT * FROM inventory WHERE status = "disponível"');
    $stmt->execute();
}
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar o formulário de uso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['equipment_id'])) {
    $equipmentId = $_POST['equipment_id'];
    $recipient = $_POST['recipient'];
    $sector = $_POST['sector'];
    $planta = $_POST['planta']; // Capturar a planta
    $adminId = $_SESSION['user_id']; // Capturar o ID do admin logado

    // Variável para armazenar o nome do arquivo
    $uploadFileName = null;

    // Diretório para uploads
    $uploadDir = '../uploads/'; 

    // Verificar se há um arquivo anexado
    if (isset($_FILES['attachment'])) {
        // Verificar erro no upload
        if ($_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileName = basename($_FILES['attachment']['name']);
            $uploadFilePath = $uploadDir . $fileName; // Caminho completo no servidor

            $fileType = pathinfo($uploadFilePath, PATHINFO_EXTENSION);
            
            // Verificar o tipo de arquivo permitido
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileType, $allowedTypes)) {
                echo "Tipo de arquivo não permitido.";
                exit();
            }

            // Mover o arquivo para o diretório de uploads
            if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
                echo "Erro ao mover o arquivo para o diretório de uploads.";
                exit();
            }

            $uploadFileName = $fileName; // Armazenar apenas o nome do arquivo
        } else {
            // Tratar erros de upload
            switch ($_FILES['attachment']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    echo "O arquivo é muito grande.";
                    exit();
                case UPLOAD_ERR_PARTIAL:
                    echo "O arquivo foi enviado parcialmente.";
                    exit();
                case UPLOAD_ERR_NO_FILE:
                    // Isso é permitido, pois o arquivo é opcional
                    break;
                default:
                    echo "Erro desconhecido ao enviar o arquivo.";
                    exit();
            }
        }
    }

    try {
        // Iniciar transação
        $pdo->beginTransaction();

        // Inserir na tabela used_equipment
        $stmt = $pdo->prepare('INSERT INTO used_equipment (equipment_id, recipient, sector) VALUES (?, ?, ?)');
        $stmt->execute([$equipmentId, $recipient, $sector]);

        // Atualizar o status e a planta do equipamento na tabela inventory para "ocupado"
        $stmt = $pdo->prepare('UPDATE inventory SET status = "ocupado", planta = ? WHERE id = ?');
        $stmt->execute([$planta, $equipmentId]);

        // Registrar histórico de uso do equipamento (incluir planta e admin)
        $description = "Equipamento usado por $recipient no setor $sector. Planta: $planta.";
        $stmt = $pdo->prepare('INSERT INTO usage_history (equipment_id, status, recipient, description, user, date, planta, admin_id, arquivo_termo) 
                                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)');
        $stmt->execute([$equipmentId, 'ocupado', $recipient, $description, $_SESSION['user_id'], $planta, $adminId, $uploadFileName]); // Salvar o nome do arquivo na coluna arquivo_termo

        // Confirmar a transação
        $pdo->commit();

        // Redirecionar para a página de equipamentos usados
        header('Location: used_equipment.php');
        exit();

    } catch (Exception $e) {
        // Desfazer a transação em caso de erro
        $pdo->rollBack();
        echo "Falha ao registrar o uso: " . $e->getMessage();
    }
}

date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');

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
    <title>Usar Equipamento</title>
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
        <h2>Usar Equipamento</h2>
        <!-- Formulário de Pesquisa -->
        <form method="post" class="mb-3">
            <div class="form-group">
                <label for="unique_id">Buscar Equipamento por Unique ID ou Nome</label>
                <input type="text" class="form-control" id="unique_id" name="unique_id" value="<?php echo htmlspecialchars($searchId); ?>">
            </div>
            <button type="submit" name="search" class="btn btn-primary">Buscar</button>
        </form>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="equipment_id">Selecionar Equipamento</label>
                <select class="form-control" id="equipment_id" name="equipment_id" required>
                    <?php foreach ($inventory as $item): ?>
                        <option value="<?php echo htmlspecialchars($item['id']); ?>"><?php echo htmlspecialchars($item['name']); ?> (ID: <?php echo htmlspecialchars($item['unique_id']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="recipient">Destinatário</label>
                <input type="text" class="form-control" id="recipient" name="recipient" required>
            </div>
            <div class="form-group">
                <label for="sector">Setor do Destinatário</label>
                <input type="text" class="form-control" id="sector" name="sector" required>
            </div>
            <div class="form-group">
                <label for="planta">Planta</label>
                <select class="form-control" id="planta" name="planta" required>
                    <option value="P1">P1</option>
                    <option value="P2">P2</option>
                </select>
            </div>
            <div class="form-group">
                <label for="attachment">Anexo (opcional)</label>
                <input type="file" class="form-control-file" id="attachment" name="attachment">
            </div>
            <button type="submit" class="btn btn-success">Registrar Uso</button>
            <button type="button" onclick="history.back()" class="btn btn-secondary">Voltar</button>
        </form>
    </div>
</div>

</body>
</html>
