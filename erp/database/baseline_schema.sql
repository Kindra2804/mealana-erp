-- baseline_schema.sql — NAHTLOS 1.0: Struktur + universelle Stammdaten fuer Neuinstallationen
-- Neu erzeugt: 2026-11-04, Stand nach Migration 222 (alle Migrationen 004–222 sind hier enthalten,
-- die Dateien wurden entfernt; neue Migrationen beginnen bei 223).
-- Enthaelt: alle Tabellenstrukturen PLUS Referenztabellen (rollen, berechtigungen, rollen_berechtigungen,
-- artikel_typen, artikel_gruppen, einheiten, steuerklassen, steuerklassen_konten, kontenplan,
-- zahlungsart_konten, laender, zahlungsbedingungen, versandklassen, kundengruppen, dokument_nummern,
-- system_einstellungen) PLUS fixe Einzel-Seeds (Jarvis-User, Diverses-Artikel 99-9999, Fachmiete,
-- Laufkunde, Hauptkanal-Shop).
-- KEINE Geschaefts-/Demodaten (Artikel, Kunden, Auftraege, Kategorien, Hersteller...).
-- Datenbank vorher mit CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci anlegen.

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: (Baseline 1.0)
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
-- Table structure for table `abholorte`
--

DROP TABLE IF EXISTS `abholorte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `abholorte` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `strasse` varchar(150) NOT NULL,
  `plz` varchar(10) NOT NULL,
  `ort` varchar(100) NOT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `oeffnungszeiten` text DEFAULT NULL,
  `hinweis` varchar(500) DEFAULT NULL,
  `ist_standard` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `abholorte`
--

LOCK TABLES `abholorte` WRITE;
/*!40000 ALTER TABLE `abholorte` DISABLE KEYS */;
/*!40000 ALTER TABLE `abholorte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aktionen`
--

DROP TABLE IF EXISTS `aktionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aktionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `gestartet` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aktionen`
--

LOCK TABLES `aktionen` WRITE;
/*!40000 ALTER TABLE `aktionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `aktionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aktionen_artikel_preise`
--

DROP TABLE IF EXISTS `aktionen_artikel_preise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aktionen_artikel_preise` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aktion_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `sub_achse_id` int(10) unsigned DEFAULT NULL,
  `kundengruppen_id` int(10) unsigned NOT NULL,
  `brutto_vk` decimal(8,2) NOT NULL,
  `netto_vk` decimal(8,2) NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aktion_artikel_achse_kg` (`aktion_id`,`artikel_id`,`sub_achse_id`,`kundengruppen_id`),
  KEY `fk_aktArtPr_artikel_id` (`artikel_id`),
  KEY `fk_aktArtPr_achse_id` (`sub_achse_id`),
  KEY `fk_aktArtPr_kg_id` (`kundengruppen_id`),
  CONSTRAINT `fk_aktArtPr_achse_id` FOREIGN KEY (`sub_achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_aktArtPr_aktion_id` FOREIGN KEY (`aktion_id`) REFERENCES `aktionen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_aktArtPr_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_aktArtPr_kg_id` FOREIGN KEY (`kundengruppen_id`) REFERENCES `kundengruppen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aktionen_artikel_preise`
--

LOCK TABLES `aktionen_artikel_preise` WRITE;
/*!40000 ALTER TABLE `aktionen_artikel_preise` DISABLE KEYS */;
/*!40000 ALTER TABLE `aktionen_artikel_preise` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aktionen_kategorien`
--

DROP TABLE IF EXISTS `aktionen_kategorien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aktionen_kategorien` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aktion_id` int(10) unsigned NOT NULL,
  `kategorie_id` int(10) unsigned NOT NULL,
  `gueltig_ab` date NOT NULL,
  `gueltig_bis` date NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aktion_kategorie` (`aktion_id`,`kategorie_id`),
  KEY `fk_aktKat_kat_id` (`kategorie_id`),
  CONSTRAINT `fk_aktKat_aktion_id` FOREIGN KEY (`aktion_id`) REFERENCES `aktionen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_aktKat_kat_id` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aktionen_kategorien`
--

LOCK TABLES `aktionen_kategorien` WRITE;
/*!40000 ALTER TABLE `aktionen_kategorien` DISABLE KEYS */;
/*!40000 ALTER TABLE `aktionen_kategorien` ENABLE KEYS */;
UNLOCK TABLES;

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
  `stufe` enum('info','warn','error') NOT NULL DEFAULT 'info',
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_aktivitaeten_benutzer` (`benutzer_id`),
  KEY `idx_aktivitaeten_erstellt_am` (`erstellt_am`),
  CONSTRAINT `fk_aktivitaeten_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aktivitaeten`
--

