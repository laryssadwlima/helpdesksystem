<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo_solicitacao'])) {
    $tipo = $_POST['tipo_solicitacao'];

    // Redirecionar para o formulário correto baseado no tipo de solicitação
    switch ($tipo) {
        case 'Novo colaborador (ficha RH)':
            header('Location: novo_colaborador.php');
            break;
        case 'Acessos':
            header('Location: acesso.php');
            break;
        case 'Instalação de software':
            header('Location: instalacao_software.php');
            break;
        case 'Impressora':
            header('Location: impressora.php');
            break;
        case 'Protheus':
        case 'Rede':
        case 'Dúvida':
        case 'Erros':
        case 'Email':
        case 'Outros':
            header('Location: descricao_simples.php?tipo='.urlencode($tipo));
            break;
        case 'Licenças':
            header('Location: licencas.php');
            break;
        case 'Solicitação de equipamento T.I':
            header('Location: equipamento_ti.php');
            break;
        default:
            header('Location: abrir_chamado.php');
    }
}
