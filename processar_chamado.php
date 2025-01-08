<?php
session_start();
require 'db.php'; // Arquivo de conexão com o banco de dados

// Verifique se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Você precisa estar logado para abrir um chamado.";
    header('Location: index.php');
    exit();
}

// Obtenha os dados do usuário logado
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT nomeCompleto, email, setor FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifique se o usuário foi encontrado
if (!$user) {
    $_SESSION['error'] = "Usuário não encontrado.";
    header('Location: index.php');
    exit();
}

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter dados do formulário
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo_solicitacao = $_POST['tipo_solicitacao'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $planta = $_POST['planta'] ?? null; // Captura o valor da planta

    // Obter a prioridade do chamado
    $prioridade = $_POST['prioridade'] ?? 'baixa'; // Define 'baixa' como padrão

    // Campos opcionais, com validação
    $equipamento_necessario = $tipo_solicitacao === 'solicitacao_equipamento' ? trim($_POST['equipamento_necessario'] ?? null) : null;
    $modelo_impressora = $tipo_solicitacao === 'impressora' ? trim($_POST['modelo_impressora'] ?? null) : null;

    // Novos campos, com validação
    $nomecolaborador = trim($_POST['nomecolaborador'] ?? null);
    $setor_colaborador = trim($_POST['setor'] ?? null);
    $datadeinicio = $_POST['datadeinicio'] ?? null;

    // Define o status padrão como 'aberto'
    $status = 'aberto';

    // Verificação de campos obrigatórios
    if (empty($titulo) || empty($tipo_solicitacao) || empty($descricao)) {
        $_SESSION['error'] = "Todos os campos obrigatórios devem ser preenchidos.";
        header('Location: abrir_chamado.php');
        exit();
    }

    try {
        // Inserir dados na tabela chamados
        $stmt_insert = $pdo->prepare('
            INSERT INTO chamados (
                user_id, titulo, tipo_solicitacao, descricao, equipamento_necessario, modelo_impressora, 
                nomeCompleto, email_utilizado, prioridade, status, planta, nomecolaborador, setor_colaborador, datadeinicio,
            ) 
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ');

        $stmt_insert->execute([
            $user_id,
            $titulo,
            $tipo_solicitacao,
            $descricao,
            $equipamento_necessario,
            $modelo_impressora,
            $user['nomeCompleto'],
            $user['email'],
            $prioridade,
            $status,
            $planta,
            $nomecolaborador,  // Novo campo
            $setor_colaborador, // Novo campo
            $datadeinicio      // Novo campo
        ]);

        // Verifique se a inserção foi bem-sucedida
        if ($stmt_insert->rowCount() > 0) {
            $_SESSION['success'] = "Chamado processado com sucesso!";
            header('Location: index.php');
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
?>
