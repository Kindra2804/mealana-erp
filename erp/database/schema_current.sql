-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mealana_erp
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `aktivitaeten`
--

DROP TABLE IF EXISTS `aktivitaeten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel`
--

DROP TABLE IF EXISTS `artikel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikelnummer` varchar(30) NOT NULL,
  `hersteller_id` int(10) unsigned DEFAULT NULL,
  `steuerklasse_id` int(10) unsigned NOT NULL,
  `artikeltyp_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `kurzbeschreibung` text DEFAULT NULL,
  `beschreibung` longtext DEFAULT NULL,
  `einheit_id` int(10) unsigned NOT NULL,
  `inhalt_menge` decimal(8,3) DEFAULT NULL,
  `inhalt_einheit` varchar(20) DEFAULT NULL,
  `laenge` int(10) unsigned DEFAULT NULL,
  `breite` int(10) unsigned DEFAULT NULL,
  `hoehe` int(10) unsigned DEFAULT NULL,
  `gewicht_artikel` decimal(8,3) DEFAULT NULL,
  `gewicht_versand` decimal(8,3) DEFAULT NULL,
  `versandklasse_id` int(10) unsigned DEFAULT NULL,
  `herkunftsland` char(2) DEFAULT NULL,
  `taric_code` varchar(20) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `varianten_darstellung` varchar(50) NOT NULL DEFAULT 'swatches',
  `grundpreis_bezugsmenge` decimal(8,3) DEFAULT NULL,
  `grundpreis_anzeigen` tinyint(1) DEFAULT 0,
  `ist_vater` tinyint(1) NOT NULL DEFAULT 0,
  `vaterartikel_id` int(10) unsigned DEFAULT NULL,
  `hat_eigenen_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `farbe_name` varchar(100) DEFAULT NULL,
  `farbe_hex` varchar(7) DEFAULT NULL,
  `charge_pflicht` tinyint(1) NOT NULL DEFAULT 0,
  `ist_auslaufartikel` tinyint(1) NOT NULL DEFAULT 0,
  `ueberverkauf_erlaubt` tinyint(1) NOT NULL DEFAULT 0,
  `technische_details` text DEFAULT NULL,
  `beschreibung_intern` text DEFAULT NULL,
  `meta_titel` varchar(70) DEFAULT NULL,
  `meta_description` varchar(160) DEFAULT NULL,
  `url_slug` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `artikelnummer` (`artikelnummer`),
  UNIQUE KEY `uk_artikel_url_slug` (`url_slug`),
  KEY `fk_artikel_hersteller` (`hersteller_id`),
  KEY `fk_artikel_steuerklasse` (`steuerklasse_id`),
  KEY `fk_artikel_artikeltyp` (`artikeltyp_id`),
  KEY `fk_artikel_einheitId` (`einheit_id`),
  KEY `fk_artikel_versandklasse` (`versandklasse_id`),
  KEY `fk_artikel_vater` (`vaterartikel_id`),
  CONSTRAINT `fk_artikel_artikeltyp` FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_einheitId` FOREIGN KEY (`einheit_id`) REFERENCES `einheiten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_vater` FOREIGN KEY (`vaterartikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_versandklasse` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_codes`
--

DROP TABLE IF EXISTS `artikel_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `typ` enum('GTIN13','GTIN8','ITF14','GS1128','ISBN','INTERN') NOT NULL,
  `beschreibung` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_artikel_codes` (`artikel_id`),
  CONSTRAINT `fk_artikel_codes` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_externe_referenzen`
--

DROP TABLE IF EXISTS `artikel_externe_referenzen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_kategorien`
--

DROP TABLE IF EXISTS `artikel_kategorien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_kategorien` (
  `artikel_id` int(10) unsigned NOT NULL,
  `kategorie_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`artikel_id`,`kategorie_id`),
  KEY `fk_ak_kategorie_id` (`kategorie_id`),
  CONSTRAINT `fk_ak_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ak_kategorie_id` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_lieferanten`
--

DROP TABLE IF EXISTS `artikel_lieferanten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_merkmale`
--

DROP TABLE IF EXISTS `artikel_merkmale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_preise`
--

DROP TABLE IF EXISTS `artikel_preise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel_typen`
--

DROP TABLE IF EXISTS `artikel_typen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_typen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hat_varianten` tinyint(1) NOT NULL DEFAULT 1,
  `hat_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `ist_download` tinyint(1) NOT NULL DEFAULT 0,
  `ist_set` tinyint(1) NOT NULL DEFAULT 0,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benutzer`
--

DROP TABLE IF EXISTS `benutzer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benutzer_rollen`
--

DROP TABLE IF EXISTS `benutzer_rollen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `benutzer_rollen` (
  `benutzer_id` int(10) unsigned NOT NULL,
  `rolle_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rolle_id`,`benutzer_id`),
  KEY `fk_benrol_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_benrol_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_benrol_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `berechtigungen`
--

DROP TABLE IF EXISTS `berechtigungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `berechtigungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `einheiten`
--

DROP TABLE IF EXISTS `einheiten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `einheiten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `kuerzel` varchar(10) DEFAULT NULL,
  `sortierung` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hersteller`
