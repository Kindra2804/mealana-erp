
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktionen` WRITE;
/*!40000 ALTER TABLE `aktionen` DISABLE KEYS */;
INSERT INTO `aktionen` VALUES (3,'Sommer 2026','Testkategoriepreise',1,'2026-06-19 18:16:14'),(4,'Mai 2026',NULL,1,'2026-06-19 18:28:13'),(5,'November 2026',NULL,0,'2026-06-19 18:29:06');
/*!40000 ALTER TABLE `aktionen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `aktionen_artikel_preise` WRITE;
/*!40000 ALTER TABLE `aktionen_artikel_preise` DISABLE KEYS */;
/*!40000 ALTER TABLE `aktionen_artikel_preise` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktionen_kategorien` WRITE;
/*!40000 ALTER TABLE `aktionen_kategorien` DISABLE KEYS */;
INSERT INTO `aktionen_kategorien` VALUES (1,3,135,'2026-06-15','2026-06-28','2026-06-19 18:16:33'),(4,4,137,'2026-05-01','2026-05-30','2026-06-19 18:28:31'),(5,5,136,'2026-11-01','2026-12-01','2026-06-19 18:29:20');
/*!40000 ALTER TABLE `aktionen_kategorien` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=280 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktivitaeten` WRITE;
/*!40000 ALTER TABLE `aktivitaeten` DISABLE KEYS */;
INSERT INTO `aktivitaeten` VALUES (260,1,'kategorie.bearbeiten','kategorien',1,'{\"name\":\"Wolle und Garne\",\"parent_id\":null}','2026-06-19 18:14:16'),(261,1,'kategorie.anlegen','kategorien',135,'{\"name\":\"Aktionskategorie aktiv\",\"parent_id\":null}','2026-06-19 18:15:03'),(262,1,'kategorie.anlegen','kategorien',136,'{\"name\":\"Aktionskategorie geplant\",\"parent_id\":null}','2026-06-19 18:15:24'),(263,1,'kategorie.anlegen','kategorien',137,'{\"name\":\"Aktionskategorie abgelaufen\",\"parent_id\":null}','2026-06-19 18:15:38'),(264,1,'kategorie.bearbeiten','kategorien',137,'{\"name\":\"Aktionskategorie abgelaufen\",\"parent_id\":null}','2026-06-19 18:15:44'),(265,1,'aktion.anlegen','aktionen',3,'{\"name\":\"Sommer 2026\"}','2026-06-19 18:16:14'),(266,1,'aktion.kategorie.hinzufuegen','aktionen',3,'{\"kategorie_id\":135}','2026-06-19 18:16:33'),(267,1,'aktion.kategorie.hinzufuegen','aktionen',3,'{\"kategorie_id\":137}','2026-06-19 18:16:54'),(268,1,'aktion.kategorie.hinzufuegen','aktionen',3,'{\"kategorie_id\":136}','2026-06-19 18:17:13'),(269,1,'aktion.starten','aktionen',3,NULL,'2026-06-19 18:17:20'),(270,1,'aktion.bearbeiten','aktionen',3,'{\"name\":\"Sommer 2026\"}','2026-06-19 18:17:29'),(271,1,'aktion.bearbeiten','aktionen',3,'{\"name\":\"Sommer 2026\"}','2026-06-19 18:27:55'),(272,1,'aktion.anlegen','aktionen',4,'{\"name\":\"Mai 2026\"}','2026-06-19 18:28:13'),(273,1,'aktion.kategorie.hinzufuegen','aktionen',4,'{\"kategorie_id\":137}','2026-06-19 18:28:31'),(274,1,'aktion.bearbeiten','aktionen',4,'{\"name\":\"Mai 2026\"}','2026-06-19 18:28:38'),(275,1,'aktion.starten','aktionen',4,NULL,'2026-06-19 18:28:40'),(276,1,'aktion.anlegen','aktionen',5,'{\"name\":\"November 2026\"}','2026-06-19 18:29:06'),(277,1,'aktion.kategorie.hinzufuegen','aktionen',5,'{\"kategorie_id\":136}','2026-06-19 18:29:20'),(278,1,'aktion.bearbeiten','aktionen',5,'{\"name\":\"November 2026\"}','2026-06-19 18:29:30'),(279,1,'hersteller.bearbeiten','hersteller',1,'{\"name\":\"DROPS Design\"}','2026-06-19 19:44:17');
/*!40000 ALTER TABLE `aktivitaeten` ENABLE KEYS */;
UNLOCK TABLES;
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
  CONSTRAINT `fk_art_partner` FOREIGN KEY (`partner_id`) REFERENCES `partner` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_artikel_artikeltyp` FOREIGN KEY (`artikeltyp_id`) REFERENCES `artikel_typen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_einheitId` FOREIGN KEY (`einheit_id`) REFERENCES `einheiten` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_hersteller` FOREIGN KEY (`hersteller_id`) REFERENCES `hersteller` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_steuerklasse` FOREIGN KEY (`steuerklasse_id`) REFERENCES `steuerklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_vater` FOREIGN KEY (`vaterartikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artikel_versandklasse` FOREIGN KEY (`versandklasse_id`) REFERENCES `versandklassen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_zustand_vater` FOREIGN KEY (`zustand_vater_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel` WRITE;
/*!40000 ALTER TABLE `artikel` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel` ENABLE KEYS */;
UNLOCK TABLES;
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
  PRIMARY KEY (`id`),
  KEY `fk_artAchs_artikel_id` (`artikel_id`),
  KEY `fk_artAchs_achse_id` (`achse_id`),
  KEY `fk_artAchs_bedingungs_achse_id` (`bedingungs_achse_id`),
  KEY `fk_artAchs_bedingungs_wert_id` (`bedingungs_wert_id`),
  CONSTRAINT `fk_artAchs_achse_id` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_artikel_id` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_bedingungs_achse_id` FOREIGN KEY (`bedingungs_achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_artAchs_bedingungs_wert_id` FOREIGN KEY (`bedingungs_wert_id`) REFERENCES `varianten_achse_werte` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_achsen` WRITE;
/*!40000 ALTER TABLE `artikel_achsen` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_achsen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_bilder` WRITE;
/*!40000 ALTER TABLE `artikel_bilder` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_bilder` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `artikel_bilder_shops` WRITE;
/*!40000 ALTER TABLE `artikel_bilder_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_bilder_shops` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_codes` WRITE;
/*!40000 ALTER TABLE `artikel_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_codes` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `artikel_externe_referenzen` WRITE;
/*!40000 ALTER TABLE `artikel_externe_referenzen` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_externe_referenzen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `artikel_kategorien` WRITE;
/*!40000 ALTER TABLE `artikel_kategorien` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_kategorien` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_lieferanten` WRITE;
/*!40000 ALTER TABLE `artikel_lieferanten` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_lieferanten` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_merkmale` WRITE;
/*!40000 ALTER TABLE `artikel_merkmale` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_merkmale` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_preise` WRITE;
/*!40000 ALTER TABLE `artikel_preise` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_preise` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_staffelpreise` WRITE;
/*!40000 ALTER TABLE `artikel_staffelpreise` DISABLE KEYS */;
/*!40000 ALTER TABLE `artikel_staffelpreise` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_typen` WRITE;
/*!40000 ALTER TABLE `artikel_typen` DISABLE KEYS */;
INSERT INTO `artikel_typen` VALUES (1,'GARN','Garn',0,1,1,0,0,1,1),(2,'NADEL','Nadel',0,1,1,0,0,2,1),(3,'METERWARE','Meterware',1,1,1,0,0,3,1),(4,'DOWNLOAD','Download',0,0,0,1,0,4,1),(5,'SET','Set',0,0,1,0,1,5,1),(6,'STANDARD','Standard',0,1,1,0,0,6,1);
/*!40000 ALTER TABLE `artikel_typen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `benutzer` WRITE;
/*!40000 ALTER TABLE `benutzer` DISABLE KEYS */;
INSERT INTO `benutzer` VALUES (1,'admin','$2y$10$Apn.W3t.e9RPE/8B7I7JQungWu/6MyQDl70iwNOmgqLAUqld9BjR2','Admin',NULL,'Administrator','indy1@gmx.at',1,'2026-06-01 20:33:14','2026-06-01 20:33:14'),(2,'system','!','Jarvis','Worker','Jarvis',NULL,0,'2026-06-07 15:04:17','2026-06-07 17:41:03');
/*!40000 ALTER TABLE `benutzer` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `benutzer_einstellungen` WRITE;
/*!40000 ALTER TABLE `benutzer_einstellungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `benutzer_einstellungen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `benutzer_rollen` WRITE;
/*!40000 ALTER TABLE `benutzer_rollen` DISABLE KEYS */;
INSERT INTO `benutzer_rollen` VALUES (1,1);
/*!40000 ALTER TABLE `benutzer_rollen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `berechtigungen` WRITE;
/*!40000 ALTER TABLE `berechtigungen` DISABLE KEYS */;
INSERT INTO `berechtigungen` VALUES (1,'artikel.anzeigen','artikel anzeigen',1,'2026-06-01 20:27:22'),(2,'artikel.bearbeiten','artikel bearbeiten',1,'2026-06-01 20:27:22'),(3,'artikel.anlegen','artikel anlegen',1,'2026-06-01 20:27:22'),(4,'artikel.loeschen','artikel löschen',1,'2026-06-01 20:27:22'),(5,'varianten.anzeigen','varianten anzeigen',1,'2026-06-01 20:27:22'),(6,'varianten.bearbeiten','varianten bearbeiten',1,'2026-06-01 20:27:22'),(7,'varianten.anlegen','varianten anlegen',1,'2026-06-01 20:27:22'),(8,'varianten.loeschen','varianten löschen',1,'2026-06-01 20:27:22'),(9,'lager.anzeigen','lager anzeigen',1,'2026-06-01 20:27:22'),(10,'lager.bearbeiten','lager bearbeiten',1,'2026-06-01 20:27:22'),(11,'lager.anlegen','lager anlegen',1,'2026-06-01 20:27:22'),(12,'lager.loeschen','lager löschen',1,'2026-06-01 20:27:22'),(13,'wareneingang.buchen','wareneingang buchen',1,'2026-06-01 20:27:22'),(14,'wareneingang.bearbeiten','wareneingang bearbeiten',1,'2026-06-01 20:27:22'),(15,'bestand.anzeigen','bestand anzeigen',1,'2026-06-01 20:27:22'),(16,'bestand.bearbeiten','bestand bearbeiten',1,'2026-06-01 20:27:22'),(17,'bestand.korrigieren','bestand korrigieren',1,'2026-06-01 20:27:22'),(18,'bestand.loeschen','bestand löschen',1,'2026-06-01 20:27:22'),(19,'lieferanten.anzeigen','lieferanten anzeigen',1,'2026-06-01 20:27:22'),(20,'lieferanten.bearbeiten','lieferanten bearbeiten',1,'2026-06-01 20:27:22'),(21,'lieferanten.anlegen','lieferanten anlegen',1,'2026-06-01 20:27:22'),(22,'lieferanten.loeschen','lieferanten löschen',1,'2026-06-01 20:27:22'),(23,'inventur.anzeigen','inventur anzeigen',1,'2026-06-01 20:27:22'),(24,'inventur.bearbeiten','inventur bearbeiten',1,'2026-06-01 20:27:22'),(25,'inventur.anlegen','inventur anlegen',1,'2026-06-01 20:27:22'),(26,'inventur.loeschen','inventur löschen',1,'2026-06-01 20:27:22'),(27,'inventurpositionen.anzeigen','inventurpositionen anzeigen',1,'2026-06-01 20:27:22'),(28,'inventurpositionen.bearbeiten','inventurpositionen bearbeiten',1,'2026-06-01 20:27:22'),(29,'inventurpositionen.anlegen','inventurpositionen anlegen',1,'2026-06-01 20:27:22'),(30,'inventurpositionen.loeschen','inventurpositionen löschen',1,'2026-06-01 20:27:22'),(31,'benutzer.anlegen','benutzer anlegen',1,'2026-06-01 20:27:22'),(32,'benutzer.bearbeiten','benutzer bearbeiten',1,'2026-06-01 20:27:22'),(33,'benutzer.loeschen','benutzer löschen',1,'2026-06-01 20:27:22'),(34,'api.zugriff','API Zugriff',1,'2026-06-01 20:27:22'),(35,'berichte.anzeigen','berichte anzeigen',1,'2026-06-01 20:27:22'),(36,'berichte.bearbeiten','berichte bearbeiten',1,'2026-06-01 20:27:22'),(37,'berichte.anlegen','berichte anlegen',1,'2026-06-01 20:27:22'),(38,'berichte.loeschen','berichte löschen',1,'2026-06-01 20:27:22'),(39,'berichte.drucken','berichte drucken',1,'2026-06-01 20:27:22'),(40,'shopabgleich.starten','shopabgleich starten',1,'2026-06-01 20:27:22'),(41,'shopabgleich.stoppen','shopabgleich stoppen',1,'2026-06-01 20:27:22'),(42,'packplatz.starten','packplatz starten',1,'2026-06-01 20:27:22'),(43,'packplatz.stoppen','packplatz stoppen',1,'2026-06-01 20:27:22'),(44,'kasse.starten','kasse starten',1,'2026-06-01 20:27:22'),(45,'kasse.stoppen','kasse stoppen',1,'2026-06-01 20:27:22');
/*!40000 ALTER TABLE `berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `dokument_nummern`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokument_nummern` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung') NOT NULL,
  `praefix` varchar(10) NOT NULL,
  `jahr` smallint(6) NOT NULL,
  `letzt_nr` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dok_typ_jahr` (`typ`,`jahr`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `dokument_nummern` WRITE;
/*!40000 ALTER TABLE `dokument_nummern` DISABLE KEYS */;
INSERT INTO `dokument_nummern` VALUES (1,'rechnung','R',2026,0),(2,'gutschrift','GS',2026,0),(3,'lieferschein','LS',2026,0),(4,'mietrechnung','MR',2026,0),(5,'abrechnung','AB',2026,0);
/*!40000 ALTER TABLE `dokument_nummern` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `einheiten` WRITE;
/*!40000 ALTER TABLE `einheiten` DISABLE KEYS */;
INSERT INTO `einheiten` VALUES (1,'Knäuel','Kn',0),(2,'Meter','m',0),(3,'Gramm','g',0),(4,'Stk','Stk',0),(5,'Set','Set',0);
/*!40000 ALTER TABLE `einheiten` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `hersteller` WRITE;
/*!40000 ALTER TABLE `hersteller` DISABLE KEYS */;
INSERT INTO `hersteller` VALUES (1,'DROPS Design',NULL,'www.garnstudio.com','NO',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-29 20:03:50',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'Schachenmayr',NULL,'www.schachenmayr.com','DE',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-29 20:03:50',NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,'Lang Yarns',NULL,'www.langyarns.com','CH',NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-29 20:03:50',NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `hersteller` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kategorien` WRITE;
/*!40000 ALTER TABLE `kategorien` DISABLE KEYS */;
INSERT INTO `kategorien` VALUES (1,NULL,'Wolle und Garne',1,1,0,NULL,NULL),(2,NULL,'Nadeln',2,1,0,NULL,NULL),(3,NULL,'Zubehör',3,1,0,NULL,NULL),(4,NULL,'Bücher und Anleitungen',4,1,0,NULL,NULL),(72,1,'Hersteller',1,1,0,NULL,NULL),(73,72,'Garnstudio DROPS',1,1,0,NULL,NULL),(74,72,'Schachenmayr',2,1,0,NULL,NULL),(75,72,'Lang Yarns',3,1,0,NULL,NULL),(76,72,'Opal',4,1,0,NULL,NULL),(77,72,'Regia',5,1,0,NULL,NULL),(78,72,'Katia',6,1,0,NULL,NULL),(79,72,'Scheepjes',7,1,0,NULL,NULL),(80,72,'Rico Design',8,1,0,NULL,NULL),(81,72,'Ferner Wolle',9,1,0,NULL,NULL),(82,72,'Pro Lana',10,1,0,NULL,NULL),(83,72,'Cheval Blanc',11,1,0,NULL,NULL),(84,72,'Rosy Green Wool',12,1,0,NULL,NULL),(85,72,'Kremke Soul Wool',13,1,0,NULL,NULL),(86,72,'BC Garn',14,1,0,NULL,NULL),(87,72,'Rellana',15,1,0,NULL,NULL),(88,72,'CraSy',16,1,0,NULL,NULL),(89,72,'Hoooked',17,1,0,NULL,NULL),(90,72,'MEALANA',18,1,0,NULL,NULL),(91,72,'Sonstige',99,1,0,NULL,NULL),(92,1,'Pakete / Sets',2,1,0,NULL,NULL),(93,92,'Pakete für Socken',1,1,0,NULL,NULL),(94,92,'Pakete für Tücher/Schals',2,1,0,NULL,NULL),(95,92,'Pakete für Hauben/Handschuhe',3,1,0,NULL,NULL),(96,2,'Nadelart',1,1,0,NULL,NULL),(97,96,'Rundnadeln',1,1,0,NULL,NULL),(98,96,'Nadelspiele',2,1,0,NULL,NULL),(99,96,'Häkelnadeln',3,1,0,NULL,NULL),(100,96,'Stricknadeln',4,1,0,NULL,NULL),(101,96,'Paarnadeln',5,1,0,NULL,NULL),(102,96,'Knooking-Nadeln',6,1,0,NULL,NULL),(103,96,'Tunesische Nadeln',7,1,0,NULL,NULL),(104,96,'Nähnadeln',8,1,0,NULL,NULL),(105,96,'Sets & austauschbare Systeme',9,1,0,NULL,NULL),(106,2,'Hersteller',2,1,0,NULL,NULL),(107,106,'Addi',1,1,0,NULL,NULL),(108,106,'ChiaoGoo',2,1,0,NULL,NULL),(109,106,'KnitPro',3,1,0,NULL,NULL),(110,106,'HiyaHiya',4,1,0,NULL,NULL),(111,106,'DROPS',5,1,0,NULL,NULL),(112,106,'LYKKE',6,1,0,NULL,NULL),(113,106,'PRYM',7,1,0,NULL,NULL),(114,106,'Sonstige',99,1,0,NULL,NULL),(115,3,'Knöpfe',1,1,0,NULL,NULL),(116,3,'Reißverschlüsse',2,1,0,NULL,NULL),(117,3,'Bänder und Kordeln',3,1,0,NULL,NULL),(118,3,'Strick- und Häkelhilfen',4,1,0,NULL,NULL),(119,3,'Taschenzubehör',5,1,0,NULL,NULL),(120,3,'Aufbewahrung und Ordnung',6,1,0,NULL,NULL),(121,3,'Sonstiges',99,1,0,NULL,NULL),(122,4,'Schwerpunkt',20,1,0,NULL,NULL),(123,122,'Stricken',10,1,0,NULL,NULL),(124,122,'Häkeln',30,1,0,NULL,NULL),(125,122,'Socken',40,1,0,NULL,NULL),(126,122,'Tücher und Schals',50,1,0,NULL,NULL),(127,122,'Filzen',60,1,0,NULL,NULL),(128,122,'Amigurumi',20,1,0,NULL,NULL),(129,122,'Sonstiges',70,1,0,NULL,NULL),(130,4,'Hersteller/Verlag',10,1,0,NULL,NULL),(131,130,'DROPS',1,1,0,NULL,NULL),(132,130,'Lang Yarns / FAM',2,1,0,NULL,NULL),(133,130,'Scheepjes',3,1,0,NULL,NULL),(134,130,'Sonstige',99,1,0,NULL,NULL),(135,NULL,'Aktionskategorie aktiv',0,1,1,NULL,NULL),(136,NULL,'Aktionskategorie geplant',0,1,1,NULL,NULL),(137,NULL,'Aktionskategorie abgelaufen',0,1,1,NULL,NULL);
/*!40000 ALTER TABLE `kategorien` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `kommissions_abrechnungen` WRITE;
/*!40000 ALTER TABLE `kommissions_abrechnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kommissions_abrechnungen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kunden` WRITE;
/*!40000 ALTER TABLE `kunden` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kunden_adressen` WRITE;
/*!40000 ALTER TABLE `kunden_adressen` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_adressen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `kunden_ansprechpartner` WRITE;
/*!40000 ALTER TABLE `kunden_ansprechpartner` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_ansprechpartner` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kunden_dsgvo_consent` WRITE;
/*!40000 ALTER TABLE `kunden_dsgvo_consent` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_dsgvo_consent` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `kunden_merge_queue` WRITE;
/*!40000 ALTER TABLE `kunden_merge_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_merge_queue` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `kunden_shops` WRITE;
/*!40000 ALTER TABLE `kunden_shops` DISABLE KEYS */;
/*!40000 ALTER TABLE `kunden_shops` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kundengruppen` WRITE;
/*!40000 ALTER TABLE `kundengruppen` DISABLE KEYS */;
INSERT INTO `kundengruppen` VALUES (1,'Endkunden',1,1,'2026-05-30 16:10:03','endkunde'),(2,'Händler',0,1,'2026-05-30 16:10:03','endkunde'),(3,'Kleingewerblich-Künstler',0,1,'2026-05-30 16:10:03','endkunde'),(4,'Endkunden-Rechnung',0,1,'2026-05-30 16:10:03','endkunde');
/*!40000 ALTER TABLE `kundengruppen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `lager` WRITE;
/*!40000 ALTER TABLE `lager` DISABLE KEYS */;
INSERT INTO `lager` VALUES (1,'Ladengeschäft','ladengeschaeft',1,'2026-05-30 16:54:27'),(2,'Messestand','messe',1,'2026-05-30 16:54:27'),(3,'Privathaus-Keller','lager',1,'2026-05-30 16:54:27');
/*!40000 ALTER TABLE `lager` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lager_bewegungen` WRITE;
/*!40000 ALTER TABLE `lager_bewegungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `lager_bewegungen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lagerbestand` WRITE;
/*!40000 ALTER TABLE `lagerbestand` DISABLE KEYS */;
/*!40000 ALTER TABLE `lagerbestand` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `lieferanten` WRITE;
/*!40000 ALTER TABLE `lieferanten` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `lieferanten_vertreter` WRITE;
/*!40000 ALTER TABLE `lieferanten_vertreter` DISABLE KEYS */;
/*!40000 ALTER TABLE `lieferanten_vertreter` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `merkmal_artikeltypen` WRITE;
/*!40000 ALTER TABLE `merkmal_artikeltypen` DISABLE KEYS */;
INSERT INTO `merkmal_artikeltypen` VALUES (1,1),(6,1);
/*!40000 ALTER TABLE `merkmal_artikeltypen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `merkmal_gruppen` WRITE;
/*!40000 ALTER TABLE `merkmal_gruppen` DISABLE KEYS */;
INSERT INTO `merkmal_gruppen` VALUES (1,'Garninfo',1,'2026-05-30 18:12:39'),(2,'Verarbeitung',1,'2026-05-30 18:12:39'),(3,'Pflege',1,'2026-05-30 18:12:39');
/*!40000 ALTER TABLE `merkmal_gruppen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `merkmal_werte` WRITE;
/*!40000 ALTER TABLE `merkmal_werte` DISABLE KEYS */;
INSERT INTO `merkmal_werte` VALUES (1,1,'125m',10),(2,1,'200m',20),(3,5,'2mm',10),(4,5,'2,5mm',20),(5,5,'3mm',30),(6,1,'320m',30);
/*!40000 ALTER TABLE `merkmal_werte` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `merkmale` WRITE;
/*!40000 ALTER TABLE `merkmale` DISABLE KEYS */;
INSERT INTO `merkmale` VALUES (1,1,'Lauflänge','lauflaenge','g/m','text',1,0,10,1,'2026-05-30 18:12:39'),(2,1,'Zusammensetzung','','','text',0,0,20,1,'2026-05-30 18:12:39'),(3,1,'Garngruppe','','','text',1,0,30,1,'2026-05-30 18:12:39'),(4,2,'Nadelstärke von','','mm','zahl',1,0,40,1,'2026-05-30 18:12:39'),(5,2,'Nadelstärke bis','','mm','zahl',1,0,50,1,'2026-05-30 18:12:39'),(6,2,'Maschenprobe','','','text',1,0,60,1,'2026-05-30 18:12:39');
/*!40000 ALTER TABLE `merkmale` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `miet_rechnungen` WRITE;
/*!40000 ALTER TABLE `miet_rechnungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `miet_rechnungen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `mietfach_mietvertraege` WRITE;
/*!40000 ALTER TABLE `mietfach_mietvertraege` DISABLE KEYS */;
/*!40000 ALTER TABLE `mietfach_mietvertraege` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `mietfaecher` WRITE;
/*!40000 ALTER TABLE `mietfaecher` DISABLE KEYS */;
/*!40000 ALTER TABLE `mietfaecher` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `partner` WRITE;
/*!40000 ALTER TABLE `partner` DISABLE KEYS */;
INSERT INTO `partner` VALUES (1,'Mollramer Seifen Fabrik','kommission','indy1@gmx.at','06764538267',NULL,NULL,NULL,0,0.00,'getrennt','fremdrechnung',NULL,1,'2026-06-21 18:24:00','2026-06-21 18:24:00');
/*!40000 ALTER TABLE `partner` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `preis_aktionen_positionen` WRITE;
/*!40000 ALTER TABLE `preis_aktionen_positionen` DISABLE KEYS */;
/*!40000 ALTER TABLE `preis_aktionen_positionen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `reservierungen` WRITE;
/*!40000 ALTER TABLE `reservierungen` DISABLE KEYS */;
/*!40000 ALTER TABLE `reservierungen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `rollen` WRITE;
/*!40000 ALTER TABLE `rollen` DISABLE KEYS */;
INSERT INTO `rollen` VALUES (1,'superadmin','Zugriff auf Alles + API-Zugriff + Benutzerverwaltung',1,'2026-06-01 19:47:17'),(2,'admin','Administrator Zugang zu Artikel, Lager, Lieferanten, Berichte',1,'2026-06-01 19:47:17'),(3,'mitarbeiter','Lager, Kasse, Packplatz',1,'2026-06-01 19:47:17');
/*!40000 ALTER TABLE `rollen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `rollen_berechtigungen` WRITE;
/*!40000 ALTER TABLE `rollen_berechtigungen` DISABLE KEYS */;
INSERT INTO `rollen_berechtigungen` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(1,45),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(2,22),(2,23),(2,24),(2,25),(2,26),(2,27),(2,28),(2,29),(2,30),(2,31),(2,32),(2,35),(2,36),(2,37),(2,38),(2,39),(2,42),(2,43),(2,44),(2,45),(3,1),(3,5),(3,9),(3,13),(3,15),(3,17),(3,19),(3,23),(3,27),(3,35),(3,39),(3,42),(3,43),(3,44),(3,45);
/*!40000 ALTER TABLE `rollen_berechtigungen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `spenden_log` WRITE;
/*!40000 ALTER TABLE `spenden_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `spenden_log` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `steuerklassen` WRITE;
/*!40000 ALTER TABLE `steuerklassen` DISABLE KEYS */;
INSERT INTO `steuerklassen` VALUES (1,'Normaler Steuersatz',20.00,'AT',1,'2026-05-29 20:03:50'),(2,'Ermäßigter Steuersatz',10.00,'AT',1,'2026-05-29 20:03:50'),(3,'Steuerfrei',0.00,'AT',1,'2026-05-29 20:03:50');
/*!40000 ALTER TABLE `steuerklassen` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `system_einstellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_einstellungen` (
  `schluessel` varchar(80) NOT NULL,
  `wert` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`schluessel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `system_einstellungen` WRITE;
/*!40000 ALTER TABLE `system_einstellungen` DISABLE KEYS */;
INSERT INTO `system_einstellungen` VALUES ('besteuerungsart','normal');
/*!40000 ALTER TABLE `system_einstellungen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `varianten_achse_werte` WRITE;
/*!40000 ALTER TABLE `varianten_achse_werte` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achse_werte` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `varianten_achsen` WRITE;
/*!40000 ALTER TABLE `varianten_achsen` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_achsen` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `varianten_kombination_werte` WRITE;
/*!40000 ALTER TABLE `varianten_kombination_werte` DISABLE KEYS */;
/*!40000 ALTER TABLE `varianten_kombination_werte` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `versandklassen` WRITE;
/*!40000 ALTER TABLE `versandklassen` DISABLE KEYS */;
/*!40000 ALTER TABLE `versandklassen` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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

