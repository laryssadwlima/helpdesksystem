<?php
session_start();
include('db.php');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$ticket_id = $_POST['ticket_id'];
$mensagem = $_POST['mensagem'];
$user_id = $_SESSION['user_id'];

// Insere a nova mensagem no ticket
$stmt = $pdo->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, mensagem) VALUES (?, ?, ?)');
$stmt->execute([$ticket_id, $user_id, $mensagem]);

// Redireciona de volta para a página do chamado
header('Location: visualizar_chamado.php?id=' . $ticket_id);
exit();
?>
