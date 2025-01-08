<?php

$host = 'localhost'; 
$dbname = 'helpdesk'; 
$username = 'root'; 
$password = ''; 

try {

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

   
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

 
    $pdo->exec("SET NAMES 'utf8'");

   
    echo "Conexão bem-sucedida!";
} catch (PDOException $e) {

    echo "Erro na conexão: " . $e->getMessage();
}
?>