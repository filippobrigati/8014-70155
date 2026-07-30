-- Procedura per la registrazione della sessione (nuova)
CREATE PROCEDURE registra_sessione(
    IN p_id_utente INT,
    IN p_token_hash VARCHAR(255),
    IN p_data_scadenza TIMESTAMP
)
BEGIN
    DELETE FROM Sessione_Utente WHERE id_utente = p_id_utente;
    
    INSERT INTO Sessione_Utente (id_utente, token_hash, data_scadenza)
    VALUES (p_id_utente, p_token_hash, p_data_scadenza);
END //

-- Procedura per la validaiione della sessione
CREATE PROCEDURE valida_sessione (IN p_token_hash VARCHAR(255))
BEGIN
    SELECT
        u.id,
        u.ruolo,
        u.nome,
        u.cognome
    FROM
        Sessione_Utente su
        JOIN Utente u ON su.id_utente = u.id
    WHERE
        su.token_hash = p_token_hash
        AND su.data_scadenza > CURRENT_TIMESTAMP
    LIMIT 1;
END

-- Procedura per la registrazione di un nuovo utente
DELIMITER //
CREATE PROCEDURE sp_registra_utente(
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_ruolo ENUM('admin', 'medico', 'cliente'),
    IN p_codice_fiscale VARCHAR(16),
    IN p_nome VARCHAR(50),
    IN p_cognome VARCHAR(50),
    IN p_data_nascita DATE,
    IN p_sesso CHAR(1)
)
BEGIN
    INSERT INTO Utente (email, password, ruolo, codice_fiscale, nome, cognome, data_nascita, sesso)
    VALUES (p_email, p_password_hash, p_ruolo, p_codice_fiscale, p_nome, p_cognome, p_data_nascita, p_sesso);
END //
DELIMITER ;

-- Procedura per l'inserimento di un nuovo admin con relativa Azienda (uso una transazione per non perdere eventuali commit a meta')
DELIMITER //
CREATE PROCEDURE sp_registra_admin_azienda(
    IN p_email VARCHAR(100),
    IN p_password_hash VARCHAR(255),
    IN p_codice_fiscale VARCHAR(16),
    IN p_nome VARCHAR(50),
    IN p_cognome VARCHAR(50),
    IN p_data_nascita DATE,
    IN p_sesso CHAR(1),
    IN p_partita_iva VARCHAR(11),
    IN p_ragione_sociale VARCHAR(255),
    IN p_via VARCHAR(255),
    IN p_civico VARCHAR(20),
    IN p_cap CHAR(5),
    IN p_comune VARCHAR(100),
    IN p_provincia CHAR(2),
    IN p_regione VARCHAR(50)
)
BEGIN
    DECLARE v_id_admin INT;

    START TRANSACTION;

    INSERT INTO Citta_CAP (cap, comune, provincia, regione)
    SELECT p_cap, p_comune, p_provincia, p_regione
    WHERE NOT EXISTS (
        SELECT 1 FROM Citta_CAP WHERE cap = p_cap
    );

    INSERT INTO Utente (email, password_hash, ruolo, codice_fiscale, nome, cognome, data_nascita, sesso)
    VALUES (p_email, p_password_hash, 'admin', p_codice_fiscale, p_nome, p_cognome, p_data_nascita, p_sesso);
    
    SET v_id_admin = LAST_INSERT_ID();

    INSERT INTO Centro_Medico (partita_iva, ragione_sociale, via, civico, cap, id_admin_creatore)
    VALUES (p_partita_iva, p_ragione_sociale, p_via, p_civico, p_cap, v_id_admin);

    COMMIT;
END //
DELIMITER ;