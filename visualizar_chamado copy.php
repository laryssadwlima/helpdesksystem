<?php
session_start();
require 'db.php'; // Substitua pelo seu arquivo de conexão
include 'config.php';

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verifique se o ticket ID foi passado e sanitizado
$ticket_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ticket_id) {
    $_SESSION['error'] = "ID de ticket inválido.";
    header('Location: index.php');
    exit();
}

// Obtenha os detalhes do ticket
$stmt = $pdo->prepare('SELECT * FROM chamados WHERE id = ?');
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifique se o ticket existe
if (!$ticket) {
    $_SESSION['error'] = "Ticket não encontrado.";
    header('Location: index.php');
    exit();
}

// Obtenha os dados do usuário que abriu o ticket
$user_id = $ticket['user_id'] ?? null;
$user = [
    'username' => 'Usuário não encontrado', 
    'nomeCompleto' => 'N/D',
    'telefone' => 'N/D', 
    'email' => 'N/D',
    'setor' => 'N/D',
    'photo' => 'default_photo.png'
];

if ($user_id) {
    $stmt_user = $pdo->prepare('SELECT username, nomeCompleto, telefone, email, setor, photo FROM users WHERE id = ?');
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC) ?? $user;
}

// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);

// Verifique se o chamado pertence ao usuário logado
$is_owner = ($user_id === $usersId);

// Verificar se o usuário já avaliou este chamado
$stmt_avaliacao = $pdo->prepare('SELECT * FROM avaliacoes WHERE chamado_id = ? AND user_id = ?');
$stmt_avaliacao->execute([$ticket_id, $usersId]);
$avaliacao_existente = $stmt_avaliacao->fetch(PDO::FETCH_ASSOC);

// Obter detalhes adicionais do ticket
$equipamentoNecessario = $ticket['equipamento_necessario'] ?? '';
$modeloImpressora = $ticket['modelo_impressora'] ?? '';
$novoColaborador = $ticket['novo_colaborador'] ?? '';
$nomecolaborador = $ticket['nomecolaborador'] ?? '';
$dataAberturaStr = $ticket['data_abertura'] ?? '';
$planta = $ticket['planta'] ?? '';
$finalizado_por_id = $ticket['finalizado_por'] ?? null;
$data_conclusao = $ticket['data_conclusao'] ?? '';

if ($finalizado_por_id) {
    // Obter o nome do usuário que finalizou o ticket
    $stmt_finalizado_por = $pdo->prepare('SELECT nomeCompleto FROM users WHERE id = ?');
    $stmt_finalizado_por->execute([$finalizado_por_id]);
    $finalizado_por_usuario = $stmt_finalizado_por->fetch(PDO::FETCH_ASSOC);
    
    // Definir o nome de quem finalizou o ticket, ou um valor padrão
    $finalizado_por_nome = $finalizado_por_usuario['nomeCompleto'] ?? 'N/D';
} else {
    // Definir um valor padrão caso o ticket não tenha sido finalizado
    $finalizado_por_nome = 'N/D';
}

// Obter mensagens relacionadas ao ticket
$stmt_mensagens = $pdo->prepare('SELECT m.*, u.username, u.photo FROM mensagens m JOIN users u ON m.sender_id = u.id WHERE m.ticket_id = ? ORDER BY m.data_envio ASC');
$stmt_mensagens->execute([$ticket_id]);
$mensagens = $stmt_mensagens->fetchAll(PDO::FETCH_ASSOC);

