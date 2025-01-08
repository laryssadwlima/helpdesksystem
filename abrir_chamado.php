<?php
session_start();
require 'db.php'; // Arquivo de conexão com o banco de dados
include 'config.php'; // Ajuste o caminho conforme necessário

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Você precisa estar logado para abrir um chamado.";
    header('Location: index.php');
    exit();
}

// Obtenha os dados do usuário logado
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT nomeCompleto, email, setor, telefone, username FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// Consultar informações do usuário logado
$usersId = $_SESSION['user_id'];
$usersQuery = "SELECT username, photo FROM users WHERE id = :user_id";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute(['user_id' => $usersId]);
$users = $usersStmt->fetch(PDO::FETCH_ASSOC);



// Inicializa variáveis
$nomeCompleto = $user['nomeCompleto'] ?? '';
$email_utilizado = $user['email'] ?? '';
$setor = $user['setor'] ?? '';
$telefone = $user['telefone'] ?? '';
$username = $user['username'] ?? '';

$status = 'aberto'; // Define o status padrão

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo_solicitacao = $_POST['tipo_solicitacao'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $prioridade = $_POST['prioridade'] ?? 'baixa'; // Define 'baixa' como padrão
    $planta = $_POST['planta'] ?? null; // Captura o valor da planta
    $nomecolaborador = trim($_POST['nomecolaborador'] ?? null);
    $datadeinicio = $_POST['datadeinicio'] ?? null;
    $modelo_impressora = trim($_POST['modelo_impressora'] ?? null);
    $equipamento_necessario = trim($_POST['equipamento_necessario'] ?? null);

    // Verificação de campos obrigatórios
    if (empty($titulo) || empty($tipo_solicitacao) || empty($descricao)) {
        $_SESSION['error'] = "Todos os campos obrigatórios devem ser preenchidos.";
        header('Location: abrir_chamado.php');
        exit();
    }

    // Tratamento de anexos
    $anexos = [];
    $upload_dir = 'anexos/';

    if (!empty($_FILES['anexos']['name'][0])) {
        foreach ($_FILES['anexos']['name'] as $key => $anexo_name) {
            $anexo_tmp_name = $_FILES['anexos']['tmp_name'][$key];
            $anexo_ext = pathinfo($anexo_name, PATHINFO_EXTENSION);
            $anexo_new_name = uniqid() . '.' . $anexo_ext;

            // Move o arquivo para o diretório de uploads
            if (move_uploaded_file($anexo_tmp_name, $upload_dir . $anexo_new_name)) {
                $anexos[] = $anexo_new_name; // Armazena o nome do arquivo
            } else {
                $_SESSION['error'] = "Falha no upload de um ou mais anexos.";
                header('Location: abrir_chamado.php');
                exit();
            }
        }
    }

    // Converte o array de nomes de arquivos em uma string separada por vírgulas
    $anexos_str = implode(',', $anexos);
    // Consultar SLA com base no tipo de solicitação
    $stmt_sla = $pdo->prepare('SELECT tempo_sla FROM configuracoes_sla WHERE tipo_solicitacao = ?');
    $stmt_sla->execute([$tipo_solicitacao]);
    $sla_config = $stmt_sla->fetch(PDO::FETCH_ASSOC);
    $tempo_sla = $sla_config ? $sla_config['tempo_sla'] : 'N/A'; // Define um valor padrão

    try {
        // Inserir dados na tabela chamados
        $stmt_insert = $pdo->prepare('
            INSERT INTO chamados (
                user_id, titulo, tipo_solicitacao, descricao, nomeCompleto, email_utilizado, prioridade, status, planta, nomecolaborador, 
                datadeinicio, anexos, tempo_sla, modelo_impressora, equipamento_necessario
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        // Execute a inserção com a variável $anexos_str
        $stmt_insert->execute([
            $user_id,
            $titulo,
            $tipo_solicitacao,
            $descricao,
            $nomeCompleto, 
            $email_utilizado,
            $prioridade,
            $status,
            $planta,
            $nomecolaborador,
            $datadeinicio,
            $anexos_str, 
            $tempo_sla,
            $modelo_impressora,
            $equipamento_necessario
        ]);
        


        // Verifique se a inserção foi bem-sucedida
        if ($stmt_insert->rowCount() > 0) {
            $numero_chamado = $pdo->lastInsertId(); // Pega o último ID inserido
            enviarEmailAberturaChamadoAdmin($numero_chamado, $nomeCompleto, $planta, $setor, $tipo_solicitacao, $descricao, 'tecnicohelpdesk@.com.br');
            enviarEmailAberturaChamadoUsuario($numero_chamado, $nomeCompleto, $planta, $setor, $tipo_solicitacao, $descricao, $email_utilizado);

            $_SESSION['success'] = "Chamado processado com sucesso!";
            header('Location: index.php'); // Redireciona para a página inicial após o envio do ticket
            exit();
        } else {
            $_SESSION['error'] = "Falha ao processar chamado.";
            header('Location: abrir_chamado.php');
            exit();
        }
    } catch (PDOException $e) {
        // Captura e exibe erros de banco de dados
        $_SESSION['error'] = "Erro ao processar chamado: " . $e->getMessage();
        header('Location: abrir_chamado.php');
        exit();
    }
}
date_default_timezone_set('America/Sao_Paulo');
$data_abertura = date('Y-m-d H:i:s');

?>



<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Abrir Chamado</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link rel="icon" type="image/x-icon" href="CSS/foto/favicon.ico">
<style>
body {
    background-color: #f8f9fa; /* Cor de fundo suave */
}


.container {
    max-width: 800px; /* Largura máxima do container */
    margin: 60px auto; /* Centraliza o container */
    padding: 20px; /* Espaçamento interno */
   
    border-radius: 10px; /* Bordas arredondadas */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Sombra suave */
    margin-left: 450px; 
  
}

h2 {
    text-align: center; /* Centraliza o título */
    margin-bottom: 20px; /* Espaço abaixo do título */
}

.form-group {
    margin-bottom: 15px; /* Espaçamento entre os campos do formulário */
}


.nav-link {
    color: white; /* Links da sidebar em branco */
}

.nav-link:hover {
    background-color: #0056b3; /* Efeito hover */
}

.right-icons {
    margin-top: 10px; /* Espaçamento acima dos ícones */
}

.notification-icon,
.settings-icon {
    color: white; /* Ícones em branco */
    margin-right: 15px; /* Espaçamento entre os ícones */
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
<div class="container mt-6">
    <h2>Abertura de Chamado</h2>
    <form id="formChamado" method="POST" enctype="multipart/form-data">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger mt-3">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success mt-3">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    <div class="form-group">
            <label for="nome">Usuário</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly required>
        </div>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nomeCompleto" name="nomeCompleto" value="<?php echo htmlspecialchars($user['nomeCompleto']); ?>" readonly required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email_utilizado" name="email_utilizado" value="<?php echo htmlspecialchars($user['email']); ?>" readonly required>
        </div>

        <div class="form-group">
            <label for="setor">Setor</label>
            <input type="text" class="form-control" id="setor" name="setor" value="<?php echo htmlspecialchars($user['setor']); ?>" readonly required>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone</label>
            <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($user['telefone']); ?>" readonly required>
        </div>

        <div class="form-group">
            <label for="data_abertura">Data de Abertura</label>
            <input type="text" class="form-control" id="data_abertura" name="data_abertura" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly required>
        </div>

        <div class="form-group">
            <label for="planta">Selecionar a Planta</label>
            <select class="form-control" id="planta" name="planta" required>
                <option value="">Selecione...</option>
                <option value="P1">P1</option>
                <option value="P2">P2</option>
            </select>
        </div>

        <div class="form-group">
            <label for="prioridade">Prioridade</label>
            <select class="form-control" id="prioridade" name="prioridade" required>
                <option value="">Selecione...</option>
                <option value="baixa">Baixa</option>
                <option value="media">Média</option>
                <option value="alta">Alta</option>
            </select>
        </div>

        <div class="form-group">
            <label for="titulo">Título:</label>
            <input type="text" class="form-control" name="titulo" id="titulo" required>
        </div>
        <div class="form-group">
            <label for="tipo_solicitacao">Tipo de Solicitação:</label>
            <select class="form-control" name="tipo_solicitacao" id="tipo_solicitacao" required>
                <option value="">Selecione</option>
                <option value="duvida">Dúvida</option>
                <option value="erros">Erros</option>
                <option value="outros">Outros</option>
                <option value="acessos">Acessos</option>
                <option value="rede">Rede</option>
                <option value="email">Email</option>
                <option value="licencas">Licenças</option>
                <option value="instalacao">Instalação de Programas</option>
                <option value="impressora">Impressora</option>
                <option value="solicitacao_equipamento">Solicitação de Equipamento</option>
                <option value="novo_colaborador">Novo Colaborador(DHO)</option>
                <!-- Adicione outros tipos conforme necessário -->
            </select>
        </div>

        <div class="form-group" id="equipamentoNecessarioContainer" style="display: none;">
            <label for="equipamento_necessario">Equipamento Necessário:</label>
            <input type="text" class="form-control" name="equipamento_necessario" id="equipamento_necessario">
        </div>
        <div class="form-group" id="modeloImpressoraContainer" style="display: none;">
            <label for="modelo_impressora">Modelo da Impressora:</label>
            <input type="text" class="form-control" name="modelo_impressora" id="modelo_impressora">
        </div>
        <div class="form-group" id="campoNovoColaborador" style="display: none;">


            <label for="nomecolaborador">Nome:</label>
            <input type="text" class="form-control" name="nomecolaborador" id="nomecolaborador" placeholder="Nome do colaborador">

            <label for="datadeinicio">Data de Início:</label>
            <input type="date" class="form-control" name="datadeinicio" id="datadeinicio">

           <!-- Campo para baixar o anexo em Word -->
           <div class="form-group">
             <label for="anexoWord">Modelo Formulario de Admissão:</label>
             <a href="CSS/modelonovascontratacoes.docx" class="btn btn-light" download>Baixar</a>
           </div>
        </div>  

        <div class="form-group">
            <label for="descricao">Descrição:</label>
            <textarea class="form-control" name="descricao" id="descricao" required></textarea>
        </div>
          
   
        <div class="form-group">
            <label for="anexo">Anexo (opcional):</label>
            <input type="file" name="anexos[]" multiple>
        </div>

        <button type="submit" class="btn btn-primary">Abrir Chamado</button>
    </form>
   
</div>

<script>
    document.getElementById('tipo_solicitacao').addEventListener('change', function() {
        const selectedValue = this.value;
        document.getElementById('equipamentoNecessarioContainer').style.display = selectedValue === 'solicitacao_equipamento' ? 'block' : 'none';
        document.getElementById('modeloImpressoraContainer').style.display = selectedValue === 'impressora' ? 'block' : 'none';
        document.getElementById('campoNovoColaborador').style.display = selectedValue === 'novo_colaborador' ? 'block' : 'none';
    });
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
