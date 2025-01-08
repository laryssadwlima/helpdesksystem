<?php
require 'vendor/autoload.php'; // Inclua o autoloader do Composer

use PHPMailer\PHPMailer\PHPMailer; // Use a classe PHPMailer
use PHPMailer\PHPMailer\Exception; // Use a classe Exception
// Função para enviar e-mails com PHPMailer
function enviarEmail($destinatario, $assunto, $mensagem) {
    $mail = new PHPMailer(true);
    
    try {
        // Configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host = ''; 
        $mail->SMTPAuth = true;
        $mail->Username = ''; 
        $mail->Password = ''; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587; 

        // Destinatários
        $mail->setFrom('', '');
        $mail->addAddress($destinatario);

        // Conteúdo do e-mail
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; 
        $mail->Subject = $assunto;
        $mail->Body = $mensagem;

        $mail->send();
        return true; // E-mail enviado com sucesso
    } catch (Exception $e) {
        return false; // E-mail não foi enviado
    }
}

// Função para enviar e-mail de abertura de chamado para admin
function enviarEmailAberturaChamadoAdmin($numero_chamado, $usuario, $planta, $setor, $tipo_solicitacao, $descricao, $admin) {
    $assunto = "Abertura de Chamado - #$numero_chamado";
    $mensagem = "
    <p>Olá Suporte,</p>
    <p>Um novo chamado foi aberto.</p>
    <p><strong>Número do Chamado:</strong> #$numero_chamado<br>
    <strong>Usuário:</strong> $usuario<br>
    <strong>Planta:</strong> $planta<br>
    <strong>Setor:</strong> $setor<br>
    <strong>Tipo de Solicitação:</strong> $tipo_solicitacao<br>
    <strong>Descrição:</strong> $descricao</p>
    <p>Atenciosamente,<br>Helpdesk </p>
    ";
    enviarEmail('', $assunto, $mensagem); // Altere para o e-mail do admin correto

}

// Função para enviar e-mail de confirmação ao usuário
function enviarEmailAberturaChamadoUsuario($numero_chamado, $usuario_nome, $planta, $setor, $tipo_solicitacao, $descricao, $usuario_email) {
    $assunto = "Chamado Aberto com Sucesso - #$numero_chamado";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>Seu chamado foi aberto com sucesso! Aqui estão os detalhes:</p>
    <p><strong>Número do Chamado:</strong> #$numero_chamado<br>
    <strong>Planta:</strong> $planta<br>
    <strong>Setor:</strong> $setor<br>
    <strong>Descrição:</strong> $descricao</p>
    <p>Agradecemos por utilizar nosso sistema. Em breve, um técnico irá atendê-lo.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($usuario_email, $assunto, $mensagem);
}

// Função para enviar mensagem de auto-cadastro de usuário
function enviarEmailAutoCadastro($usuario_nome, $usuario, $email, $senha, $telefone) {
    $assunto = "Bem-vindo ao Sistema de Helpdesk ";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>Parabéns! Sua conta foi criada com sucesso. Aqui estão suas informações:</p>
    <p><strong>Nome de Usuário:</strong> $usuario<br>
    <strong>E-mail:</strong> $email<br>
    <strong>Senha:</strong> $senha<br>
    <strong>Telefone:</strong> $telefone</p>
    <p>Agradecemos por se cadastrar. Se você tiver alguma dúvida, não hesite em entrar em contato.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($email, $assunto, $mensagem);
}

// Função para avisar o usuário que o chamado está em atendimento
function enviarEmailChamadoEmAtendimento($numero_chamado, $usuario_nome, $admin, $usuario_email) {
    $assunto = "Chamado em Atendimento - #$numero_chamado";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>Seu chamado #$numero_chamado foi atribuído ao técnico $admin para atendimento. Em breve, você receberá mais informações.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($usuario_email, $assunto, $mensagem);
}


// Função para avisar o usuário sobre nova mensagem do técnico
function enviarEmailAguardandoResposta($numero_chamado, $usuario_nome, $admin, $usuario_email) {
    $assunto = "Mensagem do Técnico - Chamado #$numero_chamado";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>O técnico $admin respondeu ao seu chamado #$numero_chamado. Por favor, verifique a mensagem e forneça sua resposta o mais breve possível.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($usuario_email, $assunto, $mensagem);
}

