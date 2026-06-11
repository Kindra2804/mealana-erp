
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
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktivitaeten` WRITE;
/*!40000 ALTER TABLE `aktivitaeten` DISABLE KEYS */;
INSERT INTO `aktivitaeten` VALUES (1,1,'artikel.bearbeiten','artikel',8,'{\"name\":\"Testartikel\"}','2026-06-04 17:41:57'),(2,1,'artikel.kategorien_aktualisieren','artikel',8,'{\"kategorie_ids\":[4,3]}','2026-06-04 17:41:57'),(3,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-04 17:42:20'),(4,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-04 17:42:20'),(5,1,'lieferant.bearbeiten','lieferanten',1,'{\"name\":\"DROPS Design A\\/S\"}','2026-06-04 20:25:59'),(6,1,'wareneingang.buchen','lagerbestand',2,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-04 20:27:32'),(7,1,'artikel.anlegen','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-04 20:29:10'),(8,1,'wareneingang.buchen','lagerbestand',3,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"2\",\"bestand_nachher\":7}','2026-06-05 08:45:33'),(9,1,'wareneingang.buchen','lagerbestand',4,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-05 08:46:00'),(10,1,'artikel.anlegen','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:20:24'),(11,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:20:39'),(12,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 13:20:39'),(13,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:23:47'),(14,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 13:23:47'),(15,1,'wareneingang.buchen','lagerbestand',5,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"4\",\"bestand_nachher\":4}','2026-06-05 17:14:29'),(16,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 17:15:09'),(17,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 17:15:09'),(18,1,'artikel.anlegen','artikel',14,'{\"name\":\"chargenartikel\"}','2026-06-05 17:16:16'),(19,1,'wareneingang.buchen','lagerbestand',6,'{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"15\",\"bestand_nachher\":15}','2026-06-05 17:16:53'),(20,1,'lager.charge_nachtragen','lagerbestand',9,'{\"charge\":\"7768\"}','2026-06-05 17:17:41'),(21,1,'lager.charge_nachtragen','lagerbestand',9,'{\"charge\":\"00079\"}','2026-06-05 17:17:52'),(22,1,'artikel.bearbeiten','artikel',14,'{\"name\":\"chargenartikel\"}','2026-06-05 17:21:59'),(23,1,'artikel.kategorien_aktualisieren','artikel',14,'{\"kategorie_ids\":[]}','2026-06-05 17:21:59'),(24,1,'vertreter.anlegen','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 11:30:31'),(25,1,'vertreter.bearbeiten','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 11:30:42'),(26,1,'vertreter.anlegen','lieferanten_vertreter',4,'{\"nachname\":\"Indra\"}','2026-06-06 11:31:09'),(27,1,'vertreter.loeschen','lieferanten_vertreter',4,NULL,'2026-06-06 11:31:13'),(28,1,'vertreter.anlegen','lieferanten_vertreter',5,'{\"nachname\":\"Indra\"}','2026-06-06 11:33:21'),(29,1,'vertreter.loeschen','lieferanten_vertreter',5,NULL,'2026-06-06 11:33:26'),(30,1,'vertreter.anlegen','lieferanten_vertreter',6,'{\"nachname\":\"Indra\"}','2026-06-06 11:34:24'),(31,1,'vertreter.bearbeiten','lieferanten_vertreter',6,'{\"nachname\":\"Indra\"}','2026-06-06 11:34:33'),(32,1,'vertreter.loeschen','lieferanten_vertreter',6,NULL,'2026-06-06 11:34:38'),(33,1,'vertreter.anlegen','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:36:08'),(34,1,'vertreter.bearbeiten','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:36:15'),(35,1,'vertreter.bearbeiten','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:45:49'),(36,1,'vertreter.loeschen','lieferanten_vertreter',7,NULL,'2026-06-06 12:22:12'),(37,1,'vertreter.bearbeiten','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 12:32:19'),(38,1,'vertreter.loeschen','lieferanten_vertreter',3,NULL,'2026-06-06 12:32:25'),(39,1,'vertreter.anlegen','lieferanten_vertreter',8,'{\"nachname\":\"Indra\"}','2026-06-06 12:35:35'),(40,1,'vertreter.bearbeiten','lieferanten_vertreter',8,'{\"nachname\":\"Indra\"}','2026-06-06 12:35:39'),(41,1,'vertreter.loeschen','lieferanten_vertreter',8,NULL,'2026-06-06 12:35:41'),(42,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-06 12:45:50'),(43,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-06 12:45:50'),(44,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-06 12:46:10'),(45,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-06 12:46:10'),(46,1,'artikel.anlegen','artikel',15,'{\"name\":\"Testartikel\"}','2026-06-06 12:47:19'),(47,1,'artikel.anlegen','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:06:47'),(48,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:52:21'),(49,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:56:10'),(50,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:56:10'),(51,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:56:31'),(52,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:56:31'),(53,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:57:51'),(54,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:57:51'),(55,1,'artikel.anlegen','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:03:29'),(56,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:14'),(57,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:14'),(58,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:41'),(59,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:41'),(60,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:57'),(61,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:57'),(62,1,'wareneingang.buchen','lagerbestand',9,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"2\",\"bestand_nachher\":2}','2026-06-06 18:51:37'),(63,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 10:24:27'),(64,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 10:24:27'),(65,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 10:24:38'),(66,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 10:24:38'),(67,1,'artikel.anlegen','artikel',18,'{\"name\":\"einheitstest\"}','2026-06-07 10:27:31'),(68,1,'artikel.bearbeiten','artikel',18,'{\"name\":\"einheitstest\"}','2026-06-07 10:27:41'),(69,1,'artikel.kategorien_aktualisieren','artikel',18,'{\"kategorie_ids\":[]}','2026-06-07 10:27:41'),(70,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-07 10:31:32'),(71,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-07 10:31:32'),(72,1,'artikel.anlegen','artikel',19,'{\"name\":\"testzwe\"}','2026-06-07 10:31:59'),(73,1,'wareneingang.buchen','lagerbestand',10,'{\"artikel_varianten_id\":\"1\",\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":9}','2026-06-07 11:25:05'),(74,1,'wareneingang.buchen','lagerbestand',11,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}','2026-06-07 11:26:37'),(75,1,'wareneingang.buchen','lagerbestand',12,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}','2026-06-07 11:28:42'),(76,1,'wareneingang.buchen','lagerbestand',13,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":15}','2026-06-07 11:30:26'),(77,1,'wareneingang.buchen','lagerbestand',14,'{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"3\",\"bestand_nachher\":3}','2026-06-07 11:36:57'),(78,1,'artikel.variante_bearbeiten','artikel_varianten',4,'{\"farbe\":\"anthrazit [Mix] (06)\"}','2026-06-07 14:13:56'),(79,1,'artikel.bearbeiten','artikel',9,'{\"name\":\"Testartikel\"}','2026-06-07 15:42:41'),(80,1,'artikel.kategorien_aktualisieren','artikel',9,'{\"kategorie_ids\":[]}','2026-06-07 15:42:41'),(81,1,'artikel.variante_bearbeiten','artikel_varianten',2,'{\"farbe\":\"weizen [Mix] (02)\"}','2026-06-07 15:43:04'),(82,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 18:39:11'),(83,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 18:39:11'),(84,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 18:39:22'),(85,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 18:39:22'),(86,1,'wareneingang.buchen','lagerbestand',15,'{\"artikel_varianten_id\":\"2\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-07 18:42:58'),(87,1,'artikel.variante_bearbeiten','artikel_varianten',4,'{\"farbe\":\"anthrazit [Mix] (06)\"}','2026-06-07 18:54:34'),(88,2,'variante_artikel_aktiv.geaendert','artikel_varianten',4,'{\"aktiv\":0,\"id\":4,\"artikel_id\":1,\"artikelnummer\":\"D-109906\"}','2026-06-07 19:01:29'),(89,1,'wareneingang.buchen','lagerbestand',16,'{\"artikel_varianten_id\":\"4\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-07 19:01:29'),(90,1,'artikel.reaktiviert','artikel',6,'{\"lager_id\":\"2\",\"menge\":\"3\"}','2026-06-08 17:55:43'),(91,1,'wareneingang.buchen','lagerbestand',17,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"3\",\"bestand_nachher\":3}','2026-06-08 17:55:43'),(92,1,'artikel.variante_bearbeiten','artikel_varianten',3,'{\"farbe\":\"perlgrau [Mix] (03)\"}','2026-06-08 17:56:25'),(93,1,'variante.reaktiviert','artikel_varianten',3,'{\"lager_id\":\"3\",\"menge\":\"1\"}','2026-06-08 17:57:37'),(94,1,'wareneingang.buchen','lagerbestand',18,'{\"artikel_varianten_id\":\"3\",\"lager_id\":\"3\",\"menge\":\"1\",\"bestand_nachher\":1}','2026-06-08 17:57:37'),(95,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-08 19:07:05'),(96,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-08 19:07:05'),(97,1,'artikel.anlegen','artikel',20,'{\"name\":\"seo-test\"}','2026-06-08 19:14:24'),(98,1,'artikel.bearbeiten','artikel',20,'{\"name\":\"seo-test\"}','2026-06-08 19:14:35'),(99,1,'artikel.kategorien_aktualisieren','artikel',20,'{\"kategorie_ids\":[]}','2026-06-08 19:14:35'),(100,1,'artikel.kind_bearbeiten','artikel',26,'{\"farbe\":\"TestfarbeNeu1\"}','2026-06-09 17:24:00'),(101,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:09:43'),(102,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:09:43'),(103,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:19:41'),(104,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:19:41'),(105,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:37:28'),(106,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:37:28'),(107,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:37:52'),(108,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:37:52');
/*!40000 ALTER TABLE `aktivitaeten` ENABLE KEYS */;
UNLOCK TABLES;
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
  `grundpreis_bezugsmenge` decimal(8,3) DEFAULT NULL,
  `grundpreis_anzeigen` tinyint(1) DEFAULT 0,
  `ist_vater` tinyint(1) NOT NULL DEFAULT 0,
  `vaterartikel_id` int(10) unsigned DEFAULT NULL,
  `hat_eigenen_lagerstand` tinyint(1) NOT NULL DEFAULT 1,
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
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel` WRITE;
/*!40000 ALTER TABLE `artikel` DISABLE KEYS */;
INSERT INTO `artikel` VALUES (1,'D-1099',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-29 20:38:45','2026-06-07 18:35:15',100.000,0,1,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(4,'Test-001',NULL,1,6,'Testartikel neu',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-05-31 12:31:08','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(6,'Test-002',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 12:32:56','2026-06-08 17:55:43',100.000,1,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(8,'Test-003',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 12:36:11','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(9,'Test-005',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 14:29:02','2026-06-07 15:42:41',100.000,1,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(10,'Test-006',NULL,1,6,'testMitPreis',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 16:17:33','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(11,'Test-007',NULL,1,6,'testMitPreis',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 16:18:05','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(12,'J-1010',3,1,4,'Dummyartikel mit Lagerbestand','Test','Testartikel',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AT',NULL,1,'2026-06-04 20:29:10','2026-06-11 08:09:43',100.000,1,0,NULL,1,0,0,1,NULL,NULL,NULL,NULL,NULL),(13,'471122',2,1,1,'Testgarn mit Charge',NULL,NULL,1,2.500,'kg',NULL,NULL,NULL,5.000,5.000,NULL,'AT',NULL,1,'2026-06-05 13:20:24','2026-06-07 09:41:53',100.000,1,0,NULL,1,1,0,0,NULL,NULL,NULL,NULL,NULL),(14,'chargenartikel',1,1,6,'chargenartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AT',NULL,1,'2026-06-05 17:16:16','2026-06-07 09:41:53',100.000,1,0,NULL,1,1,0,0,NULL,NULL,NULL,NULL,NULL),(15,'Test-008',2,1,3,'Testartikel',NULL,NULL,2,5.000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-06 12:47:19','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(16,'esrgrd',NULL,1,6,'grybsgrvbg','EAN-Test',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-06 17:06:47','2026-06-07 10:31:32',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(17,'syfvsd',NULL,1,6,'sdfvsdg sg ',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-06 18:03:29','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(18,'einheitstest',NULL,1,1,'einheitstest',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-07 10:27:31','2026-06-07 10:27:31',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(19,'testzwe',3,1,4,'testzwe',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-07 10:31:59','2026-06-07 10:31:59',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(20,'seo-test',NULL,1,6,'seo-test',NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-06-08 19:14:24','2026-06-08 19:14:24',100.000,1,0,NULL,1,0,0,0,NULL,NULL,'ysfvsv','ysdfvysvf','sdrv'),(21,'D-109901',1,1,1,'DROPS Air – natur [Uni] (01)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-30 15:07:07','2026-06-09 17:05:09',100.000,0,0,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(22,'D-109902',1,1,1,'DROPS Air – weizen [Mix] (02)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-30 15:07:07','2026-06-09 17:05:09',100.000,0,0,1,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(23,'D-109903',1,1,1,'DROPS Air – perlgrau [Mix] (03)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-30 15:07:07','2026-06-09 17:05:09',100.000,0,0,1,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(24,'D-109906',1,1,1,'DROPS Air – anthrazit [Mix] (06)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-30 15:07:07','2026-06-09 17:05:09',100.000,0,0,1,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(25,'D-109916',1,1,1,'DROPS Air – blau [Uni] (16)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-30 15:07:07','2026-06-09 17:05:09',100.000,0,0,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(26,'D-1099xx',1,1,1,'DROPS Air – TestfarbeNeu1',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-05-31 17:13:13','2026-06-09 17:24:00',100.000,0,0,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(27,'4711',1,1,1,'DROPS Air – TestfarbeNeu2',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 17:13:55','2026-06-09 17:05:09',100.000,0,0,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(28,'445',1,1,1,'DROPS Air – TestfarbeNeu3',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-05-31 17:15:53','2026-06-09 17:05:09',100.000,0,0,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(36,'D-1099-KOPIE',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:04:43','2026-06-10 13:04:43',100.000,0,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(37,'D-1099-KOPIE-KOPIE',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:06:45','2026-06-10 13:06:45',100.000,0,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(38,'Test-002-KOPIE',NULL,1,6,'Testartikel-KOPIE',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:19:33','2026-06-10 13:19:33',100.000,1,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(39,'D-1099-KOPIE-KOPIE-kopie',1,1,1,'DROPS Air-KOPIE',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:27:00','2026-06-10 13:27:00',100.000,0,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(40,'KOPIE-D-1099',1,1,1,'KOPIE-DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:27:55','2026-06-10 13:27:55',100.000,0,0,NULL,1,0,1,0,NULL,NULL,NULL,NULL,NULL),(41,'einheitstest-KOPIE',NULL,1,1,'einheitstest-KOPIE',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'2026-06-10 13:45:07','2026-06-10 13:45:07',100.000,1,0,NULL,1,0,0,0,NULL,NULL,NULL,NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_achsen` WRITE;
/*!40000 ALTER TABLE `artikel_achsen` DISABLE KEYS */;
INSERT INTO `artikel_achsen` VALUES (1,1,1,NULL,NULL,0);
/*!40000 ALTER TABLE `artikel_achsen` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_codes` WRITE;
/*!40000 ALTER TABLE `artikel_codes` DISABLE KEYS */;
INSERT INTO `artikel_codes` VALUES (5,17,'4434567890221','GTIN13',NULL),(6,21,'7071723011379','GTIN13',NULL),(7,22,'7071723011386','GTIN13',NULL),(8,23,'7071723011393','GTIN13',NULL),(9,24,'7071723011423','GTIN13',NULL),(10,25,'7071723013922','GTIN13',NULL),(12,27,'1231231231231','GTIN13',NULL),(13,28,'1231231231231','GTIN13',NULL),(21,26,'1231231231231','GTIN13',NULL);
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
INSERT INTO `artikel_kategorien` VALUES (1,1),(8,3),(8,4),(36,1),(39,1),(40,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_lieferanten` WRITE;
/*!40000 ALTER TABLE `artikel_lieferanten` DISABLE KEYS */;
INSERT INTO `artikel_lieferanten` VALUES (1,1,1,NULL,2.57,'EUR',20,'7071723011379',20,NULL,1,1,'2026-05-30 19:10:44','2026-05-30 19:10:44'),(2,36,1,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:04:43','2026-06-10 13:04:43'),(3,37,1,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:06:45','2026-06-10 13:06:45'),(4,39,1,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:27:00','2026-06-10 13:27:00'),(5,40,1,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:27:55','2026-06-10 13:27:55');
/*!40000 ALTER TABLE `artikel_lieferanten` ENABLE KEYS */;
UNLOCK TABLES;
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_merkmale` WRITE;
/*!40000 ALTER TABLE `artikel_merkmale` DISABLE KEYS */;
INSERT INTO `artikel_merkmale` VALUES (1,1,3,'C',NULL,NULL,'2026-05-30 18:12:39'),(2,1,4,NULL,5.00,NULL,'2026-05-30 18:12:39'),(3,1,5,NULL,6.00,NULL,'2026-05-30 18:12:39'),(4,1,6,'10x10cm = 17 Maschen x 22 Reihen',NULL,NULL,'2026-05-30 18:12:39'),(5,36,3,'C',NULL,NULL,'2026-06-10 13:04:43'),(6,36,4,NULL,5.00,NULL,'2026-06-10 13:04:43'),(7,36,5,NULL,6.00,NULL,'2026-06-10 13:04:43'),(8,36,6,'10x10cm = 17 Maschen x 22 Reihen',NULL,NULL,'2026-06-10 13:04:43'),(12,37,3,'C',NULL,NULL,'2026-06-10 13:06:45'),(13,37,4,NULL,5.00,NULL,'2026-06-10 13:06:45'),(14,37,5,NULL,6.00,NULL,'2026-06-10 13:06:45'),(15,37,6,'10x10cm = 17 Maschen x 22 Reihen',NULL,NULL,'2026-06-10 13:06:45'),(19,39,3,'C',NULL,NULL,'2026-06-10 13:27:00'),(20,39,4,NULL,5.00,NULL,'2026-06-10 13:27:00'),(21,39,5,NULL,6.00,NULL,'2026-06-10 13:27:00'),(22,39,6,'10x10cm = 17 Maschen x 22 Reihen',NULL,NULL,'2026-06-10 13:27:00'),(26,40,3,'C',NULL,NULL,'2026-06-10 13:27:55'),(27,40,4,NULL,5.00,NULL,'2026-06-10 13:27:55'),(28,40,5,NULL,6.00,NULL,'2026-06-10 13:27:55'),(29,40,6,'10x10cm = 17 Maschen x 22 Reihen',NULL,NULL,'2026-06-10 13:27:55');
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
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `artikel_preise` WRITE;
/*!40000 ALTER TABLE `artikel_preise` DISABLE KEYS */;
INSERT INTO `artikel_preise` VALUES (1,1,1,5.30,4.42,NULL,NULL,'2026-05-30 16:10:13'),(2,1,2,4.51,3.76,NULL,NULL,'2026-05-30 16:10:13'),(3,1,3,4.77,3.98,NULL,NULL,'2026-05-30 16:10:13'),(4,1,4,5.30,4.42,NULL,NULL,'2026-05-30 16:10:13'),(5,11,1,15.90,13.25,NULL,NULL,'2026-05-31 16:18:05'),(6,10,1,13.50,11.25,NULL,NULL,'2026-05-31 16:21:56'),(7,12,1,19.90,16.58,NULL,NULL,'2026-06-04 20:29:10'),(8,13,1,399.95,333.29,NULL,NULL,'2026-06-05 13:20:24'),(9,14,1,45.90,38.25,NULL,NULL,'2026-06-05 17:16:16'),(10,15,1,3.50,2.92,NULL,NULL,'2026-06-06 12:47:19'),(11,16,1,12.00,10.00,NULL,NULL,'2026-06-06 17:06:47'),(12,17,1,123.00,102.50,NULL,NULL,'2026-06-06 18:03:29'),(13,18,1,125.00,104.17,NULL,NULL,'2026-06-07 10:27:31'),(14,19,1,256.00,213.33,NULL,NULL,'2026-06-07 10:31:59'),(15,21,1,5.30,4.42,NULL,NULL,'2026-06-09 17:05:10'),(16,22,1,5.30,4.42,NULL,NULL,'2026-06-09 17:05:10'),(17,23,1,5.30,4.42,NULL,NULL,'2026-06-09 17:05:10'),(18,24,1,5.30,4.42,NULL,NULL,'2026-06-09 17:05:10'),(19,25,1,5.30,4.42,NULL,NULL,'2026-06-09 17:05:10'),(20,26,1,8.00,6.67,NULL,NULL,'2026-06-09 17:05:10'),(21,27,1,8.00,6.67,NULL,NULL,'2026-06-09 17:05:10'),(22,28,1,6.99,5.83,NULL,NULL,'2026-06-09 17:05:10'),(23,36,1,5.30,4.42,NULL,NULL,'2026-06-10 13:04:43'),(24,36,2,4.51,3.76,NULL,NULL,'2026-06-10 13:04:43'),(25,36,3,4.77,3.98,NULL,NULL,'2026-06-10 13:04:43'),(26,36,4,5.30,4.42,NULL,NULL,'2026-06-10 13:04:43'),(30,37,1,5.30,4.42,NULL,NULL,'2026-06-10 13:06:45'),(31,37,2,4.51,3.76,NULL,NULL,'2026-06-10 13:06:45'),(32,37,3,4.77,3.98,NULL,NULL,'2026-06-10 13:06:45'),(33,37,4,5.30,4.42,NULL,NULL,'2026-06-10 13:06:45'),(37,39,1,5.30,4.42,NULL,NULL,'2026-06-10 13:27:00'),(38,39,2,4.51,3.76,NULL,NULL,'2026-06-10 13:27:00'),(39,39,3,4.77,3.98,NULL,NULL,'2026-06-10 13:27:00'),(40,39,4,5.30,4.42,NULL,NULL,'2026-06-10 13:27:00'),(44,40,1,5.30,4.42,NULL,NULL,'2026-06-10 13:27:55'),(45,40,2,4.51,3.76,NULL,NULL,'2026-06-10 13:27:55'),(46,40,3,4.77,3.98,NULL,NULL,'2026-06-10 13:27:55'),(47,40,4,5.30,4.42,NULL,NULL,'2026-06-10 13:27:55'),(51,41,1,125.00,104.17,NULL,NULL,'2026-06-10 13:45:07');
/*!40000 ALTER TABLE `artikel_preise` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `artikel_typen` WRITE;
/*!40000 ALTER TABLE `artikel_typen` DISABLE KEYS */;
INSERT INTO `artikel_typen` VALUES (1,'GARN','Garn',1,1,0,0,1,1),(2,'NADEL','Nadel',1,1,0,0,2,1),(3,'METERWARE','Meterware',1,1,0,0,3,1),(4,'DOWNLOAD','Download',0,0,1,0,4,1),(5,'SET','Set',0,1,0,1,5,1),(6,'STANDARD','Standard',1,1,0,0,6,1);
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
  `webseite` varchar(255) DEFAULT NULL,
  `land` varchar(50) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `aktiv` tinyint(1) NOT NULL DEFAULT 1,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `hersteller` WRITE;
/*!40000 ALTER TABLE `hersteller` DISABLE KEYS */;
INSERT INTO `hersteller` VALUES (1,'DROPS Design','www.garnstudio.com','NO',NULL,1,'2026-05-29 20:03:50'),(2,'Schachenmayr','www.schachenmayr.com','DE',NULL,1,'2026-05-29 20:03:50'),(3,'Lang Yarns','www.langyarns.com','CH',NULL,1,'2026-05-29 20:03:50');
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
  `externe_id` varchar(100) DEFAULT NULL,
  `datenquelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kat_parent_id` (`parent_id`),
  CONSTRAINT `fk_kat_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kategorien` WRITE;
/*!40000 ALTER TABLE `kategorien` DISABLE KEYS */;
INSERT INTO `kategorien` VALUES (1,NULL,'Wolle und Garne',1,1,NULL,NULL),(2,NULL,'Nadeln',2,1,NULL,NULL),(3,NULL,'Zubehör',3,1,NULL,NULL),(4,NULL,'Bücher und Anleitungen',4,1,NULL,NULL),(5,NULL,'Testkategorie',0,1,NULL,NULL);
/*!40000 ALTER TABLE `kategorien` ENABLE KEYS */;
UNLOCK TABLES;
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

LOCK TABLES `kundengruppen` WRITE;
/*!40000 ALTER TABLE `kundengruppen` DISABLE KEYS */;
INSERT INTO `kundengruppen` VALUES (1,'Endkunden',0.00,1,'2026-05-30 16:10:03','endkunde'),(2,'Händler',15.00,1,'2026-05-30 16:10:03','endkunde'),(3,'Kleingewerblich-Künstler',10.00,1,'2026-05-30 16:10:03','endkunde'),(4,'Endkunden-Rechnung',0.00,1,'2026-05-30 16:10:03','endkunde');
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lager_bewegungen` WRITE;
/*!40000 ALTER TABLE `lager_bewegungen` DISABLE KEYS */;
INSERT INTO `lager_bewegungen` VALUES (1,1,NULL,NULL,'0444','eingang',5.000,7.000,12.000,NULL,NULL,'2026-05-31 19:22:23',21,NULL),(2,1,NULL,NULL,'4711','eingang',5.000,0.000,5.000,NULL,'testnotiz','2026-06-04 20:27:32',27,NULL),(3,1,NULL,NULL,'9993','eingang',2.000,5.000,7.000,NULL,NULL,'2026-06-05 08:45:33',27,NULL),(4,2,NULL,NULL,NULL,'eingang',5.000,0.000,5.000,NULL,NULL,'2026-06-05 08:46:00',9,NULL),(5,1,NULL,NULL,NULL,'eingang',4.000,0.000,4.000,NULL,NULL,'2026-06-05 17:14:29',27,NULL),(6,3,NULL,NULL,NULL,'eingang',15.000,0.000,15.000,NULL,NULL,'2026-06-05 17:16:53',14,NULL),(7,3,NULL,NULL,'7768','korrektur',4.000,15.000,11.000,NULL,'Charge nachgetragen','2026-06-05 17:17:41',14,NULL),(8,3,NULL,NULL,'00079','korrektur',9.000,11.000,2.000,NULL,'Charge nachgetragen','2026-06-05 17:17:52',14,NULL),(9,2,NULL,NULL,NULL,'eingang',2.000,0.000,2.000,NULL,NULL,'2026-06-06 18:51:37',10,1),(10,2,1,3.2000,NULL,'eingang',5.000,4.000,9.000,NULL,NULL,'2026-06-07 11:25:05',21,1),(11,2,1,5.5000,NULL,'eingang',10.000,0.000,10.000,NULL,NULL,'2026-06-07 11:26:37',15,1),(12,2,1,6.9000,NULL,'eingang',10.000,0.000,10.000,'test','testbuchung','2026-06-07 11:28:42',13,1),(13,2,1,5.9000,NULL,'eingang',5.000,10.000,15.000,'test','test','2026-06-07 11:30:26',13,1),(14,3,2,55.9000,NULL,'eingang',3.000,0.000,3.000,'test','eingangstest','2026-06-07 11:36:57',17,1),(15,3,1,5.9000,NULL,'eingang',5.000,0.000,5.000,NULL,NULL,'2026-06-07 18:42:58',22,1),(16,3,1,5.9000,NULL,'eingang',5.000,0.000,5.000,'test','Artikel durch Jarvis aktivieren lassen','2026-06-07 19:01:29',24,1),(17,2,2,6.6000,NULL,'eingang',3.000,0.000,3.000,NULL,NULL,'2026-06-08 17:55:43',6,1),(18,3,NULL,4.5000,NULL,'eingang',1.000,0.000,1.000,NULL,NULL,'2026-06-08 17:57:37',23,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lagerbestand` WRITE;
/*!40000 ALTER TABLE `lagerbestand` DISABLE KEYS */;
INSERT INTO `lagerbestand` VALUES (1,1,'0444','erfasst',12.000,0,'2026-05-30 16:54:27','2026-06-09 17:05:10',21),(2,1,NULL,'unbekannt',0.000,0,'2026-05-30 16:54:27','2026-06-09 17:05:10',22),(3,2,NULL,NULL,9.000,0,'2026-05-30 16:54:27','2026-06-09 17:05:10',21),(5,1,'9993','erfasst',7.000,0,'2026-06-04 20:27:32','2026-06-09 17:05:10',27),(7,2,NULL,'unbekannt',5.000,0,'2026-06-05 08:46:00','2026-06-05 08:46:00',9),(8,1,NULL,NULL,4.000,0,'2026-06-05 17:14:29','2026-06-09 17:05:10',27),(9,3,NULL,'nachzutragen',2.000,0,'2026-06-05 17:16:53','2026-06-05 17:17:52',14),(10,3,'7768','erfasst',4.000,0,'2026-06-05 17:17:41','2026-06-05 17:17:41',14),(11,3,'00079','erfasst',9.000,0,'2026-06-05 17:17:52','2026-06-05 17:17:52',14),(12,2,NULL,NULL,2.000,0,'2026-06-06 18:51:37','2026-06-06 18:51:37',10),(13,2,NULL,NULL,10.000,0,'2026-06-07 11:26:37','2026-06-07 11:26:37',15),(14,2,NULL,'nachzutragen',15.000,0,'2026-06-07 11:28:42','2026-06-07 11:30:26',13),(15,3,'0444','erfasst',3.000,0,'2026-06-07 11:36:57','2026-06-07 11:36:57',17),(16,3,'991177','erfasst',5.000,0,'2026-06-07 18:42:58','2026-06-09 17:05:10',22),(17,3,'99887711','erfasst',5.000,0,'2026-06-07 19:01:29','2026-06-09 17:05:10',24),(18,2,'grdyvdyf','erfasst',3.000,0,'2026-06-08 17:55:43','2026-06-08 17:55:43',6),(19,3,NULL,NULL,1.000,0,'2026-06-08 17:57:37','2026-06-09 17:05:10',23);
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
INSERT INTO `lieferanten` VALUES (1,'DROPS Design A/S','NO',NULL,'info@garnstudio.com',NULL,1,'2026-05-30 19:10:44','2026-06-04 22:25:59'),(2,'Schachenmayr','DE',NULL,'info@schachenmayr.com',NULL,1,'2026-05-30 19:10:44',NULL);
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
INSERT INTO `lieferanten_vertreter` VALUES (1,1,'Lars','Hansen','+47123456789',NULL,NULL,'Kommt jeden ersten Dienstag',1,'2026-05-30 19:10:44','2026-05-30 19:10:44'),(2,1,'Anna','Berg','+47987654321',NULL,NULL,'Zuständig für Österreich',1,'2026-05-30 19:10:44','2026-05-30 19:10:44'),(3,2,'Karl','Indra','06764538267','indy1@gmx.at','669054445212211','                                das soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werden3333',0,'2026-06-06 11:30:31','2026-06-06 12:32:25'),(4,2,'Karl','Indra','06764538267','karl.indra@mealana.at',NULL,'                zweiter test zum löschen\r\n',0,'2026-06-06 11:31:09','2026-06-06 11:31:13'),(5,2,'Karl','Indra','06764538267','karl.indra@mealana.at','669054445212211','                zweiter Text zum löschen',0,'2026-06-06 11:33:21','2026-06-06 11:33:26'),(6,2,'Karl','Indra','06764538267','karl.indra@mealana.at','669054445212211','                                zweiter test 2',0,'2026-06-06 11:34:24','2026-06-06 11:34:38'),(7,2,'Karl','Indra','06764538267','karl.indra@mealana.at','669054445212211','                                                22334455',0,'2026-06-06 11:36:08','2026-06-06 12:22:12'),(8,2,'Karl','Indra','06764538267','indy1@gmx.at','669054445212211','testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttest',0,'2026-06-06 12:35:35','2026-06-06 12:35:41');
/*!40000 ALTER TABLE `lieferanten_vertreter` ENABLE KEYS */;
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

LOCK TABLES `merkmale` WRITE;
/*!40000 ALTER TABLE `merkmale` DISABLE KEYS */;
INSERT INTO `merkmale` VALUES (1,1,'Gewicht/Länge','g/m','text',0,1,'2026-05-30 18:12:39'),(2,1,'Zusammensetzung','','text',0,1,'2026-05-30 18:12:39'),(3,1,'Garngruppe','','text',1,1,'2026-05-30 18:12:39'),(4,2,'Nadelstärke von','mm','zahl',1,1,'2026-05-30 18:12:39'),(5,2,'Nadelstärke bis','mm','zahl',1,1,'2026-05-30 18:12:39'),(6,2,'Maschenprobe','','text',0,1,'2026-05-30 18:12:39');
/*!40000 ALTER TABLE `merkmale` ENABLE KEYS */;
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
DROP TABLE IF EXISTS `varianten_achse_werte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `varianten_achse_werte` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `artikel_id` int(10) unsigned NOT NULL,
  `achse_id` int(10) unsigned NOT NULL,
  `wert` varchar(100) NOT NULL,
  `wert_zusatz` varchar(100) DEFAULT NULL,
  `aufpreis` decimal(10,2) DEFAULT 0.00,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_varAchsWert_artikel` (`artikel_id`),
  KEY `fk_varAchsWert_achse` (`achse_id`),
  CONSTRAINT `fk_varAchsWert_achse` FOREIGN KEY (`achse_id`) REFERENCES `varianten_achsen` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_varAchsWert_artikel` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `varianten_achse_werte` WRITE;
/*!40000 ALTER TABLE `varianten_achse_werte` DISABLE KEYS */;
INSERT INTO `varianten_achse_werte` VALUES (1,1,1,'natur [Uni] (01)','#F5F0E8',0.00,0),(2,1,1,'weizen [Mix] (02)','#e8d5a3',0.00,0),(3,1,1,'perlgrau [Mix] (03)','#c8c4bc',0.00,0),(4,1,1,'anthrazit [Mix] (06)','#4a4a4a',0.00,0),(5,1,1,'blau [Uni] (16)','#4A7CB5',0.00,0),(6,1,1,'TestfarbeNeu1','#16f883',0.00,0),(7,1,1,'TestfarbeNeu2','#024cf7',0.00,0),(8,1,1,'TestfarbeNeu3','#44f604',0.00,0);
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
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `varianten_achsen` WRITE;
/*!40000 ALTER TABLE `varianten_achsen` DISABLE KEYS */;
INSERT INTO `varianten_achsen` VALUES (1,'Farbe','farbe','swatches',0,'2026-06-11 11:07:43');
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
INSERT INTO `varianten_kombination_werte` VALUES (21,1),(22,2),(23,3),(24,4),(25,5),(26,6),(27,7),(28,8);
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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

