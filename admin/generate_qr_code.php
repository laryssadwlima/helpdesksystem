<?php
require '../vendor/autoload.php'; // Carregue o autoload do Composer

use Endroid\QrCode\QrCode;

// Obter o ID do equipamento
$id = isset($_GET['id']) ? $_GET['id'] : 0;

// Criar QR Code
$qrCode = new QrCode('http://192.168.35.2:8383/helpdesk/admin/equipamento.php?unique_id=' . $uniqueId); // Alterado para usar unique_id
header('Content-Type: image/png');
echo $qrCode->writeString();
