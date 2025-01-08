<?php
// Conectar ao banco de dados
include('db.php'); 

// Verificar se o usuário está logado
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
// Verificar se o usuário é admin
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Mensagem chamado aberto com sucesso 
if (isset($_SESSION['mensagem'])) {
    echo "<p>{$_SESSION['mensagem']}</p>";
    unset($_SESSION['mensagem']); // Limpa a mensagem após exibi-la
}

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);

// Inicializar as variáveis de contagem com valor 0
$abertosCount = 0;
$emAndamentoCount = 0;
$fechadosCount = 0; // Vamos ajustar o nome para "concluídos"
$totalCount = 0;

// Consultar contagens de chamados com base no papel do usuário
if ($is_admin) {
    // Consultas para admin
    $abertosQuery = "SELECT COUNT(*) as count FROM chamados WHERE status = 'Aberto'";
    $emAndamentoQuery = "SELECT COUNT(*) as count FROM chamados WHERE status = 'Em_Atendimento'";
    $concluidosQuery = "SELECT COUNT(*) as count FROM chamados WHERE status = 'Concluído'";
    $totalQuery = "SELECT COUNT(*) as count FROM chamados";
} else {
    // Consultas para usuário comum
    $abertosQuery = "SELECT COUNT(*) as count FROM chamados WHERE user_id = :user_id AND status = 'Aberto'";
    $emAndamentoQuery = "SELECT COUNT(*) as count FROM chamados WHERE user_id = :user_id AND status = 'Em_Atendimento'";
    $concluidosQuery = "SELECT COUNT(*) as count FROM chamados WHERE user_id = :user_id AND status = 'Concluído'";
    $totalQuery = "SELECT COUNT(*) as count FROM chamados WHERE user_id = :user_id";
}

// Executar as consultas e atualizar as variáveis
$abertosResult = $pdo->prepare($abertosQuery);
$emAndamentoResult = $pdo->prepare($emAndamentoQuery);
$concluidosResult = $pdo->prepare($concluidosQuery);
$totalResult = $pdo->prepare($totalQuery);

if (!$is_admin) {
    // Bind do user_id para usuários comuns
    $abertosResult->bindValue(':user_id', $usersId);
    $emAndamentoResult->bindValue(':user_id', $usersId);
    $concluidosResult->bindValue(':user_id', $usersId);
    $totalResult->bindValue(':user_id', $usersId);
}

// Executar as consultas
$abertosResult->execute();
$emAndamentoResult->execute();
$concluidosResult->execute();
$totalResult->execute();

// Atualizar contagens
$abertosCount = $abertosResult->fetch(PDO::FETCH_ASSOC)['count'];
$emAndamentoCount = $emAndamentoResult->fetch(PDO::FETCH_ASSOC)['count'];
$fechadosCount = $concluidosResult->fetch(PDO::FETCH_ASSOC)['count'];
$totalCount = $totalResult->fetch(PDO::FETCH_ASSOC)['count'];

// Inicializar variáveis de filtro
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFiltro = isset($_GET['status']) ? $_GET['status'] : '';
$prioridadeFiltro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';

// Consultar tickets de acordo com o papel do usuário (admin ou user)
$query = "SELECT * FROM chamados WHERE 1=1";

// Se o usuário não for admin, ele só pode ver seus próprios chamados
if (!$is_admin) {
    $query .= " AND user_id = :user_id";
}

// Adicionar filtro de status, se estiver definido
if (!empty($statusFiltro)) {
    $query .= " AND status = :status";
}

// Adicionar filtro de prioridade, se estiver definido
if (!empty($prioridadeFiltro)) {
    $query .= " AND prioridade = :prioridade";
}

// Adicionar pesquisa por ID, título, descrição ou tipo de solicitação, se o campo de pesquisa estiver preenchido
if (!empty($searchTerm)) {
    $query .= " AND (id LIKE :searchTerm OR titulo LIKE :searchTerm OR descricao LIKE :searchTerm OR user_id LIKE :searchTerm)";
}

$stmt = $pdo->prepare($query);

// Bind dos parâmetros
if (!$is_admin) {
    $stmt->bindValue(':user_id', $usersId);
}
if (!empty($statusFiltro)) {
    $stmt->bindValue(':status', $statusFiltro);
}
if (!empty($prioridadeFiltro)) {
    $stmt->bindValue(':prioridade', $prioridadeFiltro);
}
if (!empty($searchTerm)) {
    $stmt->bindValue(':searchTerm', '%' . $searchTerm . '%');
}

$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            return $status; // Retorna o valor original se não houver tradução
    }
}
// Inicializar variáveis de filtro
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$statusFiltro = isset($_GET['status']) ? $_GET['status'] : '';
$prioridadeFiltro = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';

// Consultar tickets de acordo com o papel do usuário (admin ou user)
$query = "SELECT * FROM chamados WHERE 1=1";

// Se o usuário não for admin, ele só pode ver seus próprios chamados
if (!$is_admin) {
    $query .= " AND user_id = :user_id";
}

// Adicionar filtro de status, se estiver definido
if (!empty($statusFiltro)) {
    $query .= " AND status = :status";
}

// Adicionar filtro de prioridade, se estiver definido
if (!empty($prioridadeFiltro)) {
    $query .= " AND prioridade = :prioridade";
}