// Lógica para envio de mensagens
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $mensagem = trim($_POST['mensagem']);
    $sender_id = $_SESSION['user_id'];
    $admin_id = $ticket['finalizado_por'] ?? null; // Identificar o admin responsável
    $is_admin = ($_SESSION['role'] === 'admin'); // Verificar se o usuário é admin

    if (!empty($mensagem)) {
        // Inserir mensagem no banco
        $stmt_send = $pdo->prepare('INSERT INTO mensagens (ticket_id, sender_id, mensagem) VALUES (?, ?, ?)');
        $stmt_send->execute([$ticket_id, $sender_id, $mensagem]);

        // Se o admin estiver enviando a mensagem
        if ($is_admin) {
            // Atualizar o status do ticket para "em_atendimento"
            $stmt_update_status = $pdo->prepare('UPDATE chamados SET status = "em_atendimento" WHERE id = ?');
            $stmt_update_status->execute([$ticket_id]);

            // Enviar e-mail ao usuário
            $stmt_user_email = $pdo->prepare('SELECT email FROM users WHERE id = ?');
            $stmt_user_email->execute([$user_id]); // $user_id é o ID do usuário normal
            $user_email = $stmt_user_email->fetch(PDO::FETCH_ASSOC)['email'];
            
            enviarEmailAguardandoResposta($ticket_id, $user['nomeCompleto'], $users['username'], $user_email);

        } else {
            // Se o usuário enviar a mensagem, notificar o admin
            if ($admin_id) {
                $stmt_admin_email = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt_admin_email->execute([$admin_id]);
                $admin_email = $stmt_admin_email->fetch(PDO::FETCH_ASSOC)['email'];
                
                enviarEmailRespostaUsuario($ticket_id, $user['nomeCompleto'], $mensagem, $admin_email);
            }
        }

        // Evitar reenvio múltiplo
        header("Location: visualizar_chamado.php?id=$ticket_id");
        exit();
    }
}

// Cálculo do SLA
$dataAberturaStr = $ticket['data_abertura'] ?? null;
$sla_time = $ticket['tempo_sla'] ?? 96; // SLA padrão de 96 horas

if ($dataAberturaStr) {
    try {
        // Cálculo do limite do SLA
        $dataCriacao = new DateTime($dataAberturaStr);
        $dataLimite = clone $dataCriacao;
        $dataLimite->modify("+$sla_time hours");

        $dataAtual = new DateTime(); // Obter a data e hora atuais

        // Verificar se o SLA expirou e se o status é "em_atendimento" ou "aberto"
        if ($dataAtual > $dataLimite && 
            ($ticket['status'] === 'em_atendimento' || $ticket['status'] === 'aberto')) {

            $slaStatus = "SLA Expirado";
            $slaClass = "danger"; // Cor vermelha para expirado

            // Mudar o status do chamado para "atrasado" se ainda não estiver
            if ($ticket['status'] !== 'atrasado') { 
                $pdo->beginTransaction();
                $stmt_update_status = $pdo->prepare('UPDATE chamados SET status = "atrasado" WHERE id = ?');
                $stmt_update_status->execute([$ticket['id']]);
                
                if ($stmt_update_status->rowCount() > 0) {
                    error_log("Status do chamado ID {$ticket['id']} atualizado para 'atrasado'");
                    
                    // Enviar e-mail ao admin sobre o chamado atrasado
                    enviarEmailEmAtraso($ticket['id'], $user['nomeCompleto'], $dataAberturaStr, $ticket['descricao']); 
                } else {
                    error_log("Nenhuma linha foi afetada ao tentar atualizar o status para 'atrasado'.");
                }

                $pdo->commit();
            }
        } else if ($ticket['status'] === 'concluido') { 
            // Chamado concluído e verificando SLA
            $slaStatus = "Chamado Concluído";
            $slaClass = "info";
        } else {
            // Chamado ainda aberto e dentro do SLA
            $slaStatus = "Dentro do SLA";
            $slaClass = "success";
        }

        // Formatação da data limite para exibição
        $dataLimiteJS = $dataLimite->format('Y-m-d H:i:s'); 

    } catch (Exception $e) {
        error_log("Erro ao processar SLA: " . $e->getMessage());
        $slaStatus = "Erro ao calcular SLA";
        $slaClass = "warning";
        $dataLimiteJS = null;
    }
} else {
    $dataLimiteJS = null; 
    $slaStatus = "Erro: Data de abertura não disponível.";
    $slaClass = "warning";
}



// Anexos do ticket
$anexos = !empty($ticket['anexos']) ? explode(',', $ticket['anexos']) : [];


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
// Definir valores padrão para evitar erros de variáveis indefinidas
$dataConclusaoFormatada = $data_conclusao ? date('d/m/Y H:i', strtotime($data_conclusao)) : 'N/D';

// Converta a data para HTML de forma segura
$dataConclusaoFormatada = htmlspecialchars($dataConclusaoFormatada ?? '', ENT_QUOTES, 'UTF-8');

