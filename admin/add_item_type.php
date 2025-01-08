<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

// Conectar ao banco de dados
$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Processar o formulário de adição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $typeName = $_POST['type_name'];

    $stmt = $pdo->prepare('INSERT INTO item_types (type_name) VALUES (?)');
    $stmt->execute([$typeName]);

    header('Location: inventory.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Tipo de Equipamento</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="icon" type="image/x-icon" href="../CSS/foto/favicon.ico">
</head>
<body>
    
    <div class="container">
        <h2>Adicionar Novo Tipo de Equipamento</h2>
        <form method="post">
            <div class="form-group">
                <label for="type_name">Nome do Tipo</label>
                <input type="text" class="form-control" id="type_name" name="type_name" required>
            </div>
            <button type="submit" class="btn btn-primary">Adicionar</button>
            <a href="add_equipment.php" class="btn btn-secondary">Voltar</a>
        </form>
    </div>
</body>
</html>
