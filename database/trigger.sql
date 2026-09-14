CREATE DEFINER=`root`@`localhost` TRIGGER `Trg_AggiornaIncasso_Fattura` AFTER INSERT ON `fattura` FOR EACH ROW BEGIN
    DECLARE v_piva_centro VARCHAR(11);

    -- Risalgo al centro medico partendo dalla visita fatturata
    SELECT s.partita_iva_centro INTO v_piva_centro
    FROM Visita v
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    WHERE v.codice = NEW.codice_visita;

    -- Aggiorno i contatori globali del centro
    UPDATE Centro_Medico
    SET 
        totale_visite_completate = totale_visite_completate + 1,
        totale_incasso = totale_incasso + NEW.importo_totale
    WHERE partita_iva = v_piva_centro;
END;

CREATE DEFINER=`root`@`localhost` TRIGGER `Trg_AggiornaIncasso_FatturaAssicurazione` AFTER INSERT ON `fatturazione_con_assicurazione` FOR EACH ROW BEGIN
    DECLARE v_piva_centro VARCHAR(11);

    SELECT s.partita_iva_centro INTO v_piva_centro
    FROM Visita v
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    WHERE v.codice = NEW.codice_visita;

    UPDATE Centro_Medico
    SET 
        totale_visite_completate = totale_visite_completate + 1,
        -- Uso il prezzo finale scontato come reale incasso del centro
        totale_incasso = totale_incasso + NEW.prezzo_finale
    WHERE partita_iva = v_piva_centro;
END;

CREATE DEFINER=`root`@`localhost` TRIGGER `Trg_AggiornaStatisticheMedico_AfterInsert` AFTER INSERT ON `recensione` FOR EACH ROW BEGIN
    DECLARE v_id_medico INT;
    
    -- Trovo il medico che ha erogato la visita recensita
    SELECT id_medico INTO v_id_medico
    FROM Visita
    WHERE codice = NEW.codice_visita;
    
    -- Inserisco la statistica o aggiorno matematicamente la media se esiste già
    INSERT INTO Statistiche_Medico (id_medico, rating_medio, numero_recensioni)
    VALUES (v_id_medico, NEW.punteggio, 1)
    ON DUPLICATE KEY UPDATE
        rating_medio = ((rating_medio * numero_recensioni) + NEW.punteggio) / (numero_recensioni + 1),
        numero_recensioni = numero_recensioni + 1;
END;

CREATE DEFINER=`root`@`localhost` TRIGGER `Trg_AggiungiMedicoCentro` AFTER INSERT ON `medico_centro` FOR EACH ROW BEGIN
    UPDATE Centro_Medico 
    SET totale_medici = totale_medici + 1 
    WHERE partita_iva = NEW.partita_iva_centro;
END;

CREATE DEFINER=`root`@`localhost` TRIGGER `Trg_RimuoviMedicoCentro` AFTER DELETE ON `medico_centro` FOR EACH ROW BEGIN
    UPDATE Centro_Medico 
    SET totale_medici = totale_medici - 1 
    WHERE partita_iva = OLD.partita_iva_centro;
END;