$link_avaliacao = "avaliacao.php?id=$ticket_id"; 

?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Ticket</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <script src="https://kit.fontawesome.com/998c60ef77.js" crossorigin="anonymous"></script>
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
    <link rel="stylesheet" href="CSS/midia.css">
    <style>
        @media print {
            /* Esconde elementos indesejados durante a impressão */
            .sidebar,
            .action-buttons,
            .profile-section,
            .no-print {
                display: none;
            }

            /* Centraliza a logo na impressão */
            .print-logo {
                display: block;
                text-align: center;
                margin-bottom: 20px; /* Espaço abaixo da logo */
            }

            .print-logo img {
                max-width: 38%; /* Ajusta a largura da logo */
                height: auto; /* Mantém a proporção */
            }
        }

        /* Estilos para a seção de mensagens */
        .messages {
            background-color: #fff; /* Fundo branco */
            border: 1px solid #ddd;
            border-radius: 5px; /* Bordas arredondadas */
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Sombra leve */
        }

        /* Estilos para cada mensagem */
        .message {
            background-color: #f8f9fa; /* Fundo leve para cada mensagem */
            border: 1px solid #e9ecef; /* Borda leve em cada mensagem */
            border-radius: 5px; /* Bordas arredondadas */
            padding: 10px;
            margin-bottom: 10px;
            display: flex; /* Flexbox para alinhar ícones e textos */
            align-items: flex-start; /* Alinha itens ao topo */
        }

        .message img {
            width: 30px; /* Largura do ícone do usuário */
            height: 30px; /* Altura do ícone do usuário */
            border-radius: 50%; /* Bordas arredondadas para formato de círculo */
            margin-right: 10px; /* Espaço entre o ícone e o texto */
        }

        .message strong {
            color: #007bff; /* Cor para o nome do usuário */
        }

        .message p {
            margin: 0;
        }

        .message small {
            display: block;
            margin-top: 5px;
            color: #6c757d; /* Cor para a data */
        }
.estrelas {
  cursor: pointer;
}

.estrela {
    font-size: 24px; /* Ajuste o tamanho conforme necessário */
    color: gray; /* Cor padrão para estrelas não preenchidas */
}

