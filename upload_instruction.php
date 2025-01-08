<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['instructionFile'];
    $uploadDir = 'instructions/';
    $uploadFile = $uploadDir . basename($file['name']);

    if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
        echo "Arquivo enviado com sucesso.";
    } else {
        echo "Falha ao enviar o arquivo.";
    }
}
?>
