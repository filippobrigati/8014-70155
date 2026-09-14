<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Anche qua il controllo per il ruolo
if ($utenteLoggato['ruolo'] !== 'cliente') {
    die("Accesso negato.");
}

// Se e' una post, quindi ho pagato la fattura, entro nell'if
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_fattura = $_POST['numero_fattura'] ?? '';
    $id_metodo_pagamento = $_POST['id_metodo_pagamento'] ?? '';

    // Controllo i campi
    if (!empty($numero_fattura) && !empty($id_metodo_pagamento)) {
        $db = \config\Database::getInstance()->getMySQL();

        try {
            $db->beginTransaction();

            // Non avendo il riferimento a quale tabella corrisponde la fattura, se con assicurazione o senza
            // devo eseguire la query di aggiornamento solamente in una delle due, facendolo in entrambe sono
            // sicuro che verra' eseguita
            $stmt1 = $db->prepare("UPDATE Fattura SET stato = 'pagata', id_metodo_pagamento = :metodo WHERE numero_fattura = :num AND stato = 'emessa'");
            $stmt1->execute([':metodo' => $id_metodo_pagamento, ':num' => $numero_fattura]);
            $count1 = $stmt1->rowCount();

            $stmt2 = $db->prepare("UPDATE Fatturazione_Con_Assicurazione SET stato = 'pagata', id_metodo_pagamento = :metodo WHERE numero_fattura = :num AND stato = 'emessa'");
            $stmt2->execute([':metodo' => $id_metodo_pagamento, ':num' => $numero_fattura]);
            $count2 = $stmt2->rowCount();

            // Se una delle due va a buon fine (quindi fattura trovata)
            if ($count1 > 0 || $count2 > 0) {
                $db->commit();

                // Chiamo util di logging e loggo il pagamento
                require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
                $logger = new \Utils\MongoLogger();
                $logger->logAzione(
                    $utenteLoggato['id'],
                    $utenteLoggato['ruolo'],
                    'UPDATE',
                    'Fattura',
                    $numero_fattura,
                    ['azione' => 'pagamento_effettuato', 'id_metodo_pagamento' => $id_metodo_pagamento]
                );

                // Reindirizzamento allo storico con parametro di successo
                header("Location: /cliente/fatture.php");
                exit;
            } else {
                $db->rollBack();
                die("Errore: Fattura non trovata, non appartenente all'utente, o già pagata.");
            }

        } catch (Exception $e) {
            $db->rollBack();
            die("Errore di sistema durante il pagamento: " . $e->getMessage());
        }
    }
}

// Se si accede in GET o mancano parametri, rimanda indietro (questa pagina solo post per il pagamento fattura)
header("Location: /cliente/fatture.php");
exit;