<?php
session_start();

// Verificar se o usuário está logado e se é um administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}


// Conectar ao banco de dados com tratamento de erros
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// Obter filtros de pesquisa
$search = isset($_GET['search']) ? $_GET['search'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$plantaFilter = isset($_GET['planta']) ? $_GET['planta'] : '';

// Preparar a consulta com os filtros
$query = 'SELECT * FROM inventory WHERE (name LIKE :search OR unique_id LIKE :search) AND status = "disponível"';
$params = ['search' => '%' . $search . '%'];

if ($typeFilter) {
    $query .= ' AND type = :typeFilter';
    $params['typeFilter'] = $typeFilter;
}

if ($plantaFilter) {
    $query .= ' AND planta = :plantaFilter';
    $params['plantaFilter'] = $plantaFilter;
}

$query .= ' ORDER BY type';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter tipos de equipamentos para o filtro
$typesStmt = $pdo->query('SELECT DISTINCT type FROM inventory');
$types = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
// Obter plantas para o filtro
$plantasStmt = $pdo->query('SELECT DISTINCT planta FROM inventory');
$plantas = $plantasStmt->fetchAll(PDO::FETCH_COLUMN);

// Obter totais disponíveis (contando o número de equipamentos disponíveis)
$totalAvailable = $pdo->query('SELECT COUNT(*) as total FROM inventory WHERE status = "disponível"')->fetchColumn();

// Preparar a consulta para o gráfico de pizza
$queryPie = 'SELECT type, COUNT(*) as total FROM inventory WHERE (name LIKE :search OR type LIKE :search) AND status = "disponível" GROUP BY type';
$paramsPie = ['search' => '%' . $search . '%'];

if ($typeFilter) {
    $queryPie .= ' HAVING type = :typeFilter';
    $paramsPie['typeFilter'] = $typeFilter;
}

$stmtPie = $pdo->prepare($queryPie);
$stmtPie->execute($paramsPie);
$inventoryCounts = $stmtPie->fetchAll(PDO::FETCH_ASSOC);

// Preparar a consulta para o gráfico de barras (tipo e planta)
$queryBar = 'SELECT type, planta, COUNT(*) as total FROM inventory WHERE (name LIKE :search OR type LIKE :search) AND status = "disponível" GROUP BY type, planta';
$paramsBar = ['search' => '%' . $search . '%'];

if ($typeFilter) {
    $queryBar .= ' AND type = :typeFilter';
    $paramsBar['typeFilter'] = $typeFilter;
}

$stmtBar = $pdo->prepare($queryBar);
$stmtBar->execute($paramsBar);
$typePlantaData = $stmtBar->fetchAll(PDO::FETCH_ASSOC);

// Processar os dados para o gráfico de barras
$typePlantaCounts = [];
foreach ($typePlantaData as $row) {
    $type = $row['type'];
    $planta = $row['planta'];
    $count = (int)$row['total'];
    if (!isset($typePlantaCounts[$type])) {
        $typePlantaCounts[$type] = ['P1' => 0, 'P2' => 0];
    }
    if (in_array($planta, ['P1', 'P2'])) {
        $typePlantaCounts[$type][$planta] += $count;
    }
}

// Preparar arrays para o gráfico de barras
$chartLabels = array_keys($typePlantaCounts);
$chartDataP1 = [];
$chartDataP2 = [];

foreach ($chartLabels as $type) {
    $chartDataP1[] = $typePlantaCounts[$type]['P1'] ?? 0;
    $chartDataP2[] = $typePlantaCounts[$type]['P2'] ?? 0;
}

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);

