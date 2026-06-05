-- MeaLana ERP Schema Dump
-- Stand: 2026-06-05
-- Generiert aus: mealana_erp (inkl. Migrations 001-007)

-- Table: aktivitaeten
CREATE TABLE `aktivitaeten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `benutzer_id` int(10) unsigned NOT NULL,
  `aktion` varchar(255) NOT NULL,
  `referenz_tabelle` varchar(50) DEFAULT NULL,
  `referenz_id` int(10) unsigned DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aktivitaeten_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_aktivitaeten_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel
CREATE TABLE `artikel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikelnummer` varchar(30) NOT NULL,
  `hersteller_id` int(10) unsigned DEFAULT NULL,
  `steuerklasse_id` int(10) unsigned NOT NULL,
  `artikeltyp` enum('GARN','NADEL','METERWARE','DOWNLOAD','SET','STANDARD') NOT NULL,
  `name` varchar(255) NOT NULL,
  `beschreibung_kurz` varchar(255) DEFAULT NULL,
  `beschreibung_lang` text DEFAULT NULL,
  `einheit` enum('Knäuel','Meter','Gramm','Stk','Set') NOT NULL,
  `inhalt_menge` decimal(8,3) DEFAULT NULL,
  `inhalt_einheit` varchar(20) DEFAULT NULL,
  `gewicht_artikel` decimal(8,3) DEFAULT NULL,
  `gewicht_versand` decimal(8,3) DEFAULT NULL,
  `herkunftsland` char(2) DEFAULT NULL,
  `taric_code` varchar(20) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `varianten_darstellung` enum('swatches','bilder','dropdown') NOT NULL DEFAULT 'swatches',
  `grundpreis_bezugsmenge` decimal(8,3) DEFAULT NULL,
  `grundpreis_anzeigen` tinyint(1) DEFAULT 0,
  `ist_vater` tinyint(1) NOT NULL DEFAULT 0,
  `charge_pflicht` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `artikelnummer` (`artikelnummer`),
  KEY `fk_artikel_hersteller` (`hersteller_id`),
  KEY `fk_artikel_steuerklasse` (`steuerklasse_id`),
  CONSTRAINT `fk_artikel_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_codes
CREATE TABLE `artikel_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `typ` enum('GTIN13','GTIN8','ITF14','GS1128','ISBN','INTERN') NOT NULL,
  `beschreibung` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_artikel_codes` (`artikel_id`),
  CONSTRAINT `fk_artikel_codes` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_externe_referenzen
CREATE TABLE `artikel_externe_referenzen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `datenquelle` varchar(50) NOT NULL,
  `externe_id` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_datenquelle_externe_id` (`datenquelle`,`externe_id`),
  KEY `fk_aer_artikel_id` (`artikel_id`),
  CONSTRAINT `fk_aer_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_kategorien
CREATE TABLE `artikel_kategorien` (
  `artikel_id` int(10) unsigned NOT NULL,
  `kategorie_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`artikel_id`,`kategorie_id`),
  KEY `fk_ak_kategorie_id` (`kategorie_id`),
  CONSTRAINT `fk_ak_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ak_kategorie_id` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_lieferanten
CREATE TABLE `artikel_lieferanten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `lieferant_id` int(10) unsigned DEFAULT NULL,
  `artikelnummer_lieferant` varchar(255) DEFAULT NULL,
  `netto_ek` decimal(8,2) DEFAULT NULL,
  `waehrung` char(3) DEFAULT NULL,
  `vpe_menge` int(10) unsigned DEFAULT NULL,
  `vpe_ean` char(13) DEFAULT NULL,
  `lieferzeit_tage` int(10) unsigned DEFAULT NULL,
  `mindestabnahme` decimal(6,2) DEFAULT NULL,
  `standard_lieferant` tinyint(1) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_artlief_artikel_id` (`artikel_id`),
  KEY `fk_artlief_lieferant_id` (`lieferant_id`),
  CONSTRAINT `fk_artlief_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artlief_lieferant_id` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_merkmale
CREATE TABLE `artikel_merkmale` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `merkmal_id` int(10) unsigned DEFAULT NULL,
  `wert_text` varchar(255) DEFAULT NULL,
  `wert_zahl` decimal(8,2) DEFAULT NULL,
  `wert_bool` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_artikel_id` (`artikel_id`),
  KEY `fk_merkmal_id` (`merkmal_id`),
  CONSTRAINT `fk_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_merkmal_id` FOREIGN KEY (`merkmal_id`) REFERENCES `merkmale` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_preise
CREATE TABLE `artikel_preise` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `kundengruppen_id` int(10) unsigned NOT NULL,
  `brutto_vk` decimal(8,2) NOT NULL,
  `netto_vk` decimal(8,2) NOT NULL,
  `gueltig_ab` datetime DEFAULT NULL,
  `gueltig_bis` datetime DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_preise_artikel` (`artikel_id`),
  KEY `fk_kundengruppen` (`kundengruppen_id`),
  CONSTRAINT `fk_kundengruppen` FOREIGN KEY (`kundengruppen_id`) REFERENCES `kundengruppen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_preise_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: artikel_varianten
CREATE TABLE `artikel_varianten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `artikelnummer` varchar(20) NOT NULL,
  `gtin` char(13) DEFAULT NULL,
  `farbe_name` varchar(50) DEFAULT NULL,
  `farbe_hex` varchar(7) DEFAULT NULL,
  `bild_url` text DEFAULT NULL,
  `brutto_vk` decimal(6,2) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_varianten_artikel` (`artikel_id`),
  CONSTRAINT `fk_varianten_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: benutzer