// Função para avisar o admin sobre chamado atrasado
function enviarEmailEmAtraso($numero_chamado, $usuario, $data_abertura, $descricao) {
    $assunto = "Chamado Atrasado - #$numero_chamado";
    $mensagem = "
    <p>Olá Suporte,</p>
    <p>O chamado #$numero_chamado está atrasado. Por favor, tome as devidas providências para atendimento.</p>
    <p><strong>Usuário:</strong> $usuario<br>
    <strong>Data de Abertura:</strong> $data_abertura<br>
    <strong>Descrição:</strong> $descricao</p>
    <p>Atenciosamente,<br> </p>
    ";
    enviarEmail('', $assunto, $mensagem); 
}

function enviarEmailRespostaUsuario($numero_chamado, $usuario, $mensagem_usuario, $admin_email) {
    $assunto = "Resposta do Usuário - Chamado #$numero_chamado";
    $mensagem = "
    <p>Olá Suporte,</p>
    <p>O usuário $usuario respondeu ao chamado #$numero_chamado.</p>
    <p><strong>Mensagem:</strong> $mensagem_usuario</p>
    <p>Por favor, verifique e responda o mais breve possível.</p>
    <p>Atenciosamente,<br></p>
    ";
    // Envia o email apenas para o admin atribuído
    enviarEmail($admin_email, $assunto, $mensagem);
}


// Função para avisar o usuário que o chamado foi concluído
function enviarEmailChamadoConcluido($numero_chamado, $usuario_nome, $usuario_email) {
    // Geração de um token único ou ID para avaliação
    $token = bin2hex(random_bytes(16)); 
    $link_avaliacao = ""; 

    $assunto = "Chamado Concluído - #$numero_chamado";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>Seu chamado #$numero_chamado foi concluído. Agradecemos por utilizar nosso sistema!</p>
    <p>Por favor, avalie o atendimento:</p>
    <p><a href='$link_avaliacao'>Clique aqui para avaliar</a></p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($usuario_email, $assunto, $mensagem);
}


function enviarEmailRecuperarSenha($usuario_nome, $usuario_email, $token) {
    $link_redefinicao = "";
    $assunto = "Recuperação de Senha";
    $mensagem = "
    <p>Olá $usuario_nome,</p>
    <p>Recebemos um pedido de recuperação de senha. Clique no link abaixo para redefinir sua senha:</p>
    <p><a href='$link_redefinicao'>Redefinir Senha</a></p>
    <p>Se você não solicitou esta recuperação, por favor, desconsidere este e-mail.</p>
    <p>Atenciosamente,<br></p>
    ";
    // Aqui você deve chamar sua função de envio de e-mail
    enviarEmail($usuario_email, $assunto, $mensagem);
}


// Função para avisar o admin sobre chamado encaminhado
function enviarEmailEncaminharChamado($numero_chamado, $usuario, $admin, $admin_destinatario) {
    $assunto = "Chamado Encaminhado - #$numero_chamado";
    $mensagem = "
    <p>Olá $admin_destinatario,</p>
    <p>O chamado #$numero_chamado foi encaminhado para você por $admin.</p>
    <p><strong>Usuário:</strong> $usuario<br>
    <strong>Descrição:</strong> Descrição do chamado...</p>
    <p>Por favor, inicie o atendimento.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($admin_destinatario, $assunto, $mensagem);
}

// Função para avisar o usuário que o chamado foi encaminhado para outro admin
function enviarEmailEncaminharChamadoUsuario($numero_chamado, $usuario, $admin_destinatario, $usuario_email) {
    $assunto = "Atualização do Chamado - #$numero_chamado";
    $mensagem = "
    <p>Olá $usuario,</p>
    <p>Informamos que seu chamado #$numero_chamado foi encaminhado para atendimento para $admin_destinatario.</p>
    <p>A equipe está trabalhando para resolver seu chamado o mais rápido possível.</p>
    <p>Atenciosamente,<br></p>
    ";
    enviarEmail($usuario_email, $assunto, $mensagem);
}

?>
