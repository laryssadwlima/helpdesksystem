<?php
session_start();

// Verificar se o usuário está logado e se é um administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

// Conectar ao banco de dados
require '../db.php'; // Certifique-se de que o caminho está correto

// Consultas para obter os dados necessários para os gráficos
try {
    $chamados_por_status = $pdo->query("SELECT status, COUNT(*) as total FROM chamados GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $chamados_por_setor = $pdo->query("
        SELECT users.setor, COUNT(chamados.id) as total 
        FROM chamados 
        JOIN users ON chamados.user_id = users.id 
        GROUP BY users.setor
    ")->fetchAll(PDO::FETCH_ASSOC);
    $tipos_de_solicitacao = $pdo->query("SELECT tipo_solicitacao, COUNT(*) as total FROM chamados GROUP BY tipo_solicitacao ORDER BY total DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $chamados_por_prioridade = $pdo->query("SELECT prioridade, COUNT(*) as total FROM chamados GROUP BY prioridade")->fetchAll(PDO::FETCH_ASSOC);
    $chamados_atrasados = $pdo->query("SELECT COUNT(*) as total FROM chamados WHERE status = 'atrasado'")->fetchColumn();


    // Função para calcular total e porcentagens por mês
    function getChamadosPorMes($pdo) {
        $totalChamados = $pdo->query("SELECT COUNT(*) as total FROM chamados")->fetchColumn();
        $chamados_por_mes = $pdo->query("SELECT MONTH(data_abertura) as mes, COUNT(*) as total FROM chamados GROUP BY mes")->fetchAll(PDO::FETCH_ASSOC);
        
        $meses = array_fill(1, 12, 0);
        foreach ($chamados_por_mes as $chamado) {
            $meses[$chamado['mes']] = $chamado['total'];
        }

        $result = [];
        foreach ($meses as $mes => $total) {
            $porcentagem = $total > 0 ? ($total / $totalChamados) * 100 : 0;
            $result[] = ['mes' => $mes, 'total' => $total, 'porcentagem' => number_format($porcentagem, 2)];
        }
        return $result;
    }

    $chamados_por_mes = getChamadosPorMes($pdo);

// Total de Chamados Concluídos por Administrador (nome de usuário)
$chamados_por_usuario = $pdo->query("
    SELECT 
        u.username,
        COALESCE(COUNT(DISTINCT chamados.id), 0) as total 
    FROM users u 
    LEFT JOIN chamados ON chamados.finalizado_por = u.id 
    WHERE u.role = 'admin' 
    AND (chamados.status = 'concluido' OR chamados.status IS NULL)
    GROUP BY u.username, u.id
")->fetchAll(PDO::FETCH_ASSOC);

    // Top 10 Tipos de Solicitação
    $top_tipos_solicitacao = $pdo->query("
        SELECT tipo_solicitacao, COUNT(*) as total 
        FROM chamados 
        GROUP BY tipo_solicitacao 
        ORDER BY total DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Avaliações (contagem de notas de 1 a 5)
    $avaliacoes = $pdo->query("
        SELECT nota, COUNT(*) as total 
        FROM avaliacoes 
        GROUP BY nota
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Erro ao carregar os dados: " . $e->getMessage();
    exit();
}

// Query para obter os chamados
$query_chamados = "SELECT id, setor, status, prioridade, tipo_solicitacao FROM chamados";
$stmt_chamados = $pdo->prepare($query_chamados);
$stmt_chamados->execute();
$chamados = $stmt_chamados->fetchAll(PDO::FETCH_ASSOC); // Preenche a variável $chamados



// Exportar relatório para Excel
if (isset($_POST['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename=relatorio_chamados.xls');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Tabela para Setores
    echo "<table border='1'><tr><th>ID do Chamado</th><th>Setor</th><th>Status</th><th>Prioridade</th><th>Tipo de Solicitação</th></tr>";
    foreach ($chamados as $chamado) { // Supondo que $chamados contém todos os dados necessários
        if ($chamado['setor'] === 'T.I') {
            echo "<tr>
                    <td>{$chamado['id']}</td>
                    <td>{$chamado['setor']}</td>
                    <td>{$chamado['status']}</td>
                    <td>{$chamado['prioridade']}</td>
                    <td>{$chamado['tipo_solicitacao']}</td>
                  </tr>";
        }
    }
    echo "</table><br>";

    // Tabela para Status
    echo "<table border='1'><tr><th>ID do Chamado</th><th>Status</th><th>Setor</th><th>Prioridade</th><th>Tipo de Solicitação</th></tr>";
    foreach ($chamados as $chamado) {
        echo "<tr>
                <td>{$chamado['id']}</td>
                <td>{$chamado['status']}</td>
                <td>{$chamado['setor']}</td>
                <td>{$chamado['prioridade']}</td>
                <td>{$chamado['tipo_solicitacao']}</td>
              </tr>";
    }
    echo "</table><br>";

    // Tabela para Tipos de Solicitação
    echo "<table border='1'><tr><th>ID do Chamado</th><th>Tipo de Solicitação</th><th>Status</th><th>Prioridade</th><th>Setor</th></tr>";
    foreach ($chamados as $chamado) {
        echo "<tr>
                <td>{$chamado['id']}</td>
                <td>{$chamado['tipo_solicitacao']}</td>
                <td>{$chamado['status']}</td>
                <td>{$chamado['prioridade']}</td>
                <td>{$chamado['setor']}</td>
              </tr>";
    }
    echo "</table><br>";

    // Tabela para Prioridade
    echo "<table border='1'><tr><th>ID do Chamado</th><th>Prioridade</th><th>Status</th><th>Setor</th><th>Tipo de Solicitação</th></tr>";
    foreach ($chamados as $chamado) {
        echo "<tr>
                <td>{$chamado['id']}</td>
                <td>{$chamado['prioridade']}</td>
                <td>{$chamado['status']}</td>
                <td>{$chamado['setor']}</td>
                <td>{$chamado['tipo_solicitacao']}</td>
              </tr>";
    }
    echo "</table><br>";

    exit();
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Chamados</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
    <style>
        .chart-container {
            position: relative;
            margin: 20px auto;
            width: 80%;
            max-width: 600px;
        }
        canvas {
            width: 100% !important;
            height: 300px !important;
        }
        h3 {
            text-align: center;
            margin-top: 20px;
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
    <div class="card">
        <h1>Relatório de Chamados</h1>
        
        <div class="chart-container ">
            <h3>Total de Chamados por Setor</h3>
            <canvas id="totalChamadosPorSetor"></canvas>
        </div>

        <div class="chart-container">
            <h3>Total de Chamados por Status</h3>
            <canvas id="totalChamadosPorStatus"></canvas>
        </div>

        <div class="chart-container">
            <h3>Total de Chamados por Mês</h3>
            <canvas id="totalChamadosPorMes"></canvas>
        </div>

        <div class="chart-container">
            <h3>Total de Chamados por Prioridade</h3>
            <canvas id="totalChamadosPorPrioridade"></canvas>
        </div>

        <div class="chart-container">
           <h3>Total de Chamados Concluídos por Administrador</h3>
           <canvas id="chamadosConcluidosPorAdmin"></canvas>
        </div>
        <div class="chart-container">
           <h3>Top 10 Tipos de Solicitação</h3>
           <canvas id="topTiposSolicitacao"></canvas>
        </div>
        <div class="chart-container">
           <h3>Avaliações (Notas de 1 a 5)</h3>
           <canvas id="avaliacoesNotas"></canvas>
        </div>

        <form method="POST" action="">
            <button type="submit" name="export_excel" class="btn btn-success mt-3">Exportar para Excel</button>
        </form>
    </div>

    <script>
        // Gráfico de Total de Chamados por Setor
        const totalChamadosPorSetorCtx = document.getElementById('totalChamadosPorSetor').getContext('2d');
        const totalChamadosPorSetorData = {
            labels: <?php echo json_encode(array_column($chamados_por_setor, 'setor')); ?>,
            datasets: [{
                label: 'Total de Chamados por Setor',
                data: <?php echo json_encode(array_column($chamados_por_setor, 'total')); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)', // Cor 1
                    'rgba(54, 162, 235, 0.8)', // Cor 2
                    'rgba(255, 206, 86, 0.8)', // Cor 3
                    'rgba(75, 192, 192, 0.8)', // Cor 4
                    'rgba(153, 102, 255, 0.8)', // Cor 5
                    'rgba(255, 159, 64, 0.8)'  // Cor 6
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)', 
                    'rgba(54, 162, 235, 1)', 
                    'rgba(255, 206, 86, 1)', 
                    'rgba(75, 192, 192, 1)', 
                    'rgba(153, 102, 255, 1)', 
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorSetorCtx, { type: 'bar', data: totalChamadosPorSetorData });

        // Gráfico de Total de Chamados por Status
        const totalChamadosPorStatusCtx = document.getElementById('totalChamadosPorStatus').getContext('2d');
        const totalChamadosPorStatusData = {
            labels: <?php echo json_encode(array_column($chamados_por_status, 'status')); ?>,
            datasets: [{
                label: 'Total de Chamados por Status',
                data: <?php echo json_encode(array_column($chamados_por_status, 'total')); ?>,
                backgroundColor: [
                    'rgba(153, 102, 255, 0.8)', 
                    'rgba(54, 162, 235, 0.8)', 
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderColor: [
                    'rgba(153, 102, 255, 1)', 
                    'rgba(54, 162, 235, 1)', 
                    'rgba(255, 206, 86, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorStatusCtx, { type: 'bar', data: totalChamadosPorStatusData });

        // Gráfico de Total de Chamados por Mês
        const totalChamadosPorMesCtx = document.getElementById('totalChamadosPorMes').getContext('2d');
        const totalChamadosPorMesData = {
            labels: <?php echo json_encode(array_map(function($mes) { return $mes['mes']; }, $chamados_por_mes)); ?>,
            datasets: [{
                label: 'Total de Chamados por Mês',
                data: <?php echo json_encode(array_column($chamados_por_mes, 'total')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.8)', 
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorMesCtx, { type: 'line', data: totalChamadosPorMesData });

        // Gráfico de Total de Chamados por Prioridade
        const totalChamadosPorPrioridadeCtx = document.getElementById('totalChamadosPorPrioridade').getContext('2d');
        const totalChamadosPorPrioridadeData = {
            labels: <?php echo json_encode(array_column($chamados_por_prioridade, 'prioridade')); ?>,
            datasets: [{
                label: 'Total de Chamados por Prioridade',
                data: <?php echo json_encode(array_column($chamados_por_prioridade, 'total')); ?>,
                backgroundColor: [
                    'rgba(153, 102, 255, 0.8)', 
                    'rgba(255, 159, 64, 0.8)', 
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderColor: [
                    'rgba(153, 102, 255, 1)', 
                    'rgba(255, 159, 64, 1)', 
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorPrioridadeCtx, { type: 'bar', data: totalChamadosPorPrioridadeData });

        // Dados para o gráfico de Chamados Concluídos por Administrador
        const chamadosPorAdmin = <?php echo json_encode($chamados_por_usuario); ?>;
        const adminLabels = chamadosPorAdmin.map(item => item.username);
        const adminData = chamadosPorAdmin.map(item => item.total);

        // Gráfico de Chamados Concluídos por Administrador
        const ctx1 = document.getElementById('chamadosConcluidosPorAdmin').getContext('2d');
        new Chart(ctx1, {
            type: 'bar', // ou 'pie', 'line', etc.
            data: {
                labels: adminLabels,
                datasets: [{
                    label: 'Total de Chamados Concluídos',
                    data: adminData,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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

        // Dados para o gráfico de Tipos de Solicitação
        const tiposSolicitacao = <?php echo json_encode($top_tipos_solicitacao); ?>;
        const tiposLabels = tiposSolicitacao.map(item => item.tipo_solicitacao);
        const tiposData = tiposSolicitacao.map(item => item.total);

        // Gráfico de Tipos de Solicitação
        const ctx2 = document.getElementById('topTiposSolicitacao').getContext('2d');
        new Chart(ctx2, {
            type: 'bar', // ou 'bar', 'line', etc.
            data: {
                labels: tiposLabels,
                datasets: [{
                    label: 'Top 10 Tipos de Solicitação',
                    data: tiposData,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)',
                        'rgba(100, 255, 86, 0.6)',
                        'rgba(255, 99, 255, 0.6)',
                        'rgba(99, 255, 132, 0.6)',
                        'rgba(100, 100, 255, 0.6)'
                    ],
                    borderColor: 'rgba(255, 255, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return `${tooltipItem.label}: ${tooltipItem.raw} (${((tooltipItem.raw / tiposData.reduce((a, b) => a + b, 0)) * 100).toFixed(2)}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Dados para o gráfico de Avaliações (Notas de 1 a 5)
        const avaliacoes = <?php echo json_encode($avaliacoes); ?>;
        const notasLabels = avaliacoes.map(item => item.nota);
        const notasData = avaliacoes.map(item => item.total);

        // Gráfico de Avaliações
        const ctx3 = document.getElementById('avaliacoesNotas').getContext('2d');
        new Chart(ctx3, {
            type: 'bar', // ou 'pie', 'line', etc.
            data: {
                labels: notasLabels,
                datasets: [{
                    label: 'Total de Avaliações',
                    data: notasData,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
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

