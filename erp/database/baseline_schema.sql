-- baseline_schema.sql — Struktur + universelle Stammdaten fuer Neuinstallationen
-- Neu erzeugt: 2026-07-09, Stand nach Migration 123.
-- Enthaelt: alle Tabellenstrukturen (0 Datensaetze) PLUS Vollinhalt der
-- Referenztabellen (rollen, berechtigungen, rollen_berechtigungen, artikel_typen,
-- einheiten, steuerklassen, laender, zahlungsbedingungen) PLUS fixe Einzel-Seeds
-- (Jarvis-User, Diverses-Artikel 99-9999, Laufkunde, Hauptkanal-Shop, Versandklassen).
-- KEINE Geschaefts-/Demodaten (Artikel, Kunden, Auftraege, Kategorien, Hersteller...).

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `partner_modus` enum('eigen','kommission','spende') NOT NULL DEFAULT 'eigen',
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
  `preise_vererben` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `grundpreis_bezugsmenge` decimal(8,3) DEFAULT NULL,
  `grundpreis_anzeigen` tinyint(1) DEFAULT 0,
  `ist_vater` tinyint(1) NOT NULL DEFAULT 0,
  `vaterartikel_id` int(10) unsigned DEFAULT NULL,
  `hat_eigenen_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
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
  `sicherheitsbestand` int(10) unsigned DEFAULT NULL COMMENT 'Puffer der nie unterschritten werden soll',
  `standardbestellmenge` int(10) unsigned DEFAULT NULL COMMENT 'Vorschlagsmenge beim manuellen Bestellvorschlag',
  `lieferzeit_text` varchar(100) DEFAULT NULL,
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
  CONSTRAINT `fk_art_gruppe` FOREIGN KEY (`artikel_gruppe_id`) REFERENCES `artikel_gruppen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_art_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_artikel_artikeltyp` FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_einheitId` FOREIGN KEY (`einheit_id`) REFERENCES `einheiten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_vater` FOREIGN KEY (`vaterartikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_versandklasse` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_zustand_vater` FOREIGN KEY (`zustand_vater_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `preis_modus` enum('aufpreis','direktpreis') NOT NULL DEFAULT 'aufpreis',
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
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_konto_nr` (`konto_nr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `brutto_ek` decimal(8,2) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `hat_varianten` tinyint(1) NOT NULL DEFAULT 1,
  `hat_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
  `ist_download` tinyint(1) NOT NULL DEFAULT 0,
  `ist_set` tinyint(1) NOT NULL DEFAULT 0,
  `sortierung` int(10) unsigned NOT NULL DEFAULT 0,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `kanal` enum('woocommerce','manuell','kasse') NOT NULL DEFAULT 'manuell',
  `shop_id` int(10) unsigned DEFAULT NULL,
  `kanal_auftrag_id` int(10) unsigned DEFAULT NULL,
  `zahlungsstatus` enum('ausstehend','bezahlt','teilbezahlt','erstattet','storniert') NOT NULL DEFAULT 'ausstehend',
  `lieferstatus` enum('neu','in_bearbeitung','versandbereit','teilgeliefert','zurueckgestellt','versendet','abgeschlossen','storniert','abholbereit','kommissioniert') NOT NULL DEFAULT 'neu',
  `zahlungsart` enum('vorkasse','paypal','rechnung','bar','nachnahme','gutschein','gemischt') NOT NULL DEFAULT 'vorkasse',
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
  `versandklasse_id` int(10) unsigned DEFAULT NULL,
  `kassen_bon_id` int(11) DEFAULT NULL COMMENT 'Gesetzt wenn dieser Auftrag an der Kasse bezahlt wurde — sperrt Rechnung-Erstellung',
  PRIMARY KEY (`id`),
  UNIQUE KEY `auftrag_nr` (`auftrag_nr`),
  KEY `fk_auftrag_kunde` (`kunden_id`),
  KEY `fk_auftrag_zahlung` (`zahlungsbedingung_id`),
  KEY `fk_auftrag_benutzer` (`erstellt_von`),
  KEY `fk_auftr_versandklassen` (`versandklasse_id`),
  CONSTRAINT `fk_auftr_versandklassen` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftrag_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftrag_kunde` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_auftrag_zahlung` FOREIGN KEY (`zahlungsbedingung_id`) REFERENCES `zahlungsbedingungen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auftrag_dokumente`
--

DROP TABLE IF EXISTS `auftrag_dokumente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_dokumente` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `typ` enum('auftragsbestaetigung','lieferschein','rechnung','gutschrift','mahnung') NOT NULL,
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
-- Table structure for table `auftrag_lieferungen`
--

DROP TABLE IF EXISTS `auftrag_lieferungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_lieferungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
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
-- Table structure for table `auftrag_positionen`
--

DROP TABLE IF EXISTS `auftrag_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auftrag_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(10) unsigned NOT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `charge` varchar(20) DEFAULT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `ean` varchar(20) DEFAULT NULL,
  `menge` int(11) NOT NULL,
  `menge_geliefert` int(10) unsigned NOT NULL DEFAULT 0,
  `menge_retourniert` int(10) unsigned NOT NULL DEFAULT 0,
  `einzelpreis_netto` decimal(10,4) NOT NULL,
  `steuer_prozent` decimal(5,2) NOT NULL DEFAULT 20.00,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gesamtpreis_netto` decimal(10,2) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_aufpos_auftrag` (`auftrag_id`),
  KEY `fk_aufpos_artikel` (`artikel_id`),
  CONSTRAINT `fk_aufpos_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_aufpos_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `bestellungen`
--

DROP TABLE IF EXISTS `bestellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bestellungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lieferant_id` int(10) unsigned NOT NULL,
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
  `gutschrift_betrag` decimal(10,2) DEFAULT NULL COMMENT 'Offenes Guthaben aus gestrichenen Positionen (DROPS-Modell)',
  `gutschrift_notiz` text DEFAULT NULL,
  `notiz` text DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_best_lieferant` (`lieferant_id`),
  KEY `fk_best_benutzer` (`benutzer_id`),
  CONSTRAINT `fk_best_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_best_lieferant` FOREIGN KEY (`lieferant_id`) REFERENCES `lieferanten` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `dokument_nummern`
--

DROP TABLE IF EXISTS `dokument_nummern`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokument_nummern` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag','pickliste') NOT NULL,
  `praefix` varchar(10) NOT NULL,
  `jahr` smallint(6) NOT NULL,
  `letzt_nr` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dok_typ_jahr` (`typ`,`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
-- Table structure for table `kassen_bon_positionen`
--

DROP TABLE IF EXISTS `kassen_bon_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_bon_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_id` int(10) unsigned NOT NULL,
  `block` enum('auftrag','addon','storno','retour') DEFAULT NULL,
  `artikel_id` int(10) unsigned DEFAULT NULL,
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
  CONSTRAINT `kassen_bon_positionen_ibfk_1` FOREIGN KEY (`bon_id`) REFERENCES `kassen_bons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `zahlungsart` enum('bar','karte_extern','gutschein','kombi') NOT NULL DEFAULT 'bar',
  `bruttobetrag` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gegeben` decimal(10,2) DEFAULT NULL,
  `rueckgeld` decimal(10,2) DEFAULT NULL,
  `bar_betrag` decimal(10,2) DEFAULT NULL,
  `karten_betrag` decimal(10,2) DEFAULT NULL,
  `gutschein_code` varchar(100) DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `bon_nr` (`bon_nr`),
  KEY `kasse_id` (`kasse_id`),
  KEY `auftrag_id` (`auftrag_id`),
  KEY `kunden_id` (`kunden_id`),
  KEY `benutzer_id` (`benutzer_id`),
  CONSTRAINT `kassen_bons_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`),
  CONSTRAINT `kassen_bons_ibfk_2` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`),
  CONSTRAINT `kassen_bons_ibfk_3` FOREIGN KEY (`kunden_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `kassen_bons_ibfk_4` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `kassen_messe_sync`
