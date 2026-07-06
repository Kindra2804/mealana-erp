
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3502 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2922 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1590 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2913 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `menge` int(10) unsigned NOT NULL,
  `menge_geliefert` int(10) unsigned NOT NULL DEFAULT 0,
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
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  `geaendert_am` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kassen_bon_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kassen_bon_positionen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `bon_id` int(10) unsigned NOT NULL,
  `block` enum('auftrag','addon','storno') DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `pickliste_auftraege`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pickliste_auftraege` (
  `pickliste_id` int(10) unsigned NOT NULL,
  `auftrag_id` int(11) NOT NULL,
  PRIMARY KEY (`pickliste_id`,`auftrag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dateiname` varchar(255) NOT NULL,
  `angewendet_am` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dateiname` (`dateiname`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
DROP TABLE IF EXISTS `system_einstellungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_einstellungen` (
  `schluessel` varchar(80) NOT NULL,
  `wert` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`schluessel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=641 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