--

DROP TABLE IF EXISTS `hersteller`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hersteller` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `webseite` varchar(255) DEFAULT NULL,
  `land` varchar(50) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kategorien`
--

DROP TABLE IF EXISTS `kategorien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kundengruppen`
--

DROP TABLE IF EXISTS `kundengruppen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kundengruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `rabatt_prozent` decimal(4,2) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `typ` enum('endkunde','haendler','vertriebspartner','intern') NOT NULL DEFAULT 'endkunde',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lager`
--

DROP TABLE IF EXISTS `lager`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lager` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `typ` enum('ladengeschaeft','messe','extern','lager') NOT NULL DEFAULT 'ladengeschaeft',
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lager_bewegungen`
--

DROP TABLE IF EXISTS `lager_bewegungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lager_bewegungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lager_id` int(10) unsigned NOT NULL,
  `lieferant_id` int(10) unsigned DEFAULT NULL,
  `ek_preis` decimal(10,4) DEFAULT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `bewegungstyp` enum('eingang','ausgang','korrektur','inventur') DEFAULT NULL,
  `menge` decimal(8,3) NOT NULL,
  `bestand_vorher` decimal(8,3) NOT NULL,
  `bestand_nachher` decimal(8,3) NOT NULL,
  `referenz` varchar(100) DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lager_bewegungen_lager_id` (`lager_id`),
  KEY `fk_lbew_artikel_id` (`artikel_id`),
  KEY `fk_lbew_benutzerId` (`benutzer_id`),
  KEY `fk_lbew_lieferantId` (`lieferant_id`),
  CONSTRAINT `fk_lager_bewegungen_lager_id` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lbew_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lbew_benutzerId` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lbew_lieferantId` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lagerbestand`
--

DROP TABLE IF EXISTS `lagerbestand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerbestand` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lager_id` int(10) unsigned DEFAULT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `charge_status` enum('erfasst','unbekannt','nachzutragen') DEFAULT 'unbekannt',
  `bestand` decimal(8,3) unsigned DEFAULT NULL,
  `mindestbestand` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `artikel_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lb_artikel_lager_charge` (`artikel_id`,`lager_id`,`charge`),
  KEY `fk_lager_id` (`lager_id`),
  CONSTRAINT `fk_lager_id` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lb_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lieferanten`
--

DROP TABLE IF EXISTS `lieferanten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lieferanten_vertreter`
--

DROP TABLE IF EXISTS `lieferanten_vertreter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `merkmal_gruppen`
--

DROP TABLE IF EXISTS `merkmal_gruppen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merkmal_gruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `merkmale`
--

DROP TABLE IF EXISTS `merkmale`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rollen`
--

DROP TABLE IF EXISTS `rollen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rollen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rollen_berechtigungen`
--

DROP TABLE IF EXISTS `rollen_berechtigungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rollen_berechtigungen` (
  `rolle_id` int(10) unsigned NOT NULL,
  `berechtigung_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rolle_id`,`berechtigung_id`),
  KEY `fk_rollber_berechtigung` (`berechtigung_id`),
  CONSTRAINT `fk_rollber_berechtigung` FOREIGN KEY (`berechtigung_id`) REFERENCES `berechtigungen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rollber_rolle` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `steuerklassen`
--

DROP TABLE IF EXISTS `steuerklassen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `steuerklassen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `satz` decimal(5,2) NOT NULL,
  `land` char(2) NOT NULL DEFAULT 'AT',
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `versandklassen`
--

DROP TABLE IF EXISTS `versandklassen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `versandklassen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `kuerzel` varchar(10) DEFAULT NULL,
  `sortierung` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'mealana_erp'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-09 21:48:47
