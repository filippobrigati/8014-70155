<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Config/Database.php';

$cap = $_GET['cap'] ?? '';

if (strlen($cap) === 5) {
    $db = \Config\Database::getInstance()->getMySQL();
    $stmt = $db->prepare("SELECT comune, provincia, regione FROM Citta_CAP WHERE cap = :cap");
    $stmt->execute([':cap' => $cap]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['found' => true, 'data' => $result]);
        exit;
    }
}

echo json_encode(['found' => false]);

?>