// Adicionar pesquisa por ID, título, descrição ou tipo de solicitação, se o campo de pesquisa estiver preenchido
if (!empty($searchTerm)) {
    $query .= " AND (id LIKE :searchTerm OR titulo LIKE :searchTerm OR nomeCompleto LIKE :searchTerm OR descricao LIKE :searchTerm)";
}

// Preparar a consulta
$stmt = $pdo->prepare($query);

// Bind dos parâmetros
if (!$is_admin) {
    $stmt->bindValue(':user_id', $usersId);
}
if (!empty($statusFiltro)) {
    $stmt->bindValue(':status', $statusFiltro);
}
if (!empty($prioridadeFiltro)) {
    $stmt->bindValue(':prioridade', $prioridadeFiltro);
}
if (!empty($searchTerm)) {
    $stmt->bindValue(':searchTerm', '%' . $searchTerm . '%');
}

// Executar a consulta
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    'novo_colaborador' => 'Novo Colaborador (DHO)'
];
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Helpdesk</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-k6RqeWeci5ZR/Lv4MR0sA0FfDOMyWcR0cOTe9xV2HR5A/Nd8dVw7RJ5E8qY3k2" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="stylesheet" href="CSS/midia.css">
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

    /* Estilos para cores de status */
    .status-aberto { color: blue; }
    .status-concluido { color: gray; } /* Altere o status "Fechado" para "Concluído" */
    .status-em_atendimento { color: orange; }
    .status-atrasado { color: red; }
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
    <h2>Painel Helpdesk</h2>
    <div class="row">
        <div class="col-md-3">
            <div class="status-icon open">
                <img src="CSS/foto/adicionar.png" alt="icon" class="icon">
                <h5>Abertos</h5>
                <h1><?php echo htmlspecialchars($abertosCount); ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="status-icon in-progress">
                <img src="CSS/foto/atender.png" alt="icon" class="icon">
                <h5>Em Atendimento</h5>
                <h1><?php echo htmlspecialchars($emAndamentoCount); ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="status-icon closed">
                <img src="CSS/foto/feito.png" alt="icon" class="icon">
                <h5>Concluídos</h5> 
                <h1><?php echo htmlspecialchars($fechadosCount); ?></h1>
            </div>
        </div>
        <div class="col-md-3">
            <div class="status-icon total">
                <img src="CSS/foto/total.png" alt="icon" class="icon">
                <h5>Total</h5>
                <h1><?php echo htmlspecialchars($totalCount); ?></h1>
            </div>
        </div>
    </div>

<div class="card">
    <h3>Chamados Recentes</h3>
    <!-- Formulário de pesquisa e filtros -->
<form method="GET" action="dashboard.php">
    <div class="row align-items-end mb-3">
        <!-- Campo de pesquisa -->
        <div class="col-md-4 mb-2">
            <input type="text" class="form-control" name="search" placeholder="Pesquisar por N°, título ou descrição " value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>

        <!-- Filtro por status -->
        <div class="col-md-3 mb-2">
            <select class="form-control" name="status">
                <option value="">Todos os Status</option>
                <option value="aberto" <?php echo ($statusFiltro == 'aberto') ? 'selected' : ''; ?>>Aberto</option>
                <option value="em_atendimento" <?php echo ($statusFiltro == 'em_atendimento') ? 'selected' : ''; ?>>Em Atendimento</option>
                <option value="concluido" <?php echo ($statusFiltro == 'concluido') ? 'selected' : ''; ?>>Concluído</option>
                <option value="atrasado" <?php echo ($statusFiltro == 'atrasado') ? 'selected' : ''; ?>>Atrasado</option>
            </select>
        </div>

        <!-- Filtro por prioridade -->
        <div class="col-md-3 mb-2">
            <select class="form-control" name="prioridade">
                <option value="">Todas as Prioridades</option>
                <option value="baixa" <?php echo ($prioridadeFiltro == 'baixa') ? 'selected' : ''; ?>>Baixa</option>
                <option value="media" <?php echo ($prioridadeFiltro == 'media') ? 'selected' : ''; ?>>Média</option>
                <option value="alta" <?php echo ($prioridadeFiltro == 'alta') ? 'selected' : ''; ?>>Alta</option>
            </select>
        </div>

        <!-- Botões de ação -->
        <div class="col-md-3 mb-2 ">
            <button type="submit" class="btn btn-primary me-2">Pesquisar</button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-trash"></i> Limpar
            </a>
        </div>
    </div>
</form>


        <table class="table table-striped">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Título</th>
                    <th>Usuário</th>
                    <th>Tipo de Solicitação</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr onclick="location.href='visualizar_chamado.php?id=<?php echo htmlspecialchars($ticket['id']); ?>'">
                        <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['titulo']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['nomeCompleto']); ?></td> 
                        <td><?php echo htmlspecialchars($tipos_solicitacao[$ticket['tipo_solicitacao']] ?? 'Tipo não especificado'); ?></td>
                        <td class="<?php echo 'prioridade-' . strtolower($ticket['prioridade']); ?>">
                            <?php echo htmlspecialchars($ticket['prioridade']); ?>
                        </td>
                        <td class="<?php echo 'status-' . strtolower(str_replace(' ', '-', $ticket['status'])); ?>">
                            <?php echo htmlspecialchars(traduzirStatus($ticket['status'])); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">Nenhum chamado encontrado.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
