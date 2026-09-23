CREATE PROCEDURE `basi_di_dati`.`EmettiFattura`(IN p_codice_visita INT, IN p_codice_voucher VARCHAR(50))
BEGIN
    DECLARE v_stato VARCHAR(20);
    DECLARE v_data_visita DATE;
    DECLARE v_ora_inizio TIME;
    DECLARE v_prezzo DECIMAL(10,2);
    DECLARE v_id_cliente INT;
    DECLARE v_num_fattura VARCHAR(20);
    DECLARE v_has_referto INT;
    
    DECLARE v_sconto DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_cod_assicurazione VARCHAR(50) DEFAULT NULL;
    DECLARE v_prezzo_finale DECIMAL(10,2);

    SELECT COUNT(*) INTO v_has_referto FROM Referto WHERE codice_visita = p_codice_visita;
    
    IF v_has_referto = 0 THEN
        SELECT 'ERROR' AS esito, 'Devi prima inserire il referto medico per questa visita.' AS messaggio;
    ELSE
        SELECT stato, data_visita, ora_inizio, prezzo_applicato, id_cliente 
        INTO v_stato, v_data_visita, v_ora_inizio, v_prezzo, v_id_cliente
        FROM Visita 
        WHERE codice = p_codice_visita;
        
        IF v_stato = 'prenotata' THEN
            IF TIMESTAMP(v_data_visita, v_ora_inizio) <= NOW() THEN
                
                START TRANSACTION;

                UPDATE Visita SET stato = 'completata' WHERE codice = p_codice_visita;
                
                IF p_codice_voucher IS NULL OR p_codice_voucher = '' THEN
                    SET v_num_fattura = CONCAT('F-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', p_codice_visita);
                    
                    INSERT INTO Fattura (numero_fattura, codice_visita, importo_totale, stato)
                    VALUES (v_num_fattura, p_codice_visita, v_prezzo, 'emessa');
                    
                ELSE
                    SELECT COALESCE(MAX(a.sconto_percentuale), 0.00), MAX(a.codice)
                    INTO v_sconto, v_cod_assicurazione
                    FROM Cliente_Assicurazione ca
                    JOIN Assicurazione a ON ca.id_assicurazione = a.id
                    WHERE ca.id_cliente = v_id_cliente;
                    
                    SET v_prezzo_finale = v_prezzo - (v_prezzo * (v_sconto / 100));
                    SET v_num_fattura = CONCAT('FA-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', p_codice_visita);
                    
                    INSERT INTO Fatturazione_Con_Assicurazione 
                        (numero_fattura, codice_visita, importo_totale, stato, prezzo_finale, sconto_applicato, codice_assicurazione, codice_voucher)
                    VALUES 
                        (v_num_fattura, p_codice_visita, v_prezzo, 'emessa', v_prezzo_finale, v_sconto, v_cod_assicurazione, p_codice_voucher);
                END IF;
                
                COMMIT;
                
                SELECT 'SUCCESS' AS esito, v_num_fattura AS numero_fattura;
            ELSE
                SELECT 'ERROR' AS esito, 'L\'orario della visita non è ancora passato.' AS messaggio;
            END IF;
        ELSE
            SELECT 'ERROR' AS esito, 'Questa visita non è in stato prenotata.' AS messaggio;
        END IF;
    END IF;
END;

CREATE PROCEDURE `basi_di_dati`.`EmettiReferto`(IN p_codice_visita INT, IN p_testo_referto TEXT)
BEGIN
    DECLARE v_data_visita DATE;
    DECLARE v_ora_inizio TIME;
    DECLARE v_count INT;

    SELECT data_visita, ora_inizio INTO v_data_visita, v_ora_inizio
    FROM Visita WHERE codice = p_codice_visita;

    SELECT COUNT(*) INTO v_count FROM Referto WHERE codice_visita = p_codice_visita;

    IF v_count > 0 THEN
        SELECT 'ERROR' AS esito, 'Esiste già un referto per questa visita.' AS messaggio;
    ELSEIF TIMESTAMP(v_data_visita, v_ora_inizio) <= NOW() THEN
        INSERT INTO Referto (codice_visita, testo_referto) VALUES (p_codice_visita, p_testo_referto);
        
        SELECT 'SUCCESS' AS esito, 'Referto salvato con successo.' AS messaggio;
    ELSE
        SELECT 'ERROR' AS esito, 'Non è possibile refertare una visita futura.' AS messaggio;
    END IF;
END;

CREATE PROCEDURE `basi_di_dati`.`GetAppuntamentiPrenotati`(IN p_id_cliente INT)
BEGIN
    SELECT 
        v.codice, v.data_visita, v.ora_inizio, v.prezzo_applicato, 
        u.nome AS medico_nome, u.cognome AS medico_cognome, u.email AS medico_email,
        s.nome AS spec_nome,
        cm.ragione_sociale, cm.via, cm.civico, cm.cap, cc.comune, cc.provincia
    FROM Visita v
    JOIN Utente u ON v.id_medico = u.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN (
        SELECT id_medico, MIN(partita_iva_centro) as p_iva 
        FROM Medico_Centro 
        GROUP BY id_medico
    ) as mc_single ON mc_single.id_medico = u.id
    LEFT JOIN Centro_Medico cm ON cm.partita_iva = mc_single.p_iva
    LEFT JOIN Citta_CAP cc ON cm.cap = cc.cap
    WHERE v.id_cliente = p_id_cliente 
      AND v.stato = 'prenotata'
    ORDER BY v.data_visita ASC, v.ora_inizio ASC;
