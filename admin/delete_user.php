<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Conectar ao banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Verifica se o ID do usuário foi passado
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

// Excluir usuário
$stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
$stmt->execute(['id' => $_GET['id']]);

header('Location: users.php');
exit();
?>
