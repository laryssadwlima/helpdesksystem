<?php
session_start();

// Função para verificar se o usuário está logado e é um administrador
function checkAdminSession() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
        header('Location: ../index.php');
        exit();
    }
}
checkAdminSession();

// Conectar ao banco de dados
try {
    $pdo = new PDO('mysql:host=localhost;dbname=helpdesk;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage()));
}

// Verificar se o ID do equipamento foi passado
if (isset($_GET['id'])) {
    $equipmentId = $_GET['id'];

    // Verificar se o equipamento está na tabela `used_equipment`
    $stmt = $pdo->prepare('SELECT * FROM used_equipment WHERE equipment_id = :equipment_id');
    $stmt->execute(['equipment_id' => $equipmentId]);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equipment) {
        // Exibir o formulário de devolução
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Processar o formulário de devolução
            $observations = $_POST['observations'];
            $returnFile = isset($_FILES['return_file']) ? $_FILES['return_file'] : null;

            try {
                // Iniciar transação
                $pdo->beginTransaction();

                // Atualizar o status do equipamento para "disponível" na tabela `inventory`
                $updateStmt = $pdo->prepare('UPDATE inventory SET status = "disponível" WHERE id = :id');
                $updateStmt->execute(['id' => $equipmentId]);

                // Remover o registro correspondente da tabela `used_equipment`
                $deleteStmt = $pdo->prepare('DELETE FROM used_equipment WHERE equipment_id = :equipment_id');
                $deleteStmt->execute(['equipment_id' => $equipmentId]);

                // Registrar no histórico a devolução
                $historyStmt = $pdo->prepare('INSERT INTO usage_history (equipment_id, status, description, user, date, return_file, admin_id) 
                                              VALUES (:equipment_id, "disponível", :observations, :user, NOW(), :return_file, :admin_id)');
                $returnFilePath = null;

                // Se houver um arquivo, mover para o diretório desejado
                if ($returnFile && $returnFile['error'] === UPLOAD_ERR_OK) {
                    $returnFilePath = '../uploads/' . basename($returnFile['name']);
                    move_uploaded_file($returnFile['tmp_name'], $returnFilePath);
                }

                $historyStmt->execute([
                    'equipment_id' => $equipmentId,
                    'observations' => $observations,
                    'user' => $_SESSION['user_id'],
                    'return_file' => $returnFilePath,
                    'admin_id' => $_SESSION['user_id'] // Armazenar o ID do admin
                ]);

                // Commit da transação
                $pdo->commit();

                // Redirecionar de volta para a página de equipamentos usados
                header('Location: used_equipment.php');
                exit();
            } catch (Exception $e) {
                // Rollback da transação em caso de erro
                $pdo->rollBack();
                echo "Erro ao devolver equipamento: " . htmlspecialchars($e->getMessage());
            }
        } else {
            // Exibir formulário de justificativa e anexo
            include 'return_form.php'; // Incluir o arquivo do formulário
            exit();
        }
    } else {
        echo "Equipamento não encontrado na tabela de equipamentos usados.";
    }
} else {
    echo "ID do equipamento não fornecido.";
}
?>
