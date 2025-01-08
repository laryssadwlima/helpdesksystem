<?php
// Conexão com o banco de dados
$dsn = "mysql:host=localhost;dbname=helpdesk"; // Atualize com seu banco
$username = "root"; // Seu usuário do banco de dados
$password = ""; // Sua senha do banco de dados

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para contar chamados por status
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM chamados GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Inicializar contagem dos status
    $statusData = [
        'aberto' => 0,
        'em_atendimento' => 0,
        'aguardando_resposta' => 0,
        'atrasado' => 0,
        'concluido' => 0
    ];

    foreach ($statusCounts as $row) {
        $statusData[$row['status']] = $row['count'];
    }

    // Consulta para os tipos de solicitação
    $stmt = $pdo->query("SELECT tipo_solicitacao, COUNT(*) AS count FROM chamados GROUP BY tipo_solicitacao");
    $solicitacaoCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consultas para os chamados por status
    $statusChamados = [];
    $statusArray = ['aberto', 'em_atendimento', 'atrasado', 'concluido'];

    foreach ($statusArray as $status) {
        $stmt = $pdo->prepare("
            SELECT chamados.*, users.nomeCompleto AS nome_admin 
            FROM chamados 
            LEFT JOIN users ON chamados.finalizado_por = users.id 
            WHERE chamados.status = :status
        ");
        $stmt->execute(['status' => $status]);
        $statusChamados[$status] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $searchTerm = isset($_GET['search']) ? $_GET['search'] : ''; 
    $statusFiltro = isset($_GET['status']) ? $_GET['status'] : ''; 
    $prioridadeFiltro = isset($_GET['prioridade']) ? $_GET['prioridade'] : ''; 
    
    // Inicializando a consulta SQL
$sql = "SELECT * FROM chamados WHERE 1=1"; // O '1=1' é uma prática comum para facilitar a adição de cláusulas

// Adicionando filtro de pesquisa de texto
if (!empty($searchTerm)) {
    $sql .= " AND (id LIKE :search OR titulo LIKE :search OR descricao LIKE :search)";
}

// Adicionando filtro por status
if (!empty($statusFiltro)) {
    $sql .= " AND status = :status";
}

// Adicionando filtro por prioridade
if (!empty($prioridadeFiltro)) {
    $sql .= " AND prioridade = :prioridade";
}

// Preparar a consulta
$stmt = $pdo->prepare($sql);

// Definindo os parâmetros
if (!empty($searchTerm)) {
    $stmt->bindValue(':search', '%' . $searchTerm . '%');
}
if (!empty($statusFiltro)) {
    $stmt->bindValue(':status', $statusFiltro);
}
if (!empty($prioridadeFiltro)) {
    $stmt->bindValue(':prioridade', $prioridadeFiltro);
}

    
    // Executando a consulta
    $stmt->execute();
    $chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consultar informações do usuário logado
    $usersId = $_SESSION['user_id'];
    $usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
    $usersStmt = $pdo->prepare($usersQuery);
    $usersStmt->execute(['user_id' => $usersId]);
    $users = $usersStmt->fetch(PDO::FETCH_ASSOC);

    // Definir o fuso horário e data atual
    date_default_timezone_set('America/Sao_Paulo');
    $data_abertura = date('Y-m-d H:i:s');

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}

// Função para traduzir status
function traduzirStatus($status) {
    switch ($status) {
        case 'aberto':
            return 'Aberto';
        case 'em_atendimento':
            return 'Em Atendimento';
        case 'concluido':
            return 'Concluído';
        case 'atrasado':
            return 'Atrasado';
        case 'aguardando_resposta':
            return 'Aguardando resposta do Usuário';
        default:
            return $status;
    }
}

// Tipos de solicitação
$tipos_solicitacao = [
    'acessos' => 'Acesso',
    'impressora' => 'Impressora',
    'instalacao' => 'Instalação de Programas',
    'protheus' => 'Protheus',
    'rede' => 'Rede',
    'duvida' => 'Dúvida',
    'solicitacao_equipamento' => 'Solicitação de Equipamento T.I',
    'email' => 'Email',
    'licencas' => 'Licenças',
    'erros' => 'Erros',
    'outros' => 'Outros',
    'instalacao' => 'Instalação',
    'novo_colaborador' => 'Novo Colaborador (DHO)'
];
?>



<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/midia.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
<style>
/* Ajuste de estilo para prioridade */
td.prioridade {
    padding: 8px;
    height: 100%;
    box-sizing: border-box;
}

td.prioridade > div {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    flex-grow: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Bolinhas para prioridade */
.prioridade-baixa::before,
.prioridade-media::before,
.prioridade-alta::before {
    content: "";
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.prioridade-baixa::before { background-color: #28a745; } /* Verde para baixa */
.prioridade-media::before { background-color: #ffc107; } /* Amarelo para média */
.prioridade-alta::before { background-color: #dc3545; } /* Vermelho para alta */

/* Ajustes para telas menores */
@media (max-width: 600px) {
    td.prioridade > div {
        flex-direction: column; /* Alinha os itens em coluna em telas menores */
        text-align: center;
    }
}

        .status-icon.open { background-color: #007BFF; }
        .status-icon.in-progress { background-color: #28A745; }
        .status-icon.closed { background-color: gray; }
        .status-icon.total { background-color: #3498DB; }
        .status-icon.atraso { background-color: #DC3545; }
        .status-icon.resposta { background-color: #FFC107; }

        .status-icon {
            background-color: #F2F2F5; /* Cor de fundo suave */
            border-radius: 15px;  /* Curva nas bordas */
            text-align: flex;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Efeito de sombra */
            margin-bottom: 20px;  /* Espaçamento entre os cards */
        }

        .status-icon .icon {
            width: 30px;    /* Tamanho dos ícones */
            height: 30px;
            margin-bottom: 10px; /* Espaçamento abaixo do ícone */
        }

        .status-icon h4 {
            font-size: 1.5rem; 
            color: #FFFFFF;       
            margin-bottom: 10px; 
        } 

        .status-icon h1 {
           font-size: 2.5rem; 
           color: #FFFFFF;    
           margin: 0;         /* Remove a margem padrão */
        }

        .chart-container {
            display: flex;
            justify-content: space-around;
            margin: px 0;
        }
        .filter-section {
            cursor: pointer;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
            position: relative;
        }
        .filter-section h5 {
            padding: 10px;
            margin: 0;
            color: #343a40;
        }
        .filter-content {
            display: none; /* Ocultar o conteúdo por padrão */
        }

        .filter-section.open .filter-content {
           display: block; /* Mostrar o conteúdo quando a seção estiver aberta */
        }
        .icon {
            margin-right: 10px;
        }
        .arrow {
            position: absolute;
            right: 10px;
            top: 15px;
            transition: transform 0.3s;
        }
        .rotated {
            transform: rotate(180deg);
        }
        /* Estilos para a bolinha de SLA */
        .sla-status::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .sla-success::before { background-color: #28a745; } /* Verde para atendido */
        .sla-danger::before { background-color: #dc3545; } /* Vermelho para expirado */
        .sla-info::before { background-color: #17a2b8; } /* Azul para em andamento */
        .sla-warning::before { background-color: #ffc107; } /* Amarelo para alerta */



            /* Estilos para cores de status */
    .status-aberto { color: blue; }
    .status-concluido { color: gray; } /* Altere o status "Fechado" para "Concluído" */
    .status-em_atendimento { color: orange; }
    .status-atrasado { color: red; }


    .table-striped .status-aberto {
       color: blue !important;
    }
    .table-striped .status-concluido {
       color: gray !important;
    }
    .table-striped .status-em_atendimento {
       color: orange !important;
    }
    .table-striped .atrasado {
       color: red !important;
    }

</style>
</head>
<body>
<div class="sidebar">
    <ul class="nav flex-column">
        <!-- Itens visíveis para todos -->
        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Painel</a></li>
        <li class="nav-item"><a class="nav-link" href="editar_perfil.php"><i class="fas fa-user"></i> Editar Perfil</a></li>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <!-- Itens exclusivos para administradores -->
            <li class="nav-item"><a class="nav-link" href="chamados.php"><i class="fas fa-eye"></i> Visualizar Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="meuschamados.php"><i class="fas fa-headset"></i> Meus Chamados</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/users.php"><i class="fas fa-users"></i> Usuários</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/inventory.php"><i class="fas fa-box"></i> Inventário</a></li>
            <li class="nav-item"><a class="nav-link" href="admin/relatorio.php"><i class="fas fa-chart-line"></i> Relatórios</a></li>
        <?php endif; ?>
    </ul>

    <!-- Botões visíveis para todos -->
    <a href="abrir_chamado.php" class="btn btn-primary btn-large abrir-chamado-btn">Abrir Chamado</a>
    <a href="sair.php" class="btn btn-primary btn-large sair-btn">Sair</a>
</div>

<div class="profile-section">
    <div class="toggle-sidebar">
        <img src="CSS/foto/f.png" alt="Logo" class="logo">
    </div>

    <div class="right-icons">
         <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="configuracoes.php"><i class="settings-icon fas fa-cog"></i></a>
         <?php endif; ?>
        <div class="theme-toggle">
            <input type="checkbox" class="checkbox" id="chk" />
            <label class="label" for="chk">
                <i class="fas fa-moon"></i>
                <i class="fas fa-sun"></i>
                <div class="ball"></div>
            </label>
        </div>
        <script src="script.js"></script>
        <script src="https://kit.fontawesome.com/998c60ef77.js" crossorigin="anonymous"></script>
    </div>
    <div class="divider"></div>
    <div class="profile-photo">
        <?php if (!empty($users['photo'])): ?>
            <img src="<?php echo htmlspecialchars($users['photo']); ?>" alt="Foto de Perfil">
        <?php else: ?>
            <i class="fas fa-user"></i>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <a href="editar_perfil.php"><?php echo htmlspecialchars($users['username']); ?></a>
    </div>
</div>
<div class="content">
    <div class="card">
        <div class="container mt-4">
        <div class="row">         <!-- Cards de Status -->
            <div class="col-xs-6 col-sm-3">
                <div class="status-icon open">
                    <h4>Aberto</h4>
                    <h5><?= $statusData['aberto'] ?></h5>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3">
                <div class="status-icon in-progress">
                    <h4>Em Atendimento</h4>
                    <h5><?= $statusData['em_atendimento'] ?></h5>
                </div>
            </div>

            <div class="col-xs-6 col-sm-3">
                <div class="status-icon atraso">
                    <h4>Em Atraso</h4>
                    <h5><?= $statusData['atrasado'] ?></h5>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3">
                <div class="status-icon closed">
                    <h4>Concluído</h4>
                    <h5><?= $statusData['concluido'] ?></h5>
                </div>
            </div>
        </div>
        <!-- Gráficos -->
    <div class="grafico">
        <div class="chart-container">
            <div>
                <canvas id="statusPieChart"></canvas>
            </div>
            <div>
                <canvas id="requestBarChart"></canvas>
            </div>
        </div>
    </div>

    <h3>Chamados</h3>
    <form method="GET" action="chamados.php">
    <div class="row align-items-end mb-3">
        <!-- Campo de pesquisa -->
        <div class="col-md-4 mb-2">
        <input type="text" class="form-control" name="search" id="searchInput" placeholder="Pesquisar por N°, título, descrição ou usuário" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>

        <!-- Filtro por prioridade -->
        <div class="col-md-3 mb-2">
            <select class="form-control" name="prioridade" id="prioritySelect">
                <option value="">Todas as Prioridades</option>
                <option value="baixa" <?php echo ($prioridadeFiltro == 'baixa') ? 'selected' : ''; ?>>Baixa</option>
                <option value="media" <?php echo ($prioridadeFiltro == 'media') ? 'selected' : ''; ?>>Média</option>
                <option value="alta" <?php echo ($prioridadeFiltro == 'alta') ? 'selected' : ''; ?>>Alta</option>
            </select>
        </div>

        <!-- Botões de ação -->
        <div class="col-md-3 mb-2">

            <a href="chamados.php" class="btn btn-secondary">
                <i class="fas fa-trash"></i> Limpar
            </a>
        </div>
    </div>
</form>



<div class="filter-section" onclick="toggleSection('filaAberta', this)">
    <h5><i class="fas fa-inbox icon" style= "color: #1582d5;"></i>Fila Aberta</h5>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div id="filaAberta" style="display: none;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Tipo de Solicitação</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statusChamados['aberto'] as $chamado): ?>
                <?php
                // Cálculo do SLA para cada chamado
                $slaStatus = 'N/A';
                $slaClass = '';

                $dataAberturaStr = $chamado['data_abertura'] ?? null;
                $sla_time = $chamado['tempo_sla'] ?? 24; // SLA padrão de 24 horas

                if ($dataAberturaStr) {
                    $dataCriacao = new DateTime($dataAberturaStr);
                    $dataLimite = clone $dataCriacao;
                    $dataLimite->modify("+$sla_time hours");

                    // Verificar se o chamado foi concluído
                    if ($chamado['status'] === 'concluido') {
                        $dataConclusaoStr = $chamado['data_conclusao'] ?? null;
                        if ($dataConclusaoStr) {
                            $dataConclusao = new DateTime($dataConclusaoStr);

                            if ($dataConclusao <= $dataLimite) {
                                $slaStatus = "SLA Atendido";
                                $slaClass = "success"; // Cor verde
                            } else {
                                $slaStatus = "SLA Expirado";
                                $slaClass = "danger"; // Cor vermelha
                            }
                        } else {
                            $slaStatus = "Erro: Data de conclusão não disponível.";
                            $slaClass = "warning"; // Cor amarela
                        }
                    } else {
                        $dataAtual = new DateTime();
                        if ($dataAtual > $dataLimite) {
                            $slaStatus = "SLA Expirado";
                            $slaClass = "danger";
                        } else {
                            $slaStatus = "Dentro do SLA";
                            $slaClass = "success";
                        }
                    }
                } else {
                    $slaStatus = "Erro: Data de abertura não disponível.";
                    $slaClass = "warning";
                }
                ?>
                <tr onclick="location.href='visualizar_chamado.php?id=<?= htmlspecialchars($chamado['id']) ?>'">
                    <td><?= htmlspecialchars($chamado['id']) ?></td>
                    <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                    <td><?= isset($tipos_solicitacao[$chamado['tipo_solicitacao']]) ? htmlspecialchars($tipos_solicitacao[$chamado['tipo_solicitacao']]) : 'Tipo Desconhecido'; ?></td>
                    <td><?= htmlspecialchars($chamado['nomeCompleto']) ?></td>
                    <td class="prioridade <?= 'prioridade-' . strtolower($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></td>
                    <td class="status <?= 'status-' . strtolower($chamado['status']) ?>">
                          <?= htmlspecialchars(traduzirStatus($chamado['status'])) ?>
                    </td>
                    <td class="sla-status sla-<?= $slaClass; ?>"><?= $slaStatus; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="filter-section" onclick="toggleSection('atrasado', this)">
    <h5><i class="fa-solid fa-circle-exclamation" style="color: #dd370e;"></i> Atrasados</h5>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div id="atrasado" style="display: none;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Tipo de Solicitação</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statusChamados['atrasado'] as $chamado): ?>
                <?php
                // Cálculo do SLA para cada chamado
                $slaStatus = 'N/A';
                $slaClass = '';

                $dataAberturaStr = $chamado['data_abertura'] ?? null;
                $sla_time = $chamado['tempo_sla'] ?? 24; // SLA padrão de 24 horas

                if ($dataAberturaStr) {
                    $dataCriacao = new DateTime($dataAberturaStr);
                    $dataLimite = clone $dataCriacao;
                    $dataLimite->modify("+$sla_time hours");

                    // Verificar se o chamado foi concluído
                    if ($chamado['status'] === 'concluido') {
                        $dataConclusaoStr = $chamado['data_conclusao'] ?? null;
                        if ($dataConclusaoStr) {
                            $dataConclusao = new DateTime($dataConclusaoStr);

                            if ($dataConclusao <= $dataLimite) {
                                $slaStatus = "SLA Atendido";
                                $slaClass = "success"; // Cor verde
                            } else {
                                $slaStatus = "SLA Expirado";
                                $slaClass = "danger"; // Cor vermelha
                            }
                        } else {
                            $slaStatus = "Erro: Data de conclusão não disponível.";
                            $slaClass = "warning"; // Cor amarela
                        }
                    } else {
                        $dataAtual = new DateTime();
                        if ($dataAtual > $dataLimite) {
                            $slaStatus = "SLA Expirado";
                            $slaClass = "danger";
                        } else {
                            $slaStatus = "Dentro do SLA";
                            $slaClass = "success";
                        }
                    }
                } else {
                    $slaStatus = "Erro: Data de abertura não disponível.";
                    $slaClass = "warning";
                }
                ?>
                <tr onclick="location.href='visualizar_chamado.php?id=<?= htmlspecialchars($chamado['id']) ?>'">
                    <td><?= htmlspecialchars($chamado['id']) ?></td>
                    <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                    <td><?= isset($tipos_solicitacao[$chamado['tipo_solicitacao']]) ? htmlspecialchars($tipos_solicitacao[$chamado['tipo_solicitacao']]) : 'Tipo Desconhecido'; ?></td>
                    <td><?= htmlspecialchars($chamado['nomeCompleto']) ?></td>
                    <td class="prioridade <?= 'prioridade-' . strtolower($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></td>
                    <td class="status <?= 'status-' . strtolower($chamado['status']) ?>">
                          <?= htmlspecialchars(traduzirStatus($chamado['status'])) ?>
                    </td>
                    <td class="sla-status sla-<?= $slaClass; ?>"><?= $slaStatus; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Seções com filtros e chamados -->
<div class="filter-section" onclick="toggleSection('emAtendimento', this)">
    <h5><i class="fas fa-tools icon" style= "color: #ec8d09;"></i>Em Atendimento</h5>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div id="emAtendimento" style="display: none;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Título</th>
                <th>Tipo de Solicitação</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
                <th>Atendido Por</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statusChamados['em_atendimento'] as $chamado): ?>
                <?php
                // Cálculo do SLA para cada chamado
                $slaStatus = 'N/A';
                $slaClass = '';

                $dataAberturaStr = $chamado['data_abertura'] ?? null;
                $sla_time = $chamado['tempo_sla'] ?? 24; // SLA padrão de 24 horas

                if ($dataAberturaStr) {
                    $dataCriacao = new DateTime($dataAberturaStr);
                    $dataLimite = clone $dataCriacao;
                    $dataLimite->modify("+$sla_time hours");

                    // Verificar se o chamado foi concluído
                    if ($chamado['status'] === 'concluido') {
                        $dataConclusaoStr = $chamado['data_conclusao'] ?? null;
                        if ($dataConclusaoStr) {
                            $dataConclusao = new DateTime($dataConclusaoStr);

                            if ($dataConclusao <= $dataLimite) {
                                $slaStatus = "SLA Atendido";
                                $slaClass = "success"; // Cor verde
                            } else {
                                $slaStatus = "SLA Expirado";
                                $slaClass = "danger"; // Cor vermelha
                            }
                        } else {
                            $slaStatus = "Erro: Data de conclusão não disponível.";
                            $slaClass = "warning"; // Cor amarela
                        }
                    } else {
                        $dataAtual = new DateTime();
                        if ($dataAtual > $dataLimite) {
                            $slaStatus = "SLA Expirado";
                            $slaClass = "danger";
                        } else {
                            $slaStatus = "Dentro do SLA";
                            $slaClass = "success";
                        }
                    }
                } else {
                    $slaStatus = "Erro: Data de abertura não disponível.";
                    $slaClass = "warning";
                }
                ?>
                <tr onclick="location.href='visualizar_chamado.php?id=<?= htmlspecialchars($chamado['id']) ?>'">
                    <td><?= htmlspecialchars($chamado['id']) ?></td>
                    <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                    <td><?= isset($tipos_solicitacao[$chamado['tipo_solicitacao']]) ? htmlspecialchars($tipos_solicitacao[$chamado['tipo_solicitacao']]) : 'Tipo Desconhecido'; ?></td>
                    <td><?= htmlspecialchars($chamado['nomeCompleto']) ?></td>
                    <td class="prioridade <?= 'prioridade-' . strtolower($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></td>
                    <td class="status <?= 'status-' . strtolower($chamado['status']) ?>">
                          <?= htmlspecialchars(traduzirStatus($chamado['status'])) ?>
                    </td>
                    <td class="sla-status sla-<?= $slaClass; ?>"><?= $slaStatus; ?></td>
                    <td><?= htmlspecialchars($chamado['nome_admin']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="filter-section" onclick="toggleSection('concluidos', this)">
    <h5><i class="fas fa-check-circle icon" style= "color: #0da074;"></i>Concluídos</h5>
    <i class="fas fa-chevron-down arrow"></i>
</div>
<div id="concluidos" style="display: none;">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Tipo de Solicitação</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA</th>
                <th>Finalizado Por</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($statusChamados['concluido'] as $chamado): ?>
                <?php
                // Cálculo do SLA para cada chamado
                $slaStatus = 'N/A';
                $slaClass = '';

                $dataAberturaStr = $chamado['data_abertura'] ?? null;
                $sla_time = $chamado['tempo_sla'] ?? 24; // SLA padrão de 24 horas

                if ($dataAberturaStr) {
                    $dataCriacao = new DateTime($dataAberturaStr);
                    $dataLimite = clone $dataCriacao;
                    $dataLimite->modify("+$sla_time hours");

                    // Verificar se o chamado foi concluído
                    if ($chamado['status'] === 'concluido') {
                        $dataConclusaoStr = $chamado['data_conclusao'] ?? null;
                        if ($dataConclusaoStr) {
                            $dataConclusao = new DateTime($dataConclusaoStr);

                            if ($dataConclusao <= $dataLimite) {
                                $slaStatus = "SLA Atendido";
                                $slaClass = "success"; // Cor verde
                            } else {
                                $slaStatus = "SLA Expirado";
                                $slaClass = "danger"; // Cor vermelha
                            }
                        } else {
                            $slaStatus = "Erro: Data de conclusão não disponível.";
                            $slaClass = "warning"; // Cor amarela
                        }
                    } else {
                        $dataAtual = new DateTime();
                        if ($dataAtual > $dataLimite) {
                            $slaStatus = "SLA Expirado";
                            $slaClass = "danger";
                        } else {
                            $slaStatus = "Dentro do SLA";
                            $slaClass = "success";
                        }
                    }
                } else {
                    $slaStatus = "Erro: Data de abertura não disponível.";
                    $slaClass = "warning";
                }
                ?>
                <tr onclick="location.href='visualizar_chamado.php?id=<?= htmlspecialchars($chamado['id']) ?>'">
                    <td><?= htmlspecialchars($chamado['id']) ?></td>
                    <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                    <td><?= isset($tipos_solicitacao[$chamado['tipo_solicitacao']]) ? htmlspecialchars($tipos_solicitacao[$chamado['tipo_solicitacao']]) : 'Tipo Desconhecido'; ?></td>
                    <td><?= htmlspecialchars($chamado['nomeCompleto']) ?></td>
                    <td class="prioridade <?= 'prioridade-' . strtolower($chamado['prioridade']) ?>"><?= htmlspecialchars($chamado['prioridade']) ?></td>
                    <td class="status <?= 'status-' . strtolower($chamado['status']) ?>">
                          <?= htmlspecialchars(traduzirStatus($chamado['status'])) ?>
                    </td>
                    <td class="sla-status sla-<?= $slaClass; ?>"><?= $slaStatus; ?></td>
                    <td><?= htmlspecialchars($chamado['nome_admin']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
        // Função para alternar a visibilidade das seções
        function toggleSection(sectionId, element) {
            var section = document.getElementById(sectionId);
            if (section.style.display === "none") {
                section.style.display = "block";
                element.querySelector('.arrow').classList.add('rotated');
            } else {
                section.style.display = "none";
                element.querySelector('.arrow').classList.remove('rotated');
            }
        }

        // Gráfico de Pizza para Status
        const statusPieChart = new Chart(document.getElementById('statusPieChart'), {
            type: 'pie',
            data: {
                labels: ['Aberto', 'Em Atendimento', 'Em Atraso', 'Concluído'],
                datasets: [{
                    label: 'Status dos Chamados',
                    data: [
                        <?= $statusData['aberto'] ?>,
                        <?= $statusData['em_atendimento'] ?>,
                     
                        <?= $statusData['atrasado'] ?>,
                        <?= $statusData['concluido'] ?>
                    ],
                    backgroundColor: ['#007bff', '#28a745', '#dc3545', '#6c757d'],
                }]
            },
        });

        // Gráfico de Barras para Tipos de Solicitação
        const requestBarChart = new Chart(document.getElementById('requestBarChart'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($solicitacaoCounts as $solicitacao): ?>
                        "<?= $solicitacao['tipo_solicitacao'] ?>",
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Tipos de Solicitação',
                    data: [
                        <?php foreach ($solicitacaoCounts as $solicitacao): ?>
                            <?= $solicitacao['count'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#007bff',
                }]
            },
        });
      // Função para alternar a visibilidade da seção
      function toggleSection(sectionId, element) {
        const section = document.getElementById(sectionId);
        const isOpen = section.style.display === 'block';

        // Alternar a visibilidade
        section.style.display = isOpen ? 'none' : 'block';

        // Armazenar o estado no Local Storage
        localStorage.setItem(sectionId + 'Open', !isOpen);
    }

    // Quando a página carregar, verifique o estado armazenado
    window.onload = function() {
        const sections = ['concluidos', 'filaAberta', 'atrasado', 'emAtendimento']; // IDs das seções
        sections.forEach(sectionId => {
            const isOpen = localStorage.getItem(sectionId + 'Open') === 'true';
            const section = document.getElementById(sectionId);
            section.style.display = isOpen ? 'block' : 'none'; // Exibir ou ocultar com base no estado armazenado
        });
    };
   // Função para filtrar a tabela
   function filterTable() {
    const searchInput = document.getElementById("searchInput").value.toLowerCase();
    const prioritySelect = document.getElementById("prioritySelect").value.toLowerCase();


    const sections = ["filaAberta", "concluidos", "atrasado", "ematendimento"]; // Adicione IDs das seções
    sections.forEach(sectionId => {
       const rows = document.querySelectorAll(`#${sectionId} table tbody tr`);
        rows.forEach(row => {
            const id = row.cells[0].innerText.toLowerCase();
            const titulo = row.cells[1].innerText.toLowerCase();
            const tipo = row.cells[2].innerText.toLowerCase();
            const solicitante = row.cells[3].innerText.toLowerCase();
            const prioridade = row.cells[4].innerText.toLowerCase();

            const matchesSearch = id.includes(searchInput) ||
                                  titulo.includes(searchInput) ||
                                  tipo.includes(searchInput) ||
                                  solicitante.includes(searchInput);
            const matchesPriority = prioritySelect === "" || prioridade === prioritySelect;

            if (matchesSearch && matchesPriority) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });
}


document.getElementById("searchInput").addEventListener("input", filterTable);
document.getElementById("prioritySelect").addEventListener("change", filterTable);


</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