--

DROP TABLE IF EXISTS `kassen_messe_sync`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_messe_sync` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kasse_id` int(10) unsigned NOT NULL,
  `lager_id` int(10) unsigned NOT NULL,
  `typ` enum('pre','post') NOT NULL,
  `status` enum('vorbereitet','abgeschlossen','fehler') NOT NULL DEFAULT 'vorbereitet',
  `artikel_count` int(10) unsigned NOT NULL DEFAULT 0,
  `bon_count` int(10) unsigned NOT NULL DEFAULT 0,
  `umsatz` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sync_token` varchar(64) NOT NULL,
  `notiz` varchar(500) DEFAULT NULL,
  `benutzer_id` int(10) unsigned NOT NULL,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  `abgeschlossen_am` datetime DEFAULT NULL,
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
  `label` varchar(100) DEFAULT NULL COMMENT 'Überschreibt Artikelname wenn gesetzt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kasse_slot` (`kasse_id`,`slot`),
  KEY `artikel_id` (`artikel_id`),
  CONSTRAINT `kassen_schnellwahl_ibfk_1` FOREIGN KEY (`kasse_id`) REFERENCES `kassen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kassen_schnellwahl_ibfk_2` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `ist_aktions_kategorie` tinyint(1) NOT NULL DEFAULT 0,
  `externe_id` varchar(100) DEFAULT NULL,
  `datenquelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kat_parent_id` (`parent_id`),
  CONSTRAINT `fk_kat_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `kunden`
--

DROP TABLE IF EXISTS `kunden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kundennummer` varchar(20) NOT NULL,
  `status` enum('aktiv','gesperrt','geloescht') NOT NULL DEFAULT 'aktiv',
  `ist_laufkunde` tinyint(1) NOT NULL DEFAULT 0,
  `ist_firma` tinyint(1) NOT NULL DEFAULT 0,
  `kundengruppe_id` int(10) unsigned DEFAULT NULL,
  `zahlungsbedingung_id` int(10) unsigned DEFAULT NULL,
  `standardzahlungsart` enum('vorkasse','rechnung','kreditkarte','paypal','bar') DEFAULT NULL,
  `kreditlimit` decimal(10,2) DEFAULT NULL,
  `sprache` varchar(5) NOT NULL DEFAULT 'de',
  `kundenherkunft` enum('shop','messe','empfehlung','walkin','kasse','erp') NOT NULL DEFAULT 'erp',
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
  KEY `fk_kunde_kg` (`kundengruppe_id`),
  KEY `fk_kunde_zb` (`zahlungsbedingung_id`),
  KEY `idx_email_hash` (`email_hash`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_kunde_kg` FOREIGN KEY (`kundengruppe_id`) REFERENCES `kundengruppen` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kunde_zb` FOREIGN KEY (`zahlungsbedingung_id`) REFERENCES `zahlungsbedingungen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lager_partner` (`partner_id`),
  KEY `fk_lager_kunde` (`kunde_id`),
  CONSTRAINT `fk_lager_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`),
  CONSTRAINT `fk_lager_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
