-- basi_di_dati.Assicurazione definition

CREATE TABLE `Assicurazione` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `codice` varchar(50) NOT NULL,
  `partita_iva` varchar(11) NOT NULL,
  `sconto_percentuale` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codice` (`codice`),
  UNIQUE KEY `partita_iva` (`partita_iva`),
  CONSTRAINT `chk_sconto_percentuale` CHECK (((`sconto_percentuale` >= 0) and (`sconto_percentuale` <= 100)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Citta_CAP definition

CREATE TABLE `Citta_CAP` (
  `cap` char(5) NOT NULL,
  `comune` varchar(100) NOT NULL,
  `provincia` char(2) NOT NULL,
  `regione` varchar(50) NOT NULL,
  PRIMARY KEY (`cap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Metodo_Pagamento definition

CREATE TABLE `Metodo_Pagamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descrizione` varchar(50) NOT NULL,
  `attivo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `descrizione` (`descrizione`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Utente definition

CREATE TABLE `Utente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codice_fiscale` varchar(16) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `cognome` varchar(50) NOT NULL,
  `data_nascita` date NOT NULL,
  `sesso` char(1) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `ruolo` enum('admin','medico','cliente') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `codice_fiscale` (`codice_fiscale`,`ruolo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Agenda_Presenze definition

CREATE TABLE `Agenda_Presenze` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_medico` int NOT NULL,
  `giorno_settimana` smallint NOT NULL,
  `ora_inizio` time NOT NULL,
  `ora_fine` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_medico` (`id_medico`),
  CONSTRAINT `agenda_presenze_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `Utente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agenda_presenze_chk_1` CHECK ((`giorno_settimana` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Centro_Medico definition

CREATE TABLE `Centro_Medico` (
  `partita_iva` varchar(11) NOT NULL,
  `ragione_sociale` varchar(100) NOT NULL,
  `via` varchar(100) NOT NULL,
  `civico` varchar(10) NOT NULL,
  `cap` char(5) NOT NULL,
  `totale_medici` int NOT NULL DEFAULT '0',
  `totale_visite_completate` int NOT NULL DEFAULT '0',
  `totale_incasso` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`partita_iva`),
  KEY `cap` (`cap`),
  CONSTRAINT `centro_medico_ibfk_1` FOREIGN KEY (`cap`) REFERENCES `Citta_CAP` (`cap`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Cliente_Assicurazione definition

CREATE TABLE `Cliente_Assicurazione` (
  `id_cliente` int NOT NULL,
  `id_assicurazione` int NOT NULL,
  PRIMARY KEY (`id_cliente`),
  KEY `id_assicurazione` (`id_assicurazione`),
  CONSTRAINT `cliente_ass_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `Utente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cliente_ass_ibfk_2` FOREIGN KEY (`id_assicurazione`) REFERENCES `Assicurazione` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Medico_Centro definition

