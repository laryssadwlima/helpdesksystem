<?php
session_start();
include('db.php');

// Verifica se o admin está logado
if (!$_SESSION['is_admin']) {
    header('Location: index.php');
    exit();
}

$ticket_id = $_GET['id'];

// Deleta o chamado
$stmt = $pdo->prepare('DELETE FROM tickets WHERE id = ?');
$stmt->execute([$ticket_id]);

header('Location: dashboard.php');
exit();
?>
