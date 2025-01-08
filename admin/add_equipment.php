<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
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

// Incluir o autoload do Composer
require '../vendor/autoload.php';

use Endroid\QrCode\QrCode; 
use Endroid\QrCode\Writer\PngWriter; 

// Obter tipos de equipamentos
$typesStmt = $pdo->query('SELECT * FROM item_types');
$itemTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);

// Obter fabricantes
$manufacturersStmt = $pdo->query('SELECT * FROM manufacturers');
$manufacturers = $manufacturersStmt->fetchAll(PDO::FETCH_ASSOC);

// Função para gerar um ID único
function generateUniqueId($pdo) {
    do {
        $id = rand(100000, 999999);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inventory WHERE unique_id = ?');
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();
    } while ($count > 0);
    return $id;
}

// Processar o formulário de adição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $manufacturer = trim($_POST['manufacturer']);
    $quantity = intval($_POST['quantity']);
    $observations = trim($_POST['observations']);
    $features = $_POST['features'];
    $planta= $_POST['planta'];

    // Gerar e inserir múltiplos registros
    for ($i = 0; $i < $quantity; $i++) {
        $uniqueId = generateUniqueId($pdo);
        $equipmentUrl = "http://192.168.35.2:8383/helpdesk/admin/equipamento.php?unique_id=$uniqueId"; // Mude o 'id' para 'unique_id'

        try {
            $stmt = $pdo->prepare('INSERT INTO inventory (name, type, manufacturer, planta, observations, features, unique_id, url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $type, $manufacturer, $planta, $observations, implode(',', $features), $uniqueId, $equipmentUrl]);

            // Gerar QR Code
            $qrCodeDir = '../qr_codes';
            $qrCode = new QrCode($equipmentUrl);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            file_put_contents("$qrCodeDir/$uniqueId.png", $result->getString());

        } catch (PDOException $e) {
            die("Erro ao inserir dados no banco: " . $e->getMessage());
        }
    }

    header('Location: inventory.php');
    exit();
}
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Equipamento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
       <link rel="stylesheet" href="../CSS/style.css">
       <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <script>
        function addFeature() {
            const featureContainer = document.getElementById('features');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'features[]';
            input.className = 'form-control mt-2';
            input.placeholder = 'Característica';
            featureContainer.appendChild(input);
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
        <h2>Adicionar Novo Equipamento</h2>
        <form method="post">
            <div class="form-group">
                <label for="name">Nome</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="type">Tipo</label>
                <div class="input-group">
                    <select class="form-control" id="type" name="type" required>
                        <?php foreach ($itemTypes as $itemType): ?>
                            <option value="<?php echo htmlspecialchars($itemType['type_name']); ?>"><?php echo htmlspecialchars($itemType['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="window.location.href='add_item_type.php'">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="manufacturer">Fabricante</label>
                <div class="input-group">
                    <select class="form-control" id="manufacturer" name="manufacturer" required>
                        <?php foreach ($manufacturers as $manufacturer): ?>
                            <option value="<?php echo htmlspecialchars($manufacturer['name']); ?>"><?php echo htmlspecialchars($manufacturer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button" onclick="window.location.href='add_manufacturer.php'">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="quantity">Quantidade</label>
                <input type="number" class="form-control" id="quantity" name="quantity" required>
            </div>
            <div class="form-group">
                <label for="observations">Observações</label>
                <textarea class="form-control" id="observations" name="observations"></textarea>
            </div>
            <div class="form-group">
               <label for="planta">Planta</label>
               <select class="form-control" id="planta" name="planta" required>
                   <option value="P1">P1</option>
                   <option value="P2">P2</option>
               </select>
            </div>

            <div class="form-group">
                <label>Características</label>
                <div id="features">
                    <input type="text" name="features[]" class="form-control mt-2" placeholder="Característica">
                </div>
                <button type="button" class="btn btn-outline-secondary mt-2" onclick="addFeature()">
                    <i class="fas fa-plus"></i> Adicionar Nova Característica
                </button>
            </div>
            <button type="submit" class="btn btn-primary">Adicionar</button>
            <a href="inventory.php" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</div>    
</body>
</html>