-- Table structure for table `mahnungen`
--

DROP TABLE IF EXISTS `mahnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mahnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `auftrag_id` int(11) NOT NULL,
  `typ` enum('erinnerung','stornierung','hinweis') NOT NULL,
  `gesendet_am` datetime NOT NULL DEFAULT current_timestamp(),
  `mail_an` varchar(255) DEFAULT NULL,
  `erstellt_von` enum('cronjob','manuell') NOT NULL DEFAULT 'cronjob',
  PRIMARY KEY (`id`),
  KEY `idx_auftrag` (`auftrag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `miet_rechnungen`
--

DROP TABLE IF EXISTS `miet_rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `miet_rechnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fach_id` int(10) unsigned NOT NULL,
  `partner_id` int(10) unsigned NOT NULL,
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
  UNIQUE KEY `uk_mietrg_nr` (`rechnungs_nr`),
  KEY `fk_mietrg_fach` (`fach_id`),
  KEY `idx_mietrg_periode` (`periode`),
  KEY `idx_mietrg_partner` (`partner_id`),
  KEY `idx_mietrg_status` (`status`),
  CONSTRAINT `fk_mietrg_fach` FOREIGN KEY (`fach_id`) REFERENCES `mietfaecher` (`id`),
  CONSTRAINT `fk_mietrg_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `packplatz_ruecklagerungen`
--

DROP TABLE IF EXISTS `packplatz_ruecklagerungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packplatz_ruecklagerungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kassen_bon_id` int(10) unsigned NOT NULL,
  `bon_nr` varchar(30) NOT NULL,
  `auftrag_id` int(10) unsigned DEFAULT NULL,
  `auftrag_nr` varchar(20) DEFAULT NULL,
  `artikel_id` int(10) unsigned NOT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `menge` int(10) unsigned NOT NULL,
  `charge` varchar(50) DEFAULT NULL,
  `kasse_id` int(10) unsigned NOT NULL,
  `status` enum('offen','erledigt') NOT NULL DEFAULT 'offen',
  `erledigt_am` datetime DEFAULT NULL,
  `erledigt_von` int(10) unsigned DEFAULT NULL,
  `erledigt_lager_id` int(10) unsigned DEFAULT NULL,
  `erledigt_zustand` enum('neu','gebraucht','beschaedigt','defekt') DEFAULT NULL,
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
-- Table structure for table `partner`
--

DROP TABLE IF EXISTS `partner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `typ` enum('mietfach','kommission','spende','beides') NOT NULL DEFAULT 'mietfach',
  `email` varchar(200) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `uid_nummer` varchar(30) DEFAULT NULL,
  `zvr_nummer` varchar(30) DEFAULT NULL,
  `kleinunternehmer` tinyint(1) NOT NULL DEFAULT 0,
  `provisions_satz` decimal(5,2) NOT NULL DEFAULT 0.00,
  `abrechnungs_modus` enum('getrennt','gegenverrechnung') NOT NULL DEFAULT 'getrennt',
  `abrechnungs_beleg_typ` enum('gutschrift','fremdrechnung','info') NOT NULL DEFAULT 'gutschrift',
  `notiz` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partner_typ` (`typ`),
  KEY `idx_partner_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `rechnungen`
--

DROP TABLE IF EXISTS `rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rechnungen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rechnung_nr` varchar(20) NOT NULL,
  `auftrag_id` int(10) unsigned NOT NULL,
  `nettobetrag` decimal(10,2) NOT NULL,
  `steuerbetrag` decimal(10,2) NOT NULL,
  `bruttobetrag` decimal(10,2) NOT NULL,
  `faellig_am` date DEFAULT NULL,
  `storniert` tinyint(1) NOT NULL DEFAULT 0,
  `storno_von` int(10) unsigned DEFAULT NULL,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `erstellt_von` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rechnung_nr` (`rechnung_nr`),
  KEY `fk_re_auftrag` (`auftrag_id`),
  KEY `fk_re_storno` (`storno_von`),
  KEY `fk_re_benutzer` (`erstellt_von`),
  CONSTRAINT `fk_re_auftrag` FOREIGN KEY (`auftrag_id`) REFERENCES `auftraege` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_re_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_re_storno` FOREIGN KEY (`storno_von`) REFERENCES `rechnungen` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shops` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo_pfad` varchar(255) DEFAULT NULL,
  `sub_marke` tinyint(1) NOT NULL DEFAULT 0,
  `wc_url` varchar(255) DEFAULT NULL,
  `wc_key` varchar(255) DEFAULT NULL,
  `wc_secret` varchar(255) DEFAULT NULL,
  `ist_aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  PRIMARY KEY (`id`),
  KEY `fk_varAchsWert_artikel` (`artikel_id`),
  KEY `fk_varAchsWert_achse` (`achse_id`),
  KEY `fk_varAchsWert_bedWert` (`bedingungs_wert_id`),
  CONSTRAINT `fk_varAchsWert_achse` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varAchsWert_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varAchsWert_bedWert` FOREIGN KEY (`bedingungs_wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-09 18:43:46

-- ============================================================
-- Stammdaten (Referenztabellen, Volldump)
-- ============================================================
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
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `rollen`
--