END;

CREATE PROCEDURE `basi_di_dati`.`GetProssimiAppuntamentiMedico`(
    IN p_id_medico INT, 
    IN p_limit INT, 
    IN p_offset INT
)
BEGIN
    SELECT 
        v.codice, v.data_visita, v.ora_inizio, v.prezzo_applicato,
        s.nome AS spec_nome,
        u.nome AS paziente_nome, 
        u.cognome AS paziente_cognome, 
        u.codice_fiscale, 
        u.telefono, 
        u.email, 
        u.data_nascita,
        IF(r.id IS NOT NULL, 1, 0) AS ha_referto
    FROM Visita v
    JOIN Utente u ON v.id_cliente = u.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN Referto r ON v.codice = r.codice_visita
    WHERE v.id_medico = p_id_medico 
      AND v.stato = 'prenotata'
    ORDER BY v.data_visita ASC, v.ora_inizio ASC
    LIMIT p_limit OFFSET p_offset;
END;

CREATE PROCEDURE `basi_di_dati`.`GetProssimiAppuntamentiMedico_Count`(
    IN p_id_medico INT
)
BEGIN
    SELECT COUNT(*) AS total
    FROM Visita v
    JOIN Utente u ON v.id_cliente = u.id
    WHERE v.id_medico = p_id_medico 
      AND v.stato = 'prenotata';
END;

CREATE PROCEDURE `basi_di_dati`.`registra_sessione`(
    IN p_id_utente INT, 
    IN p_token_hash VARCHAR(255), 
    IN p_scadenza TIMESTAMP
)
BEGIN
    INSERT INTO Sessione_Utente (id_utente, token_hash, data_scadenza)
    VALUES (p_id_utente, p_token_hash, p_scadenza);
END;

CREATE PROCEDURE `basi_di_dati`.`sp_registra_admin_azienda`(
    IN p_email VARCHAR(100), IN p_password VARCHAR(255),
    IN p_cf VARCHAR(16), IN p_nome VARCHAR(50), IN p_cognome VARCHAR(50),
    IN p_data_nascita DATE, IN p_sesso CHAR(1),
    IN p_piva VARCHAR(11), IN p_ragione VARCHAR(100), IN p_via VARCHAR(100),
    IN p_civico VARCHAR(10), IN p_cap CHAR(5), IN p_comune VARCHAR(100),
    IN p_provincia CHAR(2), IN p_regione VARCHAR(50)
)
BEGIN
    DECLARE v_id_admin INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    INSERT INTO Utente (email, password_hash, ruolo, codice_fiscale, nome, cognome, data_nascita, sesso)
    VALUES (p_email, p_password, 'admin', p_cf, p_nome, p_cognome, p_data_nascita, p_sesso);
    
    SET v_id_admin = LAST_INSERT_ID();

    INSERT IGNORE INTO Citta_CAP (cap, comune, provincia, regione)
    VALUES (p_cap, p_comune, p_provincia, p_regione);

    INSERT INTO Centro_Medico (partita_iva, ragione_sociale, via, civico, cap)
    VALUES (p_piva, p_ragione, p_via, p_civico, p_cap);

    INSERT INTO Admin_Centro (id_admin, partita_iva_centro)
    VALUES (v_id_admin, p_piva);

    COMMIT;
END;

CREATE PROCEDURE `basi_di_dati`.`sp_registra_utente`(
    IN p_email VARCHAR(100), 
    IN p_password VARCHAR(255), 
    IN p_ruolo ENUM('admin','medico','cliente'),
    IN p_cf VARCHAR(16), 
    IN p_nome VARCHAR(50), 
    IN p_cognome VARCHAR(50),
    IN p_data_nascita DATE, 
    IN p_sesso CHAR(1)
)
BEGIN
    INSERT INTO Utente (email, password_hash, ruolo, codice_fiscale, nome, cognome, data_nascita, sesso)
    VALUES (p_email, p_password, p_ruolo, p_cf, p_nome, p_cognome, p_data_nascita, p_sesso);
END;

CREATE PROCEDURE `basi_di_dati`.`valida_sessione`(
    IN p_token_hash VARCHAR(255)
)
BEGIN
    SELECT u.id, u.ruolo, u.nome, u.cognome
    FROM Sessione_Utente su
    JOIN Utente u ON su.id_utente = u.id
    WHERE su.token_hash = p_token_hash 
      AND su.data_scadenza > CURRENT_TIMESTAMP
    LIMIT 1;
END;