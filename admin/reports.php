<?php
session_start();

// Verifique se o usuário é um administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php'); // Redireciona se não for admin
    exit();
}

// Conectar ao banco de dados
require 'db.php';

// Consultas para obter os dados necessários para os gráficos
// Exemplo de consultas; você deve adaptar conforme a estrutura do seu banco de dados
$tickets_por_status = $pdo->query("SELECT status, COUNT(*) as total FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$tickets_por_mes = $pdo->query("SELECT MONTH(data_abertura) as mes, COUNT(*) as total FROM tickets GROUP BY mes")->fetchAll(PDO::FETCH_ASSOC);
$tickets_por_admin = $pdo->query("SELECT admin_id, COUNT(*) as total FROM tickets GROUP BY admin_id")->fetchAll(PDO::FETCH_ASSOC);
$tickets_por_setor = $pdo->query("SELECT setor, COUNT(*) as total FROM tickets GROUP BY setor")->fetchAll(PDO::FETCH_ASSOC);
$tipos_de_solicitacao = $pdo->query("SELECT tipo, COUNT(*) as total FROM tickets GROUP BY tipo ORDER BY total DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$tickets_por_prioridade = $pdo->query("SELECT prioridade, COUNT(*) as total FROM tickets GROUP BY prioridade")->fetchAll(PDO::FETCH_ASSOC);
$tickets_por_avaliacao = $pdo->query("SELECT avaliacao, COUNT(*) as total FROM tickets GROUP BY avaliacao")->fetchAll(PDO::FETCH_ASSOC);
$tickets_encerrados_por_mes = $pdo->query("SELECT MONTH(data_encerramento) as mes, COUNT(*) as total FROM tickets WHERE status = 'encerrado' GROUP BY mes")->fetchAll(PDO::FETCH_ASSOC);
$tickets_atrasados = $pdo->query("SELECT COUNT(*) as total FROM tickets WHERE status = 'atrasado'")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Tickets</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
</head>
<body>
    <div class="container mt-5">
        <h1>Relatório de Tickets</h1>
        <canvas id="ticketsPorStatus" width="400" height="200"></canvas>
        <canvas id="totalChamadosPorMes" width="400" height="200"></canvas>
        <canvas id="ticketsAtendidosPorAdmin" width="400" height="200"></canvas>
        <canvas id="totalChamadosPorSetor" width="400" height="200"></canvas>
        <canvas id="top10TiposSolicitacao" width="400" height="200"></canvas>
        <canvas id="ticketsPorPrioridade" width="400" height="200"></canvas>
        <canvas id="avaliacao" width="400" height="200"></canvas>
        <canvas id="encerradosPorMes" width="400" height="200"></canvas>
        <canvas id="ticketsAtrasados" width="400" height="200"></canvas>
    </div>

    <script>
        // Gráfico de Tickets por Status
        const ticketsPorStatusCtx = document.getElementById('ticketsPorStatus').getContext('2d');
        const ticketsPorStatusData = {
            labels: <?php echo json_encode(array_column($tickets_por_status, 'status')); ?>,
            datasets: [{
                label: 'Tickets por Status',
                data: <?php echo json_encode(array_column($tickets_por_status, 'total')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };
        new Chart(ticketsPorStatusCtx, { type: 'bar', data: ticketsPorStatusData });

        // Gráfico de Total de Chamados por Mês
        const totalChamadosPorMesCtx = document.getElementById('totalChamadosPorMes').getContext('2d');
        const totalChamadosPorMesData = {
            labels: <?php echo json_encode(array_column($tickets_por_mes, 'mes')); ?>,
            datasets: [{
                label: 'Total de Chamados por Mês',
                data: <?php echo json_encode(array_column($tickets_por_mes, 'total')); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorMesCtx, { type: 'line', data: totalChamadosPorMesData });

        // Gráfico de Tickets Atendidos por Admin
        const ticketsAtendidosPorAdminCtx = document.getElementById('ticketsAtendidosPorAdmin').getContext('2d');
        const ticketsAtendidosPorAdminData = {
            labels: <?php echo json_encode(array_column($tickets_por_admin, 'admin_id')); ?>,
            datasets: [{
                label: 'Tickets Atendidos por Admin',
                data: <?php echo json_encode(array_column($tickets_por_admin, 'total')); ?>,
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        };
        new Chart(ticketsAtendidosPorAdminCtx, { type: 'pie', data: ticketsAtendidosPorAdminData });

        // Gráfico de Total de Chamados por Setor
        const totalChamadosPorSetorCtx = document.getElementById('totalChamadosPorSetor').getContext('2d');
        const totalChamadosPorSetorData = {
            labels: <?php echo json_encode(array_column($tickets_por_setor, 'setor')); ?>,
            datasets: [{
                label: 'Total de Chamados por Setor',
                data: <?php echo json_encode(array_column($tickets_por_setor, 'total')); ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }]
        };
        new Chart(totalChamadosPorSetorCtx, { type: 'doughnut', data: totalChamadosPorSetorData });

        // Gráfico dos 10 Tipos de Solicitação
        const top10TiposSolicitacaoCtx = document.getElementById('top10TiposSolicitacao').getContext('2d');
        const top10TiposSolicitacaoData = {
            labels: <?php echo json_encode(array_column($tipos_de_solicitacao, 'tipo')); ?>,
            datasets: [{
                label: 'Top 10 Tipos de Solicitação',
                data: <?php echo json_encode(array_column($tipos_de_solicitacao, 'total')); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        };
        new Chart(top10TiposSolicitacaoCtx, { type: 'bar', data: top10TiposSolicitacaoData });

        // Gráfico de Tickets por Prioridade
        const ticketsPorPrioridadeCtx = document.getElementById('ticketsPorPrioridade').getContext('2d');
        const ticketsPorPrioridadeData = {
            labels: <?php echo json_encode(array_column($tickets_por_prioridade, 'prioridade')); ?>,
            datasets: [{
                label: 'Tickets por Prioridade',
                data: <?php echo json_encode(array_column($tickets_por_prioridade, 'total')); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };
        new Chart(ticketsPorPrioridadeCtx, { type: 'polarArea', data: ticketsPorPrioridadeData });

        // Gráfico de Avaliação
        const avaliacaoCtx = document.getElementById('avaliacao').getContext('2d');
        const avaliacaoData = {
            labels: <?php echo json_encode(array_column($tickets_por_avaliacao, 'avaliacao')); ?>,
            datasets: [{
                label: 'Avaliação dos Tickets',
                data: <?php echo json_encode(array_column($tickets_por_avaliacao, 'total')); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        };
        new Chart(avaliacaoCtx, { type: 'bar', data: avaliacaoData });

        // Gráfico de Encerrados por Mês
        const encerradosPorMesCtx = document.getElementById('encerradosPorMes').getContext('2d');
        const encerradosPorMesData = {
            labels: <?php echo json_encode(array_column($tickets_encerrados_por_mes, 'mes')); ?>,
            datasets: [{
                label: 'Tickets Encerrados por Mês',
                data: <?php echo json_encode(array_column($tickets_encerrados_por_mes, 'total')); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        };
        new Chart(encerradosPorMesCtx, { type: 'line', data: encerradosPorMesData });

        // Gráfico de Tickets Atrasados
        const ticketsAtrasadosCtx = document.getElementById('ticketsAtrasados').getContext('2d');
        const ticketsAtrasadosData = {
            labels: ['Atrasados'],
            datasets: [{
                label: 'Tickets Atrasados',
                data: [<?php echo $tickets_atrasados; ?>],
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        };
        new Chart(ticketsAtrasadosCtx, { type: 'bar', data: ticketsAtrasadosData });

        // Aqui você pode adicionar a lógica para abrir novos gráficos ao clicar
    </script>
</body>
</html>
