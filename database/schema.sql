CREATE TABLE
    Utente (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codice_fiscale VARCHAR(16) NOT NULL,
        nome VARCHAR(50) NOT NULL,
        cognome VARCHAR(50) NOT NULL,
        data_nascita DATE NOT NULL,
        sesso CHAR(1) NOT NULL,
        telefono VARCHAR(20),
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        ruolo ENUM ('admin', 'medico', 'cliente') NOT NULL,
        UNIQUE (codice_fiscale, ruolo)
    ) ENGINE = InnoDB;

CREATE TABLE
    Citta_CAP (
        cap CHAR(5) PRIMARY KEY,
        comune VARCHAR(100) NOT NULL,
        provincia CHAR(2) NOT NULL,
        regione VARCHAR(50) NOT NULL
    ) ENGINE = InnoDB;

CREATE TABLE
    Centro_Medico (
        partita_iva VARCHAR(11) PRIMARY KEY,
        ragione_sociale VARCHAR(100) NOT NULL,
        via VARCHAR(100) NOT NULL,
        civico VARCHAR(10) NOT NULL,
        cap CHAR(5) NOT NULL,
        id_admin_creatore INT NOT NULL,
        FOREIGN KEY (cap) REFERENCES Citta_CAP (cap) ON DELETE RESTRICT,
        FOREIGN KEY (id_admin_creatore) REFERENCES Utente (id) ON DELETE RESTRICT
    ) ENGINE = InnoDB;

CREATE TABLE
    Reparto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        partita_iva_centro VARCHAR(11) NOT NULL,
        FOREIGN KEY (partita_iva_centro) REFERENCES Centro_Medico (partita_iva) ON DELETE CASCADE
    ) ENGINE = InnoDB;

CREATE TABLE
    Specializzazione (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        id_padre INT DEFAULT NULL,
        FOREIGN KEY (id_padre) REFERENCES Specializzazione (id) ON DELETE SET NULL
    ) ENGINE = InnoDB;

CREATE TABLE
    Medico_Centro (
        id_medico INT NOT NULL,
        partita_iva_centro VARCHAR(11) NOT NULL,
        PRIMARY KEY (id_medico, partita_iva_centro),
        FOREIGN KEY (id_medico) REFERENCES Utente (id) ON DELETE CASCADE,
        FOREIGN KEY (partita_iva_centro) REFERENCES Centro_Medico (partita_iva) ON DELETE CASCADE
    ) ENGINE = InnoDB;

CREATE TABLE
    Medico_Specializzazione (
        id_medico INT NOT NULL,
        id_specializzazione INT NOT NULL,
        costo DECIMAL(10, 2) NOT NULL,
        durata_minuti SMALLINT NOT NULL,
        PRIMARY KEY (id_medico, id_specializzazione),
        FOREIGN KEY (id_medico) REFERENCES Utente (id) ON DELETE CASCADE,
        FOREIGN KEY (id_specializzazione) REFERENCES Specializzazione (id) ON DELETE CASCADE
    ) ENGINE = InnoDB;

CREATE TABLE
    Agenda_Presenze (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_medico INT NOT NULL,
        giorno_settimana SMALLINT NOT NULL CHECK (giorno_settimana BETWEEN 1 AND 5),
        ora_inizio TIME NOT NULL,
        ora_fine TIME NOT NULL,
        FOREIGN KEY (id_medico) REFERENCES Utente (id) ON DELETE CASCADE
    ) ENGINE = InnoDB;

CREATE TABLE
    Visita (
        codice INT AUTO_INCREMENT PRIMARY KEY,
        id_cliente INT,
        id_medico INT,
        id_specializzazione INT NOT NULL,
        data_visita DATE NOT NULL,
        ora_inizio TIME NOT NULL,
        prezzo_applicato DECIMAL(10, 2) NOT NULL,
        stato ENUM ('prenotata', 'completata', 'disdetta') DEFAULT 'prenotata' NOT NULL,
        storico_cf_cliente VARCHAR(16),
        storico_nome_cliente VARCHAR(50),
        storico_cognome_cliente VARCHAR(50),
        storico_nome_medico VARCHAR(50),
        storico_cognome_medico VARCHAR(50),
        FOREIGN KEY (id_cliente) REFERENCES Utente (id) ON DELETE SET NULL,
        FOREIGN KEY (id_medico) REFERENCES Utente (id) ON DELETE SET NULL,
        FOREIGN KEY (id_specializzazione) REFERENCES Specializzazione (id) ON DELETE RESTRICT
    ) ENGINE = InnoDB;

CREATE TABLE
    Recensione (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codice_visita INT NOT NULL UNIQUE,
        punteggio SMALLINT NOT NULL CHECK (punteggio BETWEEN 1 AND 5),
        commento TEXT,
        data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (codice_visita) REFERENCES Visita (codice) ON DELETE CASCADE
    ) ENGINE = InnoDB;

CREATE TABLE
    Referto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codice_visita INT NOT NULL UNIQUE,
        testo_referto TEXT NOT NULL,
        data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (codice_visita) REFERENCES Visita (codice) ON DELETE RESTRICT
    ) ENGINE = InnoDB;

CREATE TABLE
    Metodo_Pagamento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        descrizione VARCHAR(50) UNIQUE NOT NULL,
        attivo BOOLEAN DEFAULT TRUE
    ) ENGINE = InnoDB;

CREATE TABLE
    Fattura (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_fattura VARCHAR(20) UNIQUE NOT NULL,
        codice_visita INT NOT NULL UNIQUE,
        id_metodo_pagamento INT,
        data_emissione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        importo_totale DECIMAL(10, 2) NOT NULL,
        stato ENUM ('emessa', 'pagata', 'stornata') DEFAULT 'emessa' NOT NULL,
        FOREIGN KEY (codice_visita) REFERENCES Visita (codice) ON DELETE RESTRICT,
        FOREIGN KEY (id_metodo_pagamento) REFERENCES Metodo_Pagamento (id) ON DELETE SET NULL
    ) ENGINE = InnoDB;

-- Tabella per la gestione della sessione, non fa parte dello "schema" effettivo (da consegna)
CREATE TABLE Sessione_Utente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    token_hash VARCHAR(255) UNIQUE NOT NULL,
    data_scadenza TIMESTAMP NOT NULL,
    FOREIGN KEY (id_utente) REFERENCES Utente(id) ON DELETE CASCADE
) ENGINE=InnoDB;