CREATE TABLE `Medico_Centro` (
  `id_medico` int NOT NULL,
  `partita_iva_centro` varchar(11) NOT NULL,
  PRIMARY KEY (`id_medico`,`partita_iva_centro`),
  KEY `partita_iva_centro` (`partita_iva_centro`),
  CONSTRAINT `medico_centro_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `Utente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medico_centro_ibfk_2` FOREIGN KEY (`partita_iva_centro`) REFERENCES `Centro_Medico` (`partita_iva`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Sessione_Utente definition

CREATE TABLE `Sessione_Utente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_utente` int NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `data_scadenza` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `id_utente` (`id_utente`),
  CONSTRAINT `sessione_utente_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `Utente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Specializzazione definition

CREATE TABLE `Specializzazione` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `id_padre` int DEFAULT NULL,
  `partita_iva_centro` varchar(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_padre` (`id_padre`),
  KEY `fk_spec_centro` (`partita_iva_centro`),
  CONSTRAINT `fk_spec_centro` FOREIGN KEY (`partita_iva_centro`) REFERENCES `Centro_Medico` (`partita_iva`) ON DELETE CASCADE,
  CONSTRAINT `specializzazione_ibfk_1` FOREIGN KEY (`id_padre`) REFERENCES `Specializzazione` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Statistiche_Medico definition

CREATE TABLE `Statistiche_Medico` (
  `id_medico` int NOT NULL,
  `rating_medio` decimal(3,2) NOT NULL DEFAULT '0.00',
  `numero_recensioni` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_medico`),
  CONSTRAINT `fk_statistiche_medico` FOREIGN KEY (`id_medico`) REFERENCES `Utente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Visita definition

CREATE TABLE `Visita` (
  `codice` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_medico` int DEFAULT NULL,
  `id_specializzazione` int NOT NULL,
  `data_visita` date NOT NULL,
  `ora_inizio` time NOT NULL,
  `prezzo_applicato` decimal(10,2) NOT NULL,
  `stato` enum('prenotata','completata','disdetta') NOT NULL DEFAULT 'prenotata',
  `storico_cf_cliente` varchar(16) DEFAULT NULL,
  `storico_nome_cliente` varchar(50) DEFAULT NULL,
  `storico_cognome_cliente` varchar(50) DEFAULT NULL,
  `storico_nome_medico` varchar(50) DEFAULT NULL,
  `storico_cognome_medico` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`codice`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_medico` (`id_medico`),
  KEY `id_specializzazione` (`id_specializzazione`),
  CONSTRAINT `visita_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `Utente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `visita_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `Utente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `visita_ibfk_3` FOREIGN KEY (`id_specializzazione`) REFERENCES `Specializzazione` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Admin_Centro definition

CREATE TABLE `Admin_Centro` (
  `id_admin` int NOT NULL,
  `partita_iva_centro` varchar(11) NOT NULL,
  PRIMARY KEY (`id_admin`,`partita_iva_centro`),
  KEY `partita_iva_centro` (`partita_iva_centro`),
  CONSTRAINT `admin_centro_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `Utente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_centro_ibfk_2` FOREIGN KEY (`partita_iva_centro`) REFERENCES `Centro_Medico` (`partita_iva`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Fattura definition

CREATE TABLE `Fattura` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_fattura` varchar(20) NOT NULL,
  `codice_visita` int NOT NULL,
  `data_emissione` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `importo_totale` decimal(10,2) NOT NULL,
  `stato` enum('emessa','pagata','stornata') NOT NULL DEFAULT 'emessa',
  `id_metodo_pagamento` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_fattura` (`numero_fattura`),
  UNIQUE KEY `codice_visita` (`codice_visita`),
  CONSTRAINT `fattura_ibfk_1` FOREIGN KEY (`codice_visita`) REFERENCES `Visita` (`codice`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Fatturazione_Con_Assicurazione definition

CREATE TABLE `Fatturazione_Con_Assicurazione` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_fattura` varchar(20) NOT NULL,
  `codice_visita` int NOT NULL,
  `data_emissione` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `importo_totale` decimal(10,2) NOT NULL,
  `stato` enum('emessa','pagata','stornata') NOT NULL DEFAULT 'emessa',
  `prezzo_finale` decimal(10,2) NOT NULL,
  `sconto_applicato` decimal(5,2) NOT NULL DEFAULT '0.00',
  `codice_assicurazione` varchar(50) DEFAULT NULL,
  `codice_voucher` varchar(50) DEFAULT NULL,
  `id_metodo_pagamento` bigint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_fattura` (`numero_fattura`),
  UNIQUE KEY `codice_visita` (`codice_visita`),
  CONSTRAINT `fattura_ass_ibfk_1` FOREIGN KEY (`codice_visita`) REFERENCES `Visita` (`codice`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Medico_Specializzazione definition

CREATE TABLE `Medico_Specializzazione` (
  `id_medico` int NOT NULL,
  `id_specializzazione` int NOT NULL,
  `costo` decimal(10,2) NOT NULL,
  `durata_minuti` smallint NOT NULL,
  PRIMARY KEY (`id_medico`,`id_specializzazione`),
  KEY `id_specializzazione` (`id_specializzazione`),
  CONSTRAINT `medico_specializzazione_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `Utente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medico_specializzazione_ibfk_2` FOREIGN KEY (`id_specializzazione`) REFERENCES `Specializzazione` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Recensione definition

CREATE TABLE `Recensione` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codice_visita` int NOT NULL,
  `punteggio` smallint NOT NULL,
  `commento` text,
  `data_inserimento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codice_visita` (`codice_visita`),
  CONSTRAINT `recensione_ibfk_1` FOREIGN KEY (`codice_visita`) REFERENCES `Visita` (`codice`) ON DELETE CASCADE,
  CONSTRAINT `recensione_chk_1` CHECK ((`punteggio` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- basi_di_dati.Referto definition

CREATE TABLE `Referto` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codice_visita` int NOT NULL,
  `testo_referto` text NOT NULL,
  `data_creazione` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codice_visita` (`codice_visita`),
  CONSTRAINT `referto_ibfk_1` FOREIGN KEY (`codice_visita`) REFERENCES `Visita` (`codice`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;