LOCK TABLES `aktivitaeten` WRITE;
/*!40000 ALTER TABLE `aktivitaeten` DISABLE KEYS */;
/*!40000 ALTER TABLE `aktivitaeten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `arbeitsplaetze`
--

DROP TABLE IF EXISTS `arbeitsplaetze`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arbeitsplaetze` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `geraete_token` varchar(36) NOT NULL,
  `typ` enum('kasse','lager','buero','mobil') NOT NULL,
  `kasse_id` int(10) unsigned DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `geraete_token` (`geraete_token`),
  KEY `fk_arbeitsplatz_kasse` (`kasse_id`),
  CONSTRAINT `fk_arbeitsplatz_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arbeitsplaetze`
--

LOCK TABLES `arbeitsplaetze` WRITE;
/*!40000 ALTER TABLE `arbeitsplaetze` DISABLE KEYS */;
/*!40000 ALTER TABLE `arbeitsplaetze` ENABLE KEYS */;
UNLOCK TABLES;

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
  `partner_id` int(10) unsigned DEFAULT NULL,
  `partner_modus` enum('eigen','kommission','spende','fremdrechnung') NOT NULL DEFAULT 'eigen',
  `steuerklasse_id` int(10) unsigned NOT NULL,
  `artikel_gruppe_id` int(10) unsigned DEFAULT NULL,
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
  `deaktiviert_mit_vater` tinyint(1) NOT NULL DEFAULT 0,
  `zustand` varchar(30) NOT NULL DEFAULT 'neu',
  `zustand_vater_id` int(10) unsigned DEFAULT NULL,
  `uvp` decimal(8,2) DEFAULT NULL,
  `ist_hervorgehoben` tinyint(1) NOT NULL DEFAULT 0,
  `download_dateiname` varchar(255) DEFAULT NULL,
  `download_limit` int(11) DEFAULT NULL,
  `preise_vererben` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `grundpreis_bezugsmenge` decimal(8,3) DEFAULT NULL,
  `grundpreis_anzeigen` tinyint(1) DEFAULT 0,
  `mindestabnahme_modus` enum('erbt_typ','eigene_werte','deaktiviert') NOT NULL DEFAULT 'erbt_typ',
  `mindestabnahme` decimal(10,3) DEFAULT NULL,
  `abnahmeintervall` decimal(10,3) DEFAULT NULL,
  `ist_vater` tinyint(1) NOT NULL DEFAULT 0,
  `vaterartikel_id` int(10) unsigned DEFAULT NULL,
  `hat_eigenen_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `ist_konfigurierbar` tinyint(1) NOT NULL DEFAULT 0,
  `keine_lagerbestandsfuehrung` tinyint(1) NOT NULL DEFAULT 0,
  `lizenz_modul` varchar(20) DEFAULT NULL,
  `ist_gutschein` tinyint(1) NOT NULL DEFAULT 0,
  `charge_pflicht` tinyint(1) NOT NULL DEFAULT 0,
  `ist_auslaufartikel` tinyint(1) NOT NULL DEFAULT 0,
  `auslauf_mit_vater` tinyint(1) NOT NULL DEFAULT 0,
  `ueberverkauf_erlaubt` tinyint(1) NOT NULL DEFAULT 0,
  `technische_details` text DEFAULT NULL,
  `beschreibung_intern` text DEFAULT NULL,
  `meta_titel` varchar(70) DEFAULT NULL,
  `meta_description` varchar(160) DEFAULT NULL,
  `url_slug` varchar(255) DEFAULT NULL,
  `meldebestand` int(10) unsigned DEFAULT NULL COMMENT 'Auslöser Bestellvorschlag — bei Unterschreitung Infobox im Bestellwesen',
  `letzte_inventur_am` date DEFAULT NULL,
  `sicherheitsbestand` int(10) unsigned DEFAULT NULL COMMENT 'Puffer der nie unterschritten werden soll',
  `standardbestellmenge` int(10) unsigned DEFAULT NULL COMMENT 'Vorschlagsmenge beim manuellen Bestellvorschlag',
  `lieferzeit_text` varchar(100) DEFAULT NULL,
  `stammplatz_id` int(10) unsigned DEFAULT NULL,
  `nachfuellplatz_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `artikelnummer` (`artikelnummer`),
  UNIQUE KEY `uk_artikel_url_slug` (`url_slug`),
  KEY `fk_artikel_hersteller` (`hersteller_id`),
  KEY `fk_artikel_steuerklasse` (`steuerklasse_id`),
  KEY `fk_artikel_artikeltyp` (`artikeltyp_id`),
  KEY `fk_artikel_einheitId` (`einheit_id`),
  KEY `fk_artikel_versandklasse` (`versandklasse_id`),
  KEY `fk_artikel_vater` (`vaterartikel_id`),
  KEY `idx_zustand_vater` (`zustand_vater_id`),
  KEY `idx_art_partner` (`partner_id`),
  KEY `idx_art_pmodus` (`partner_modus`),
  KEY `fk_art_gruppe` (`artikel_gruppe_id`),
  KEY `fk_artikel_stammplatz` (`stammplatz_id`),
  KEY `fk_artikel_nachfuellplatz` (`nachfuellplatz_id`),
  CONSTRAINT `fk_art_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_art_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_artikel_artikeltyp` FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_einheitId` FOREIGN KEY (`einheit_id`) REFERENCES `einheiten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_nachfuellplatz` FOREIGN KEY (`nachfuellplatz_id`) REFERENCES `lagerplaetze` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_artikel_stammplatz` FOREIGN KEY (`stammplatz_id`) REFERENCES `lagerplaetze` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_artikel_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_vater` FOREIGN KEY (`vaterartikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_versandklasse` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_zustand_vater` FOREIGN KEY (`zustand_vater_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel`
--

LOCK TABLES `artikel` WRITE;
/*!40000 ALTER TABLE `artikel` DISABLE KEYS */;
INSERT INTO `artikel` VALUES (1,'99-9999',NULL,NULL,'eigen',1,NULL,6,'Diverses (Kasse)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,NULL,NULL,0,'2026-11-04 09:23:21','2026-11-04 09:23:22','2026-11-04 09:23:21',NULL,0,'erbt_typ',NULL,NULL,0,NULL,0,0,0,NULL,0,0,0,0,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'FACHMIETE',NULL,NULL,'eigen',1,5,6,'Fachmiete',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,NULL,NULL,0,'2026-11-04 09:23:26','2026-11-04 09:23:26','2026-11-04 09:23:26',NULL,0,'erbt_typ',NULL,NULL,0,NULL,1,0,1,NULL,0,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `artikel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_achsen`
--

DROP TABLE IF EXISTS `artikel_achsen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_achsen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `achse_id` int(10) unsigned NOT NULL,
  `bedingungs_achse_id` int(10) unsigned DEFAULT NULL,
  `bedingungs_wert_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `preis_modus` enum('aufpreis','direktpreis') NOT NULL DEFAULT 'direktpreis',
  `preis_wert` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_artAchs_artikel_id` (`artikel_id`),
  KEY `fk_artAchs_achse_id` (`achse_id`),
  KEY `fk_artAchs_bedingungs_achse_id` (`bedingungs_achse_id`),
  KEY `fk_artAchs_bedingungs_wert_id` (`bedingungs_wert_id`),
  CONSTRAINT `fk_artAchs_achse_id` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_bedingungs_achse_id` FOREIGN KEY (`bedingungs_achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_bedingungs_wert_id` FOREIGN KEY (`bedingungs_wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_achsen`
--

LOCK TABLES `artikel_achsen` WRITE;
/*!40000 ALTER TABLE `artikel_achsen` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_achsen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_bilder`
--

DROP TABLE IF EXISTS `artikel_bilder`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_bilder` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `dateiname` varchar(255) NOT NULL,
  `alt_text` varchar(255) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_artbild_artikel` (`artikel_id`),
  CONSTRAINT `fk_artbild_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_bilder`
--

LOCK TABLES `artikel_bilder` WRITE;
/*!40000 ALTER TABLE `artikel_bilder` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_bilder` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_bilder_shops`
--

DROP TABLE IF EXISTS `artikel_bilder_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_bilder_shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bild_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `sync_status` enum('pending','synced','error') NOT NULL DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `fehler_meldung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bild_shop` (`bild_id`,`shop_id`),
  CONSTRAINT `fk_bildsync_bild` FOREIGN KEY (`bild_id`) REFERENCES `artikel_bilder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_bilder_shops`
--

LOCK TABLES `artikel_bilder_shops` WRITE;
/*!40000 ALTER TABLE `artikel_bilder_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_bilder_shops` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_codes`
--

LOCK TABLES `artikel_codes` WRITE;
/*!40000 ALTER TABLE `artikel_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_downloads_shops`
--

DROP TABLE IF EXISTS `artikel_downloads_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_downloads_shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `dateiname_synced` varchar(255) DEFAULT NULL,
  `external_media_id` varchar(255) DEFAULT NULL,
  `external_media_url` varchar(500) DEFAULT NULL,
  `sync_status` enum('pending','synced','error') NOT NULL DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `fehler_meldung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_download_shop` (`artikel_id`,`shop_id`),
  CONSTRAINT `fk_downloadshop_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_downloads_shops`
--

LOCK TABLES `artikel_downloads_shops` WRITE;
/*!40000 ALTER TABLE `artikel_downloads_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_downloads_shops` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `artikel_externe_referenzen`
--

LOCK TABLES `artikel_externe_referenzen` WRITE;
/*!40000 ALTER TABLE `artikel_externe_referenzen` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_externe_referenzen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_gruppen`
--

DROP TABLE IF EXISTS `artikel_gruppen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_gruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `konto_nr` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `an_kasse_waehlbar` tinyint(1) NOT NULL DEFAULT 1,
  `standard_steuer_prozent` decimal(5,2) DEFAULT NULL,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_konto_nr` (`konto_nr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_gruppen`
--

LOCK TABLES `artikel_gruppen` WRITE;
/*!40000 ALTER TABLE `artikel_gruppen` DISABLE KEYS */;
INSERT INTO `artikel_gruppen` VALUES (1,'4065','Messe',1,1,NULL,65),(2,'4070','Workshops',1,1,NULL,70),(3,'PARTNER','Partnerware (Kommission/Fremd)',1,0,NULL,99),(4,'4890','Mahngebühren',1,0,NULL,98),(5,'4800','Fachmiete',1,0,NULL,97);
/*!40000 ALTER TABLE `artikel_gruppen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_kategorie_shop_ausschluss`
--

DROP TABLE IF EXISTS `artikel_kategorie_shop_ausschluss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_kategorie_shop_ausschluss` (
  `artikel_id` int(10) unsigned NOT NULL,
  `kategorie_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`artikel_id`,`kategorie_id`,`shop_id`),
  KEY `fk_akshopaus_kategorie` (`kategorie_id`),
  KEY `fk_akshopaus_shop` (`shop_id`),
  CONSTRAINT `fk_akshopaus_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_akshopaus_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_akshopaus_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_kategorie_shop_ausschluss`
--

LOCK TABLES `artikel_kategorie_shop_ausschluss` WRITE;
/*!40000 ALTER TABLE `artikel_kategorie_shop_ausschluss` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_kategorie_shop_ausschluss` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `artikel_kategorien`
--

LOCK TABLES `artikel_kategorien` WRITE;
/*!40000 ALTER TABLE `artikel_kategorien` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_kategorien` ENABLE KEYS */;
UNLOCK TABLES;

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
  `brutto_ek` decimal(8,2) DEFAULT NULL,
  `waehrung` char(3) DEFAULT NULL,
  `vpe_menge` int(10) unsigned DEFAULT NULL,
  `vpe_ean` char(13) DEFAULT NULL,
  `lieferzeit_tage` int(10) unsigned DEFAULT NULL,
  `mindestabnahme` decimal(6,2) DEFAULT NULL,
  `standard_lieferant` tinyint(1) DEFAULT NULL,
  `dropshipping` tinyint(1) DEFAULT NULL COMMENT 'NULL = wie Lieferant, 0 = ausgenommen, 1 = ja',
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_artlief_artikel_id` (`artikel_id`),
  KEY `fk_artlief_lieferant_id` (`lieferant_id`),
  CONSTRAINT `fk_artlief_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artlief_lieferant_id` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_lieferanten`
--

LOCK TABLES `artikel_lieferanten` WRITE;
/*!40000 ALTER TABLE `artikel_lieferanten` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_lieferanten` ENABLE KEYS */;
UNLOCK TABLES;

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
  `merkmal_wert_id` int(10) unsigned NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artikel_merkmal_wert` (`artikel_id`,`merkmal_wert_id`),
  KEY `fk_merkmal_id` (`merkmal_id`),
  KEY `fk_am_wert` (`merkmal_wert_id`),
  CONSTRAINT `fk_am_wert` FOREIGN KEY (`merkmal_wert_id`) REFERENCES `merkmal_werte` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_merkmal_id` FOREIGN KEY (`merkmal_id`) REFERENCES `merkmale` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_merkmale`
--

LOCK TABLES `artikel_merkmale` WRITE;
/*!40000 ALTER TABLE `artikel_merkmale` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_merkmale` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_preise`
--

LOCK TABLES `artikel_preise` WRITE;
/*!40000 ALTER TABLE `artikel_preise` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_preise` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_shops`
--

DROP TABLE IF EXISTS `artikel_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `external_id` varchar(255) DEFAULT NULL,
  `sync_status` enum('pending','synced','error') NOT NULL DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `fehler_meldung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_artikel_shop` (`artikel_id`,`shop_id`),
  KEY `idx_artshop_sync_status` (`sync_status`),
  KEY `fk_artshop_shop` (`shop_id`),
  CONSTRAINT `fk_artshop_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_artshop_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_shops`
--

LOCK TABLES `artikel_shops` WRITE;
/*!40000 ALTER TABLE `artikel_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `artikel_staffelpreise`
--

DROP TABLE IF EXISTS `artikel_staffelpreise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `artikel_staffelpreise` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `kundengruppen_id` int(10) unsigned NOT NULL,
  `menge_ab` decimal(8,3) NOT NULL,
  `brutto_vk` decimal(8,2) NOT NULL,
  `netto_vk` decimal(8,2) NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_artStaff_artikel_id` (`artikel_id`),
  KEY `fk_artStaff_kundengruppen_id` (`kundengruppen_id`),
  CONSTRAINT `fk_artStaff_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artStaff_kundengruppen_id` FOREIGN KEY (`kundengruppen_id`) REFERENCES `kundengruppen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_staffelpreise`
--

LOCK TABLES `artikel_staffelpreise` WRITE;
/*!40000 ALTER TABLE `artikel_staffelpreise` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_staffelpreise` ENABLE KEYS */;
UNLOCK TABLES;

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
  `teilbar` tinyint(1) NOT NULL DEFAULT 0,
  `mindestabnahme_default` decimal(10,3) DEFAULT NULL,
  `abnahmeintervall_default` decimal(10,3) DEFAULT NULL,
  `hat_varianten` tinyint(1) NOT NULL DEFAULT 1,
  `hat_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `ist_download` tinyint(1) NOT NULL DEFAULT 0,
  `ist_set` tinyint(1) NOT NULL DEFAULT 0,
  `ist_gutschein` tinyint(1) NOT NULL DEFAULT 0,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `artikel_typen`
--

LOCK TABLES `artikel_typen` WRITE;
/*!40000 ALTER TABLE `artikel_typen` DISABLE KEYS */;
INSERT INTO `artikel_typen` VALUES (1,'GARN','Garn',0,NULL,NULL,1,1,0,0,0,1,1),(2,'NADEL','Nadel',0,NULL,NULL,1,1,0,0,0,2,1),(3,'METERWARE','Meterware',1,0.200,0.100,1,1,0,0,0,3,1),(4,'DOWNLOAD','Download',0,NULL,NULL,0,0,1,0,0,4,1),(5,'SET','Set',0,NULL,NULL,0,1,0,1,0,5,1),(6,'STANDARD','Standard',0,NULL,NULL,1,1,0,0,0,6,1),(7,'GUTSCHEIN','Gutschein',0,NULL,NULL,0,0,0,0,1,7,1),(8,'LIZENZ','Lizenz',0,NULL,NULL,0,0,0,0,0,8,1);
/*!40000 ALTER TABLE `artikel_typen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftraege`
--

DROP TABLE IF EXISTS `auftraege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftraege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_nr` varchar(20) NOT NULL,
  `kunden_id` int(10) unsigned DEFAULT NULL,
  `kunden_snapshot` text DEFAULT NULL,
  `lieferadresse_snapshot` text DEFAULT NULL,
  `rechnungsadresse_snapshot` text DEFAULT NULL,
  `kanal` enum('woocommerce','manuell','kasse','jtl_archiv','haendler') NOT NULL DEFAULT 'manuell',
  `shop_id` int(10) unsigned DEFAULT NULL,
  `kanal_auftrag_id` int(10) unsigned DEFAULT NULL,
  `shop_status_gemeldet` varchar(20) DEFAULT NULL,
  `zahlungsstatus` enum('ausstehend','bezahlt','teilbezahlt','erstattet','storniert') NOT NULL DEFAULT 'ausstehend',
  `lieferstatus` enum('neu','in_bearbeitung','versandbereit','teilgeliefert','zurueckgestellt','versendet','abgeschlossen','storniert','abholbereit','kommissioniert','retoure_offen') NOT NULL DEFAULT 'neu',
  `zahlungsart` enum('vorkasse','paypal','rechnung','bar','karte','nachnahme','gutschein','gemischt') NOT NULL DEFAULT 'vorkasse',
  `zahlungsbedingung_id` int(10) unsigned DEFAULT NULL,
  `gutschein_id` int(10) unsigned DEFAULT NULL,
  `gutschein_betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `versandkosten` decimal(10,2) NOT NULL DEFAULT 0.00,
  `versand_tracking` varchar(100) DEFAULT NULL,
  `versand_datum` datetime DEFAULT NULL,
  `rabatt_gesamt` decimal(10,2) NOT NULL DEFAULT 0.00,
  `nettobetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `steuerbetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bruttobetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `bezahlt_am` datetime DEFAULT NULL,
  `mahnung_stufe` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `mahnung_gesendet_am` datetime DEFAULT NULL,
  `tracking_nr` varchar(100) DEFAULT NULL,
  `versanddienstleister` varchar(50) DEFAULT NULL,
  `notiz_intern` text DEFAULT NULL,
  `notiz_versand` text DEFAULT NULL,
  `kontakt_notiz` varchar(255) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  `lieferart` enum('versand','abholung') NOT NULL DEFAULT 'versand',
  `abholort_id` int(10) unsigned DEFAULT NULL,
  `versandklasse_id` int(10) unsigned DEFAULT NULL,
  `kassen_bon_id` int(11) DEFAULT NULL COMMENT 'Gesetzt wenn dieser Auftrag an der Kasse bezahlt wurde — sperrt Rechnung-Erstellung',
  PRIMARY KEY (`id`),
  UNIQUE KEY `auftrag_nr` (`auftrag_nr`),
  KEY `fk_auftrag_kunde` (`kunden_id`),
  KEY `fk_auftrag_zahlung` (`zahlungsbedingung_id`),
  KEY `fk_auftrag_benutzer` (`erstellt_von`),
  KEY `fk_auftr_versandklassen` (`versandklasse_id`),
  KEY `fk_auftraege_gutschein` (`gutschein_id`),
  KEY `fk_auftrag_abholort` (`abholort_id`),
  CONSTRAINT `fk_auftr_versandklassen` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftraege_gutschein` FOREIGN KEY (`gutschein_id`) REFERENCES `gutscheine` (`id`),
  CONSTRAINT `fk_auftrag_abholort` FOREIGN KEY (`abholort_id`) REFERENCES `abholorte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_auftrag_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftrag_kunde` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftrag_zahlung` FOREIGN KEY (`zahlungsbedingung_id`) REFERENCES `zahlungsbedingungen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftraege`
--

LOCK TABLES `auftraege` WRITE;
/*!40000 ALTER TABLE `auftraege` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftraege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_dokumente`
--

DROP TABLE IF EXISTS `auftrag_dokumente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_dokumente` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `typ` enum('auftragsbestaetigung','lieferschein','rechnung','gutschrift','mahnung','abholzettel') NOT NULL,
  `beleg_nr` varchar(30) DEFAULT NULL,
  `dateiname` varchar(255) NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_adok_auftrag` (`auftrag_id`),
  KEY `fk_adok_benutzer` (`erstellt_von`),
  CONSTRAINT `fk_adok_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_adok_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_dokumente`
--

LOCK TABLES `auftrag_dokumente` WRITE;
/*!40000 ALTER TABLE `auftrag_dokumente` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_dokumente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_lieferung_positionen`
--

DROP TABLE IF EXISTS `auftrag_lieferung_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_lieferung_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferung_id` int(10) unsigned NOT NULL,
  `auftrag_position_id` int(10) unsigned NOT NULL,
  `menge` decimal(10,3) NOT NULL,
  `charge` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_auflp_lieferung` (`lieferung_id`),
  KEY `fk_auflp_position` (`auftrag_position_id`),
  CONSTRAINT `fk_auflp_lieferung` FOREIGN KEY (`lieferung_id`) REFERENCES `auftrag_lieferungen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_auflp_position` FOREIGN KEY (`auftrag_position_id`) REFERENCES `auftrag_positionen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_lieferung_positionen`
--

LOCK TABLES `auftrag_lieferung_positionen` WRITE;
/*!40000 ALTER TABLE `auftrag_lieferung_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_lieferung_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_lieferungen`
--

DROP TABLE IF EXISTS `auftrag_lieferungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_lieferungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `bestellung_id` int(10) unsigned DEFAULT NULL COMMENT 'gesetzt = Dropship-Lieferung durch den Lieferanten',
  `tracking_nr` varchar(100) NOT NULL,
  `versanddienstleister` varchar(50) DEFAULT NULL,
  `versand_datum` datetime NOT NULL DEFAULT current_timestamp(),
  `ist_teillieferung` tinyint(1) NOT NULL DEFAULT 0,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auftrag_id` (`auftrag_id`),
  CONSTRAINT `fk_auftrag_lieferungen_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_lieferungen`
--

LOCK TABLES `auftrag_lieferungen` WRITE;
/*!40000 ALTER TABLE `auftrag_lieferungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_lieferungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_positionen`
--

DROP TABLE IF EXISTS `auftrag_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `charge` varchar(255) DEFAULT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `menge` int(11) NOT NULL,
  `menge_geliefert` int(10) unsigned NOT NULL DEFAULT 0,
  `menge_abgeholt` int(10) unsigned NOT NULL DEFAULT 0,
  `menge_retourniert` int(10) unsigned NOT NULL DEFAULT 0,
  `menge_gutgeschrieben` int(10) unsigned NOT NULL DEFAULT 0,
  `menge_verrechnet` int(11) NOT NULL DEFAULT 0,
  `einzelpreis_netto` decimal(10,4) NOT NULL,
  `steuer_prozent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `preisbasis` enum('brutto','netto') NOT NULL DEFAULT 'brutto',
  `gesamtpreis_netto` decimal(10,2) NOT NULL,
  `konfig_freitext` text DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_aufpos_auftrag` (`auftrag_id`),
  KEY `fk_aufpos_artikel` (`artikel_id`),
  CONSTRAINT `fk_aufpos_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_aufpos_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_positionen`
--

LOCK TABLES `auftrag_positionen` WRITE;
/*!40000 ALTER TABLE `auftrag_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_statuslog`
--

DROP TABLE IF EXISTS `auftrag_statuslog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_statuslog` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `felder_geaendert` text DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_alog_auftrag` (`auftrag_id`),
  KEY `fk_alog_benutzer` (`erstellt_von`),
  CONSTRAINT `fk_alog_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_alog_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_statuslog`
--

LOCK TABLES `auftrag_statuslog` WRITE;
/*!40000 ALTER TABLE `auftrag_statuslog` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_statuslog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auftrag_zahlungen`
--

DROP TABLE IF EXISTS `auftrag_zahlungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_zahlungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `buchungsdatum` date NOT NULL,
  `zahlungsweg` enum('ueberweisung','paypal','bar','karte','gutschein','nachnahme','sonstig') DEFAULT NULL,
  `kassen_bon_id` int(10) unsigned DEFAULT NULL,
  `notiz` varchar(255) DEFAULT NULL,
  `erfasst_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erfasst_von` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_aufzahl_auftrag` (`auftrag_id`),
  KEY `fk_aufzahl_benutzer` (`erfasst_von`),
  CONSTRAINT `fk_aufzahl_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aufzahl_benutzer` FOREIGN KEY (`erfasst_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auftrag_zahlungen`
--

LOCK TABLES `auftrag_zahlungen` WRITE;
/*!40000 ALTER TABLE `auftrag_zahlungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `auftrag_zahlungen` ENABLE KEYS */;
UNLOCK TABLES;

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
  `manager_pin_hash` varchar(255) DEFAULT NULL,
  `vorname` varchar(255) DEFAULT NULL,
  `nachname` varchar(255) DEFAULT NULL,
  `formularname` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `max_sessions` int(10) unsigned NOT NULL DEFAULT 0,
  `sitzung_ungueltig_ab` datetime DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `benutzer`
--

LOCK TABLES `benutzer` WRITE;
/*!40000 ALTER TABLE `benutzer` DISABLE KEYS */;
INSERT INTO `benutzer` VALUES (1,'system','!',NULL,NULL,NULL,'Jarvis (System)',NULL,1,0,NULL,'2026-11-04 09:23:21','2026-11-04 09:23:21');
/*!40000 ALTER TABLE `benutzer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `benutzer_einstellungen`
--

DROP TABLE IF EXISTS `benutzer_einstellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `benutzer_einstellungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `benutzer_id` int(10) unsigned NOT NULL,
  `schluessel` varchar(100) NOT NULL,
  `wert` text NOT NULL,
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_benutzer_schluessel` (`benutzer_id`,`schluessel`),
  CONSTRAINT `fk_benutzereinst_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `benutzer_einstellungen`
--

LOCK TABLES `benutzer_einstellungen` WRITE;
/*!40000 ALTER TABLE `benutzer_einstellungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `benutzer_einstellungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `benutzer_passwort_tokens`
--

DROP TABLE IF EXISTS `benutzer_passwort_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `benutzer_passwort_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `benutzer_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ausgestellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `laeuft_ab_am` timestamp NULL DEFAULT NULL,
  `verwendet_am` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `fk_bpt_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_bpt_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `benutzer_passwort_tokens`
--

LOCK TABLES `benutzer_passwort_tokens` WRITE;
/*!40000 ALTER TABLE `benutzer_passwort_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `benutzer_passwort_tokens` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `benutzer_rollen`
--

LOCK TABLES `benutzer_rollen` WRITE;
/*!40000 ALTER TABLE `benutzer_rollen` DISABLE KEYS */;
/*!40000 ALTER TABLE `benutzer_rollen` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `berechtigungen`
--

LOCK TABLES `berechtigungen` WRITE;
/*!40000 ALTER TABLE `berechtigungen` DISABLE KEYS */;
INSERT INTO `berechtigungen` VALUES (1,'artikel.anzeigen','artikel anzeigen',1,'2026-06-01 20:27:22'),(2,'artikel.bearbeiten','artikel bearbeiten',1,'2026-06-01 20:27:22'),(3,'artikel.anlegen','artikel anlegen',1,'2026-06-01 20:27:22'),(4,'artikel.loeschen','artikel löschen',1,'2026-06-01 20:27:22'),(5,'varianten.anzeigen','varianten anzeigen',1,'2026-06-01 20:27:22'),(6,'varianten.bearbeiten','varianten bearbeiten',1,'2026-06-01 20:27:22'),(7,'varianten.anlegen','varianten anlegen',1,'2026-06-01 20:27:22'),(8,'varianten.loeschen','varianten löschen',1,'2026-06-01 20:27:22'),(9,'lager.anzeigen','lager anzeigen',1,'2026-06-01 20:27:22'),(10,'lager.bearbeiten','lager bearbeiten',1,'2026-06-01 20:27:22'),(11,'lager.anlegen','lager anlegen',1,'2026-06-01 20:27:22'),(12,'lager.loeschen','lager löschen',1,'2026-06-01 20:27:22'),(13,'wareneingang.buchen','wareneingang buchen',1,'2026-06-01 20:27:22'),(14,'wareneingang.bearbeiten','wareneingang bearbeiten',1,'2026-06-01 20:27:22'),(15,'bestand.anzeigen','bestand anzeigen',1,'2026-06-01 20:27:22'),(16,'bestand.bearbeiten','bestand bearbeiten',1,'2026-06-01 20:27:22'),(17,'bestand.korrigieren','bestand korrigieren',1,'2026-06-01 20:27:22'),(18,'bestand.loeschen','bestand löschen',1,'2026-06-01 20:27:22'),(19,'lieferanten.anzeigen','lieferanten anzeigen',1,'2026-06-01 20:27:22'),(20,'lieferanten.bearbeiten','lieferanten bearbeiten',1,'2026-06-01 20:27:22'),(21,'lieferanten.anlegen','lieferanten anlegen',1,'2026-06-01 20:27:22'),(22,'lieferanten.loeschen','lieferanten löschen',1,'2026-06-01 20:27:22'),(23,'inventur.anzeigen','inventur anzeigen',1,'2026-06-01 20:27:22'),(24,'inventur.bearbeiten','inventur bearbeiten',1,'2026-06-01 20:27:22'),(25,'inventur.anlegen','inventur anlegen',1,'2026-06-01 20:27:22'),(26,'inventur.loeschen','inventur löschen',1,'2026-06-01 20:27:22'),(27,'inventurpositionen.anzeigen','inventurpositionen anzeigen',1,'2026-06-01 20:27:22'),(28,'inventurpositionen.bearbeiten','inventurpositionen bearbeiten',1,'2026-06-01 20:27:22'),(29,'inventurpositionen.anlegen','inventurpositionen anlegen',1,'2026-06-01 20:27:22'),(30,'inventurpositionen.loeschen','inventurpositionen löschen',1,'2026-06-01 20:27:22'),(31,'benutzer.anlegen','benutzer anlegen',1,'2026-06-01 20:27:22'),(32,'benutzer.bearbeiten','benutzer bearbeiten',1,'2026-06-01 20:27:22'),(33,'benutzer.loeschen','benutzer löschen',1,'2026-06-01 20:27:22'),(34,'api.zugriff','API Zugriff',1,'2026-06-01 20:27:22'),(35,'berichte.anzeigen','berichte anzeigen',1,'2026-06-01 20:27:22'),(36,'berichte.bearbeiten','berichte bearbeiten',1,'2026-06-01 20:27:22'),(37,'berichte.anlegen','berichte anlegen',1,'2026-06-01 20:27:22'),(38,'berichte.loeschen','berichte löschen',1,'2026-06-01 20:27:22'),(39,'berichte.drucken','berichte drucken',1,'2026-06-01 20:27:22'),(40,'shopabgleich.starten','shopabgleich starten',1,'2026-06-01 20:27:22'),(41,'shopabgleich.stoppen','shopabgleich stoppen',1,'2026-06-01 20:27:22'),(42,'packplatz.starten','packplatz starten',1,'2026-06-01 20:27:22'),(43,'packplatz.stoppen','packplatz stoppen',1,'2026-06-01 20:27:22'),(44,'kasse.starten','kasse starten',1,'2026-06-01 20:27:22'),(45,'kasse.stoppen','kasse stoppen',1,'2026-06-01 20:27:22'),(46,'kunden.anzeigen','kunden anzeigen',1,'2026-07-05 12:16:02'),(47,'kunden.bearbeiten','kunden bearbeiten',1,'2026-07-05 12:16:02'),(48,'kunden.anlegen','kunden anlegen',1,'2026-07-05 12:16:02'),(49,'kunden.loeschen','kunden löschen',1,'2026-07-05 12:16:02'),(50,'auftraege.anzeigen','aufträge anzeigen',1,'2026-07-05 12:16:02'),(51,'auftraege.anlegen','aufträge anlegen',1,'2026-07-05 12:16:02'),(52,'auftraege.bearbeiten','aufträge bearbeiten',1,'2026-07-05 12:16:02'),(53,'auftraege.stornieren','aufträge stornieren',1,'2026-07-05 12:16:02'),(54,'partner.anzeigen','partner anzeigen',1,'2026-07-05 12:16:02'),(55,'partner.anlegen','partner anlegen',1,'2026-07-05 12:16:02'),(56,'partner.bearbeiten','partner bearbeiten',1,'2026-07-05 12:16:02'),(57,'partner.loeschen','partner löschen',1,'2026-07-05 12:16:02'),(58,'bestellwesen.anzeigen','bestellwesen anzeigen',1,'2026-07-05 12:16:02'),(59,'bestellwesen.anlegen','bestellwesen anlegen',1,'2026-07-05 12:16:02'),(60,'bestellwesen.bearbeiten','bestellwesen bearbeiten',1,'2026-07-05 12:16:02'),(61,'einstellungen.anzeigen','einstellungen anzeigen',1,'2026-07-05 12:16:02'),(62,'einstellungen.bearbeiten','einstellungen bearbeiten',1,'2026-07-05 12:16:02'),(63,'lizenz.verwalten','lizenz verwalten (nur Superadmin, in der Matrix-UI fix gesperrt)',1,'2026-07-05 12:16:02'),(64,'dashboard.zugriff','dashboard zugriff',1,'2026-07-05 12:16:02'),(65,'kasse.auszahlung','kasse auszahlung (künftig Manager-Override)',1,'2026-07-05 12:16:02'),(66,'kasse.verwaltung','kassen-instanzen verwalten',1,'2026-07-05 12:16:02'),(67,'packplatz.retoure','packplatz retoure erfassen',1,'2026-07-05 12:16:02'),(68,'packplatz.gutschrift','packplatz gutschrift auslösen (künftig Manager-Override)',1,'2026-07-05 12:16:02'),(69,'versand.anzeigen','versand anzeigen',1,'2026-07-05 12:16:02'),(70,'versand.bearbeiten','versand bearbeiten',1,'2026-07-05 12:16:02'),(71,'buchhaltung.anzeigen','buchhaltung anzeigen',1,'2026-07-05 12:16:02'),(72,'benutzer.anzeigen','benutzer anzeigen',1,'2026-07-05 12:16:02'),(73,'system.log','aktivitäten-log einsehen',1,'2026-11-04 09:23:22');
/*!40000 ALTER TABLE `berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bestellung_dokumente`
--

DROP TABLE IF EXISTS `bestellung_dokumente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellung_dokumente` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bestellung_id` int(10) unsigned NOT NULL,
  `typ` enum('bestellung') NOT NULL DEFAULT 'bestellung',
  `dateiname` varchar(255) NOT NULL,
  `mail_gesendet_am` timestamp NULL DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bdok_bestellung` (`bestellung_id`),
  KEY `fk_bdok_benutzer` (`erstellt_von`),
  CONSTRAINT `fk_bdok_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bdok_bestellung` FOREIGN KEY (`bestellung_id`) REFERENCES `bestellungen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bestellung_dokumente`
--

LOCK TABLES `bestellung_dokumente` WRITE;
/*!40000 ALTER TABLE `bestellung_dokumente` DISABLE KEYS */;
/*!40000 ALTER TABLE `bestellung_dokumente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bestellung_eingaenge`
--

DROP TABLE IF EXISTS `bestellung_eingaenge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellung_eingaenge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int(10) unsigned NOT NULL,
  `bewegung_id` int(10) unsigned DEFAULT NULL COMMENT 'FK auf lager_bewegungen — NULL wenn Charge "zu erfassen"',
  `menge` decimal(10,3) NOT NULL,
  `charge` varchar(100) DEFAULT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bein_position` (`position_id`),
  KEY `fk_bein_bewegung` (`bewegung_id`),
  KEY `fk_bein_lager` (`lager_id`),
  KEY `fk_bein_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_bein_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bein_bewegung` FOREIGN KEY (`bewegung_id`) REFERENCES `lager_bewegungen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bein_lager` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bein_position` FOREIGN KEY (`position_id`) REFERENCES `bestellung_positionen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bestellung_eingaenge`
--

LOCK TABLES `bestellung_eingaenge` WRITE;
/*!40000 ALTER TABLE `bestellung_eingaenge` DISABLE KEYS */;
/*!40000 ALTER TABLE `bestellung_eingaenge` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bestellung_positionen`
--

DROP TABLE IF EXISTS `bestellung_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellung_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bestellung_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `menge_bestellt` decimal(10,3) NOT NULL,
  `menge_eingegangen` decimal(10,3) NOT NULL DEFAULT 0.000,
  `ek_preis` decimal(10,4) DEFAULT NULL COMMENT 'EK-Preis-Snapshot zum Bestellzeitpunkt',
  `lieferzeit_text` varchar(100) DEFAULT NULL,
  `gestrichen` tinyint(1) NOT NULL DEFAULT 0,
  `notiz` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bpos_bestellung` (`bestellung_id`),
  KEY `fk_bpos_artikel` (`artikel_id`),
  CONSTRAINT `fk_bpos_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bpos_bestellung` FOREIGN KEY (`bestellung_id`) REFERENCES `bestellungen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bestellung_positionen`
--

LOCK TABLES `bestellung_positionen` WRITE;
/*!40000 ALTER TABLE `bestellung_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `bestellung_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bestellung_zahlungen`
--

DROP TABLE IF EXISTS `bestellung_zahlungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellung_zahlungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bestellung_id` int(10) unsigned NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `art` enum('ueberweisung','guthaben_verrechnung') NOT NULL DEFAULT 'ueberweisung',
  `buchungsdatum` date NOT NULL,
  `notiz` varchar(255) DEFAULT NULL,
  `erfasst_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erfasst_von` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bezahl_bestellung` (`bestellung_id`),
  KEY `fk_bezahl_benutzer` (`erfasst_von`),
  CONSTRAINT `fk_bezahl_benutzer` FOREIGN KEY (`erfasst_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bezahl_bestellung` FOREIGN KEY (`bestellung_id`) REFERENCES `bestellungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bestellung_zahlungen`
--

LOCK TABLES `bestellung_zahlungen` WRITE;
/*!40000 ALTER TABLE `bestellung_zahlungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `bestellung_zahlungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bestellungen`
--

DROP TABLE IF EXISTS `bestellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
  `typ` enum('normal','dropshipping') NOT NULL DEFAULT 'normal',
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `lieferadresse_snapshot` text DEFAULT NULL,
  `dropship_pauschale` decimal(10,2) DEFAULT NULL,
  `status` enum('entwurf','offen','teilgeliefert','erledigt','storniert') NOT NULL DEFAULT 'entwurf',
  `bestelldatum` date NOT NULL,
  `erwartet_am` date DEFAULT NULL,
  `lieferzeit_text` varchar(100) DEFAULT NULL COMMENT 'Freitext z.B. "ab KW38", überschreibt lieferzeit_tage aus artikel_lieferanten',
  `ab_nummer` varchar(100) DEFAULT NULL COMMENT 'Auftragsbestätigungs-Nummer vom Lieferanten',
  `zahlungsart` varchar(50) DEFAULT NULL COMMENT 'vorkasse | rechnung | lastschrift',
  `ls_nummer` varchar(100) DEFAULT NULL COMMENT 'Lieferschein-Nummer vom Lieferanten',
  `rechnung_nummer` varchar(100) DEFAULT NULL,
  `rechnung_betrag` decimal(10,2) DEFAULT NULL,
  `rechnung_datum` date DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_best_lieferant` (`lieferant_id`),
  KEY `fk_best_benutzer` (`benutzer_id`),
  KEY `idx_best_auftrag` (`auftrag_id`),
  CONSTRAINT `fk_best_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_best_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_best_lieferant` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bestellungen`
--

LOCK TABLES `bestellungen` WRITE;
/*!40000 ALTER TABLE `bestellungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `bestellungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bfr_ausfaelle`
--

DROP TABLE IF EXISTS `bfr_ausfaelle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bfr_ausfaelle` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `erste_erkennung_am` datetime NOT NULL,
  `letzte_erkennung_am` datetime NOT NULL,
  `geloest_am` datetime DEFAULT NULL,
  `anzahl_ereignisse` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_bfrausfall_kasse` (`kasse_id`),
  CONSTRAINT `fk_bfrausfall_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bfr_ausfaelle`
--

LOCK TABLES `bfr_ausfaelle` WRITE;
/*!40000 ALTER TABLE `bfr_ausfaelle` DISABLE KEYS */;
/*!40000 ALTER TABLE `bfr_ausfaelle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bfr_ausfall_ereignisse`
--

DROP TABLE IF EXISTS `bfr_ausfall_ereignisse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bfr_ausfall_ereignisse` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ausfall_id` int(10) unsigned NOT NULL,
  `typ` enum('dienst_nicht_erreichbar','sicherheitseinrichtung_ausgefallen') NOT NULL,
  `bon_nr` varchar(50) DEFAULT NULL,
  `anzahl_versuche` int(10) unsigned NOT NULL DEFAULT 1,
  `aufgetreten_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bfrausfallereignis_ausfall` (`ausfall_id`),
  CONSTRAINT `fk_bfrausfallereignis_ausfall` FOREIGN KEY (`ausfall_id`) REFERENCES `bfr_ausfaelle` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bfr_ausfall_ereignisse`
--

LOCK TABLES `bfr_ausfall_ereignisse` WRITE;
/*!40000 ALTER TABLE `bfr_ausfall_ereignisse` DISABLE KEYS */;
/*!40000 ALTER TABLE `bfr_ausfall_ereignisse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bfr_kassen_registrierungen`
--

DROP TABLE IF EXISTS `bfr_kassen_registrierungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bfr_kassen_registrierungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `rksv_kassen_id` varchar(50) NOT NULL,
  `bfr_url` varchar(255) NOT NULL,
  `uid_nummer` varchar(30) DEFAULT NULL,
  `vertrauensdiensteanbieter` varchar(50) DEFAULT NULL,
  `zertifikat_seriennr_dez` varchar(50) DEFAULT NULL,
  `zertifikat_seriennr_hex` varchar(50) DEFAULT NULL,
  `zertifikat_gemeldet_am` datetime DEFAULT NULL,
  `zertifikat_gueltig_bis` date DEFAULT NULL,
  `kasse_gemeldet_am` datetime DEFAULT NULL,
  `startbeleg_geprueft_am` datetime DEFAULT NULL,
  `startbeleg_inhalt` text DEFAULT NULL,
  `abgeschlossen_am` datetime DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bfrreg_kasse` (`kasse_id`),
  KEY `fk_bfrreg_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_bfrreg_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_bfrreg_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bfr_kassen_registrierungen`
--

LOCK TABLES `bfr_kassen_registrierungen` WRITE;
/*!40000 ALTER TABLE `bfr_kassen_registrierungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `bfr_kassen_registrierungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bfr_kommunikation_log`
--

DROP TABLE IF EXISTS `bfr_kommunikation_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bfr_kommunikation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned DEFAULT NULL,
  `endpunkt` varchar(20) NOT NULL,
  `request_body` text DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `curl_fehler` varchar(255) DEFAULT NULL,
  `dauer_ms` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bkl_kasse` (`kasse_id`),
  KEY `idx_bkl_erstellt` (`erstellt_am`),
  CONSTRAINT `fk_bkl_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bfr_kommunikation_log`
--

LOCK TABLES `bfr_kommunikation_log` WRITE;
/*!40000 ALTER TABLE `bfr_kommunikation_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `bfr_kommunikation_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bfr_nullbelege`
--

DROP TABLE IF EXISTS `bfr_nullbelege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bfr_nullbelege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `monat` char(7) NOT NULL,
  `beleg_nr` varchar(50) NOT NULL,
  `ausgeloest_durch` enum('manuell','automatisch') NOT NULL,
  `rksv_signatur` varchar(255) DEFAULT NULL,
  `rksv_qr` varchar(500) DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `signiert_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nullbeleg_kasse_monat` (`kasse_id`,`monat`),
  KEY `fk_nullbeleg_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_nullbeleg_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_nullbeleg_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bfr_nullbelege`
--

LOCK TABLES `bfr_nullbelege` WRITE;
/*!40000 ALTER TABLE `bfr_nullbelege` DISABLE KEYS */;
/*!40000 ALTER TABLE `bfr_nullbelege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dokument_nummern`
--

DROP TABLE IF EXISTS `dokument_nummern`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokument_nummern` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag','pickliste','partner_uebernahme','partner_rueckgabe','haendler_lieferung','haendler_ruecknahme','haendler_schwund','partner_auszahlung') NOT NULL,
  `kuerzel` varchar(5) NOT NULL DEFAULT '',
  `praefix` varchar(10) NOT NULL,
  `jahr` smallint(6) NOT NULL,
  `letzt_nr` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dok_typ_kuerzel_jahr` (`typ`,`kuerzel`,`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dokument_nummern`
--

LOCK TABLES `dokument_nummern` WRITE;
/*!40000 ALTER TABLE `dokument_nummern` DISABLE KEYS */;
INSERT INTO `dokument_nummern` VALUES (1,'partner_uebernahme','','US',2026,0),(2,'partner_rueckgabe','','RS',2026,0),(3,'haendler_lieferung','','HL',2026,0),(4,'haendler_ruecknahme','','HR',2026,0),(5,'haendler_schwund','','HS',2026,0),(6,'partner_auszahlung','','ZA',2026,0),(7,'auftrag','','A',2026,0),(8,'rechnung','','R',2026,0),(9,'gutschrift','','GS',2026,0),(10,'lieferschein','','LS',2026,0),(11,'mietrechnung','','MR',2026,0),(12,'abrechnung','','AB',2026,0),(13,'pickliste','','PL',2026,0);
/*!40000 ALTER TABLE `dokument_nummern` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `einheiten`
--

LOCK TABLES `einheiten` WRITE;
/*!40000 ALTER TABLE `einheiten` DISABLE KEYS */;
INSERT INTO `einheiten` VALUES (1,'Knäuel','Kn',0),(2,'Meter','m',0),(3,'Gramm','g',0),(4,'Stück','Stk',0),(5,'Set','Set',0),(6,'Paar','pa',0),(7,'Packung','Pkg',0),(8,'Rolle','Rl',0),(9,'Strang','Str',0),(10,'Bollen','Bln',0),(11,'Spiel','Spl',0),(12,'Liter','l',0),(13,'Zentimeter','cm',0);
/*!40000 ALTER TABLE `einheiten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gutschein_transaktionen`
--

DROP TABLE IF EXISTS `gutschein_transaktionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gutschein_transaktionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gutschein_id` int(10) unsigned NOT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `kassen_bon_id` int(10) unsigned DEFAULT NULL,
  `betrag` decimal(10,2) NOT NULL COMMENT 'negativ = Einloesung, positiv = Aufladung/Erstattung',
  `kanal` enum('kasse','erp','woocommerce') NOT NULL,
  `notiz` varchar(255) DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gtrans_gutschein` (`gutschein_id`),
  KEY `fk_gtrans_auftrag` (`auftrag_id`),
  KEY `fk_gtrans_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_gtrans_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`),
  CONSTRAINT `fk_gtrans_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`),
  CONSTRAINT `fk_gtrans_gutschein` FOREIGN KEY (`gutschein_id`) REFERENCES `gutscheine` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gutschein_transaktionen`
--

LOCK TABLES `gutschein_transaktionen` WRITE;
/*!40000 ALTER TABLE `gutschein_transaktionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `gutschein_transaktionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gutschein_vorlagen`
--

DROP TABLE IF EXISTS `gutschein_vorlagen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gutschein_vorlagen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `hintergrundbild_pfad` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gutschein_vorlagen`
--

LOCK TABLES `gutschein_vorlagen` WRITE;
/*!40000 ALTER TABLE `gutschein_vorlagen` DISABLE KEYS */;
/*!40000 ALTER TABLE `gutschein_vorlagen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gutscheine`
--

DROP TABLE IF EXISTS `gutscheine`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gutscheine` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `vorlage_id` int(10) unsigned DEFAULT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `restguthaben` decimal(10,2) NOT NULL,
  `gueltig_bis` date DEFAULT NULL,
  `status` enum('aktiv','teilweise','eingeloest','abgelaufen','storniert') NOT NULL DEFAULT 'aktiv',
  `kunden_id` int(10) unsigned DEFAULT NULL,
  `empfaenger_name` varchar(150) DEFAULT NULL,
  `empfaenger_email` varchar(150) DEFAULT NULL,
  `zustellung_am` date DEFAULT NULL,
  `versandart` enum('versenden','selbst_ausdrucken') NOT NULL DEFAULT 'selbst_ausdrucken',
  `grusstext` text DEFAULT NULL,
  `versendet_am` datetime DEFAULT NULL,
  `woo_coupon_id` int(10) unsigned DEFAULT NULL,
  `woo_sync_faellig` tinyint(1) NOT NULL DEFAULT 0,
  `shop_id` int(10) unsigned DEFAULT NULL,
  `kanal_erstellt` enum('kasse','erp','woocommerce','manuell') NOT NULL DEFAULT 'manuell',
  `auftrag_id_ursprung` int(10) unsigned DEFAULT NULL COMMENT 'Bestellung/Bon, aus dem der Gutschein entstand (Kauf ODER Erstattung)',
  `vorgaenger_gutschein_id` int(10) unsigned DEFAULT NULL,
  `kanal_line_item_id` varchar(50) DEFAULT NULL,
  `ausgestellt_von` int(10) unsigned NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gutschein_code` (`code`),
  KEY `idx_gutschein_kunde` (`kunden_id`),
  KEY `idx_gutschein_status` (`status`),
  KEY `fk_gutschein_vorlage` (`vorlage_id`),
  KEY `fk_gutschein_shop` (`shop_id`),
  KEY `fk_gutschein_ausgestellt_von` (`ausgestellt_von`),
  KEY `idx_gutschein_line_item` (`auftrag_id_ursprung`,`kanal_line_item_id`),
  KEY `fk_gutschein_vorgaenger` (`vorgaenger_gutschein_id`),
  KEY `idx_gutschein_woo_sync` (`woo_sync_faellig`),
  CONSTRAINT `fk_gutschein_ausgestellt_von` FOREIGN KEY (`ausgestellt_von`) REFERENCES `benutzer` (`id`),
  CONSTRAINT `fk_gutschein_kunde` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `fk_gutschein_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`),
  CONSTRAINT `fk_gutschein_vorgaenger` FOREIGN KEY (`vorgaenger_gutschein_id`) REFERENCES `gutscheine` (`id`),
  CONSTRAINT `fk_gutschein_vorlage` FOREIGN KEY (`vorlage_id`) REFERENCES `gutschein_vorlagen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gutscheine`
--

LOCK TABLES `gutscheine` WRITE;
/*!40000 ALTER TABLE `gutscheine` DISABLE KEYS */;
/*!40000 ALTER TABLE `gutscheine` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gutschrift_positionen`
--

DROP TABLE IF EXISTS `gutschrift_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gutschrift_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gutschrift_id` int(10) unsigned NOT NULL,
  `auftrag_position_id` int(10) unsigned DEFAULT NULL,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `menge` int(11) NOT NULL,
  `einzelpreis_netto` decimal(10,4) NOT NULL,
  `steuer_prozent` decimal(5,2) NOT NULL,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `preisbasis` enum('brutto','netto') NOT NULL DEFAULT 'brutto',
  `netto` decimal(10,2) NOT NULL,
  `steuer` decimal(10,2) NOT NULL,
  `brutto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gsp_gutschrift` (`gutschrift_id`),
  KEY `fk_gsp_position` (`auftrag_position_id`),
  CONSTRAINT `fk_gsp_gutschrift` FOREIGN KEY (`gutschrift_id`) REFERENCES `gutschriften` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gsp_position` FOREIGN KEY (`auftrag_position_id`) REFERENCES `auftrag_positionen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gutschrift_positionen`
--

LOCK TABLES `gutschrift_positionen` WRITE;
/*!40000 ALTER TABLE `gutschrift_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `gutschrift_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gutschriften`
--

DROP TABLE IF EXISTS `gutschriften`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gutschriften` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gutschrift_nr` varchar(20) NOT NULL,
  `auftrag_id` int(10) unsigned NOT NULL,
  `rechnung_id` int(10) unsigned DEFAULT NULL,
  `art` enum('vollstorno','teilgutschrift') NOT NULL,
  `grund` varchar(500) DEFAULT NULL,
  `nettobetrag` decimal(10,2) NOT NULL,
  `steuerbetrag` decimal(10,2) NOT NULL,
  `bruttobetrag` decimal(10,2) NOT NULL,
  `versandkosten_brutto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dateiname` varchar(255) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gutschrift_nr` (`gutschrift_nr`),
  KEY `idx_gs_auftrag` (`auftrag_id`),
  KEY `fk_gs_rechnung` (`rechnung_id`),
  CONSTRAINT `fk_gs_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`),
  CONSTRAINT `fk_gs_rechnung` FOREIGN KEY (`rechnung_id`) REFERENCES `rechnungen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gutschriften`
--

LOCK TABLES `gutschriften` WRITE;
/*!40000 ALTER TABLE `gutschriften` DISABLE KEYS */;
/*!40000 ALTER TABLE `gutschriften` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `haendler_beleg_positionen`
--

DROP TABLE IF EXISTS `haendler_beleg_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `haendler_beleg_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `beleg_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `artikelnummer` varchar(50) NOT NULL,
  `bezeichnung` varchar(300) NOT NULL,
  `menge` decimal(10,3) NOT NULL,
  `menge_offen` decimal(10,3) NOT NULL DEFAULT 0.000,
  `charge` varchar(255) DEFAULT NULL,
  `preis_netto` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `vk_brutto` decimal(10,2) DEFAULT NULL,
  `steuer_prozent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `lieferung_position_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hbp_fifo` (`artikel_id`,`menge_offen`),
  KEY `fk_hbp_beleg` (`beleg_id`),
  CONSTRAINT `fk_hbp_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`),
  CONSTRAINT `fk_hbp_beleg` FOREIGN KEY (`beleg_id`) REFERENCES `haendler_belege` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `haendler_beleg_positionen`
--

LOCK TABLES `haendler_beleg_positionen` WRITE;
/*!40000 ALTER TABLE `haendler_beleg_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `haendler_beleg_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `haendler_belege`
--

DROP TABLE IF EXISTS `haendler_belege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `haendler_belege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `typ` enum('lieferung','ruecknahme','schwund','verkauf') NOT NULL,
  `nummer` varchar(30) NOT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `von_lager_id` int(10) unsigned DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_haendler_beleg_nummer` (`nummer`),
  KEY `idx_haendler_beleg_kunde` (`kunde_id`,`erstellt_am`),
  KEY `fk_hb_lager` (`lager_id`),
  CONSTRAINT `fk_hb_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `fk_hb_lager` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `haendler_belege`
--

LOCK TABLES `haendler_belege` WRITE;
/*!40000 ALTER TABLE `haendler_belege` DISABLE KEYS */;
/*!40000 ALTER TABLE `haendler_belege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hersteller`
--

DROP TABLE IF EXISTS `hersteller`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hersteller` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `handelsname` varchar(100) DEFAULT NULL,
  `webseite` varchar(255) DEFAULT NULL,
  `land` varchar(50) DEFAULT NULL,
  `strasse` varchar(255) DEFAULT NULL,
  `plz` varchar(20) DEFAULT NULL,
  `ort` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `logo_pfad` varchar(255) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `reo_name` varchar(255) DEFAULT NULL,
  `reo_strasse` varchar(255) DEFAULT NULL,
  `reo_plz` varchar(20) DEFAULT NULL,
  `reo_ort` varchar(100) DEFAULT NULL,
  `reo_land` char(2) DEFAULT NULL,
  `reo_email` varchar(255) DEFAULT NULL,
  `aktualisiert_am` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hersteller`
--

LOCK TABLES `hersteller` WRITE;
/*!40000 ALTER TABLE `hersteller` DISABLE KEYS */;
/*!40000 ALTER TABLE `hersteller` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hersteller_shops`
--

DROP TABLE IF EXISTS `hersteller_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hersteller_shops` (
  `hersteller_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `externe_attribut_id` varchar(100) DEFAULT NULL,
  `externe_term_id` varchar(100) DEFAULT NULL,
  `externe_manufacturer_id` varchar(100) DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`hersteller_id`,`shop_id`),
  KEY `fk_herstellershop_shop` (`shop_id`),
  CONSTRAINT `fk_herstellershop_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_herstellershop_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hersteller_shops`
--

LOCK TABLES `hersteller_shops` WRITE;
/*!40000 ALTER TABLE `hersteller_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `hersteller_shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventur_laeufe`
--

DROP TABLE IF EXISTS `inventur_laeufe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventur_laeufe` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scope_tabelle` varchar(20) NOT NULL COMMENT 'lager | lagerplaetze | kategorien | artikel | mietfaecher',
  `scope_id` int(10) unsigned NOT NULL,
  `scope_bezeichnung` varchar(150) NOT NULL,
  `blind_modus` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('laufend','pausiert','abgeschlossen','abgebrochen') NOT NULL DEFAULT 'laufend',
  `vorgaenger_lauf_id` int(10) unsigned DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `gestartet_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `beendet_am` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_invlauf_benutzer` (`benutzer_id`),
  KEY `fk_invlauf_vorgaenger` (`vorgaenger_lauf_id`),
  CONSTRAINT `fk_invlauf_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_invlauf_vorgaenger` FOREIGN KEY (`vorgaenger_lauf_id`) REFERENCES `inventur_laeufe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventur_laeufe`
--

LOCK TABLES `inventur_laeufe` WRITE;
/*!40000 ALTER TABLE `inventur_laeufe` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventur_laeufe` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventur_positionen`
--

DROP TABLE IF EXISTS `inventur_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventur_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventur_lauf_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `lagerplatz_id` int(10) unsigned DEFAULT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `soll_menge` decimal(8,3) DEFAULT NULL COMMENT 'Snapshot bei Aufnahme in die Zählliste, NULL wenn unbekannt (z.B. erster Lagerplatz-Zählgang)',
  `ist_menge` decimal(8,3) DEFAULT NULL COMMENT 'NULL = noch nicht gezählt',
  `status` enum('offen','gezaehlt') NOT NULL DEFAULT 'offen',
  `notiz` varchar(255) DEFAULT NULL,
  `gezaehlt_von` int(10) unsigned DEFAULT NULL,
  `gezaehlt_am` timestamp NULL DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_invpos_lauf` (`inventur_lauf_id`),
  KEY `fk_invpos_artikel` (`artikel_id`),
  KEY `fk_invpos_lager` (`lager_id`),
  KEY `fk_invpos_platz` (`lagerplatz_id`),
  KEY `fk_invpos_benutzer` (`gezaehlt_von`),
  CONSTRAINT `fk_invpos_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_invpos_benutzer` FOREIGN KEY (`gezaehlt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invpos_lager` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_invpos_lauf` FOREIGN KEY (`inventur_lauf_id`) REFERENCES `inventur_laeufe` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invpos_platz` FOREIGN KEY (`lagerplatz_id`) REFERENCES `lagerplaetze` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventur_positionen`
--

LOCK TABLES `inventur_positionen` WRITE;
/*!40000 ALTER TABLE `inventur_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventur_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventur_zaehl_sperren`
--

DROP TABLE IF EXISTS `inventur_zaehl_sperren`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventur_zaehl_sperren` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `inventur_lauf_id` int(10) unsigned NOT NULL,
  `lagerplatz_id` int(10) unsigned NOT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `aktiv_seit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zuletzt_aktiv_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invsperre_lauf_platz` (`inventur_lauf_id`,`lagerplatz_id`),
  KEY `fk_invsperre_platz` (`lagerplatz_id`),
  KEY `fk_invsperre_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_invsperre_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_invsperre_lauf` FOREIGN KEY (`inventur_lauf_id`) REFERENCES `inventur_laeufe` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invsperre_platz` FOREIGN KEY (`lagerplatz_id`) REFERENCES `lagerplaetze` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventur_zaehl_sperren`
--

LOCK TABLES `inventur_zaehl_sperren` WRITE;
/*!40000 ALTER TABLE `inventur_zaehl_sperren` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventur_zaehl_sperren` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen`
--

DROP TABLE IF EXISTS `kassen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `kasse_nr` varchar(10) NOT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `modus` enum('online','offline') NOT NULL DEFAULT 'online',
  `ausgabe_format` enum('fragen','80mm','a4') NOT NULL DEFAULT 'fragen' COMMENT 'Bon-Ausgabeformat: fragen=Auswahl nach Zahlung, 80mm=Thermodruck, a4=A4-Rechnung',
  `rksv_kassen_id` varchar(50) DEFAULT NULL,
  `bfr_url` varchar(255) DEFAULT NULL,
  `bfr_umsatzzaehler` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bfr_aktiv_seit` datetime DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `bon_logo` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kasse_nr` (`kasse_nr`),
  KEY `lager_id` (`lager_id`),
  CONSTRAINT `kassen_ibfk_1` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen`
--

LOCK TABLES `kassen` WRITE;
/*!40000 ALTER TABLE `kassen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_abschluesse`
--

DROP TABLE IF EXISTS `kassen_abschluesse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_abschluesse` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_id` int(10) unsigned NOT NULL,
  `kasse_id` int(10) unsigned NOT NULL,
  `kassierer_id` int(10) unsigned DEFAULT NULL,
  `typ` enum('x','z') NOT NULL,
  `datum` date NOT NULL,
  `bis_zeitpunkt` datetime DEFAULT NULL,
  `kassenstand` decimal(10,2) NOT NULL DEFAULT 0.00,
  `daten` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Kennzahlen, Steueraufstellung, Kassenbuch, Bon-Range' CHECK (json_valid(`daten`)),
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kasse_datum` (`kasse_id`,`datum`),
  KEY `idx_typ` (`typ`),
  KEY `fk_ka_bon` (`bon_id`),
  KEY `fk_ka_kass2` (`kassierer_id`),
  CONSTRAINT `fk_ka_bon` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`),
  CONSTRAINT `fk_ka_kass2` FOREIGN KEY (`kassierer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ka_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_abschluesse`
--

LOCK TABLES `kassen_abschluesse` WRITE;
/*!40000 ALTER TABLE `kassen_abschluesse` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_abschluesse` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_bon_auftraege`
--

DROP TABLE IF EXISTS `kassen_bon_auftraege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_bon_auftraege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_id` int(10) unsigned NOT NULL,
  `auftrag_id` int(10) unsigned NOT NULL,
  `vorher_bezahlt` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bon_auftrag` (`bon_id`,`auftrag_id`),
  KEY `idx_auftrag` (`auftrag_id`),
  CONSTRAINT `fk_kba_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kba_bon` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_bon_auftraege`
--

LOCK TABLES `kassen_bon_auftraege` WRITE;
/*!40000 ALTER TABLE `kassen_bon_auftraege` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_bon_auftraege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_bon_positionen`
--

DROP TABLE IF EXISTS `kassen_bon_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_bon_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_id` int(10) unsigned NOT NULL,
  `block` enum('auftrag','addon','storno','retour','gutschein_kauf','gutschein_verkauf','zahlung') DEFAULT NULL,
  `web_auftrag_id` int(10) unsigned DEFAULT NULL,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `artikel_gruppe_id` int(10) unsigned DEFAULT NULL,
  `partner_id` int(10) unsigned DEFAULT NULL,
  `partner_modus` enum('kommission','fremdrechnung','spende') DEFAULT NULL,
  `bezeichnung` varchar(300) NOT NULL,
  `ean` varchar(50) DEFAULT NULL,
  `menge` decimal(10,3) NOT NULL DEFAULT 1.000,
  `einzelpreis_brutto` decimal(10,4) NOT NULL,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `steuer_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `charge` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `bon_id` (`bon_id`),
  KEY `fk_kbp_gruppe` (`artikel_gruppe_id`),
  KEY `idx_kbp_partner` (`partner_id`,`partner_modus`),
  CONSTRAINT `fk_kbp_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `kassen_bon_positionen_ibfk_1` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_bon_positionen`
--

LOCK TABLES `kassen_bon_positionen` WRITE;
/*!40000 ALTER TABLE `kassen_bon_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_bon_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_bons`
--

DROP TABLE IF EXISTS `kassen_bons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_bons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_nr` varchar(30) NOT NULL,
  `typ` enum('verkauf','storno','x_bon','z_bon') NOT NULL DEFAULT 'verkauf',
  `kasse_id` int(10) unsigned NOT NULL DEFAULT 1,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `kunden_id` int(10) unsigned DEFAULT NULL,
  `zahlungsart` enum('bar','karte_extern','gutschein','gutschein_ausgabe','kombi') NOT NULL DEFAULT 'bar',
  `bruttobetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fremd_betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `spende_betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `spende_bar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gegeben` decimal(10,2) DEFAULT NULL,
  `rueckgeld` decimal(10,2) DEFAULT NULL,
  `bar_betrag` decimal(10,2) DEFAULT NULL,
  `karten_betrag` decimal(10,2) DEFAULT NULL,
  `gutschein_code` varchar(100) DEFAULT NULL,
  `gutschein_id` int(10) unsigned DEFAULT NULL,
  `gutschein_betrag` decimal(10,2) DEFAULT NULL,
  `rksv_signatur` text DEFAULT NULL,
  `rksv_qr` varchar(500) DEFAULT NULL,
  `steuer_a` decimal(10,2) DEFAULT NULL,
  `steuer_b` decimal(10,2) DEFAULT NULL,
  `steuer_c` decimal(10,2) DEFAULT NULL,
  `steuer_d` decimal(10,2) DEFAULT NULL,
  `steuer_e` decimal(10,2) DEFAULT NULL,
  `signiert_am` datetime DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `storniert` tinyint(1) NOT NULL DEFAULT 0,
  `storno_von_id` int(10) unsigned DEFAULT NULL,
  `gedruckt` tinyint(1) NOT NULL DEFAULT 0,
  `notiz` varchar(500) DEFAULT NULL,
  `web_auftrag_id` int(11) DEFAULT NULL COMMENT 'Referenz zum Original-Webauftrag der an der Kasse abgewickelt wurde',
  `messe_sync_id` int(10) unsigned DEFAULT NULL,
  `handbeleg_nr` varchar(30) DEFAULT NULL,
  `handbeleg_datum` date DEFAULT NULL,
  `paperless_token` varchar(64) DEFAULT NULL COMMENT 'Token auf der Belegseite (48 hex)',
  `paperless_url` varchar(255) DEFAULT NULL COMMENT 'Abruf-Link für den QR-Code',
  `paperless_hochgeladen_am` datetime DEFAULT NULL,
  `paperless_abgerufen_am` datetime DEFAULT NULL COMMENT 'erster Abruf durch den Kunden',
  PRIMARY KEY (`id`),
  UNIQUE KEY `bon_nr` (`bon_nr`),
  KEY `kasse_id` (`kasse_id`),
  KEY `auftrag_id` (`auftrag_id`),
  KEY `kunden_id` (`kunden_id`),
  KEY `benutzer_id` (`benutzer_id`),
  KEY `fk_kassenbons_gutschein` (`gutschein_id`),
  KEY `idx_messe_handbeleg` (`messe_sync_id`,`handbeleg_nr`),
  CONSTRAINT `fk_kassenbons_gutschein` FOREIGN KEY (`gutschein_id`) REFERENCES `gutscheine` (`id`),
  CONSTRAINT `fk_kb_messe_sync` FOREIGN KEY (`messe_sync_id`) REFERENCES `kassen_messe_sync` (`id`),
  CONSTRAINT `kassen_bons_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`),
  CONSTRAINT `kassen_bons_ibfk_2` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`),
  CONSTRAINT `kassen_bons_ibfk_3` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `kassen_bons_ibfk_4` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_bons`
--

LOCK TABLES `kassen_bons` WRITE;
/*!40000 ALTER TABLE `kassen_bons` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_bons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_geparkte_bons`
--

DROP TABLE IF EXISTS `kassen_geparkte_bons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_geparkte_bons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `kassierer_id` int(10) unsigned DEFAULT NULL,
  `kunden_id` int(10) unsigned DEFAULT NULL,
  `kunden_name` varchar(120) DEFAULT NULL,
  `warenkorb` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`warenkorb`)),
  `global_rabatt` decimal(5,2) NOT NULL DEFAULT 0.00,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `notiz` varchar(255) DEFAULT NULL,
  `kontext` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`kontext`)),
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kasse` (`kasse_id`),
  KEY `fk_gpb_kass2` (`kassierer_id`),
  CONSTRAINT `fk_gpb_kass2` FOREIGN KEY (`kassierer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gpb_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_geparkte_bons`
--

LOCK TABLES `kassen_geparkte_bons` WRITE;
/*!40000 ALTER TABLE `kassen_geparkte_bons` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_geparkte_bons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_live_state`
--

DROP TABLE IF EXISTS `kassen_live_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_live_state` (
  `kasse_id` int(10) unsigned NOT NULL,
  `zustand` enum('idle','warenkorb','abrechnen') NOT NULL DEFAULT 'idle',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`kasse_id`),
  CONSTRAINT `fk_kls_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_live_state`
--

LOCK TABLES `kassen_live_state` WRITE;
/*!40000 ALTER TABLE `kassen_live_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_live_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_messe_freitext`
--

DROP TABLE IF EXISTS `kassen_messe_freitext`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_messe_freitext` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sync_id` int(10) unsigned NOT NULL,
  `bezeichnung` varchar(300) NOT NULL,
  `artikel_gruppe_id` int(10) unsigned DEFAULT NULL,
  `menge` int(10) unsigned NOT NULL DEFAULT 1,
  `einzelpreis` decimal(10,2) NOT NULL DEFAULT 0.00,
  `zugabe` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Werbe-Zugabe, 0 €',
  PRIMARY KEY (`id`),
  KEY `idx_sync` (`sync_id`),
  KEY `fk_mft_gruppe` (`artikel_gruppe_id`),
  CONSTRAINT `fk_mft_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mft_sync` FOREIGN KEY (`sync_id`) REFERENCES `kassen_messe_sync` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_messe_freitext`
--

LOCK TABLES `kassen_messe_freitext` WRITE;
/*!40000 ALTER TABLE `kassen_messe_freitext` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_messe_freitext` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_messe_sync`
--

DROP TABLE IF EXISTS `kassen_messe_sync`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_messe_sync` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned DEFAULT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `typ` enum('pre','post') NOT NULL,
  `variante` enum('elektronisch','papier') NOT NULL DEFAULT 'elektronisch',
  `status` enum('vorbereitet','abgeschlossen','fehler') NOT NULL DEFAULT 'vorbereitet',
  `artikel_count` int(10) unsigned NOT NULL DEFAULT 0,
  `bon_count` int(10) unsigned NOT NULL DEFAULT 0,
  `umsatz` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sync_token` varchar(64) NOT NULL,
  `notiz` varchar(500) DEFAULT NULL,
  `differenz_begruendung` varchar(500) DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `abgeschlossen_am` datetime DEFAULT NULL,
  `rueckkehr_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_token` (`sync_token`),
  KEY `kasse_id` (`kasse_id`),
  KEY `lager_id` (`lager_id`),
  KEY `benutzer_id` (`benutzer_id`),
  CONSTRAINT `kassen_messe_sync_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`),
  CONSTRAINT `kassen_messe_sync_ibfk_2` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`),
  CONSTRAINT `kassen_messe_sync_ibfk_3` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_messe_sync`
--

LOCK TABLES `kassen_messe_sync` WRITE;
/*!40000 ALTER TABLE `kassen_messe_sync` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_messe_sync` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_messe_umbuchungen`
--

DROP TABLE IF EXISTS `kassen_messe_umbuchungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_messe_umbuchungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sync_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `bezeichnung` varchar(300) NOT NULL,
  `ean` varchar(50) DEFAULT NULL,
  `charge` varchar(100) DEFAULT NULL,
  `menge_raus` decimal(10,3) NOT NULL,
  `menge_rueck` decimal(10,3) NOT NULL DEFAULT 0.000,
  `menge_strich` decimal(10,3) DEFAULT NULL,
  `menge_verkauft` decimal(10,3) GENERATED ALWAYS AS (`menge_raus` - `menge_rueck`) STORED,
  `menge_schwund` decimal(10,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`id`),
  KEY `sync_id` (`sync_id`),
  KEY `artikel_id` (`artikel_id`),
  CONSTRAINT `kassen_messe_umbuchungen_ibfk_1` FOREIGN KEY (`sync_id`) REFERENCES `kassen_messe_sync` (`id`),
  CONSTRAINT `kassen_messe_umbuchungen_ibfk_2` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_messe_umbuchungen`
--

LOCK TABLES `kassen_messe_umbuchungen` WRITE;
/*!40000 ALTER TABLE `kassen_messe_umbuchungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_messe_umbuchungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassen_schnellwahl`
--

DROP TABLE IF EXISTS `kassen_schnellwahl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_schnellwahl` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `slot` tinyint(3) unsigned NOT NULL COMMENT '1-9, links oben nach rechts unten',
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `artikel_gruppe_id` int(10) unsigned DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL COMMENT 'Überschreibt Artikelname wenn gesetzt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kasse_slot` (`kasse_id`,`slot`),
  KEY `artikel_id` (`artikel_id`),
  KEY `fk_ksw_gruppe` (`artikel_gruppe_id`),
  CONSTRAINT `fk_ksw_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `kassen_schnellwahl_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kassen_schnellwahl_ibfk_2` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassen_schnellwahl`
--

LOCK TABLES `kassen_schnellwahl` WRITE;
/*!40000 ALTER TABLE `kassen_schnellwahl` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassen_schnellwahl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kassenbuch`
--

DROP TABLE IF EXISTS `kassenbuch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassenbuch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('einlage','entnahme','anfangsbestand') NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `notiz` varchar(500) DEFAULT NULL,
  `kasse_id` int(10) unsigned NOT NULL DEFAULT 1,
  `bon_id` int(10) unsigned DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kasse_id` (`kasse_id`),
  KEY `bon_id` (`bon_id`),
  KEY `benutzer_id` (`benutzer_id`),
  CONSTRAINT `kassenbuch_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`),
  CONSTRAINT `kassenbuch_ibfk_2` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`),
  CONSTRAINT `kassenbuch_ibfk_3` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kassenbuch`
--

LOCK TABLES `kassenbuch` WRITE;
/*!40000 ALTER TABLE `kassenbuch` DISABLE KEYS */;
/*!40000 ALTER TABLE `kassenbuch` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kategorie_shops`
--

DROP TABLE IF EXISTS `kategorie_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kategorie_shops` (
  `kategorie_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `ausgeschlossen` tinyint(1) NOT NULL DEFAULT 0,
  `aktion_sichtbarkeit_synced_am` timestamp NULL DEFAULT NULL,
  `externe_kategorie_id` varchar(100) DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `bild_pfad_synced` varchar(255) DEFAULT NULL,
  `bild_external_id` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`kategorie_id`,`shop_id`),
  KEY `fk_katshop_shop` (`shop_id`),
  CONSTRAINT `fk_katshop_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_katshop_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategorie_shops`
--

LOCK TABLES `kategorie_shops` WRITE;
/*!40000 ALTER TABLE `kategorie_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `kategorie_shops` ENABLE KEYS */;
UNLOCK TABLES;

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
  `beschreibung` text DEFAULT NULL,
  `bild_pfad` varchar(255) DEFAULT NULL,
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `ist_aktions_kategorie` tinyint(1) NOT NULL DEFAULT 0,
  `externe_id` varchar(100) DEFAULT NULL,
  `datenquelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kat_parent_id` (`parent_id`),
  CONSTRAINT `fk_kat_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategorien`
--

LOCK TABLES `kategorien` WRITE;
/*!40000 ALTER TABLE `kategorien` DISABLE KEYS */;
/*!40000 ALTER TABLE `kategorien` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kommissions_abrechnungen`
--

DROP TABLE IF EXISTS `kommissions_abrechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kommissions_abrechnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) unsigned NOT NULL,
  `periode_von` date NOT NULL,
  `periode_bis` date NOT NULL,
  `gesamt_verkauft` decimal(10,2) NOT NULL DEFAULT 0.00,
  `provisions_satz` decimal(5,2) NOT NULL DEFAULT 0.00,
  `provision_betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `auszahlung_betrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `abrechnungs_typ` enum('gutschrift','fremdrechnung','info') NOT NULL,
  `belegnummer` varchar(20) DEFAULT NULL,
  `fremdrechnungs_nr` varchar(50) DEFAULT NULL,
  `fremdrechnungs_datum` date DEFAULT NULL,
  `beleg_pfad` varchar(255) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `ausgezahlt_am` date DEFAULT NULL,
  `status` enum('erstellt','ausgezahlt','storniert') NOT NULL DEFAULT 'erstellt',
  PRIMARY KEY (`id`),
  KEY `idx_komabrg_partner` (`partner_id`),
  KEY `idx_komabrg_periode` (`periode_von`,`periode_bis`),
  KEY `idx_komabrg_status` (`status`),
  CONSTRAINT `fk_komabrg_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kommissions_abrechnungen`
--

LOCK TABLES `kommissions_abrechnungen` WRITE;
/*!40000 ALTER TABLE `kommissions_abrechnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kommissions_abrechnungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kontenplan`
--

DROP TABLE IF EXISTS `kontenplan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kontenplan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kontonummer` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `typ` enum('erloes','aufwand','steuer','bank','kasse','verbindlichkeit') NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kontenplan_nr` (`kontonummer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kontenplan`
--

LOCK TABLES `kontenplan` WRITE;
/*!40000 ALTER TABLE `kontenplan` DISABLE KEYS */;
INSERT INTO `kontenplan` VALUES (5,'2700','Kassa','kasse',1),(6,'2800','Bank','bank',1),(7,'2510','Vorsteuer 10%','steuer',1),(8,'2513','Vorsteuer 13%','steuer',1),(9,'2520','Vorsteuer 20%','steuer',1),(10,'3510','Umsatzsteuer 10%','steuer',1),(11,'3513','Umsatzsteuer 13%','steuer',1),(12,'3520','Umsatzsteuer 20%','steuer',1),(13,'3230','Erhaltene Anzahlungen 0% Gutschein','verbindlichkeit',1),(14,'4065','Messe','erloes',1),(15,'4070','Workshops','erloes',1),(17,'2801','PayPal','bank',1),(18,'4890','Mahngebühren','erloes',1),(19,'4800','Mieterlöse Fachmiete','erloes',1),(20,'4080','Erlöse Kommissionsware','erloes',1),(21,'3710','Verrechnung Partner Fremdumsätze','verbindlichkeit',1),(22,'3720','Durchlaufende Spenden','verbindlichkeit',1);
/*!40000 ALTER TABLE `kontenplan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden`
--

DROP TABLE IF EXISTS `kunden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kundennummer` varchar(20) NOT NULL,
  `debitorennummer` varchar(10) DEFAULT NULL,
  `status` enum('aktiv','gesperrt','geloescht') NOT NULL DEFAULT 'aktiv',
  `ist_laufkunde` tinyint(1) NOT NULL DEFAULT 0,
  `ist_firma` tinyint(1) NOT NULL DEFAULT 0,
  `kundengruppe_id` int(10) unsigned DEFAULT NULL,
  `zahlungsbedingung_id` int(10) unsigned DEFAULT NULL,
  `standardzahlungsart` enum('vorkasse','rechnung','kreditkarte','paypal','bar') DEFAULT NULL,
  `kreditlimit` decimal(10,2) DEFAULT NULL,
  `sprache` varchar(5) NOT NULL DEFAULT 'de',
  `kundenherkunft` enum('shop','messe','empfehlung','walkin','kasse','erp','jtl_archiv') NOT NULL DEFAULT 'erp',
  `vorname_enc` blob DEFAULT NULL,
  `nachname_enc` blob DEFAULT NULL,
  `firmenname_enc` blob DEFAULT NULL,
  `email_enc` blob DEFAULT NULL,
  `email_hash` char(64) DEFAULT NULL,
  `telefon_enc` blob DEFAULT NULL,
  `mobil_enc` blob DEFAULT NULL,
  `geburtsdatum_enc` blob DEFAULT NULL,
  `uid_nummer_enc` blob DEFAULT NULL,
  `notiz_enc` blob DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kundennummer` (`kundennummer`),
  UNIQUE KEY `uq_kunden_debitorennummer` (`debitorennummer`),
  KEY `fk_kunde_kg` (`kundengruppe_id`),
  KEY `fk_kunde_zb` (`zahlungsbedingung_id`),
  KEY `idx_email_hash` (`email_hash`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_kunde_kg` FOREIGN KEY (`kundengruppe_id`) REFERENCES `kundengruppen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kunde_zb` FOREIGN KEY (`zahlungsbedingung_id`) REFERENCES `zahlungsbedingungen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden`
--

LOCK TABLES `kunden` WRITE;
/*!40000 ALTER TABLE `kunden` DISABLE KEYS */;
INSERT INTO `kunden` VALUES (1,'LAUFKUNDE',NULL,'aktiv',1,0,NULL,NULL,NULL,NULL,'de','kasse',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-04 09:23:21','2026-11-04 09:23:21');
/*!40000 ALTER TABLE `kunden` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_adressen`
--

DROP TABLE IF EXISTS `kunden_adressen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_adressen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `adresstyp` enum('haupt','rechnung','lieferung') NOT NULL DEFAULT 'haupt',
  `ist_standard` tinyint(1) NOT NULL DEFAULT 0,
  `land` varchar(2) NOT NULL DEFAULT 'AT',
  `firma_enc` blob DEFAULT NULL,
  `vorname_enc` blob DEFAULT NULL,
  `nachname_enc` blob DEFAULT NULL,
  `strasse_enc` blob DEFAULT NULL,
  `hausnummer_enc` blob DEFAULT NULL,
  `plz_enc` blob DEFAULT NULL,
  `ort_enc` blob DEFAULT NULL,
  `zusatz_enc` blob DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kaddr_kunde` (`kunde_id`),
  CONSTRAINT `fk_kaddr_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_adressen`
--

LOCK TABLES `kunden_adressen` WRITE;
/*!40000 ALTER TABLE `kunden_adressen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_adressen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_ansprechpartner`
--

DROP TABLE IF EXISTS `kunden_ansprechpartner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_ansprechpartner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `ist_haupt` tinyint(1) NOT NULL DEFAULT 0,
  `vorname_enc` blob DEFAULT NULL,
  `nachname_enc` blob DEFAULT NULL,
  `position_enc` blob DEFAULT NULL,
  `email_enc` blob DEFAULT NULL,
  `email_hash` char(64) DEFAULT NULL,
  `telefon_enc` blob DEFAULT NULL,
  `notiz_enc` blob DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kansp_kunde` (`kunde_id`),
  CONSTRAINT `fk_kansp_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_ansprechpartner`
--

LOCK TABLES `kunden_ansprechpartner` WRITE;
/*!40000 ALTER TABLE `kunden_ansprechpartner` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_ansprechpartner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_dsgvo_consent`
--

DROP TABLE IF EXISTS `kunden_dsgvo_consent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_dsgvo_consent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `consent_typ` enum('newsletter','marketing','profiling') NOT NULL,
  `eingewilligt` tinyint(1) NOT NULL,
  `eingewilligt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `quelle` enum('shop','messe','kasse','erp_manuell','telefon') NOT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `widerrufen_am` timestamp NULL DEFAULT NULL,
  `kommentar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consent_kunde` (`kunde_id`),
  CONSTRAINT `fk_consent_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_dsgvo_consent`
--

LOCK TABLES `kunden_dsgvo_consent` WRITE;
/*!40000 ALTER TABLE `kunden_dsgvo_consent` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_dsgvo_consent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_jtl_referenzen`
--

DROP TABLE IF EXISTS `kunden_jtl_referenzen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_jtl_referenzen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `jtl_kundennummer` varchar(20) NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jtl_kundennummer` (`jtl_kundennummer`),
  KEY `idx_kjtlref_kunde` (`kunde_id`),
  CONSTRAINT `fk_kjtlref_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_jtl_referenzen`
--

LOCK TABLES `kunden_jtl_referenzen` WRITE;
/*!40000 ALTER TABLE `kunden_jtl_referenzen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_jtl_referenzen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_merge_queue`
--

DROP TABLE IF EXISTS `kunden_merge_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_merge_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_a_id` int(10) unsigned NOT NULL,
  `kunde_b_id` int(10) unsigned NOT NULL,
  `erkannt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erkennungsgrund` varchar(255) NOT NULL,
  `status` enum('offen','gemerged','abgelehnt') NOT NULL DEFAULT 'offen',
  `bearbeitet_von` int(10) unsigned DEFAULT NULL,
  `bearbeitet_am` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_merge_a` (`kunde_a_id`),
  KEY `fk_merge_b` (`kunde_b_id`),
  KEY `idx_merge_status` (`status`),
  CONSTRAINT `fk_merge_a` FOREIGN KEY (`kunde_a_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_merge_b` FOREIGN KEY (`kunde_b_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_merge_queue`
--

LOCK TABLES `kunden_merge_queue` WRITE;
/*!40000 ALTER TABLE `kunden_merge_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_merge_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunden_shops`
--

DROP TABLE IF EXISTS `kunden_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden_shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunde_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `external_id` varchar(255) DEFAULT NULL,
  `sync_status` enum('pending','synced','error') NOT NULL DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `fehler_meldung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kunde_shop` (`kunde_id`,`shop_id`),
  CONSTRAINT `fk_kdshop_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunden_shops`
--

LOCK TABLES `kunden_shops` WRITE;
/*!40000 ALTER TABLE `kunden_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kundengruppen`
--

DROP TABLE IF EXISTS `kundengruppen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kundengruppen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `ist_standard` tinyint(1) NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `typ` enum('endkunde','haendler','vertriebspartner','intern') NOT NULL DEFAULT 'endkunde',
  `rabatt_prozent` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kundengruppen`
--

LOCK TABLES `kundengruppen` WRITE;
/*!40000 ALTER TABLE `kundengruppen` DISABLE KEYS */;
INSERT INTO `kundengruppen` VALUES (1,'Endkunden',1,1,'2026-11-04 09:27:17','endkunde',NULL),(2,'Händler',0,1,'2026-11-04 09:27:17','haendler',NULL);
/*!40000 ALTER TABLE `kundengruppen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laender`
--

DROP TABLE IF EXISTS `laender`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `laender` (
  `iso_code` char(2) NOT NULL,
  `name_de` varchar(100) NOT NULL,
  `ist_eu_mitglied` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`iso_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laender`
--

LOCK TABLES `laender` WRITE;
/*!40000 ALTER TABLE `laender` DISABLE KEYS */;
INSERT INTO `laender` VALUES ('AD','Andorra',0),('AE','Vereinigte Arabische Emirate',0),('AF','Afghanistan',0),('AG','Antigua und Barbuda',0),('AI','Anguilla',0),('AL','Albanien',0),('AM','Armenien',0),('AO','Angola',0),('AQ','Antarktis',0),('AR','Argentinien',0),('AS','Amerikanisch-Samoa',0),('AT','Österreich',1),('AU','Australien',0),('AW','Aruba',0),('AX','Åland',0),('AZ','Aserbaidschan',0),('BA','Bosnien und Herzegowina',0),('BB','Barbados',0),('BD','Bangladesch',0),('BE','Belgien',1),('BF','Burkina Faso',0),('BG','Bulgarien',1),('BH','Bahrain',0),('BI','Burundi',0),('BJ','Benin',0),('BL','Saint-Barthélemy',0),('BM','Bermuda',0),('BN','Brunei Darussalam',0),('BO','Bolivien',0),('BQ','Bonaire, Sint Eustatius und Saba',0),('BR','Brasilien',0),('BS','Bahamas',0),('BT','Bhutan',0),('BV','Bouvetinsel',0),('BW','Botsuana',0),('BY','Belarus (Weißrussland)',0),('BZ','Belize',0),('CA','Kanada',0),('CC','Kokosinseln',0),('CD','Kongo, Demokratische Republik',0),('CF','Zentralafrikanische Republik',0),('CG','Kongo',0),('CH','Schweiz',0),('CI','Elfenbeinküste',0),('CK','Cookinseln',0),('CL','Chile',0),('CM','Kamerun',0),('CN','China',0),('CO','Kolumbien',0),('CR','Costa Rica',0),('CU','Kuba',0),('CV','Kap Verde',0),('CW','Curaçao',0),('CX','Weihnachtsinsel',0),('CY','Zypern',1),('CZ','Tschechien',1),('DE','Deutschland',1),('DJ','Dschibuti',0),('DK','Dänemark',1),('DM','Dominica',0),('DO','Dominikanische Republik',0),('DZ','Algerien',0),('EC','Ecuador',0),('EE','Estland',1),('EG','Ägypten',0),('EH','Westsahara',0),('ER','Eritrea',0),('ES','Spanien',1),('ET','Äthiopien',0),('FI','Finnland',1),('FJ','Fidschi',0),('FK','Falklandinseln',0),('FM','Mikronesien',0),('FO','Färöer',0),('FR','Frankreich',1),('GA','Gabun',0),('GB','Vereinigtes Königreich',0),('GD','Grenada',0),('GE','Georgien',0),('GF','Französisch-Guayana',0),('GG','Guernsey',0),('GH','Ghana',0),('GI','Gibraltar',0),('GL','Grönland',0),('GM','Gambia',0),('GN','Guinea',0),('GP','Guadeloupe',0),('GQ','Äquatorialguinea',0),('GR','Griechenland',1),('GS','Südgeorgien und die Südlichen Sandwichinseln',0),('GT','Guatemala',0),('GU','Guam',0),('GW','Guinea-Bissau',0),('GY','Guyana',0),('HK','Hongkong',0),('HM','Heard- und McDonald-Inseln',0),('HN','Honduras',0),('HR','Kroatien',1),('HT','Haiti',0),('HU','Ungarn',1),('ID','Indonesien',0),('IE','Irland',1),('IL','Israel',0),('IM','Isle of Man',0),('IN','Indien',0),('IO','Britisches Territorium im Indischen Ozean',0),('IQ','Irak',0),('IR','Iran',0),('IS','Island',0),('IT','Italien',1),('JE','Jersey',0),('JM','Jamaika',0),('JO','Jordanien',0),('JP','Japan',0),('KE','Kenia',0),('KG','Kirgisistan',0),('KH','Kambodscha',0),('KI','Kiribati',0),('KM','Komoren',0),('KN','St. Kitts und Nevis',0),('KP','Nordkorea',0),('KR','Südkorea',0),('KW','Kuwait',0),('KY','Kaimaninseln',0),('KZ','Kasachstan',0),('LA','Laos',0),('LB','Libanon',0),('LC','St. Lucia',0),('LI','Liechtenstein',0),('LK','Sri Lanka',0),('LR','Liberia',0),('LS','Lesotho',0),('LT','Litauen',1),('LU','Luxemburg',1),('LV','Lettland',1),('LY','Libyen',0),('MA','Marokko',0),('MC','Monaco',0),('MD','Moldau',0),('ME','Montenegro',0),('MF','Saint-Martin (franz. Teil)',0),('MG','Madagaskar',0),('MH','Marshallinseln',0),('MK','Nordmazedonien',0),('ML','Mali',0),('MM','Myanmar',0),('MN','Mongolei',0),('MO','Macau',0),('MP','Nördliche Marianen',0),('MQ','Martinique',0),('MR','Mauretanien',0),('MS','Montserrat',0),('MT','Malta',1),('MU','Mauritius',0),('MV','Malediven',0),('MW','Malawi',0),('MX','Mexiko',0),('MY','Malaysia',0),('MZ','Mosambik',0),('NA','Namibia',0),('NC','Neukaledonien',0),('NE','Niger',0),('NF','Norfolkinsel',0),('NG','Nigeria',0),('NI','Nicaragua',0),('NL','Niederlande',1),('NO','Norwegen',0),('NP','Nepal',0),('NR','Nauru',0),('NU','Niue',0),('NZ','Neuseeland',0),('OM','Oman',0),('PA','Panama',0),('PE','Peru',0),('PF','Französisch-Polynesien',0),('PG','Papua-Neuguinea',0),('PH','Philippinen',0),('PK','Pakistan',0),('PL','Polen',1),('PM','Saint-Pierre und Miquelon',0),('PN','Pitcairninseln',0),('PR','Puerto Rico',0),('PS','Palästina',0),('PT','Portugal',1),('PW','Palau',0),('PY','Paraguay',0),('QA','Katar',0),('RE','Réunion',0),('RO','Rumänien',1),('RS','Serbien',0),('RU','Russland',0),('RW','Ruanda',0),('SA','Saudi-Arabien',0),('SB','Salomonen',0),('SC','Seychellen',0),('SD','Sudan',0),('SE','Schweden',1),('SG','Singapur',0),('SH','St. Helena',0),('SI','Slowenien',1),('SJ','Svalbard und Jan Mayen',0),('SK','Slowakei',1),('SL','Sierra Leone',0),('SM','San Marino',0),('SN','Senegal',0),('SO','Somalia',0),('SR','Suriname',0),('SS','Südsudan',0),('ST','São Tomé und Príncipe',0),('SV','El Salvador',0),('SX','Sint Maarten (niederl. Teil)',0),('SY','Syrien',0),('SZ','Eswatini (Swasiland)',0),('TC','Turks- und Caicosinseln',0),('TD','Tschad',0),('TF','Französische Süd- und Antarktisgebiete',0),('TG','Togo',0),('TH','Thailand',0),('TJ','Tadschikistan',0),('TK','Tokelau',0),('TL','Timor-Leste (Osttimor)',0),('TM','Turkmenistan',0),('TN','Tunesien',0),('TO','Tonga',0),('TR','Türkei',0),('TT','Trinidad und Tobago',0),('TV','Tuvalu',0),('TW','Taiwan',0),('TZ','Tansania',0),('UA','Ukraine',0),('UG','Uganda',0),('UM','Amerikanische Überseeinseln, kleinere',0),('US','Vereinigte Staaten',0),('UY','Uruguay',0),('UZ','Usbekistan',0),('VA','Vatikanstadt',0),('VC','St. Vincent und die Grenadinen',0),('VE','Venezuela',0),('VG','Britische Jungferninseln',0),('VI','Amerikanische Jungferninseln',0),('VN','Vietnam',0),('VU','Vanuatu',0),('WF','Wallis und Futuna',0),('WS','Samoa',0),('YE','Jemen',0),('YT','Mayotte',0),('ZA','Südafrika',0),('ZM','Sambia',0),('ZW','Simbabwe',0);
/*!40000 ALTER TABLE `laender` ENABLE KEYS */;
UNLOCK TABLES;

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
  `fuer_offline_kasse_waehlbar` tinyint(1) NOT NULL DEFAULT 0,
  `lager_beziehung` enum('eigen','partner_bestand','haendler_aussenlager') NOT NULL DEFAULT 'eigen',
  `partner_id` int(10) unsigned DEFAULT NULL,
  `kunde_id` int(10) unsigned DEFAULT NULL,
  `abholort_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lager_partner` (`partner_id`),
  KEY `fk_lager_partner` (`partner_id`),
  KEY `fk_lager_kunde` (`kunde_id`),
  KEY `fk_lager_abholort` (`abholort_id`),
  CONSTRAINT `fk_lager_abholort` FOREIGN KEY (`abholort_id`) REFERENCES `abholorte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lager_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `fk_lager_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lager`
--

LOCK TABLES `lager` WRITE;
/*!40000 ALTER TABLE `lager` DISABLE KEYS */;
/*!40000 ALTER TABLE `lager` ENABLE KEYS */;
UNLOCK TABLES;

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
  `bewegungstyp` enum('eingang','ausgang','korrektur','inventur','schwund') NOT NULL DEFAULT 'ausgang',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lager_bewegungen`
--

LOCK TABLES `lager_bewegungen` WRITE;
/*!40000 ALTER TABLE `lager_bewegungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `lager_bewegungen` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerbestand`
--

LOCK TABLES `lagerbestand` WRITE;
/*!40000 ALTER TABLE `lagerbestand` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerbestand` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lagerbestand_lagerplaetze`
--

DROP TABLE IF EXISTS `lagerbestand_lagerplaetze`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerbestand_lagerplaetze` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lagerbestand_id` int(10) unsigned NOT NULL,
  `lagerplatz_id` int(10) unsigned NOT NULL,
  `menge` decimal(8,3) NOT NULL DEFAULT 0.000,
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lbl_bestand_platz` (`lagerbestand_id`,`lagerplatz_id`),
  KEY `fk_lbl_platz` (`lagerplatz_id`),
  CONSTRAINT `fk_lbl_bestand` FOREIGN KEY (`lagerbestand_id`) REFERENCES `lagerbestand` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lbl_platz` FOREIGN KEY (`lagerplatz_id`) REFERENCES `lagerplaetze` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerbestand_lagerplaetze`
--

LOCK TABLES `lagerbestand_lagerplaetze` WRITE;
/*!40000 ALTER TABLE `lagerbestand_lagerplaetze` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerbestand_lagerplaetze` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lagerplaetze`
--

DROP TABLE IF EXISTS `lagerplaetze`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerplaetze` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lager_id` int(10) unsigned NOT NULL,
  `mietfach_id` int(10) unsigned DEFAULT NULL,
  `bereich` varchar(20) DEFAULT NULL,
  `regal` varchar(10) DEFAULT NULL,
  `fach` varchar(10) DEFAULT NULL,
  `bezeichnung` varchar(50) NOT NULL,
  `sortierung` varchar(60) NOT NULL DEFAULT '',
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lagerplatz_bezeichnung` (`lager_id`,`bezeichnung`),
  UNIQUE KEY `uq_lagerplatz_mietfach` (`mietfach_id`),
  CONSTRAINT `fk_lagerplatz_lager` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lagerplatz_mietfach` FOREIGN KEY (`mietfach_id`) REFERENCES `mietfaecher` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerplaetze`
--

LOCK TABLES `lagerplaetze` WRITE;
/*!40000 ALTER TABLE `lagerplaetze` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerplaetze` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lagerwert_snapshot_lager`
--

DROP TABLE IF EXISTS `lagerwert_snapshot_lager`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerwert_snapshot_lager` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_id` int(11) NOT NULL,
  `lager_id` int(11) NOT NULL,
  `lager_name` varchar(150) NOT NULL,
  `lager_beziehung` varchar(30) NOT NULL,
  `wert` decimal(14,2) NOT NULL DEFAULT 0.00,
  `menge` decimal(14,3) NOT NULL DEFAULT 0.000,
  `artikel_anzahl` int(11) NOT NULL DEFAULT 0,
  `artikel_ohne_ek` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  CONSTRAINT `fk_lwsl_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `lagerwert_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerwert_snapshot_lager`
--

LOCK TABLES `lagerwert_snapshot_lager` WRITE;
/*!40000 ALTER TABLE `lagerwert_snapshot_lager` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerwert_snapshot_lager` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lagerwert_snapshot_positionen`
--

DROP TABLE IF EXISTS `lagerwert_snapshot_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerwert_snapshot_positionen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snapshot_id` int(11) NOT NULL,
  `artikel_id` int(11) NOT NULL,
  `artikelnummer` varchar(100) NOT NULL,
  `artikel_name` varchar(255) NOT NULL,
  `lager_id` int(11) NOT NULL,
  `menge` decimal(14,3) NOT NULL,
  `ek_netto` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `ek_quelle` enum('wareneingang','standardlieferant','lieferant','vater','keiner') NOT NULL,
  `wert` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  CONSTRAINT `fk_lwsp_snapshot` FOREIGN KEY (`snapshot_id`) REFERENCES `lagerwert_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerwert_snapshot_positionen`
--

LOCK TABLES `lagerwert_snapshot_positionen` WRITE;
/*!40000 ALTER TABLE `lagerwert_snapshot_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerwert_snapshot_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lagerwert_snapshots`
--

DROP TABLE IF EXISTS `lagerwert_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lagerwert_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stichtag` datetime NOT NULL,
  `anlass` enum('monatsende','inventur_start','inventur_abschluss') NOT NULL,
  `inventur_lauf_id` int(11) DEFAULT NULL,
  `wert_eigen` decimal(14,2) NOT NULL DEFAULT 0.00,
  `wert_haendler` decimal(14,2) NOT NULL DEFAULT 0.00,
  `menge_gesamt` decimal(14,3) NOT NULL DEFAULT 0.000,
  `artikel_anzahl` int(11) NOT NULL DEFAULT 0,
  `artikel_ohne_ek` int(11) NOT NULL DEFAULT 0,
  `benutzer_id` int(11) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stichtag` (`stichtag`),
  KEY `idx_lauf` (`inventur_lauf_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lagerwert_snapshots`
--

LOCK TABLES `lagerwert_snapshots` WRITE;
/*!40000 ALTER TABLE `lagerwert_snapshots` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerwert_snapshots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lieferanten`
--

DROP TABLE IF EXISTS `lieferanten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lieferanten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `firma` varchar(255) DEFAULT NULL,
  `firmenzusatz` varchar(255) DEFAULT NULL,
  `land` char(2) NOT NULL DEFAULT 'AT',
  `strasse` varchar(255) DEFAULT NULL,
  `plz` varchar(20) DEFAULT NULL,
  `ort` varchar(100) DEFAULT NULL,
  `kundennummer` varchar(100) DEFAULT NULL COMMENT 'Unsere Kundennummer beim Lieferanten',
  `kreditorennummer` varchar(10) DEFAULT NULL,
  `ustid` varchar(30) DEFAULT NULL COMMENT 'USt-IdNr., z.B. ATU12345678',
  `steuerregel` enum('inland','eu_igl','drittland_einfuhr','reverse_charge') NOT NULL DEFAULT 'inland',
  `waehrung` char(3) NOT NULL DEFAULT 'EUR',
  `zahlungsziel_tage` smallint(5) unsigned DEFAULT NULL COMMENT 'Zahlungsziel in Tagen, z.B. 30',
  `skonto_prozent` decimal(5,2) DEFAULT NULL COMMENT 'Skonto-Prozentsatz, z.B. 2.00',
  `skonto_tage` smallint(5) unsigned DEFAULT NULL COMMENT 'Tage für Skonto-Abzug, z.B. 14',
  `mindestbestellwert` decimal(10,2) DEFAULT NULL,
  `standard_lieferkosten` decimal(10,2) DEFAULT NULL COMMENT 'Vorbelegung für Bestellung, dort überschreibbar',
  `lieferzeit_tage` smallint(5) unsigned DEFAULT NULL COMMENT 'Standard-Lieferzeit in Tagen',
  `lieferbedingung` varchar(50) DEFAULT NULL COMMENT 'frei_haus | ab_werk | ab_lager | sonstige',
  `dropshipping_aktiv` tinyint(1) NOT NULL DEFAULT 0,
  `dropship_pauschale` decimal(10,2) DEFAULT NULL COMMENT 'Netto-Pauschale pro Dropship-Lieferung, Vorgabe für die Bestellung',
  `interne_notizen` text DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `bic` varchar(11) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `kontoinhaber` varchar(255) DEFAULT NULL COMMENT 'nur befüllen wenn abweichend von Firma',
  `website` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefon` varchar(255) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lieferant_land` (`land`),
  CONSTRAINT `fk_lieferant_land` FOREIGN KEY (`land`) REFERENCES `laender` (`iso_code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lieferanten`
--

LOCK TABLES `lieferanten` WRITE;
/*!40000 ALTER TABLE `lieferanten` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lieferanten_guthaben_bewegungen`
--

DROP TABLE IF EXISTS `lieferanten_guthaben_bewegungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lieferanten_guthaben_bewegungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
  `bestellung_id` int(10) unsigned DEFAULT NULL COMMENT 'Bestellung die die Bewegung ausgelöst hat',
  `betrag` decimal(10,2) NOT NULL COMMENT 'positiv = Zugang, negativ = Verbrauch',
  `typ` enum('gutschrift_erhalten','verrechnet') NOT NULL,
  `datum` date NOT NULL,
  `notiz` varchar(255) DEFAULT NULL,
  `erfasst_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erfasst_von` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_liefguth_lieferant` (`lieferant_id`),
  KEY `fk_liefguth_bestellung` (`bestellung_id`),
  KEY `fk_liefguth_benutzer` (`erfasst_von`),
  CONSTRAINT `fk_liefguth_benutzer` FOREIGN KEY (`erfasst_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_liefguth_bestellung` FOREIGN KEY (`bestellung_id`) REFERENCES `bestellungen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_liefguth_lieferant` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lieferanten_guthaben_bewegungen`
--

LOCK TABLES `lieferanten_guthaben_bewegungen` WRITE;
/*!40000 ALTER TABLE `lieferanten_guthaben_bewegungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten_guthaben_bewegungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lieferanten_vertreter`
--

DROP TABLE IF EXISTS `lieferanten_vertreter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lieferanten_vertreter` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
  `anrede` enum('herr','frau','divers') DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lieferanten_vertreter`
--

LOCK TABLES `lieferanten_vertreter` WRITE;
/*!40000 ALTER TABLE `lieferanten_vertreter` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten_vertreter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lieferanten_zugaenge`
--

DROP TABLE IF EXISTS `lieferanten_zugaenge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lieferanten_zugaenge` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
  `bezeichnung` varchar(100) NOT NULL COMMENT 'z.B. Bestellportal, Händlerlogin, FTP',
  `url` varchar(500) DEFAULT NULL,
  `benutzername` varchar(255) DEFAULT NULL,
  `passwort_enc` blob DEFAULT NULL COMMENT 'AES-256-GCM verschlüsselt',
  `notizen` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lzug_lieferant` (`lieferant_id`),
  CONSTRAINT `fk_lzug_lieferant` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lieferanten_zugaenge`
--

LOCK TABLES `lieferanten_zugaenge` WRITE;
/*!40000 ALTER TABLE `lieferanten_zugaenge` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten_zugaenge` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lizenz_ausgaben`
--

DROP TABLE IF EXISTS `lizenz_ausgaben`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lizenz_ausgaben` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lizenz_kunde_id` int(10) unsigned NOT NULL,
  `ausgabe` int(10) unsigned NOT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `module` text NOT NULL COMMENT 'JSON {code: anzahl}',
  `wartung_bis` date DEFAULT NULL COMMENT 'NULL = unbegrenzt (Hauslizenz)',
  `hauslizenz` tinyint(1) NOT NULL DEFAULT 0,
  `grund` varchar(255) DEFAULT NULL,
  `inhalt` text NOT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lizenz_ausgabe` (`lizenz_kunde_id`,`ausgabe`),
  KEY `fk_ausgabe_auftrag` (`auftrag_id`),
  CONSTRAINT `fk_ausgabe_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ausgabe_kunde` FOREIGN KEY (`lizenz_kunde_id`) REFERENCES `lizenz_kunden` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lizenz_ausgaben`
--

LOCK TABLES `lizenz_ausgaben` WRITE;
/*!40000 ALTER TABLE `lizenz_ausgaben` DISABLE KEYS */;
/*!40000 ALTER TABLE `lizenz_ausgaben` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lizenz_dateien`
--

DROP TABLE IF EXISTS `lizenz_dateien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lizenz_dateien` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lizenz_nr` varchar(30) NOT NULL,
  `inhalt` text NOT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 0,
  `eingespielt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `eingespielt_von` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lizenz_aktiv` (`aktiv`),
  KEY `fk_lizenz_benutzer` (`eingespielt_von`),
  CONSTRAINT `fk_lizenz_benutzer` FOREIGN KEY (`eingespielt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lizenz_dateien`
--

LOCK TABLES `lizenz_dateien` WRITE;
/*!40000 ALTER TABLE `lizenz_dateien` DISABLE KEYS */;
/*!40000 ALTER TABLE `lizenz_dateien` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lizenz_kunden`
--

DROP TABLE IF EXISTS `lizenz_kunden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lizenz_kunden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lizenz_nr` varchar(20) NOT NULL,
  `produkt` char(2) NOT NULL DEFAULT '10',
  `laufnr` int(10) unsigned NOT NULL,
  `kunde_id` int(10) unsigned DEFAULT NULL,
  `firma` varchar(255) NOT NULL,
  `uid` varchar(30) DEFAULT NULL,
  `steuernummer` varchar(50) DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lizenz_nr` (`lizenz_nr`),
  UNIQUE KEY `uk_lizenz_laufnr` (`laufnr`),
  KEY `idx_lizenz_kunde` (`kunde_id`),
  CONSTRAINT `fk_lizenz_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lizenz_kunden`
--

LOCK TABLES `lizenz_kunden` WRITE;
/*!40000 ALTER TABLE `lizenz_kunden` DISABLE KEYS */;
/*!40000 ALTER TABLE `lizenz_kunden` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mahnungen`
--

DROP TABLE IF EXISTS `mahnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mahnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(11) NOT NULL,
  `typ` enum('erinnerung','stornierung','hinweis','mahnung1','mahnung2') NOT NULL,
  `status` enum('vorgeschlagen','versendet','verworfen') NOT NULL DEFAULT 'versendet',
  `vorgeschlagen_am` datetime DEFAULT NULL,
  `gesendet_am` datetime DEFAULT current_timestamp(),
  `mail_an` varchar(255) DEFAULT NULL,
  `offen_betrag` decimal(10,2) DEFAULT NULL COMMENT 'offener Rechnungsbetrag beim Versand',
  `gebuehr` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gebuehr_erlassen_am` datetime DEFAULT NULL,
  `neue_frist` date DEFAULT NULL,
  `dateiname` varchar(255) DEFAULT NULL,
  `erstellt_von` enum('cronjob','manuell') NOT NULL DEFAULT 'cronjob',
  `bearbeitet_von` int(11) DEFAULT NULL COMMENT 'wer freigegeben/verworfen/erlassen hat',
  PRIMARY KEY (`id`),
  KEY `idx_auftrag` (`auftrag_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mahnungen`
--

LOCK TABLES `mahnungen` WRITE;
/*!40000 ALTER TABLE `mahnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `mahnungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merkmal_artikeltypen`
--

DROP TABLE IF EXISTS `merkmal_artikeltypen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merkmal_artikeltypen` (
  `merkmal_id` int(10) unsigned NOT NULL,
  `artikeltyp_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`merkmal_id`,`artikeltyp_id`),
  KEY `fk_mat_artikeltyp` (`artikeltyp_id`),
  CONSTRAINT `fk_mat_artikeltyp` FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mat_merkmal` FOREIGN KEY (`merkmal_id`) REFERENCES `merkmale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merkmal_artikeltypen`
--

LOCK TABLES `merkmal_artikeltypen` WRITE;
/*!40000 ALTER TABLE `merkmal_artikeltypen` DISABLE KEYS */;
/*!40000 ALTER TABLE `merkmal_artikeltypen` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merkmal_gruppen`
--

LOCK TABLES `merkmal_gruppen` WRITE;
/*!40000 ALTER TABLE `merkmal_gruppen` DISABLE KEYS */;
/*!40000 ALTER TABLE `merkmal_gruppen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `merkmal_werte`
--

DROP TABLE IF EXISTS `merkmal_werte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merkmal_werte` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merkmal_id` int(10) unsigned NOT NULL,
  `wert` varchar(255) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_mw_merkmal` (`merkmal_id`),
  CONSTRAINT `fk_mw_merkmal` FOREIGN KEY (`merkmal_id`) REFERENCES `merkmale` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merkmal_werte`
--

LOCK TABLES `merkmal_werte` WRITE;
/*!40000 ALTER TABLE `merkmal_werte` DISABLE KEYS */;
/*!40000 ALTER TABLE `merkmal_werte` ENABLE KEYS */;
UNLOCK TABLES;

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
  `slug` varchar(80) NOT NULL DEFAULT '',
  `einheit` varchar(50) NOT NULL,
  `datentyp` enum('text','zahl','bool') DEFAULT NULL,
  `filterbar` tinyint(1) DEFAULT NULL,
  `mehrfach_auswahl` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_merkmal_gruppen_id` (`merkmal_gruppen_id`),
  CONSTRAINT `fk_merkmal_gruppen_id` FOREIGN KEY (`merkmal_gruppen_id`) REFERENCES `merkmal_gruppen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merkmale`
--

LOCK TABLES `merkmale` WRITE;
/*!40000 ALTER TABLE `merkmale` DISABLE KEYS */;
/*!40000 ALTER TABLE `merkmale` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `miet_rechnungen`
--

DROP TABLE IF EXISTS `miet_rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `miet_rechnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vertrag_id` int(10) unsigned DEFAULT NULL,
  `fach_id` int(10) unsigned NOT NULL,
  `partner_id` int(10) unsigned NOT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `rechnung_id` int(10) unsigned DEFAULT NULL,
  `periode` char(7) NOT NULL,
  `betrag_netto` decimal(10,2) NOT NULL,
  `mwst_satz` decimal(5,2) NOT NULL,
  `betrag_brutto` decimal(10,2) NOT NULL,
  `rechnungs_nr` varchar(20) NOT NULL,
  `erstellt_am` date NOT NULL,
  `faellig_am` date NOT NULL,
  `bezahlt_am` date DEFAULT NULL,
  `status` enum('offen','bezahlt','storniert') NOT NULL DEFAULT 'offen',
  `beleg_pfad` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mietrg_vertrag_periode` (`vertrag_id`,`periode`),
  KEY `fk_mietrg_fach` (`fach_id`),
  KEY `idx_mietrg_periode` (`periode`),
  KEY `idx_mietrg_partner` (`partner_id`),
  KEY `idx_mietrg_status` (`status`),
  KEY `idx_mietrg_auftrag` (`auftrag_id`),
  CONSTRAINT `fk_mietrg_fach` FOREIGN KEY (`fach_id`) REFERENCES `mietfaecher` (`id`),
  CONSTRAINT `fk_mietrg_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `miet_rechnungen`
--

LOCK TABLES `miet_rechnungen` WRITE;
/*!40000 ALTER TABLE `miet_rechnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `miet_rechnungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mietfach_mietvertraege`
--

DROP TABLE IF EXISTS `mietfach_mietvertraege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mietfach_mietvertraege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mietfach_id` int(10) unsigned NOT NULL,
  `partner_id` int(10) unsigned NOT NULL,
  `mietbetrag_monatlich` decimal(10,2) NOT NULL,
  `mwst_satz` decimal(5,2) NOT NULL DEFAULT 20.00,
  `mietbeginn` date NOT NULL,
  `mietende` date DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mv_fach` (`mietfach_id`),
  KEY `idx_mv_partner` (`partner_id`),
  CONSTRAINT `fk_mv_fach` FOREIGN KEY (`mietfach_id`) REFERENCES `mietfaecher` (`id`),
  CONSTRAINT `fk_mv_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mietfach_mietvertraege`
--

LOCK TABLES `mietfach_mietvertraege` WRITE;
/*!40000 ALTER TABLE `mietfach_mietvertraege` DISABLE KEYS */;
/*!40000 ALTER TABLE `mietfach_mietvertraege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mietfaecher`
--

DROP TABLE IF EXISTS `mietfaecher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mietfaecher` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fach_bezeichnung` varchar(50) NOT NULL,
  `ort_beschreibung` varchar(200) DEFAULT NULL,
  `laenge_cm` decimal(6,1) DEFAULT NULL,
  `breite_cm` decimal(6,1) DEFAULT NULL,
  `hoehe_cm` decimal(6,1) DEFAULT NULL,
  `standard_preis` decimal(10,2) DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mietf_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mietfaecher`
--

LOCK TABLES `mietfaecher` WRITE;
/*!40000 ALTER TABLE `mietfaecher` DISABLE KEYS */;
/*!40000 ALTER TABLE `mietfaecher` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offene_auswahl`
--

DROP TABLE IF EXISTS `offene_auswahl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offene_auswahl` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kunden_name` varchar(200) DEFAULT NULL,
  `kunden_id` int(10) unsigned DEFAULT NULL,
  `lager_id` int(10) unsigned NOT NULL DEFAULT 1,
  `positionen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`positionen`)),
  `ausgegeben_am` datetime NOT NULL DEFAULT current_timestamp(),
  `rueckgabe_bis` date DEFAULT NULL,
  `status` enum('offen','gekauft','zurueck') NOT NULL DEFAULT 'offen',
  `bon_id` int(10) unsigned DEFAULT NULL,
  `notiz` varchar(500) DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kunden_id` (`kunden_id`),
  KEY `bon_id` (`bon_id`),
  KEY `benutzer_id` (`benutzer_id`),
  CONSTRAINT `offene_auswahl_ibfk_1` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `offene_auswahl_ibfk_2` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`),
  CONSTRAINT `offene_auswahl_ibfk_3` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offene_auswahl`
--

LOCK TABLES `offene_auswahl` WRITE;
/*!40000 ALTER TABLE `offene_auswahl` DISABLE KEYS */;
/*!40000 ALTER TABLE `offene_auswahl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packplatz_ruecklagerungen`
--

DROP TABLE IF EXISTS `packplatz_ruecklagerungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packplatz_ruecklagerungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quelle` enum('kasse','gutschrift') NOT NULL DEFAULT 'kasse',
  `kassen_bon_id` int(10) unsigned DEFAULT NULL,
  `bon_nr` varchar(30) DEFAULT NULL,
  `gutschrift_nr` varchar(30) DEFAULT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `auftrag_nr` varchar(20) DEFAULT NULL,
  `auftrag_position_id` int(10) unsigned DEFAULT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `menge` int(10) unsigned NOT NULL,
  `charge` varchar(50) DEFAULT NULL,
  `lager_vorschlag_id` int(10) unsigned DEFAULT NULL,
  `kasse_id` int(10) unsigned DEFAULT NULL,
  `status` enum('offen','erledigt') NOT NULL DEFAULT 'offen',
  `erledigt_am` datetime DEFAULT NULL,
  `erledigt_von` int(10) unsigned DEFAULT NULL,
  `erledigt_lager_id` int(10) unsigned DEFAULT NULL,
  `erledigt_zustand` enum('neu','gebraucht','retour','beschaedigt','defekt') DEFAULT NULL,
  `erledigt_artikel_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_bon` (`kassen_bon_id`),
  KEY `fk_pr_auftrag` (`auftrag_id`),
  KEY `fk_pr_artikel` (`artikel_id`),
  KEY `fk_pr_kasse` (`kasse_id`),
  KEY `fk_pr_erledigt_von` (`erledigt_von`),
  KEY `fk_pr_erledigt_lager` (`erledigt_lager_id`),
  CONSTRAINT `fk_pr_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`),
  CONSTRAINT `fk_pr_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`),
  CONSTRAINT `fk_pr_bon` FOREIGN KEY (`kassen_bon_id`) REFERENCES `kassen_bons` (`id`),
  CONSTRAINT `fk_pr_erledigt_lager` FOREIGN KEY (`erledigt_lager_id`) REFERENCES `lager` (`id`),
  CONSTRAINT `fk_pr_erledigt_von` FOREIGN KEY (`erledigt_von`) REFERENCES `benutzer` (`id`),
  CONSTRAINT `fk_pr_kasse` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packplatz_ruecklagerungen`
--

LOCK TABLES `packplatz_ruecklagerungen` WRITE;
/*!40000 ALTER TABLE `packplatz_ruecklagerungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `packplatz_ruecklagerungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partner`
--

DROP TABLE IF EXISTS `partner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `strasse` varchar(200) DEFAULT NULL,
  `plz` varchar(10) DEFAULT NULL,
  `ort` varchar(100) DEFAULT NULL,
  `land` char(2) NOT NULL DEFAULT 'AT',
  `typ` enum('fremdrechnung','kommission','spende') NOT NULL DEFAULT 'fremdrechnung',
  `email` varchar(200) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `uid_nummer` varchar(30) DEFAULT NULL,
  `zvr_nummer` varchar(30) DEFAULT NULL,
  `kleinunternehmer` tinyint(1) NOT NULL DEFAULT 0,
  `ku_text` varchar(255) DEFAULT NULL,
  `kunde_id` int(10) unsigned DEFAULT NULL,
  `lieferant_id` int(10) unsigned DEFAULT NULL,
  `provisions_satz` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notiz` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partner_typ` (`typ`),
  KEY `idx_partner_aktiv` (`aktiv`),
  KEY `fk_partner_kunde` (`kunde_id`),
  KEY `fk_partner_lieferant` (`lieferant_id`),
  CONSTRAINT `fk_partner_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_partner_lieferant` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner`
--

LOCK TABLES `partner` WRITE;
/*!40000 ALTER TABLE `partner` DISABLE KEYS */;
/*!40000 ALTER TABLE `partner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partner_auszahlungen`
--

DROP TABLE IF EXISTS `partner_auszahlungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_auszahlungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) unsigned NOT NULL,
  `nummer` varchar(30) NOT NULL,
  `datum` date NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `art` enum('bar_kasse','spendenlade','ueberweisung') NOT NULL,
  `zeitraum_von` date DEFAULT NULL,
  `zeitraum_bis` date DEFAULT NULL,
  `kasse_id` int(10) unsigned DEFAULT NULL,
  `kassenbuch_id` int(10) unsigned DEFAULT NULL,
  `notiz` varchar(500) DEFAULT NULL,
  `storniert` tinyint(1) NOT NULL DEFAULT 0,
  `storno_kassenbuch_id` int(10) unsigned DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pa_nummer` (`nummer`),
  KEY `idx_pa_partner` (`partner_id`,`storniert`),
  CONSTRAINT `fk_pa_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_auszahlungen`
--

LOCK TABLES `partner_auszahlungen` WRITE;
/*!40000 ALTER TABLE `partner_auszahlungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `partner_auszahlungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partner_beleg_positionen`
--

DROP TABLE IF EXISTS `partner_beleg_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_beleg_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `beleg_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `artikelnummer` varchar(50) NOT NULL,
  `bezeichnung` varchar(300) NOT NULL,
  `menge` decimal(10,3) NOT NULL,
  `charge` varchar(255) DEFAULT NULL,
  `lagerplatz_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pbp_beleg` (`beleg_id`),
  KEY `fk_pbp_artikel` (`artikel_id`),
  CONSTRAINT `fk_pbp_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`),
  CONSTRAINT `fk_pbp_beleg` FOREIGN KEY (`beleg_id`) REFERENCES `partner_belege` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_beleg_positionen`
--

LOCK TABLES `partner_beleg_positionen` WRITE;
/*!40000 ALTER TABLE `partner_beleg_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `partner_beleg_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `partner_belege`
--

DROP TABLE IF EXISTS `partner_belege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_belege` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) unsigned NOT NULL,
  `typ` enum('uebernahme','rueckgabe') NOT NULL,
  `nummer` varchar(30) NOT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `notiz` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partner_beleg_nummer` (`nummer`),
  KEY `idx_partner_beleg_partner` (`partner_id`,`erstellt_am`),
  KEY `fk_pb_lager` (`lager_id`),
  CONSTRAINT `fk_pb_lager` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`),
  CONSTRAINT `fk_pb_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_belege`
--

LOCK TABLES `partner_belege` WRITE;
/*!40000 ALTER TABLE `partner_belege` DISABLE KEYS */;
/*!40000 ALTER TABLE `partner_belege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pickliste_auftraege`
--

DROP TABLE IF EXISTS `pickliste_auftraege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pickliste_auftraege` (
  `pickliste_id` int(10) unsigned NOT NULL,
  `auftrag_id` int(11) NOT NULL,
  PRIMARY KEY (`pickliste_id`,`auftrag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pickliste_auftraege`
--

LOCK TABLES `pickliste_auftraege` WRITE;
/*!40000 ALTER TABLE `pickliste_auftraege` DISABLE KEYS */;
/*!40000 ALTER TABLE `pickliste_auftraege` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `picklisten`
--

DROP TABLE IF EXISTS `picklisten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `picklisten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nummer` varchar(30) NOT NULL,
  `status` enum('offen','gedruckt','abgeschlossen') NOT NULL DEFAULT 'offen',
  `erstellt_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `abgeschlossen_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nummer` (`nummer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `picklisten`
--

LOCK TABLES `picklisten` WRITE;
/*!40000 ALTER TABLE `picklisten` DISABLE KEYS */;
/*!40000 ALTER TABLE `picklisten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `position_konfiguration`
--

DROP TABLE IF EXISTS `position_konfiguration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `position_konfiguration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `referenz_tabelle` varchar(50) NOT NULL,
  `referenz_id` int(10) unsigned NOT NULL,
  `achse_id` int(10) unsigned NOT NULL,
  `wert_id` int(10) unsigned NOT NULL,
  `wert_text` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_posKonfig_achse` (`achse_id`),
  KEY `fk_posKonfig_wert` (`wert_id`),
  KEY `idx_poskonfig_referenz` (`referenz_tabelle`,`referenz_id`),
  CONSTRAINT `fk_posKonfig_achse` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`),
  CONSTRAINT `fk_posKonfig_wert` FOREIGN KEY (`wert_id`) REFERENCES `varianten_achse_werte` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `position_konfiguration`
--

LOCK TABLES `position_konfiguration` WRITE;
/*!40000 ALTER TABLE `position_konfiguration` DISABLE KEYS */;
/*!40000 ALTER TABLE `position_konfiguration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preis_aktionen_positionen`
--

DROP TABLE IF EXISTS `preis_aktionen_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preis_aktionen_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `kundengruppen_id` int(10) unsigned DEFAULT NULL,
  `brutto_vk` decimal(8,2) NOT NULL,
  `netto_vk` decimal(8,2) NOT NULL,
  `gueltig_ab` datetime DEFAULT NULL,
  `gueltig_bis` datetime DEFAULT NULL,
  `bis_lagerstand_null` tinyint(1) NOT NULL DEFAULT 0,
  `preis_vorher_brutto` decimal(8,2) DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_prAktPos_artikel_id` (`artikel_id`),
  KEY `fk_prAktPos_kg_id` (`kundengruppen_id`),
  CONSTRAINT `fk_prAktPos_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_prAktPos_kg_id` FOREIGN KEY (`kundengruppen_id`) REFERENCES `kundengruppen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preis_aktionen_positionen`
--

LOCK TABLES `preis_aktionen_positionen` WRITE;
/*!40000 ALTER TABLE `preis_aktionen_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `preis_aktionen_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rechnung_positionen`
--

DROP TABLE IF EXISTS `rechnung_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rechnung_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rechnung_id` int(10) unsigned NOT NULL,
  `auftrag_position_id` int(10) unsigned DEFAULT NULL,
  `artikel_id` int(10) unsigned DEFAULT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `menge` int(11) NOT NULL,
  `einzelpreis_netto` decimal(10,4) NOT NULL,
  `steuer_prozent` decimal(5,2) NOT NULL,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `preisbasis` enum('brutto','netto') NOT NULL DEFAULT 'brutto',
  `netto` decimal(10,2) NOT NULL,
  `steuer` decimal(10,2) NOT NULL,
  `brutto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rp_rechnung` (`rechnung_id`),
  KEY `idx_rp_position` (`auftrag_position_id`),
  CONSTRAINT `fk_rp_position` FOREIGN KEY (`auftrag_position_id`) REFERENCES `auftrag_positionen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rp_rechnung` FOREIGN KEY (`rechnung_id`) REFERENCES `rechnungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rechnung_positionen`
--

LOCK TABLES `rechnung_positionen` WRITE;
/*!40000 ALTER TABLE `rechnung_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `rechnung_positionen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rechnungen`
--

DROP TABLE IF EXISTS `rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rechnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rechnung_nr` varchar(20) NOT NULL,
  `auftrag_id` int(10) unsigned NOT NULL,
  `lieferung_id` int(10) unsigned DEFAULT NULL,
  `nettobetrag` decimal(10,2) NOT NULL,
  `steuerbetrag` decimal(10,2) NOT NULL,
  `bruttobetrag` decimal(10,2) NOT NULL,
  `versandkosten_brutto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `faellig_am` date DEFAULT NULL,
  `leistungsdatum` date DEFAULT NULL,
  `dateiname` varchar(255) DEFAULT NULL,
  `ausstaendig_json` text DEFAULT NULL,
  `storniert` tinyint(1) NOT NULL DEFAULT 0,
  `storno_von` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rechnung_nr` (`rechnung_nr`),
  KEY `fk_re_auftrag` (`auftrag_id`),
  KEY `fk_re_storno` (`storno_von`),
  KEY `fk_re_benutzer` (`erstellt_von`),
  KEY `fk_re_lieferung` (`lieferung_id`),
  CONSTRAINT `fk_re_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_re_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_re_lieferung` FOREIGN KEY (`lieferung_id`) REFERENCES `auftrag_lieferungen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_re_storno` FOREIGN KEY (`storno_von`) REFERENCES `rechnungen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rechnungen`
--

LOCK TABLES `rechnungen` WRITE;
/*!40000 ALTER TABLE `rechnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `rechnungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservierungen`
--

DROP TABLE IF EXISTS `reservierungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservierungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `lager_id` int(10) unsigned NOT NULL DEFAULT 1,
  `menge` int(10) unsigned NOT NULL,
  `kanal` varchar(30) NOT NULL,
  `referenz_tabelle` varchar(50) DEFAULT NULL,
  `referenz_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'offen',
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reservierungen_artikel_id` (`artikel_id`),
  KEY `fk_reservierungen_lager_id` (`lager_id`),
  CONSTRAINT `fk_reservierungen_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reservierungen_lager_id` FOREIGN KEY (`lager_id`) REFERENCES `lager` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservierungen`
--

LOCK TABLES `reservierungen` WRITE;
/*!40000 ALTER TABLE `reservierungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `reservierungen` ENABLE KEYS */;
UNLOCK TABLES;

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
  `rang` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rollen`
--

LOCK TABLES `rollen` WRITE;
/*!40000 ALTER TABLE `rollen` DISABLE KEYS */;
INSERT INTO `rollen` VALUES (1,'superadmin','Zugriff auf Alles + API-Zugriff + Benutzerverwaltung',100,1,'2026-06-01 19:47:17'),(2,'admin','Administrator Zugang zu Artikel, Lager, Lieferanten, Berichte',90,1,'2026-06-01 19:47:17'),(4,'assistent','Wie Admin, aber darf keine Lizenzen verwalten und kann von Admin jederzeit entmachtet werden',80,1,'2026-07-05 12:16:02'),(5,'manager','Alles außer Einstellungen; gibt Manager-Codes für Geldgeschäfte frei',70,1,'2026-07-05 12:16:02'),(6,'kassier','Kasse-Betrieb, Artikel/Bestand nur lesend',50,1,'2026-07-05 12:16:02'),(7,'lager','Lager, Bestellwesen, Wareneingang, Inventur',50,1,'2026-07-05 12:16:02'),(8,'packplatz','Packplatz, Versand, Retoure erfassen',50,1,'2026-07-05 12:16:02'),(9,'praktikant','Artikel-Datenpflege ohne Löschrechte, kein Dashboard',30,1,'2026-07-05 12:16:02'),(10,'readonly','Alle Module nur lesend',10,1,'2026-07-05 12:16:02');
/*!40000 ALTER TABLE `rollen` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `rollen_berechtigungen`
--

LOCK TABLES `rollen_berechtigungen` WRITE;
/*!40000 ALTER TABLE `rollen_berechtigungen` DISABLE KEYS */;
INSERT INTO `rollen_berechtigungen` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(1,49),(1,50),(1,51),(1,52),(1,53),(1,54),(1,55),(1,56),(1,57),(1,58),(1,59),(1,60),(1,61),(1,62),(1,63),(1,64),(1,65),(1,66),(1,67),(1,68),(1,69),(1,70),(1,71),(1,72),(1,73),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(2,22),(2,23),(2,24),(2,25),(2,26),(2,27),(2,28),(2,29),(2,30),(2,31),(2,32),(2,33),(2,34),(2,35),(2,36),(2,37),(2,38),(2,39),(2,40),(2,41),(2,42),(2,43),(2,44),(2,45),(2,46),(2,47),(2,48),(2,49),(2,50),(2,51),(2,52),(2,53),(2,54),(2,55),(2,56),(2,57),(2,58),(2,59),(2,60),(2,61),(2,62),(2,64),(2,65),(2,66),(2,67),(2,68),(2,69),(2,70),(2,71),(2,72),(2,73),(4,1),(4,2),(4,3),(4,4),(4,5),(4,6),(4,7),(4,8),(4,9),(4,10),(4,11),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(4,18),(4,19),(4,20),(4,21),(4,22),(4,23),(4,24),(4,25),(4,26),(4,27),(4,28),(4,29),(4,30),(4,31),(4,32),(4,33),(4,34),(4,35),(4,36),(4,37),(4,38),(4,39),(4,40),(4,41),(4,42),(4,43),(4,44),(4,45),(4,46),(4,47),(4,48),(4,49),(4,50),(4,51),(4,52),(4,53),(4,54),(4,55),(4,56),(4,57),(4,58),(4,59),(4,60),(4,61),(4,62),(4,64),(4,65),(4,66),(4,67),(4,68),(4,69),(4,70),(4,71),(4,72),(4,73),(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,7),(5,8),(5,9),(5,10),(5,11),(5,12),(5,13),(5,14),(5,15),(5,16),(5,17),(5,18),(5,19),(5,20),(5,21),(5,22),(5,23),(5,24),(5,25),(5,26),(5,27),(5,28),(5,29),(5,30),(5,31),(5,32),(5,33),(5,34),(5,35),(5,36),(5,37),(5,38),(5,39),(5,40),(5,41),(5,42),(5,43),(5,44),(5,45),(5,46),(5,47),(5,48),(5,49),(5,50),(5,51),(5,52),(5,53),(5,54),(5,55),(5,56),(5,57),(5,58),(5,59),(5,60),(5,64),(5,65),(5,66),(5,67),(5,68),(5,69),(5,70),(5,71),(5,72),(6,1),(6,5),(6,15),(6,44),(6,45),(7,1),(7,5),(7,9),(7,10),(7,11),(7,12),(7,13),(7,14),(7,15),(7,16),(7,17),(7,18),(7,19),(7,23),(7,24),(7,25),(7,26),(7,27),(7,28),(7,29),(7,30),(7,58),(7,59),(7,60),(8,1),(8,15),(8,42),(8,43),(8,67),(8,69),(8,70),(9,1),(9,2),(9,3),(9,5),(9,6),(9,7),(10,1),(10,5),(10,9),(10,15),(10,19),(10,23),(10,27),(10,35),(10,46),(10,50),(10,54),(10,58),(10,61),(10,64),(10,69),(10,71),(10,72);
/*!40000 ALTER TABLE `rollen_berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateiname` varchar(255) NOT NULL,
  `angewendet_am` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dateiname` (`dateiname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

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
  `arbeitsplatz_id` int(10) unsigned DEFAULT NULL,
  `geraete_token` varchar(36) DEFAULT NULL,
  `letzte_aktivitaet` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sessions_benutzer` (`benutzer_id`),
  KEY `fk_sessions_arbeitsplatz` (`arbeitsplatz_id`),
  CONSTRAINT `fk_sessions_arbeitsplatz` FOREIGN KEY (`arbeitsplatz_id`) REFERENCES `arbeitsplaetze` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sessions_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `nr_kuerzel` varchar(5) NOT NULL DEFAULT '',
  `name` varchar(100) NOT NULL,
  `logo_pfad` varchar(255) DEFAULT NULL,
  `sub_marke` tinyint(1) NOT NULL DEFAULT 0,
  `wc_url` varchar(255) DEFAULT NULL,
  `wc_key` varchar(255) DEFAULT NULL,
  `wc_secret` varchar(255) DEFAULT NULL,
  `wp_username` varchar(100) DEFAULT NULL,
  `wp_app_password` varchar(255) DEFAULT NULL,
  `bulk_import_aktiv` tinyint(1) NOT NULL DEFAULT 0,
  `sync_pausiert` tinyint(1) NOT NULL DEFAULT 0,
  `bilder_basis_url` varchar(255) DEFAULT NULL,
  `aktueller_sync_log` varchar(255) DEFAULT NULL,
  `bestellungen_letzter_sync` timestamp NULL DEFAULT NULL,
  `ist_aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shops`
--

LOCK TABLES `shops` WRITE;
/*!40000 ALTER TABLE `shops` DISABLE KEYS */;
INSERT INTO `shops` VALUES (1,'hauptkanal','','Hauptkanal',NULL,0,NULL,NULL,NULL,NULL,NULL,0,0,NULL,NULL,NULL,1,'2026-11-04 10:23:21');
/*!40000 ALTER TABLE `shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spenden_log`
--

DROP TABLE IF EXISTS `spenden_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spenden_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `spender_name` varchar(100) DEFAULT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `datum` timestamp NOT NULL DEFAULT current_timestamp(),
  `kassen_beleg_nr` varchar(50) DEFAULT NULL,
  `weitergeleitet` tinyint(1) NOT NULL DEFAULT 0,
  `weitergeleitet_am` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_splog_artikel` (`artikel_id`),
  KEY `idx_splog_partner` (`partner_id`),
  KEY `idx_splog_datum` (`datum`),
  KEY `idx_splog_weitergeleitet` (`weitergeleitet`),
  CONSTRAINT `fk_splog_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`),
  CONSTRAINT `fk_splog_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spenden_log`
--

LOCK TABLES `spenden_log` WRITE;
/*!40000 ALTER TABLE `spenden_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `spenden_log` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `steuerklassen`
--

LOCK TABLES `steuerklassen` WRITE;
/*!40000 ALTER TABLE `steuerklassen` DISABLE KEYS */;
INSERT INTO `steuerklassen` VALUES (1,'Normaler Steuersatz',20.00,'AT',1,'2026-05-29 20:03:50'),(2,'Ermäßigter Steuersatz',10.00,'AT',1,'2026-05-29 20:03:50'),(3,'Steuerfrei',0.00,'AT',1,'2026-05-29 20:03:50'),(4,'Ermäßigter Steuersatz 13%',13.00,'AT',0,'2026-11-04 09:23:22'),(5,'Sonder-Steuersatz 4,9%',4.90,'AT',0,'2026-11-04 09:23:22');
/*!40000 ALTER TABLE `steuerklassen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `steuerklassen_konten`
--

DROP TABLE IF EXISTS `steuerklassen_konten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `steuerklassen_konten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `steuerklasse_id` int(10) unsigned NOT NULL,
  `steuer_konto_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_steuerklasse` (`steuerklasse_id`),
  KEY `fk_sk_konto` (`steuer_konto_id`),
  CONSTRAINT `fk_sk_konto` FOREIGN KEY (`steuer_konto_id`) REFERENCES `kontenplan` (`id`),
  CONSTRAINT `fk_sk_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `steuerklassen_konten`
--

LOCK TABLES `steuerklassen_konten` WRITE;
/*!40000 ALTER TABLE `steuerklassen_konten` DISABLE KEYS */;
INSERT INTO `steuerklassen_konten` VALUES (1,1,12),(2,2,10),(3,4,11),(4,3,NULL),(5,5,NULL);
/*!40000 ALTER TABLE `steuerklassen_konten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superadmin_uebergaben`
--

DROP TABLE IF EXISTS `superadmin_uebergaben`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `superadmin_uebergaben` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nonce` varchar(64) NOT NULL,
  `schluessel_nr` varchar(30) NOT NULL,
  `neuer_benutzer_id` int(10) unsigned NOT NULL,
  `alte_superadmins` text DEFAULT NULL COMMENT 'JSON: [{benutzer_id, aktion}]',
  `ip_adresse` varchar(45) DEFAULT NULL,
  `verwendet_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uebergabe_nonce` (`nonce`),
  KEY `fk_uebergabe_benutzer` (`neuer_benutzer_id`),
  CONSTRAINT `fk_uebergabe_benutzer` FOREIGN KEY (`neuer_benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_uebergaben`
--

LOCK TABLES `superadmin_uebergaben` WRITE;
/*!40000 ALTER TABLE `superadmin_uebergaben` DISABLE KEYS */;
/*!40000 ALTER TABLE `superadmin_uebergaben` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_einstellungen`
--

DROP TABLE IF EXISTS `system_einstellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_einstellungen` (
  `schluessel` varchar(80) NOT NULL,
  `wert` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`schluessel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_einstellungen`
--

LOCK TABLES `system_einstellungen` WRITE;
/*!40000 ALTER TABLE `system_einstellungen` DISABLE KEYS */;
INSERT INTO `system_einstellungen` VALUES ('beleg_bilder_abholzettel','0'),('beleg_bilder_auftragsbestaetigung','0'),('beleg_bilder_gutschrift','0'),('beleg_bilder_kassenbon_a4','0'),('beleg_bilder_lieferschein','1'),('beleg_bilder_mail_abholung','0'),('beleg_bilder_mail_auftragsbestaetigung','1'),('beleg_bilder_mail_versand','0'),('beleg_bilder_pickliste','1'),('beleg_bilder_rechnung','1'),('datev_berater_nr',''),('datev_mandant_nr',''),('datev_sachkontenlaenge','4'),('datev_wj_beginn',''),('fachmiete_vorlauf_tage','2'),('gutschein_gueltigkeit_tage','3650'),('gutschein_mindestbetrag_shop','10.00'),('gutschein_shop_id','1'),('konto_partner_fremd','3710'),('konto_partner_kommission','4080'),('konto_partner_spende','3720'),('lieferschein_charge_anzeigen','0'),('lizenz_privat_schluessel_pfad',''),('mahnung_erinnerung_tage','7'),('mahnung_gebuehr_stufe1','5.00'),('mahnung_gebuehr_stufe2','10.00'),('mahnung_stufe1_tage','14'),('mahnung_stufe2_tage','14'),('paperless_schluessel',''),('paperless_url','');
/*!40000 ALTER TABLE `system_einstellungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `update_verlauf`
--

DROP TABLE IF EXISTS `update_verlauf`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `update_verlauf` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version_von` varchar(20) NOT NULL,
  `version_nach` varchar(20) NOT NULL,
  `status` enum('laeuft','erfolgreich','fehlgeschlagen','zurueckgespielt') NOT NULL,
  `sicherung_ordner` varchar(100) NOT NULL COMMENT 'Ordner unter erp/storage/updates/ (Datenbank, alte Dateien, Paket)',
  `meldung` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned DEFAULT NULL,
  `gestartet_am` datetime NOT NULL,
  `beendet_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `update_verlauf`
--

LOCK TABLES `update_verlauf` WRITE;
/*!40000 ALTER TABLE `update_verlauf` DISABLE KEYS */;
/*!40000 ALTER TABLE `update_verlauf` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varianten_achse_werte`
--

DROP TABLE IF EXISTS `varianten_achse_werte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_achse_werte` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `achse_id` int(10) unsigned NOT NULL,
  `wert` varchar(100) NOT NULL,
  `wert_zusatz` varchar(100) DEFAULT NULL,
  `bedingungs_wert_id` int(10) unsigned DEFAULT NULL,
  `aufpreis` decimal(10,2) DEFAULT 0.00,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `aktualisiert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rohmaterial_artikel_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_varAchsWert_artikel` (`artikel_id`),
  KEY `fk_varAchsWert_achse` (`achse_id`),
  KEY `fk_varAchsWert_bedWert` (`bedingungs_wert_id`),
  KEY `fk_varachswert_rohmaterial` (`rohmaterial_artikel_id`),
  CONSTRAINT `fk_varAchsWert_achse` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varAchsWert_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varAchsWert_bedWert` FOREIGN KEY (`bedingungs_wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_varachswert_rohmaterial` FOREIGN KEY (`rohmaterial_artikel_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varianten_achse_werte`
--

LOCK TABLES `varianten_achse_werte` WRITE;
/*!40000 ALTER TABLE `varianten_achse_werte` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achse_werte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varianten_achse_werte_shops`
--

DROP TABLE IF EXISTS `varianten_achse_werte_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_achse_werte_shops` (
  `wert_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `externe_term_id` varchar(100) DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`wert_id`,`shop_id`),
  KEY `fk_wertshop_shop` (`shop_id`),
  CONSTRAINT `fk_wertshop_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wertshop_wert` FOREIGN KEY (`wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varianten_achse_werte_shops`
--

LOCK TABLES `varianten_achse_werte_shops` WRITE;
/*!40000 ALTER TABLE `varianten_achse_werte_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achse_werte_shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varianten_achsen`
--

DROP TABLE IF EXISTS `varianten_achsen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_achsen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(30) NOT NULL,
  `darstellungsform` varchar(30) NOT NULL,
  `ist_gruppe` tinyint(1) NOT NULL DEFAULT 0,
  `im_kindnamen_anzeigen` tinyint(1) NOT NULL DEFAULT 1,
  `abhaengig_von_achse_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_varAchsen_abhaengigVon` (`abhaengig_von_achse_id`),
  CONSTRAINT `fk_varAchsen_abhaengigVon` FOREIGN KEY (`abhaengig_von_achse_id`) REFERENCES `varianten_achsen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varianten_achsen`
--

LOCK TABLES `varianten_achsen` WRITE;
/*!40000 ALTER TABLE `varianten_achsen` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achsen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varianten_achsen_shops`
--

DROP TABLE IF EXISTS `varianten_achsen_shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_achsen_shops` (
  `achse_id` int(10) unsigned NOT NULL,
  `shop_id` int(10) unsigned NOT NULL,
  `externe_attribut_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`achse_id`,`shop_id`),
  KEY `fk_achseshop_shop` (`shop_id`),
  CONSTRAINT `fk_achseshop_achse` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_achseshop_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varianten_achsen_shops`
--

LOCK TABLES `varianten_achsen_shops` WRITE;
/*!40000 ALTER TABLE `varianten_achsen_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achsen_shops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varianten_kombination_werte`
--

DROP TABLE IF EXISTS `varianten_kombination_werte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_kombination_werte` (
  `kombination_id` int(10) unsigned NOT NULL,
  `wert_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`kombination_id`,`wert_id`),
  KEY `fk_varKombiWert_wert_id` (`wert_id`),
  CONSTRAINT `fk_varKombiWert_kombination_id` FOREIGN KEY (`kombination_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varKombiWert_wert_id` FOREIGN KEY (`wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varianten_kombination_werte`
--

LOCK TABLES `varianten_kombination_werte` WRITE;
/*!40000 ALTER TABLE `varianten_kombination_werte` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_kombination_werte` ENABLE KEYS */;
UNLOCK TABLES;

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
  `preis_brutto` decimal(10,2) DEFAULT NULL,
  `artikel_gruppe_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_vsk_gruppe` (`artikel_gruppe_id`),
  CONSTRAINT `fk_vsk_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versandklassen`
--

LOCK TABLES `versandklassen` WRITE;
/*!40000 ALTER TABLE `versandklassen` DISABLE KEYS */;
INSERT INTO `versandklassen` VALUES (1,'Standardversand mit Post AT','SAT','Std. AT',1,6.50,NULL),(2,'Versand + Teillieferung mit Post AT','TLAT','Std. TL AT',2,9.50,NULL),(3,'Nachnahme Post AT','NN','NN AT',3,13.00,NULL),(4,'Standardversand DE','SDE','Std. DE',4,9.90,NULL),(5,'Standardversand IT/HU','SEU','Std. EU',5,15.90,NULL);
/*!40000 ALTER TABLE `versandklassen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zahlungsart_konten`
--

DROP TABLE IF EXISTS `zahlungsart_konten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zahlungsart_konten` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `zahlungsart` varchar(30) NOT NULL,
  `konto_id` int(10) unsigned DEFAULT NULL,
  `hinweis` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zahlungsart` (`zahlungsart`),
  KEY `fk_zk_konto` (`konto_id`),
  CONSTRAINT `fk_zk_konto` FOREIGN KEY (`konto_id`) REFERENCES `kontenplan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zahlungsart_konten`
--

LOCK TABLES `zahlungsart_konten` WRITE;
/*!40000 ALTER TABLE `zahlungsart_konten` DISABLE KEYS */;
INSERT INTO `zahlungsart_konten` VALUES (1,'bar',5,NULL),(2,'karte_extern',6,NULL),(3,'paypal',17,NULL),(4,'vorkasse',6,'Überweisung vorab'),(5,'nachnahme',6,NULL),(6,'gutschein',13,NULL),(7,'rechnung',NULL,'Kein einfaches Zahlungskonto: Erlös (Warengruppe) + individuelles Debitorenkonto, Ausgleich bei Zahlungseingang über Bank'),(8,'gemischt',NULL,'Mehrere Zahlarten in einer Auftrags-Buchung kombiniert — Sonderbehandlung im Export'),(9,'kombi',NULL,'Mehrere Zahlarten in einem Kassenbon kombiniert — Sonderbehandlung im Export');
/*!40000 ALTER TABLE `zahlungsart_konten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `zahlungsbedingungen`
--

DROP TABLE IF EXISTS `zahlungsbedingungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zahlungsbedingungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` varchar(255) NOT NULL DEFAULT '',
  `netto_tage` int(11) NOT NULL DEFAULT 30,
  `skonto_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `skonto_tage` int(11) NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zb_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `zahlungsbedingungen`
--

LOCK TABLES `zahlungsbedingungen` WRITE;
/*!40000 ALTER TABLE `zahlungsbedingungen` DISABLE KEYS */;
INSERT INTO `zahlungsbedingungen` VALUES (1,'Sofort fällig','Zahlung bei Erhalt / Lieferung',0,0.00,0,1,'2026-06-19 14:10:29'),(2,'14 Tage netto','Zahlung innerhalb 14 Tagen',14,0.00,0,1,'2026-06-19 14:10:29'),(3,'30 Tage netto','Zahlung innerhalb 30 Tagen',30,0.00,0,1,'2026-06-19 14:10:29'),(4,'14/2 30 netto','2% Skonto bei Zahlung bis 14 Tage, sonst 30 Tage',30,2.00,14,1,'2026-06-19 14:10:29'),(5,'Vorauskasse','Zahlung vor Lieferung',0,0.00,0,1,'2026-06-19 14:10:29');
/*!40000 ALTER TABLE `zahlungsbedingungen` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
