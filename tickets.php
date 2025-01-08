
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$pdo = new PDO('mysql:host=localhost;dbname=helpdesk', 'root', '');

// Tela Para usuarios normais

// Obter chamados do usuário
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM tickets WHERE user_id = ?');
$stmt->execute([$user_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
// Te
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Chamados</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2>Meus Chamados</h2>
        <a href="create_ticket.php" class="btn btn-primary">Abrir Novo Chamado</a>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['priority']); ?></td>
                    <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                    <td>
                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-info">Visualizar</a>
                        <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-warning">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
