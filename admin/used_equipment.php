<?php
session_start();

// Função para verificar se o usuário está logado e é um administrador
function checkAdminSession() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        header('Location: ../index.php');
        exit();
    }
}
checkAdminSession();

// Conectar ao banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage()));
}

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);

// Obter filtros de pesquisa
$search = isset($_GET['search']) ? $_GET['search'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$sectorFilter = isset($_GET['sector']) ? $_GET['sector'] : '';
$plantaFilter = isset($_GET['planta']) ? $_GET['planta'] : '';  // Adicionando o filtro de planta

// Preparar a consulta com os filtros
$query = 'SELECT ue.equipment_id, i.unique_id, i.planta, i.name, i.type, ue.recipient, ue.date_used, ue.sector 
          FROM used_equipment ue 
          JOIN inventory i ON ue.equipment_id = i.id 
          WHERE (i.name LIKE :search OR i.type LIKE :search OR i.unique_id LIKE :search OR ue.recipient LIKE :search)
          AND i.status = "ocupado"';

$params = ['search' => '%' . $search . '%'];

if ($typeFilter) {
    $query .= ' AND i.type = :typeFilter';
    $params['typeFilter'] = $typeFilter;
}

if ($sectorFilter) {
    $query .= ' AND ue.sector = :sectorFilter';
    $params['sectorFilter'] = $sectorFilter;
}

if ($plantaFilter) {  // Adicionando o filtro de planta na query
    $query .= ' AND i.planta = :plantaFilter';
    $params['plantaFilter'] = $plantaFilter;
}

$query .= ' ORDER BY i.type';
$stmt = $pdo->prepare($query);
$stmt->execute($params);

$usedEquipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Consultar dados para o gráfico por tipo de equipamento e planta
$chartQuery = "
    SELECT i.type, i.planta, COUNT(ue.id) AS count
    FROM used_equipment ue
    JOIN inventory i ON ue.equipment_id = i.id
    GROUP BY i.type, i.planta
";
$chartStmt = $pdo->query($chartQuery);
$chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar os dados para os gráficos por planta (P1 e P2)
$equipmentTypes = [];
$dataP1 = [];
$dataP2 = [];

foreach ($chartData as $row) {
    $equipmentType = $row['type'];
    if (!in_array($equipmentType, $equipmentTypes)) {
        $equipmentTypes[] = $equipmentType;
    }

    // Dividir os dados entre as plantas P1 e P2
    if ($row['planta'] == 'P1') {
        $dataP1[] = $row['count'];
    } elseif ($row['planta'] == 'P2') {
        $dataP2[] = $row['count'];
    }
}
// Obter tipos de equipamentos para o filtro
$typesStmt = $pdo->query('SELECT DISTINCT type FROM inventory');
$types = $typesStmt->fetchAll(PDO::FETCH_COLUMN);

// Obter setores para o filtro
$sectorsStmt = $pdo->query('SELECT DISTINCT sector FROM used_equipment');
$sectors = $sectorsStmt->fetchAll(PDO::FETCH_COLUMN);
// Obter totais disponíveis (contando o número de equipamentos disponíveis)
$totalAvailable = $pdo->query('SELECT COUNT(*) as total FROM inventory WHERE status = "ocupado"')->fetchColumn();

// Obter plantas para o filtro
$plantaStmt = $pdo->query('SELECT DISTINCT planta FROM inventory');
$planta = $plantaStmt->fetchAll(PDO::FETCH_COLUMN);

// Definir o fuso horário e data atual
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');
// Obter dados para o gráfico de setores
$sectorQuery = "
    SELECT sector, COUNT(id) AS count
    FROM used_equipment
    GROUP BY sector
";
$sectorStmt = $pdo->query($sectorQuery);
$sectorData = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar dados para o gráfico de setores
$sectorLabels = [];
$sectorCounts = [];
foreach ($sectorData as $row) {
    $sectorLabels[] = htmlspecialchars($row['sector']); // Escapando para evitar XSS
    $sectorCounts[] = (int)$row['count']; // Garantindo que sejam inteiros
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Equipamentos Usados</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <style>
        .grafico {
            margin-top: 30px;
        }
        /* Ajustar o tamanho do gráfico */
        #equipmentByTypeChart {
            width: 400px;
            height: 200px;
        }
        #sectorPieChart {
            width: 000px;
            height: 200px;
        }
    </style>
</head>
<body>
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
        <a href="use_equipment.php" class="btn btn-primary mb-3">Atualizar Equipamento para usado</a>

    <h2 class="text-center">Gráficos</h2> 
        <div class="grafico">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex justify-content-center">
                        <canvas id="sectorPieChart" style="max-width: 400px; max-height: 400px;"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                     <canvas id="equipmentByTypeChart" style="max-width: 500px; max-height: 400px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <h3>Total de Equipamentos Usados: <?php echo htmlspecialchars($totalAvailable ?? 0); ?></h3>
        <form method="GET" action="">
            <div class="form-inline mb-3">
                <input type="text" name="search" class="form-control mr-2" placeholder="Buscar por nome, tipo ou ID" value="<?php echo htmlspecialchars($search); ?>">
                <select name="type" class="form-control mr-2">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type == $typeFilter ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sector" class="form-control mr-2">
                    <option value="">Todos os setores</option>
                    <?php foreach ($sectors as $sector): ?>
                        <option value="<?php echo htmlspecialchars($sector); ?>" <?php echo $sector == $sectorFilter ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sector); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="planta" class="form-control mr-2">
                    <option value="">Todas as plantas</option>  <!-- Corrigido para "planta" -->
                    <?php foreach ($planta as $plantaItem): ?>
                        <option value="<?php echo htmlspecialchars($plantaItem); ?>" <?php echo $plantaItem == $plantaFilter ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($plantaItem); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </form>
    </div>     
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Destinatário</th>
                    <th>Tipo</th>
                    <th>Planta</th>
                    <th>Setor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usedEquipment as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['unique_id'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['recipient'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($item['planta'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['sector'] ?? 'N/A'); ?></td>
                    <td>
                        <!-- Botões de ação usando equipment_id -->
                        <a href="edit_equipment.php?id=<?php echo htmlspecialchars($item['equipment_id']); ?>" class="btn btn-info btn-sm">Editar</a>
                        <a href="delete_equipment.php?id=<?php echo htmlspecialchars($item['equipment_id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Tem certeza que deseja excluir este equipamento?');">Excluir</a>
                        <a href="equipamento.php?id=<?php echo htmlspecialchars($item['equipment_id']); ?>" class="btn btn-warning  btn-sm">Histórico</a>
                        <a href="return_to_inventory.php?id=<?php echo htmlspecialchars($item['equipment_id']); ?>" class="btn btn-success btn-sm">Devolver ao inventário</a>

                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>   
<script>
   const ctx1 = document.getElementById('sectorPieChart').getContext('2d');
    const sectorLabels = <?php echo json_encode($sectorLabels); ?>;
    const sectorCounts = <?php echo json_encode($sectorCounts); ?>;

    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: sectorLabels,
            datasets: [{
                label: 'Setores',
                data: sectorCounts,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        }
    });

    const ctx2 = document.getElementById('equipmentByTypeChart').getContext('2d');
    const equipmentTypes = <?php echo json_encode($equipmentTypes); ?>;
    const dataP1 = <?php echo json_encode($dataP1); ?>;
    const dataP2 = <?php echo json_encode($dataP2); ?>;

    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: equipmentTypes,
            datasets: [{
                label: 'Planta P1',
                data: dataP1,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }, {
                label: 'Planta P2',
                data: dataP2,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>
</body>
</html>
