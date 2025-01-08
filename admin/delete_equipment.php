<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Conectar ao banco de dados
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

    // Excluir equipamento
    $stmt = $pdo->prepare('DELETE FROM inventory WHERE id = ?');
    $stmt->execute([$id]);

    header('Location: inventory.php');
    exit();
} else {
    echo "ID do equipamento não fornecido.";
}
?>
