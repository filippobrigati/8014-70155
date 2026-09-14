<?php

namespace Controllers;
use Config\Database;
use PDO;

class AuthController
{

    public function login($email, $password_in_chiaro)
    {
        $db = Database::getInstance()->getMySQL();

        $stmt = $db->prepare("SELECT * FROM Utente WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $utente = $stmt->fetch();
        $stmt->closeCursor();

        if ($utente && password_verify($password_in_chiaro, $utente['password_hash'])) {

            $token_chiaro = bin2hex(random_bytes(32));
            $token_hash_db = hash('sha256', $token_chiaro);

            $scadenza_timestamp = time() + (86400 * 30);
            $scadenza_db = date('Y-m-d H:i:s', $scadenza_timestamp);

            $stmtSession = $db->prepare("CALL registra_sessione(:id_utente, :token_hash, :scadenza)");
            $stmtSession->execute([
                ':id_utente' => $utente['id'],
                ':token_hash' => $token_hash_db,
                ':scadenza' => $scadenza_db
            ]);

            setcookie(
                'auth_token',
                $token_chiaro,
                [
                    'expires' => $scadenza_timestamp,
                    'path' => '/',
                    'domain' => '',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );

            header("Location: /");
            exit;

        } else {
            return "Email o password errati.";
        }
    }

    public function logout($id)
    {
        $db = Database::getInstance()->getMySQL();
        $stmt = $db->prepare("DELETE FROM Sessione_Utente WHERE Sessione_Utente.id_utente = :id");
        $stmt->execute([":id" => $this->$id]);
        $stmt->closeCursor();

        header("Location: /auth/login");
        exit;
    }

    public function registrazioneUtente($email, $password, $ruolo, $datiAnagrafici)
    {
        $db = Database::getInstance()->getMySQL();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("CALL sp_registra_utente(:email, :password, :ruolo, :cf, :nome, :cognome, :data_nascita, :sesso)");

        return $stmt->execute([
            ':email' => $email,
            ':password' => $passwordHash,
            ':ruolo' => $ruolo,
            ':cf' => $datiAnagrafici['codice_fiscale'],
            ':nome' => $datiAnagrafici['nome'],
            ':cognome' => $datiAnagrafici['cognome'],
            ':data_nascita' => $datiAnagrafici['data_nascita'],
            ':sesso' => $datiAnagrafici['sesso']
        ]);
    }

    public function registrazioneAdminConAzienda($email, $password, $datiAnagrafici, $partita_iva, $ragione_sociale, $via, $civico, $cap, $comune, $provincia, $regione)
    {
        $db = Database::getInstance()->getMySQL();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("CALL sp_registra_admin_azienda(:email, :password, :cf, :nome, :cognome, :data_nascita, :sesso, :piva, :ragione, :via, :civico, :cap, :comune, :provincia, :regione)");

        return $stmt->execute([
            ':email' => $email,
            ':password' => $passwordHash,
            ':cf' => $datiAnagrafici['codice_fiscale'],
            ':nome' => $datiAnagrafici['nome'],
            ':cognome' => $datiAnagrafici['cognome'],
            ':data_nascita' => $datiAnagrafici['data_nascita'],
            ':sesso' => $datiAnagrafici['sesso'],
            ':piva' => $partita_iva,
            ':ragione' => $ragione_sociale,
            ':via' => $via,
            ':civico' => $civico,
            ':cap' => $cap,
            ':comune' => $comune,
            ':provincia' => $provincia,
            ':regione' => $regione
        ]);
    }
}