CREATE TABLE `benutzer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `passwort` varchar(255) NOT NULL,
  `vorname` varchar(255) DEFAULT NULL,
  `nachname` varchar(255) DEFAULT NULL,
  `formularname` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: benutzer_rollen
CREATE TABLE `benutzer_rollen` (
  `benutzer_id` int(10) unsigned NOT NULL,
  `rolle_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rolle_id`,`benutzer_id`),
  KEY `fk_benrol_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_benrol_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_benrol_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: berechtigungen
CREATE TABLE `berechtigungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: hersteller
CREATE TABLE `hersteller` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `webseite` varchar(255) DEFAULT NULL,
  `land` varchar(50) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: kategorien
CREATE TABLE `kategorien` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `externe_id` varchar(100) DEFAULT NULL,
  `datenquelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kat_parent_id` (`parent_id`),
  CONSTRAINT `fk_kat_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: kundengruppen
CREATE TABLE `kundengruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rabatt_prozent` decimal(4,2) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `typ` enum('endkunde','haendler','vertriebspartner','intern') NOT NULL DEFAULT 'endkunde',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lager
CREATE TABLE `lager` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `typ` enum('ladengeschaeft','messe','extern','lager') NOT NULL DEFAULT 'ladengeschaeft',
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lager_bewegungen
CREATE TABLE `lager_bewegungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_varianten_id` int(10) unsigned DEFAULT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `bewegungstyp` enum('eingang','ausgang','korrektur','inventur') DEFAULT NULL,
  `menge` decimal(8,3) NOT NULL,
  `bestand_vorher` decimal(8,3) NOT NULL,
  `bestand_nachher` decimal(8,3) NOT NULL,
  `referenz` varchar(100) DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `artikel_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lager_bewegungen_artikel_varianten_id` (`artikel_varianten_id`),
  KEY `fk_lager_bewegungen_lager_id` (`lager_id`),
  KEY `fk_lbew_artikel_id` (`artikel_id`),
  CONSTRAINT `fk_lager_bewegungen_artikel_varianten_id` FOREIGN KEY (`artikel_varianten_id`) REFERENCES `artikel_varianten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lager_bewegungen_lager_id` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lbew_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_lbew_genau_eine_referenz` CHECK (`artikel_varianten_id` is not null and `artikel_id` is null or `artikel_varianten_id` is null and `artikel_id` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lagerbestand
CREATE TABLE `lagerbestand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_varianten_id` int(10) unsigned DEFAULT NULL,
  `lager_id` int(10) unsigned DEFAULT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `charge_status` enum('erfasst','unbekannt','nachzutragen') DEFAULT 'unbekannt',
  `bestand` decimal(8,3) unsigned DEFAULT NULL,
  `mindestbestand` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `artikel_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_variante_lager_charge` (`artikel_varianten_id`,`lager_id`,`charge`),
  UNIQUE KEY `uq_lb_artikel_lager_charge` (`artikel_id`,`lager_id`,`charge`),
  KEY `fk_lager_id` (`lager_id`),
  CONSTRAINT `fk_artikel_varianten` FOREIGN KEY (`artikel_varianten_id`) REFERENCES `artikel_varianten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lager_id` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lb_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_lb_genau_eine_referenz` CHECK (`artikel_varianten_id` is not null and `artikel_id` is null or `artikel_varianten_id` is null and `artikel_id` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lieferanten
CREATE TABLE `lieferanten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `land` char(2) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefon` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lieferanten_vertreter
CREATE TABLE `lieferanten_vertreter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
  `vorname` varchar(255) DEFAULT NULL,
  `nachname` varchar(255) DEFAULT NULL,
  `telefon` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobil` varchar(255) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vertreter_lieferant_id` (`lieferant_id`),
  CONSTRAINT `fk_vertreter_lieferant_id` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: merkmal_gruppen
CREATE TABLE `merkmal_gruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: merkmale
CREATE TABLE `merkmale` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merkmal_gruppen_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `einheit` varchar(50) NOT NULL,
  `datentyp` enum('text','zahl','bool') DEFAULT NULL,
  `filterbar` tinyint(1) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_merkmal_gruppen_id` (`merkmal_gruppen_id`),
  CONSTRAINT `fk_merkmal_gruppen_id` FOREIGN KEY (`merkmal_gruppen_id`) REFERENCES `merkmal_gruppen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: rollen
CREATE TABLE `rollen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: rollen_berechtigungen
CREATE TABLE `rollen_berechtigungen` (
  `rolle_id` int(10) unsigned NOT NULL,
  `berechtigung_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rolle_id`,`berechtigung_id`),
  KEY `fk_rollber_berechtigung` (`berechtigung_id`),
  CONSTRAINT `fk_rollber_berechtigung` FOREIGN KEY (`berechtigung_id`) REFERENCES `berechtigungen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rollber_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: sessions
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `letzte_aktivitaet` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sessions_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_sessions_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: steuerklassen
CREATE TABLE `steuerklassen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `satz` decimal(5,2) NOT NULL,
  `land` char(2) NOT NULL DEFAULT 'AT',
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