LOCK TABLES `rollen` WRITE;
/*!40000 ALTER TABLE `rollen` DISABLE KEYS */;
INSERT INTO `rollen` (`id`, `name`, `beschreibung`, `rang`, `aktiv`, `erstellt_am`) VALUES (1,'superadmin','Zugriff auf Alles + API-Zugriff + Benutzerverwaltung',100,1,'2026-06-01 19:47:17'),(2,'admin','Administrator Zugang zu Artikel, Lager, Lieferanten, Berichte',90,1,'2026-06-01 19:47:17'),(4,'assistent','Wie Admin, aber darf keine Lizenzen verwalten und kann von Admin jederzeit entmachtet werden',80,1,'2026-07-05 12:16:02'),(5,'manager','Alles außer Einstellungen; gibt Manager-Codes für Geldgeschäfte frei',70,1,'2026-07-05 12:16:02'),(6,'kassier','Kasse-Betrieb, Artikel/Bestand nur lesend',50,1,'2026-07-05 12:16:02'),(7,'lager','Lager, Bestellwesen, Wareneingang, Inventur',50,1,'2026-07-05 12:16:02'),(8,'packplatz','Packplatz, Versand, Retoure erfassen',50,1,'2026-07-05 12:16:02'),(9,'praktikant','Artikel-Datenpflege ohne Löschrechte, kein Dashboard',30,1,'2026-07-05 12:16:02'),(10,'readonly','Alle Module nur lesend',10,1,'2026-07-05 12:16:02');
/*!40000 ALTER TABLE `rollen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `berechtigungen`
--

LOCK TABLES `berechtigungen` WRITE;
/*!40000 ALTER TABLE `berechtigungen` DISABLE KEYS */;
INSERT INTO `berechtigungen` (`id`, `name`, `beschreibung`, `aktiv`, `erstellt_am`) VALUES (1,'artikel.anzeigen','artikel anzeigen',1,'2026-06-01 20:27:22'),(2,'artikel.bearbeiten','artikel bearbeiten',1,'2026-06-01 20:27:22'),(3,'artikel.anlegen','artikel anlegen',1,'2026-06-01 20:27:22'),(4,'artikel.loeschen','artikel löschen',1,'2026-06-01 20:27:22'),(5,'varianten.anzeigen','varianten anzeigen',1,'2026-06-01 20:27:22'),(6,'varianten.bearbeiten','varianten bearbeiten',1,'2026-06-01 20:27:22'),(7,'varianten.anlegen','varianten anlegen',1,'2026-06-01 20:27:22'),(8,'varianten.loeschen','varianten löschen',1,'2026-06-01 20:27:22'),(9,'lager.anzeigen','lager anzeigen',1,'2026-06-01 20:27:22'),(10,'lager.bearbeiten','lager bearbeiten',1,'2026-06-01 20:27:22'),(11,'lager.anlegen','lager anlegen',1,'2026-06-01 20:27:22'),(12,'lager.loeschen','lager löschen',1,'2026-06-01 20:27:22'),(13,'wareneingang.buchen','wareneingang buchen',1,'2026-06-01 20:27:22'),(14,'wareneingang.bearbeiten','wareneingang bearbeiten',1,'2026-06-01 20:27:22'),(15,'bestand.anzeigen','bestand anzeigen',1,'2026-06-01 20:27:22'),(16,'bestand.bearbeiten','bestand bearbeiten',1,'2026-06-01 20:27:22'),(17,'bestand.korrigieren','bestand korrigieren',1,'2026-06-01 20:27:22'),(18,'bestand.loeschen','bestand löschen',1,'2026-06-01 20:27:22'),(19,'lieferanten.anzeigen','lieferanten anzeigen',1,'2026-06-01 20:27:22'),(20,'lieferanten.bearbeiten','lieferanten bearbeiten',1,'2026-06-01 20:27:22'),(21,'lieferanten.anlegen','lieferanten anlegen',1,'2026-06-01 20:27:22'),(22,'lieferanten.loeschen','lieferanten löschen',1,'2026-06-01 20:27:22'),(23,'inventur.anzeigen','inventur anzeigen',1,'2026-06-01 20:27:22'),(24,'inventur.bearbeiten','inventur bearbeiten',1,'2026-06-01 20:27:22'),(25,'inventur.anlegen','inventur anlegen',1,'2026-06-01 20:27:22'),(26,'inventur.loeschen','inventur löschen',1,'2026-06-01 20:27:22'),(27,'inventurpositionen.anzeigen','inventurpositionen anzeigen',1,'2026-06-01 20:27:22'),(28,'inventurpositionen.bearbeiten','inventurpositionen bearbeiten',1,'2026-06-01 20:27:22'),(29,'inventurpositionen.anlegen','inventurpositionen anlegen',1,'2026-06-01 20:27:22'),(30,'inventurpositionen.loeschen','inventurpositionen löschen',1,'2026-06-01 20:27:22'),(31,'benutzer.anlegen','benutzer anlegen',1,'2026-06-01 20:27:22'),(32,'benutzer.bearbeiten','benutzer bearbeiten',1,'2026-06-01 20:27:22'),(33,'benutzer.loeschen','benutzer löschen',1,'2026-06-01 20:27:22'),(34,'api.zugriff','API Zugriff',1,'2026-06-01 20:27:22'),(35,'berichte.anzeigen','berichte anzeigen',1,'2026-06-01 20:27:22'),(36,'berichte.bearbeiten','berichte bearbeiten',1,'2026-06-01 20:27:22'),(37,'berichte.anlegen','berichte anlegen',1,'2026-06-01 20:27:22'),(38,'berichte.loeschen','berichte löschen',1,'2026-06-01 20:27:22'),(39,'berichte.drucken','berichte drucken',1,'2026-06-01 20:27:22'),(40,'shopabgleich.starten','shopabgleich starten',1,'2026-06-01 20:27:22'),(41,'shopabgleich.stoppen','shopabgleich stoppen',1,'2026-06-01 20:27:22'),(42,'packplatz.starten','packplatz starten',1,'2026-06-01 20:27:22'),(43,'packplatz.stoppen','packplatz stoppen',1,'2026-06-01 20:27:22'),(44,'kasse.starten','kasse starten',1,'2026-06-01 20:27:22'),(45,'kasse.stoppen','kasse stoppen',1,'2026-06-01 20:27:22'),(46,'kunden.anzeigen','kunden anzeigen',1,'2026-07-05 12:16:02'),(47,'kunden.bearbeiten','kunden bearbeiten',1,'2026-07-05 12:16:02'),(48,'kunden.anlegen','kunden anlegen',1,'2026-07-05 12:16:02'),(49,'kunden.loeschen','kunden löschen',1,'2026-07-05 12:16:02'),(50,'auftraege.anzeigen','aufträge anzeigen',1,'2026-07-05 12:16:02'),(51,'auftraege.anlegen','aufträge anlegen',1,'2026-07-05 12:16:02'),(52,'auftraege.bearbeiten','aufträge bearbeiten',1,'2026-07-05 12:16:02'),(53,'auftraege.stornieren','aufträge stornieren',1,'2026-07-05 12:16:02'),(54,'partner.anzeigen','partner anzeigen',1,'2026-07-05 12:16:02'),(55,'partner.anlegen','partner anlegen',1,'2026-07-05 12:16:02'),(56,'partner.bearbeiten','partner bearbeiten',1,'2026-07-05 12:16:02'),(57,'partner.loeschen','partner löschen',1,'2026-07-05 12:16:02'),(58,'bestellwesen.anzeigen','bestellwesen anzeigen',1,'2026-07-05 12:16:02'),(59,'bestellwesen.anlegen','bestellwesen anlegen',1,'2026-07-05 12:16:02'),(60,'bestellwesen.bearbeiten','bestellwesen bearbeiten',1,'2026-07-05 12:16:02'),(61,'einstellungen.anzeigen','einstellungen anzeigen',1,'2026-07-05 12:16:02'),(62,'einstellungen.bearbeiten','einstellungen bearbeiten',1,'2026-07-05 12:16:02'),(63,'lizenz.verwalten','lizenz verwalten (nur Superadmin, in der Matrix-UI fix gesperrt)',1,'2026-07-05 12:16:02'),(64,'dashboard.zugriff','dashboard zugriff',1,'2026-07-05 12:16:02'),(65,'kasse.auszahlung','kasse auszahlung (künftig Manager-Override)',1,'2026-07-05 12:16:02'),(66,'kasse.verwaltung','kassen-instanzen verwalten',1,'2026-07-05 12:16:02'),(67,'packplatz.retoure','packplatz retoure erfassen',1,'2026-07-05 12:16:02'),(68,'packplatz.gutschrift','packplatz gutschrift auslösen (künftig Manager-Override)',1,'2026-07-05 12:16:02'),(69,'versand.anzeigen','versand anzeigen',1,'2026-07-05 12:16:02'),(70,'versand.bearbeiten','versand bearbeiten',1,'2026-07-05 12:16:02'),(71,'buchhaltung.anzeigen','buchhaltung anzeigen',1,'2026-07-05 12:16:02'),(72,'benutzer.anzeigen','benutzer anzeigen',1,'2026-07-05 12:16:02');
/*!40000 ALTER TABLE `berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `rollen_berechtigungen`
--

LOCK TABLES `rollen_berechtigungen` WRITE;
/*!40000 ALTER TABLE `rollen_berechtigungen` DISABLE KEYS */;
INSERT INTO `rollen_berechtigungen` (`rolle_id`, `berechtigung_id`) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(1,46),(1,47),(1,48),(1,49),(1,50),(1,51),(1,52),(1,53),(1,54),(1,55),(1,56),(1,57),(1,58),(1,59),(1,60),(1,61),(1,62),(1,63),(1,64),(1,65),(1,66),(1,67),(1,68),(1,69),(1,70),(1,71),(1,72),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(2,22),(2,23),(2,24),(2,25),(2,26),(2,27),(2,28),(2,29),(2,30),(2,31),(2,32),(2,33),(2,34),(2,35),(2,36),(2,37),(2,38),(2,39),(2,40),(2,41),(2,42),(2,43),(2,44),(2,45),(2,46),(2,47),(2,48),(2,49),(2,50),(2,51),(2,52),(2,53),(2,54),(2,55),(2,56),(2,57),(2,58),(2,59),(2,60),(2,61),(2,62),(2,64),(2,65),(2,66),(2,67),(2,68),(2,69),(2,70),(2,71),(2,72),(4,1),(4,2),(4,3),(4,4),(4,5),(4,6),(4,7),(4,8),(4,9),(4,10),(4,11),(4,12),(4,13),(4,14),(4,15),(4,16),(4,17),(4,18),(4,19),(4,20),(4,21),(4,22),(4,23),(4,24),(4,25),(4,26),(4,27),(4,28),(4,29),(4,30),(4,31),(4,32),(4,33),(4,34),(4,35),(4,36),(4,37),(4,38),(4,39),(4,40),(4,41),(4,42),(4,43),(4,44),(4,45),(4,46),(4,47),(4,48),(4,49),(4,50),(4,51),(4,52),(4,53),(4,54),(4,55),(4,56),(4,57),(4,58),(4,59),(4,60),(4,61),(4,62),(4,64),(4,65),(4,66),(4,67),(4,68),(4,69),(4,70),(4,71),(4,72),(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,7),(5,8),(5,9),(5,10),(5,11),(5,12),(5,13),(5,14),(5,15),(5,16),(5,17),(5,18),(5,19),(5,20),(5,21),(5,22),(5,23),(5,24),(5,25),(5,26),(5,27),(5,28),(5,29),(5,30),(5,31),(5,32),(5,33),(5,34),(5,35),(5,36),(5,37),(5,38),(5,39),(5,40),(5,41),(5,42),(5,43),(5,44),(5,45),(5,46),(5,47),(5,48),(5,49),(5,50),(5,51),(5,52),(5,53),(5,54),(5,55),(5,56),(5,57),(5,58),(5,59),(5,60),(5,64),(5,65),(5,66),(5,67),(5,68),(5,69),(5,70),(5,71),(5,72),(6,1),(6,5),(6,15),(6,44),(6,45),(7,1),(7,5),(7,9),(7,10),(7,11),(7,12),(7,13),(7,14),(7,15),(7,16),(7,17),(7,18),(7,19),(7,23),(7,24),(7,25),(7,26),(7,27),(7,28),(7,29),(7,30),(7,58),(7,59),(7,60),(8,1),(8,15),(8,42),(8,43),(8,67),(8,69),(8,70),(9,1),(9,2),(9,3),(9,5),(9,6),(9,7),(10,1),(10,5),(10,9),(10,15),(10,19),(10,23),(10,27),(10,35),(10,46),(10,50),(10,54),(10,58),(10,61),(10,64),(10,69),(10,71),(10,72);
/*!40000 ALTER TABLE `rollen_berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `artikel_typen`
--

LOCK TABLES `artikel_typen` WRITE;
/*!40000 ALTER TABLE `artikel_typen` DISABLE KEYS */;
INSERT INTO `artikel_typen` (`id`, `code`, `name`, `teilbar`, `hat_varianten`, `hat_lagerstand`, `ist_download`, `ist_set`, `sortierung`, `aktiv`) VALUES (1,'GARN','Garn',0,1,1,0,0,1,1),(2,'NADEL','Nadel',0,1,1,0,0,2,1),(3,'METERWARE','Meterware',1,1,1,0,0,3,1),(4,'DOWNLOAD','Download',0,0,0,1,0,4,1),(5,'SET','Set',0,0,1,0,1,5,1),(6,'STANDARD','Standard',0,1,1,0,0,6,1);
/*!40000 ALTER TABLE `artikel_typen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `einheiten`
--

LOCK TABLES `einheiten` WRITE;
/*!40000 ALTER TABLE `einheiten` DISABLE KEYS */;
INSERT INTO `einheiten` (`id`, `name`, `kuerzel`, `sortierung`) VALUES (1,'Knäuel','Kn',0),(2,'Meter','m',0),(3,'Gramm','g',0),(4,'Stk','Stk',0),(5,'Set','Set',0);
/*!40000 ALTER TABLE `einheiten` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `steuerklassen`
--

LOCK TABLES `steuerklassen` WRITE;
/*!40000 ALTER TABLE `steuerklassen` DISABLE KEYS */;
INSERT INTO `steuerklassen` (`id`, `name`, `satz`, `land`, `aktiv`, `erstellt_am`) VALUES (1,'Normaler Steuersatz',20.00,'AT',1,'2026-05-29 20:03:50'),(2,'Ermäßigter Steuersatz',10.00,'AT',1,'2026-05-29 20:03:50'),(3,'Steuerfrei',0.00,'AT',1,'2026-05-29 20:03:50');
/*!40000 ALTER TABLE `steuerklassen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `laender`
--

LOCK TABLES `laender` WRITE;
/*!40000 ALTER TABLE `laender` DISABLE KEYS */;
INSERT INTO `laender` (`iso_code`, `name_de`, `ist_eu_mitglied`) VALUES ('AD','Andorra',0),('AE','Vereinigte Arabische Emirate',0),('AF','Afghanistan',0),('AG','Antigua und Barbuda',0),('AI','Anguilla',0),('AL','Albanien',0),('AM','Armenien',0),('AO','Angola',0),('AQ','Antarktis',0),('AR','Argentinien',0),('AS','Amerikanisch-Samoa',0),('AT','Österreich',1),('AU','Australien',0),('AW','Aruba',0),('AX','Åland',0),('AZ','Aserbaidschan',0),('BA','Bosnien und Herzegowina',0),('BB','Barbados',0),('BD','Bangladesch',0),('BE','Belgien',1),('BF','Burkina Faso',0),('BG','Bulgarien',1),('BH','Bahrain',0),('BI','Burundi',0),('BJ','Benin',0),('BL','Saint-Barthélemy',0),('BM','Bermuda',0),('BN','Brunei Darussalam',0),('BO','Bolivien',0),('BQ','Bonaire, Sint Eustatius und Saba',0),('BR','Brasilien',0),('BS','Bahamas',0),('BT','Bhutan',0),('BV','Bouvetinsel',0),('BW','Botsuana',0),('BY','Belarus (Weißrussland)',0),('BZ','Belize',0),('CA','Kanada',0),('CC','Kokosinseln',0),('CD','Kongo, Demokratische Republik',0),('CF','Zentralafrikanische Republik',0),('CG','Kongo',0),('CH','Schweiz',0),('CI','Elfenbeinküste',0),('CK','Cookinseln',0),('CL','Chile',0),('CM','Kamerun',0),('CN','China',0),('CO','Kolumbien',0),('CR','Costa Rica',0),('CU','Kuba',0),('CV','Kap Verde',0),('CW','Curaçao',0),('CX','Weihnachtsinsel',0),('CY','Zypern',1),('CZ','Tschechien',1),('DE','Deutschland',1),('DJ','Dschibuti',0),('DK','Dänemark',1),('DM','Dominica',0),('DO','Dominikanische Republik',0),('DZ','Algerien',0),('EC','Ecuador',0),('EE','Estland',1),('EG','Ägypten',0),('EH','Westsahara',0),('ER','Eritrea',0),('ES','Spanien',1),('ET','Äthiopien',0),('FI','Finnland',1),('FJ','Fidschi',0),('FK','Falklandinseln',0),('FM','Mikronesien',0),('FO','Färöer',0),('FR','Frankreich',1),('GA','Gabun',0),('GB','Vereinigtes Königreich',0),('GD','Grenada',0),('GE','Georgien',0),('GF','Französisch-Guayana',0),('GG','Guernsey',0),('GH','Ghana',0),('GI','Gibraltar',0),('GL','Grönland',0),('GM','Gambia',0),('GN','Guinea',0),('GP','Guadeloupe',0),('GQ','Äquatorialguinea',0),('GR','Griechenland',1),('GS','Südgeorgien und die Südlichen Sandwichinseln',0),('GT','Guatemala',0),('GU','Guam',0),('GW','Guinea-Bissau',0),('GY','Guyana',0),('HK','Hongkong',0),('HM','Heard- und McDonald-Inseln',0),('HN','Honduras',0),('HR','Kroatien',1),('HT','Haiti',0),('HU','Ungarn',1),('ID','Indonesien',0),('IE','Irland',1),('IL','Israel',0),('IM','Isle of Man',0),('IN','Indien',0),('IO','Britisches Territorium im Indischen Ozean',0),('IQ','Irak',0),('IR','Iran',0),('IS','Island',0),('IT','Italien',1),('JE','Jersey',0),('JM','Jamaika',0),('JO','Jordanien',0),('JP','Japan',0),('KE','Kenia',0),('KG','Kirgisistan',0),('KH','Kambodscha',0),('KI','Kiribati',0),('KM','Komoren',0),('KN','St. Kitts und Nevis',0),('KP','Nordkorea',0),('KR','Südkorea',0),('KW','Kuwait',0),('KY','Kaimaninseln',0),('KZ','Kasachstan',0),('LA','Laos',0),('LB','Libanon',0),('LC','St. Lucia',0),('LI','Liechtenstein',0),('LK','Sri Lanka',0),('LR','Liberia',0),('LS','Lesotho',0),('LT','Litauen',1),('LU','Luxemburg',1),('LV','Lettland',1),('LY','Libyen',0),('MA','Marokko',0),('MC','Monaco',0),('MD','Moldau',0),('ME','Montenegro',0),('MF','Saint-Martin (franz. Teil)',0),('MG','Madagaskar',0),('MH','Marshallinseln',0),('MK','Nordmazedonien',0),('ML','Mali',0),('MM','Myanmar',0),('MN','Mongolei',0),('MO','Macau',0),('MP','Nördliche Marianen',0),('MQ','Martinique',0),('MR','Mauretanien',0),('MS','Montserrat',0),('MT','Malta',1),('MU','Mauritius',0),('MV','Malediven',0),('MW','Malawi',0),('MX','Mexiko',0),('MY','Malaysia',0),('MZ','Mosambik',0),('NA','Namibia',0),('NC','Neukaledonien',0),('NE','Niger',0),('NF','Norfolkinsel',0),('NG','Nigeria',0),('NI','Nicaragua',0),('NL','Niederlande',1),('NO','Norwegen',0),('NP','Nepal',0),('NR','Nauru',0),('NU','Niue',0),('NZ','Neuseeland',0),('OM','Oman',0),('PA','Panama',0),('PE','Peru',0),('PF','Französisch-Polynesien',0),('PG','Papua-Neuguinea',0),('PH','Philippinen',0),('PK','Pakistan',0),('PL','Polen',1),('PM','Saint-Pierre und Miquelon',0),('PN','Pitcairninseln',0),('PR','Puerto Rico',0),('PS','Palästina',0),('PT','Portugal',1),('PW','Palau',0),('PY','Paraguay',0),('QA','Katar',0),('RE','Réunion',0),('RO','Rumänien',1),('RS','Serbien',0),('RU','Russland',0),('RW','Ruanda',0),('SA','Saudi-Arabien',0),('SB','Salomonen',0),('SC','Seychellen',0),('SD','Sudan',0),('SE','Schweden',1),('SG','Singapur',0),('SH','St. Helena',0),('SI','Slowenien',1),('SJ','Svalbard und Jan Mayen',0),('SK','Slowakei',1),('SL','Sierra Leone',0),('SM','San Marino',0),('SN','Senegal',0),('SO','Somalia',0),('SR','Suriname',0),('SS','Südsudan',0),('ST','São Tomé und Príncipe',0),('SV','El Salvador',0),('SX','Sint Maarten (niederl. Teil)',0),('SY','Syrien',0),('SZ','Eswatini (Swasiland)',0),('TC','Turks- und Caicosinseln',0),('TD','Tschad',0),('TF','Französische Süd- und Antarktisgebiete',0),('TG','Togo',0),('TH','Thailand',0),('TJ','Tadschikistan',0),('TK','Tokelau',0),('TL','Timor-Leste (Osttimor)',0),('TM','Turkmenistan',0),('TN','Tunesien',0),('TO','Tonga',0),('TR','Türkei',0),('TT','Trinidad und Tobago',0),('TV','Tuvalu',0),('TW','Taiwan',0),('TZ','Tansania',0),('UA','Ukraine',0),('UG','Uganda',0),('UM','Amerikanische Überseeinseln, kleinere',0),('US','Vereinigte Staaten',0),('UY','Uruguay',0),('UZ','Usbekistan',0),('VA','Vatikanstadt',0),('VC','St. Vincent und die Grenadinen',0),('VE','Venezuela',0),('VG','Britische Jungferninseln',0),('VI','Amerikanische Jungferninseln',0),('VN','Vietnam',0),('VU','Vanuatu',0),('WF','Wallis und Futuna',0),('WS','Samoa',0),('YE','Jemen',0),('YT','Mayotte',0),('ZA','Südafrika',0),('ZM','Sambia',0),('ZW','Simbabwe',0);
/*!40000 ALTER TABLE `laender` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `zahlungsbedingungen`
--

LOCK TABLES `zahlungsbedingungen` WRITE;
/*!40000 ALTER TABLE `zahlungsbedingungen` DISABLE KEYS */;
INSERT INTO `zahlungsbedingungen` (`id`, `name`, `beschreibung`, `netto_tage`, `skonto_prozent`, `skonto_tage`, `aktiv`, `erstellt_am`) VALUES (1,'Sofort fällig','Zahlung bei Erhalt / Lieferung',0,0.00,0,1,'2026-06-19 14:10:29'),(2,'14 Tage netto','Zahlung innerhalb 14 Tagen',14,0.00,0,1,'2026-06-19 14:10:29'),(3,'30 Tage netto','Zahlung innerhalb 30 Tagen',30,0.00,0,1,'2026-06-19 14:10:29'),(4,'14/2 30 netto','2% Skonto bei Zahlung bis 14 Tage, sonst 30 Tage',30,2.00,14,1,'2026-06-19 14:10:29'),(5,'Vorauskasse','Zahlung vor Lieferung',0,0.00,0,1,'2026-06-19 14:10:29');
/*!40000 ALTER TABLE `zahlungsbedingungen` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-09 18:43:53

-- ============================================================
-- Fixe Einzel-Seeds
-- ============================================================
-- Manuelle Seed-Zeilen für Neuinstallationen (Teil der Baseline, ersetzt die alten
-- Migrationen 011, 013, 046, 064, 078, 097, 105 sowie den Rollen-Teil von 005).

-- Jarvis: System-Benutzer für Logging ohne Session (Cronjobs, BFR-Nachsignierung).
-- Passwort '!' ist absichtlich kein gueltiger bcrypt-Hash, Login unmoeglich.
-- Keine feste ID noetig: Code sucht ueberall per username='system'.
INSERT IGNORE INTO benutzer (username, passwort, formularname, aktiv)
VALUES ('system', '!', 'Jarvis (System)', 1);

-- Diverses (Kasse): FK-Platzhalter fuer freie Kassenpositionen ohne echten Artikel.
-- Code sucht per artikelnummer='99-9999', keine feste ID noetig.
INSERT IGNORE INTO artikel
    (artikelnummer, name, artikeltyp_id, steuerklasse_id, einheit_id,
     ueberverkauf_erlaubt, hat_eigenen_lagerstand, aktiv)
SELECT
    '99-9999',
    'Diverses (Kasse)',
    (SELECT id FROM artikel_typen  WHERE code = 'STANDARD' LIMIT 1),
    (SELECT id FROM steuerklassen  WHERE satz = 20 AND land = 'AT' LIMIT 1),
    (SELECT MIN(id) FROM einheiten),
    1, 0, 1;

-- Laufkunde: fixer Systemdatensatz, wird an der Kasse als Anonymous-Kunde verwendet.
INSERT IGNORE INTO kunden (kundennummer, status, ist_laufkunde, kundenherkunft)
VALUES ('LAUFKUNDE', 'aktiv', 1, 'kasse');

-- Hauptkanal: neutraler Default-Shop (shop_id 1) fuer Auftraege ohne Kanalbindung
-- (Kasse etc.). Echte Multi-Shop-Kanaele legt jede Installation selbst an.
INSERT IGNORE INTO shops (slug, name, sub_marke, ist_aktiv)
VALUES ('hauptkanal', 'Hauptkanal', 0, 1);

-- Versandklassen: generische Standardwerte. artikel_gruppe_id bewusst NULL
-- (Buchhaltungs-Kontenzuordnung ist installationsspezifisch, wird spaeter
-- in Einstellungen -> Buchhaltung nachgetragen).
INSERT INTO versandklassen (name, code, kuerzel, sortierung, preis_brutto, artikel_gruppe_id) VALUES
    ('Standardversand mit Post AT',           'SAT',  'Std. AT',    1, 6.50,  NULL),
    ('Versand + Teillieferung mit Post AT',   'TLAT', 'Std. TL AT', 2, 9.50,  NULL),
    ('Nachnahme Post AT',                     'NN',   'NN AT',      3, 13.00, NULL),
    ('Standardversand DE',                    'SDE',  'Std. DE',    4, 9.90,  NULL),
    ('Standardversand IT/HU',                 'SEU',  'Std. EU',    5, 15.90, NULL);