// Definir o fuso horário e data atual
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Inventário de Equipamentos</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../CSS/style.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Chart.js Plugin Datalabels -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <style>
        .grafico {
            margin-top: 30px;
        }
        .card {
            margin-top: 30px;
        }
        .profile-photo img {
            width: 50px;
            height: 50px;
        }
        /* Espaçamento entre os botões */
        .btn-spacing {
            margin-right: 10px;
        }
    </style>
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
<h1>Inventário</h1>
        <a href="add_equipment.php" class="btn btn-primary btn-spacing">Adicionar Novo Equipamento</a>
        <a href="used_equipment.php" class="btn btn-primary btn-spacing">Configurar Usados</a>

    <div class="card">
    <h2 class="text-center">Gráficos</h2> 
        <div class="grafico">
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex justify-content-center">
                        <canvas id="inventoryPieChart" style="max-width: 400px; max-height: 400px;"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                     <canvas id="typePlantaBarChart" style="max-width: 500px; max-height: 400px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="painel mt-4">
            <h3>Total de Equipamentos Disponíveis: <?php echo htmlspecialchars($totalAvailable ?? 0); ?></h3>
            <form method="get" class="form-inline mb-3">
            <input type="text" name="search" class="form-control mr-2" placeholder="Pesquisar por nome ou ID" value="<?php echo htmlspecialchars($search); ?>">
                <select name="type" class="form-control mr-2">
                    <option value="">Todos os Tipos</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($typeFilter === $type) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="planta" class="form-control mr-2">
                    <option value="">Todas as Plantas</option>
                   <?php foreach ($plantas as $planta): ?>
                       <option value="<?php echo htmlspecialchars($planta); ?>" <?php echo ($plantaFilter === $planta) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($planta); ?>
                       </option>
                  <?php endforeach; ?>
               </select>
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>     
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Observações</th>
                        <th>Planta</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['unique_id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['type'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['observations'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['planta'] ?? ''); ?></td>
                        <td>
                            <a href="edit_equipment.php?id=<?php echo htmlspecialchars($item['id'] ?? ''); ?>" class="btn btn-info">Editar</a>
                            <a href="delete_equipment.php?id=<?php echo htmlspecialchars($item['id'] ?? ''); ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este equipamento?');">Excluir</a>
                            <a href="equipamento.php?id=<?php echo htmlspecialchars($item['id'] ?? ''); ?>" class="btn btn-warning">Ver Histórico</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>    

<script>

// Gráfico de Equipamentos Usados por Tipo (Pie Chart)
const inventoryCounts = <?php echo json_encode($inventoryCounts); ?>;

const pieLabels = inventoryCounts.map(item => item.type);
const pieData = inventoryCounts.map(item => parseInt(item.total));

const pieCtx = document.getElementById('inventoryPieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: pieLabels,
        datasets: [{
            label: 'Quantidade por Tipo',
            data: pieData,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                '#FF9F40', '#C9CBCF', '#FF6384', '#36A2EB', '#FFCE56'
            ],
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
                        text: 'Distribuição por Tipo de Equipamento'
                    }
        }
    }
});

// Gráfico de Equipamentos Usados por Tipo e Planta (Bar Chart)
const chartLabels = <?php echo json_encode($chartLabels); ?>;
const chartDataP1 = <?php echo json_encode($chartDataP1); ?>;
const chartDataP2 = <?php echo json_encode($chartDataP2); ?>;

const typePlantaChartData = {
    labels: chartLabels,
    datasets: [
        {
            label: 'P1',
            data: chartDataP1,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        },
        {
            label: 'P2',
            data: chartDataP2,
            backgroundColor: 'rgba(255, 165, 0, 0.5)', // Cor laranja com 50% de opacidade
            borderColor: 'rgba(255, 165, 0, 1)', // Cor laranja sólida
            borderWidth: 1
        }
    ]
};

const barCtx = document.getElementById('typePlantaBarChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: typePlantaChartData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Quantidade de Equipamentos por Tipo e Planta'
            },
            // Configuração do plugin Datalabels
            datalabels: {
                color: '#000',
                formatter: (value, context) => {
                    return value;
                },
                anchor: 'end',
                align: 'start',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Quantidade'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Tipo de Equipamento'
                }
            }
        }
    },
    plugins: [ChartDataLabels]
});
</script>

</body>
</html>