.estrela.preenchida {
    color: gold; /* Cor para estrelas preenchidas */
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
    <h2><i class="fas fa-ticket-alt"></i> N°: <?php echo htmlspecialchars($ticket_id); ?></h2>
    <hr>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title"><?php echo htmlspecialchars($ticket['titulo'] ?? 'Título não encontrado'); ?></h5>
            <p class="card-subtitle text-muted">Abertura: <?php echo htmlspecialchars($ticket['data_abertura'] ?? ''); ?></p>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Tipo de Solicitação: 
                        <span class="badge badge-primary">
                            <?php 
                            $tipo_solicitacao = $ticket['tipo_solicitacao'] ?? 'Tipo não especificado';
                            echo htmlspecialchars($tipos_solicitacao[$tipo_solicitacao] ?? 'Tipo não especificado'); 
                            ?>
                        </span>
                    </h5>
                    <p><strong>Nome do Usuário:</strong> <?php echo htmlspecialchars($user['nomeCompleto']); ?></p>
                    <p><strong>Telefone:</strong> <?php echo htmlspecialchars($user['telefone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Setor:</strong> <?php echo htmlspecialchars($user['setor']); ?></p>
                    <p><strong>Prioridade:</strong> <?php echo htmlspecialchars($ticket['prioridade'] ?? 'Não especificada'); ?></p>
    
                </div>
                <div class="col-md-6">
                    <p><strong>Status:</strong> <span class="badge badge-<?php echo ($ticket['status'] == 'concluido') ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></p>
                    
                    <strong>SLA:</strong>
                    <?php if ($ticket['status'] === 'concluido'): ?>
                        <!-- Exibir SLA Atendido ou Expirado se o ticket estiver concluído -->
                        <div class="alert alert-<?php echo $slaClass; ?>"><?php echo $slaStatus; ?></div>
                    <?php else: ?>
                         <!-- Exibir contador de tempo restante do SLA se o ticket não estiver concluído -->
                        <div id="contador" class="alert alert-info">Carregando o tempo restante...</div>
                        <script>
                            let slaLimite = new Date("<?php echo $dataLimiteJS; ?>").getTime();
                            // Se a data limite for válida, iniciar o contador
                            if (!isNaN(slaLimite)) {
                                let x = setInterval(function() {
                                    let agora = new Date().getTime();
                                    let distancia = slaLimite - agora;
                                     // Cálculos de tempo
                                     let dias = Math.floor(distancia / (1000 * 60 * 60 * 24));
                                     let horas = Math.floor((distancia % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                     let minutos = Math.floor((distancia % (1000 * 60 * 60)) / (1000 * 60));
                                     let segundos = Math.floor((distancia % (1000 * 60)) / 1000);
                                     // Exibir o tempo restante no elemento "contador"
                                     if (distancia > 0) {
                                        document.getElementById("contador").innerHTML = dias + "d " + horas + "h " + minutos + "m " + segundos + "s ";
                                    } else {
                                        clearInterval(x);
                                        document.getElementById("contador").innerHTML = "SLA expirado";
                                    }
                                }, 1000);
                            } else {
                                document.getElementById("contador").innerHTML = "Erro ao carregar o tempo SLA";
                            }
                        </script>
                    <?php endif; ?>
                </div>
            </div>

            <hr>
                <h6>Descrição</h6>
                <p><?php echo nl2br(htmlspecialchars($ticket['descricao'] ?? 'Descrição não encontrada')); ?></p>
                <!-- Campos adicionais -->
                <div id="equipamentoNecessarioContainer" style="display: <?php echo ($ticket['tipo_solicitacao'] === 'solicitacao_equipamento') ? 'block' : 'none'; ?>">
                    <h6>Equipamento Necessário</h6>
                    <p><?php echo htmlspecialchars($equipamentoNecessario); ?></p>
                </div>

                <div id="modeloImpressoraContainer" style="display: <?php echo ($ticket['tipo_solicitacao'] === 'impressora') ? 'block' : 'none'; ?>">
                    <h6>Modelo da Impressora</h6>
                    <p><?php echo htmlspecialchars($modeloImpressora); ?></p>
                </div>

                <div id="campoNovoColaborador" style="display: <?php echo ($ticket['tipo_solicitacao'] === 'novo_colaborador') ? 'block' : 'none'; ?>">
                    <h6>Novo Colaborador</h6>
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($nomecolaborador); ?></p>
                    <p><strong>Data de Início:</strong> <?php echo htmlspecialchars($datadeinicio); ?></p>
                </div> 
               
            </div>
        </div>
        <div class="card">
           <div class="anexos">
             <h5><i class="fa-solid fa-file-arrow-down" style="color: #195af0;"></i> Anexos</h5>
             <ul class="list-group">
                <?php if ($anexos && count($anexos) > 0): ?>
                    <?php foreach ($anexos as $anexo): ?>
                        <li class="list-group">
                            <a href="anexos/<?php echo htmlspecialchars($anexo); ?>" target="_blank"><?php echo htmlspecialchars($anexo); ?></a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="list-group">Nenhum anexo encontrado.</li>
                <?php endif; ?>
             </ul>
           </div>
        </div>
         <div class="card">
            <h4>
             <i class="fa-solid fa-comments" style="color: #195af0;"></i>
             Chat
            </h4>
            <h5>Mensagens</h5>
            
            <div id="mensagens">
                <?php foreach ($mensagens as $mensagem): ?>
                    <div class="message">
                    <img src="<?php echo htmlspecialchars($mensagem['photo'] ?: 'default_photo.png'); ?>" alt="Usuário">
                      <div>
                          <strong><?php echo htmlspecialchars($mensagem['username']); ?></strong>
                          <p><?php echo htmlspecialchars($mensagem['mensagem']); ?></p>
                          <small><?php echo htmlspecialchars($mensagem['data_envio']); ?></small>
                      </div>
                    </div>
                <?php endforeach; ?>
            </div>


<!-- Formulário para enviar nova mensagem -->
<?php if ($ticket['status'] !== 'concluido'): ?>
    <form method="post">
        <div class="form-group">
            <label for="mensagem">Enviar Mensagem:</label>
            <textarea class="form-control" id="mensagem" name="mensagem" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Enviar</button>
    </form>
<?php else: ?>
    <p class="text-muted">Este chamado foi concluído, não é possível enviar mensagens.</p>
<?php endif; ?>

<?php if ($ticket['status'] === 'concluido'): ?>
    <div class="card">
        <h5><i class="fa-solid fa-circle-check" style="color: #2e9a19;"></i> Dados de Conclusão</h5>
        <p><strong><i class="fa-solid fa-user"></i> Finalizado por:</strong> <?php echo htmlspecialchars($finalizado_por_nome); ?></p>
        <p><strong><i class="fa-solid fa-calendar"></i> Data de Conclusão:</strong> <?php echo htmlspecialchars($dataConclusaoFormatada); ?></p>
        <p><strong><i class="fa-solid fa-star-half-stroke"></i> Avaliação do Usuário:</strong></p>
        <div class="avaliacao">
            <?php
            // Recupera a avaliação do chamado
            $stmt_avaliacao = $pdo->prepare('SELECT nota, comentario FROM avaliacoes WHERE chamado_id = ? AND user_id = ?');
            $stmt_avaliacao->execute([$ticket['id'], $usersId]); // Certifique-se de passar o ID do usuário
            $avaliacao = $stmt_avaliacao->fetch(PDO::FETCH_ASSOC);

            if ($avaliacao): 
                $nota = (int) $avaliacao['nota'];
                $comentario = $avaliacao['comentario'];
            ?>
                <div class="estrelas">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="estrela <?= $i <= $nota ? 'preenchida' : '' ?>" 
                              data-comentario="<?= htmlspecialchars($comentario) ?>" 
                              onclick="mostrarComentario(this)">
                            ★
                        </span>
                    <?php endfor; ?>
                </div>
                <p id="comentario" style="display:none;"></p>
            <?php else: ?>
                <p>N/D</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Caso o ticket não esteja concluído, ocultar os dados de conclusão -->
    <p><strong>Realizando Atendimento:</strong> <?php echo htmlspecialchars($finalizado_por_nome ?? 'Não especificado'); ?></p>           
<?php endif; ?>

<!-- Modificação no botão de deletar (somente se status não for atendimento ou concluído) -->
<div class="card-footer ">
    <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="atender_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-success"><i class="fas fa-thumbtack"></i> Atender Chamado</a>
        <a href="encaminhar_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-warning"><i class="fas fa-share"></i> Encaminhar</a>
    <?php else: ?>
        <?php if ($ticket['status'] !== 'atendimento' && $ticket['status'] !== 'concluído'): ?>
            <form action="deletar_ticket.php?id=<?php echo $ticket_id; ?>" method="POST" style="display:inline;">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja deletar este chamado?');"><i class="fas fa-trash"></i> Deletar Chamado</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Mostrar o botão de avaliação apenas se o ticket está concluído e pertence ao usuário -->
    <?php if ($is_owner && $ticket['status'] === 'concluido' && !$avaliacao_existente) : ?>
        <a href="<?php echo $link_avaliacao; ?>" class="btn btn-primary">Avaliar Chamado</a>
    <?php endif; ?>
    
    <a href="index.php" class="btn btn-secondary"> Voltar</a>
</div>

<script>

  function openDocument(url) {
    var fileExtension = url.split('.').pop().toLowerCase();
    if (fileExtension === 'pdf' || fileExtension === 'jpg' || fileExtension === 'png') {
        // Para PDF e imagens, abre em uma nova janela
        window.open(url, 'popup', 'width=800,height=600');
    } else if (fileExtension === 'doc' || fileExtension === 'docx' || fileExtension === 'xls' || fileExtension === 'xlsx') {
        // Para documentos do Word e Excel, usa Google Docs
        var googleDocsUrl = 'https://docs.google.com/gview?url=' + encodeURIComponent(url) + '&embedded=true';
        window.open(googleDocsUrl, 'popup', 'width=800,height=600');
    } else {
        alert('Formato de arquivo não suportado para visualização.');
    }
  }       


function mostrarComentario(element) {
    const comentario = element.getAttribute('data-comentario');
    const comentarioElement = document.getElementById('comentario');

    if (comentario) {
        comentarioElement.textContent = comentario;
        comentarioElement.style.display = 'block';
    } else {
        comentarioElement.style.display = 'none';
    }
}

</script>
</body>
</html>
