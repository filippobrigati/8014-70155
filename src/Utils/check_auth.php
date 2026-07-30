<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Config/Database.php';

function verificaAccesso()
{
    if (!isset($_COOKIE['auth_token'])) {
        header("Location: auth/login.php");
        exit;
    }

    $db = \Config\Database::getInstance()->getMySQL();
    $stmt = $db->prepare("CALL valida_sessione(:token_hash)");
    $stmt->execute([':token_hash' => hash('sha256', $_COOKIE['auth_token'])]);
    $utente = $stmt->fetch();

    if (!$utente) {
        setcookie('auth_token', '', time() - 3600, '/');
        header("Location: auth/login.php");
        exit;
    }

    return $utente;
}

$utenteLoggato = verificaAccesso();
?>