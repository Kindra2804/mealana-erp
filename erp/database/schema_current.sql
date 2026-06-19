
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktionen` WRITE;
/*!40000 ALTER TABLE `aktionen` DISABLE KEYS */;
INSERT INTO `aktionen` VALUES (2,'DROPS-TEST','Testaktion',1,'2026-06-18 19:17:09');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktionen_artikel_preise` WRITE;
/*!40000 ALTER TABLE `aktionen_artikel_preise` DISABLE KEYS */;
INSERT INTO `aktionen_artikel_preise` VALUES (1,2,17,NULL,1,114.00,95.00,'2026-06-18 19:26:55'),(2,2,1,NULL,1,5.10,4.25,'2026-06-18 19:26:55'),(3,2,6,NULL,1,12.00,10.00,'2026-06-18 19:26:55'),(4,2,102,NULL,1,7.70,6.42,'2026-06-18 19:26:55'),(5,2,17,NULL,1,114.00,95.00,'2026-06-19 13:06:35'),(6,2,1,NULL,1,5.10,4.25,'2026-06-19 13:06:35'),(7,2,74,NULL,1,2.00,1.67,'2026-06-19 13:06:35'),(8,2,6,NULL,1,12.00,10.00,'2026-06-19 13:06:35'),(9,2,102,NULL,1,7.70,6.42,'2026-06-19 13:06:35'),(10,2,56,NULL,1,5.50,4.58,'2026-06-19 13:39:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktionen_kategorien` WRITE;
/*!40000 ALTER TABLE `aktionen_kategorien` DISABLE KEYS */;
INSERT INTO `aktionen_kategorien` VALUES (1,2,1,'2026-06-18','2026-06-19','2026-06-18 19:17:30');
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
) ENGINE=InnoDB AUTO_INCREMENT=259 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `aktivitaeten` WRITE;
/*!40000 ALTER TABLE `aktivitaeten` DISABLE KEYS */;
INSERT INTO `aktivitaeten` VALUES (1,1,'artikel.bearbeiten','artikel',8,'{\"name\":\"Testartikel\"}','2026-06-04 17:41:57'),(2,1,'artikel.kategorien_aktualisieren','artikel',8,'{\"kategorie_ids\":[4,3]}','2026-06-04 17:41:57'),(3,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-04 17:42:20'),(4,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-04 17:42:20'),(5,1,'lieferant.bearbeiten','lieferanten',1,'{\"name\":\"DROPS Design A\\/S\"}','2026-06-04 20:25:59'),(6,1,'wareneingang.buchen','lagerbestand',2,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-04 20:27:32'),(7,1,'artikel.anlegen','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-04 20:29:10'),(8,1,'wareneingang.buchen','lagerbestand',3,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"2\",\"bestand_nachher\":7}','2026-06-05 08:45:33'),(9,1,'wareneingang.buchen','lagerbestand',4,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-05 08:46:00'),(10,1,'artikel.anlegen','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:20:24'),(11,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:20:39'),(12,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 13:20:39'),(13,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 13:23:47'),(14,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 13:23:47'),(15,1,'wareneingang.buchen','lagerbestand',5,'{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"4\",\"bestand_nachher\":4}','2026-06-05 17:14:29'),(16,1,'artikel.bearbeiten','artikel',13,'{\"name\":\"Testgarn mit Charge\"}','2026-06-05 17:15:09'),(17,1,'artikel.kategorien_aktualisieren','artikel',13,'{\"kategorie_ids\":[]}','2026-06-05 17:15:09'),(18,1,'artikel.anlegen','artikel',14,'{\"name\":\"chargenartikel\"}','2026-06-05 17:16:16'),(19,1,'wareneingang.buchen','lagerbestand',6,'{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"15\",\"bestand_nachher\":15}','2026-06-05 17:16:53'),(20,1,'lager.charge_nachtragen','lagerbestand',9,'{\"charge\":\"7768\"}','2026-06-05 17:17:41'),(21,1,'lager.charge_nachtragen','lagerbestand',9,'{\"charge\":\"00079\"}','2026-06-05 17:17:52'),(22,1,'artikel.bearbeiten','artikel',14,'{\"name\":\"chargenartikel\"}','2026-06-05 17:21:59'),(23,1,'artikel.kategorien_aktualisieren','artikel',14,'{\"kategorie_ids\":[]}','2026-06-05 17:21:59'),(24,1,'vertreter.anlegen','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 11:30:31'),(25,1,'vertreter.bearbeiten','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 11:30:42'),(26,1,'vertreter.anlegen','lieferanten_vertreter',4,'{\"nachname\":\"Indra\"}','2026-06-06 11:31:09'),(27,1,'vertreter.loeschen','lieferanten_vertreter',4,NULL,'2026-06-06 11:31:13'),(28,1,'vertreter.anlegen','lieferanten_vertreter',5,'{\"nachname\":\"Indra\"}','2026-06-06 11:33:21'),(29,1,'vertreter.loeschen','lieferanten_vertreter',5,NULL,'2026-06-06 11:33:26'),(30,1,'vertreter.anlegen','lieferanten_vertreter',6,'{\"nachname\":\"Indra\"}','2026-06-06 11:34:24'),(31,1,'vertreter.bearbeiten','lieferanten_vertreter',6,'{\"nachname\":\"Indra\"}','2026-06-06 11:34:33'),(32,1,'vertreter.loeschen','lieferanten_vertreter',6,NULL,'2026-06-06 11:34:38'),(33,1,'vertreter.anlegen','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:36:08'),(34,1,'vertreter.bearbeiten','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:36:15'),(35,1,'vertreter.bearbeiten','lieferanten_vertreter',7,'{\"nachname\":\"Indra\"}','2026-06-06 11:45:49'),(36,1,'vertreter.loeschen','lieferanten_vertreter',7,NULL,'2026-06-06 12:22:12'),(37,1,'vertreter.bearbeiten','lieferanten_vertreter',3,'{\"nachname\":\"Indra\"}','2026-06-06 12:32:19'),(38,1,'vertreter.loeschen','lieferanten_vertreter',3,NULL,'2026-06-06 12:32:25'),(39,1,'vertreter.anlegen','lieferanten_vertreter',8,'{\"nachname\":\"Indra\"}','2026-06-06 12:35:35'),(40,1,'vertreter.bearbeiten','lieferanten_vertreter',8,'{\"nachname\":\"Indra\"}','2026-06-06 12:35:39'),(41,1,'vertreter.loeschen','lieferanten_vertreter',8,NULL,'2026-06-06 12:35:41'),(42,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-06 12:45:50'),(43,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-06 12:45:50'),(44,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-06 12:46:10'),(45,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-06 12:46:10'),(46,1,'artikel.anlegen','artikel',15,'{\"name\":\"Testartikel\"}','2026-06-06 12:47:19'),(47,1,'artikel.anlegen','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:06:47'),(48,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:52:21'),(49,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:56:10'),(50,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:56:10'),(51,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:56:31'),(52,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:56:31'),(53,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-06 17:57:51'),(54,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-06 17:57:51'),(55,1,'artikel.anlegen','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:03:29'),(56,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:14'),(57,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:14'),(58,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:41'),(59,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:41'),(60,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"sdfvsdg sg \"}','2026-06-06 18:04:57'),(61,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[]}','2026-06-06 18:04:57'),(62,1,'wareneingang.buchen','lagerbestand',9,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"2\",\"bestand_nachher\":2}','2026-06-06 18:51:37'),(63,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 10:24:27'),(64,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 10:24:27'),(65,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 10:24:38'),(66,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 10:24:38'),(67,1,'artikel.anlegen','artikel',18,'{\"name\":\"einheitstest\"}','2026-06-07 10:27:31'),(68,1,'artikel.bearbeiten','artikel',18,'{\"name\":\"einheitstest\"}','2026-06-07 10:27:41'),(69,1,'artikel.kategorien_aktualisieren','artikel',18,'{\"kategorie_ids\":[]}','2026-06-07 10:27:41'),(70,1,'artikel.bearbeiten','artikel',16,'{\"name\":\"grybsgrvbg\"}','2026-06-07 10:31:32'),(71,1,'artikel.kategorien_aktualisieren','artikel',16,'{\"kategorie_ids\":[]}','2026-06-07 10:31:32'),(72,1,'artikel.anlegen','artikel',19,'{\"name\":\"testzwe\"}','2026-06-07 10:31:59'),(73,1,'wareneingang.buchen','lagerbestand',10,'{\"artikel_varianten_id\":\"1\",\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":9}','2026-06-07 11:25:05'),(74,1,'wareneingang.buchen','lagerbestand',11,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}','2026-06-07 11:26:37'),(75,1,'wareneingang.buchen','lagerbestand',12,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}','2026-06-07 11:28:42'),(76,1,'wareneingang.buchen','lagerbestand',13,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":15}','2026-06-07 11:30:26'),(77,1,'wareneingang.buchen','lagerbestand',14,'{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"3\",\"bestand_nachher\":3}','2026-06-07 11:36:57'),(78,1,'artikel.variante_bearbeiten','artikel_varianten',4,'{\"farbe\":\"anthrazit [Mix] (06)\"}','2026-06-07 14:13:56'),(79,1,'artikel.bearbeiten','artikel',9,'{\"name\":\"Testartikel\"}','2026-06-07 15:42:41'),(80,1,'artikel.kategorien_aktualisieren','artikel',9,'{\"kategorie_ids\":[]}','2026-06-07 15:42:41'),(81,1,'artikel.variante_bearbeiten','artikel_varianten',2,'{\"farbe\":\"weizen [Mix] (02)\"}','2026-06-07 15:43:04'),(82,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 18:39:11'),(83,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 18:39:11'),(84,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-07 18:39:22'),(85,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-07 18:39:22'),(86,1,'wareneingang.buchen','lagerbestand',15,'{\"artikel_varianten_id\":\"2\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-07 18:42:58'),(87,1,'artikel.variante_bearbeiten','artikel_varianten',4,'{\"farbe\":\"anthrazit [Mix] (06)\"}','2026-06-07 18:54:34'),(88,2,'variante_artikel_aktiv.geaendert','artikel_varianten',4,'{\"aktiv\":0,\"id\":4,\"artikel_id\":1,\"artikelnummer\":\"D-109906\"}','2026-06-07 19:01:29'),(89,1,'wareneingang.buchen','lagerbestand',16,'{\"artikel_varianten_id\":\"4\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}','2026-06-07 19:01:29'),(90,1,'artikel.reaktiviert','artikel',6,'{\"lager_id\":\"2\",\"menge\":\"3\"}','2026-06-08 17:55:43'),(91,1,'wareneingang.buchen','lagerbestand',17,'{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"3\",\"bestand_nachher\":3}','2026-06-08 17:55:43'),(92,1,'artikel.variante_bearbeiten','artikel_varianten',3,'{\"farbe\":\"perlgrau [Mix] (03)\"}','2026-06-08 17:56:25'),(93,1,'variante.reaktiviert','artikel_varianten',3,'{\"lager_id\":\"3\",\"menge\":\"1\"}','2026-06-08 17:57:37'),(94,1,'wareneingang.buchen','lagerbestand',18,'{\"artikel_varianten_id\":\"3\",\"lager_id\":\"3\",\"menge\":\"1\",\"bestand_nachher\":1}','2026-06-08 17:57:37'),(95,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-08 19:07:05'),(96,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-08 19:07:05'),(97,1,'artikel.anlegen','artikel',20,'{\"name\":\"seo-test\"}','2026-06-08 19:14:24'),(98,1,'artikel.bearbeiten','artikel',20,'{\"name\":\"seo-test\"}','2026-06-08 19:14:35'),(99,1,'artikel.kategorien_aktualisieren','artikel',20,'{\"kategorie_ids\":[]}','2026-06-08 19:14:35'),(100,1,'artikel.kind_bearbeiten','artikel',26,'{\"farbe\":\"TestfarbeNeu1\"}','2026-06-09 17:24:00'),(101,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:09:43'),(102,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:09:43'),(103,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:19:41'),(104,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:19:41'),(105,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:37:28'),(106,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:37:28'),(107,1,'artikel.bearbeiten','artikel',12,'{\"name\":\"Dummyartikel mit Lagerbestand\"}','2026-06-11 08:37:52'),(108,1,'artikel.kategorien_aktualisieren','artikel',12,'{\"kategorie_ids\":[]}','2026-06-11 08:37:52'),(109,1,'achse.anlegen','varianten_achsen',2,'{\"name\":\"St\\u00e4rke\",\"code\":\"staerke\",\"darstellungsform\":\"dropdown\"}','2026-06-11 15:13:47'),(110,1,'achse.updaten','varianten_achsen',2,'{\"name\":\"St\\u00e4rke\",\"code\":\"staerke\",\"darstellungsform\":\"dropdown\"}','2026-06-11 15:14:00'),(111,1,'achsenUndWerte.speichern','artikel_achsen',15,'{\"achsen_anzahl\":2,\"werte_anzahl\":5}','2026-06-11 20:34:55'),(112,1,'artikel.bearbeiten','artikel',15,'{\"name\":\"Testartikel\"}','2026-06-11 20:35:10'),(113,1,'artikel.kategorien_aktualisieren','artikel',15,'{\"kategorie_ids\":[]}','2026-06-11 20:35:10'),(114,1,'artikel.bearbeiten','artikel',17,'{\"name\":\"Artikelvater f\\u00fcr VarKombis\"}','2026-06-12 08:20:49'),(115,1,'artikel.kategorien_aktualisieren','artikel',17,'{\"kategorie_ids\":[1]}','2026-06-12 08:20:49'),(116,1,'varkombi.erstellen','artikel',1,'{\"varKombi_anzahl\":4}','2026-06-12 12:28:41'),(117,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-13 12:32:13'),(118,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-13 12:33:14'),(119,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-13 13:16:51'),(120,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-13 13:16:51'),(121,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-13 13:16:56'),(122,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-13 13:16:56'),(123,1,'artikel.bearbeiten','artikel',45,'{\"name\":\"DROPS Air natur [Uni] (01)\"}','2026-06-13 14:20:22'),(124,1,'artikel.kategorien_aktualisieren','artikel',45,'{\"kategorie_ids\":[]}','2026-06-13 14:20:22'),(125,1,'wareneingang.buchen','lagerbestand',19,'{\"artikel_id\":1,\"lager_id\":1,\"menge\":5,\"bestand_nachher\":5}','2026-06-14 10:40:01'),(126,1,'wareneingang.buchen','lagerbestand',20,'{\"artikel_id\":1,\"lager_id\":3,\"menge\":1,\"bestand_nachher\":1}','2026-06-14 10:45:40'),(127,1,'wareneingang.buchen','lagerbestand',21,'{\"artikel_id\":1,\"lager_id\":1,\"menge\":2,\"bestand_nachher\":2}','2026-06-14 10:48:18'),(128,1,'varkombi.erstellen','artikel',1,'{\"varKombi_anzahl\":1}','2026-06-14 10:55:36'),(129,1,'varkombi.erstellen','artikel',1,'{\"varKombi_anzahl\":1}','2026-06-14 11:02:45'),(130,1,'wareneingang.buchen','lagerbestand',22,'{\"artikel_id\":6,\"lager_id\":1,\"menge\":0.001,\"bestand_nachher\":0.001}','2026-06-14 11:56:10'),(131,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-14 13:35:05'),(132,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-14 13:35:05'),(133,1,'artikel.anlegen','artikel',50,'{\"name\":\"Test Meterware\"}','2026-06-14 14:27:54'),(134,1,'wareneingang.buchen','lagerbestand',23,'{\"artikel_id\":50,\"lager_id\":1,\"menge\":3.6,\"bestand_nachher\":3.6}','2026-06-14 14:28:21'),(135,1,'artikel.bearbeiten','artikel',50,'{\"name\":\"Test Meterware\"}','2026-06-14 14:48:28'),(136,1,'artikel.kategorien_aktualisieren','artikel',50,'{\"kategorie_ids\":[]}','2026-06-14 14:48:28'),(137,1,'artikel.anlegen','artikel',51,'{\"name\":\"gebraucht\"}','2026-06-14 15:07:39'),(138,1,'artikel.bearbeiten','artikel',6,'{\"name\":\"Testartikel\"}','2026-06-14 16:44:50'),(139,1,'artikel.kategorien_aktualisieren','artikel',6,'{\"kategorie_ids\":[1]}','2026-06-14 16:44:50'),(140,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-15 18:49:56'),(141,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-15 18:49:56'),(142,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-15 18:50:47'),(143,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-15 18:50:47'),(144,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-15 20:16:16'),(145,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1]}','2026-06-15 20:16:16'),(146,1,'artikel.bearbeiten','artikel',49,'{\"name\":\"DROPS Air Testfarbe - Neu1\"}','2026-06-16 17:24:29'),(147,1,'artikel.kategorien_aktualisieren','artikel',49,'{\"kategorie_ids\":[]}','2026-06-16 17:24:29'),(148,1,'artikel.bearbeiten','artikel',49,'{\"name\":\"DROPS Air Testfarbe - Neu1\"}','2026-06-16 17:34:08'),(149,1,'artikel.kategorien_aktualisieren','artikel',49,'{\"kategorie_ids\":[]}','2026-06-16 17:34:08'),(150,1,'artikel.bearbeiten','artikel',46,'{\"name\":\"DROPS Air weizen [Mix] (02)\"}','2026-06-16 17:34:34'),(151,1,'artikel.kategorien_aktualisieren','artikel',46,'{\"kategorie_ids\":[]}','2026-06-16 17:34:34'),(152,1,'artikel.bearbeiten','artikel',47,'{\"name\":\"DROPS Air perlgrau [Mix] (03)\"}','2026-06-16 17:35:04'),(153,1,'artikel.kategorien_aktualisieren','artikel',47,'{\"kategorie_ids\":[]}','2026-06-16 17:35:04'),(154,1,'artikel.bearbeiten','artikel',44,'{\"name\":\"DROPS Air blau [Uni] (16)\"}','2026-06-16 17:35:32'),(155,1,'artikel.kategorien_aktualisieren','artikel',44,'{\"kategorie_ids\":[]}','2026-06-16 17:35:32'),(156,1,'artikel.bearbeiten','artikel',1,'{\"name\":\"DROPS Air\"}','2026-06-16 17:51:36'),(157,1,'artikel.kategorien_aktualisieren','artikel',1,'{\"kategorie_ids\":[1,72,73]}','2026-06-16 17:51:36'),(158,1,'achsenUndWerte.speichern','artikel_achsen',102,'{\"achsen_anzahl\":1,\"werte_anzahl\":1}','2026-06-16 18:24:07'),(159,1,'achsenUndWerte.speichern','artikel_achsen',102,'{\"achsen_anzahl\":1,\"werte_anzahl\":2}','2026-06-16 18:24:42'),(160,1,'varkombi.erstellen','artikel',102,'{\"varKombi_anzahl\":1}','2026-06-16 18:25:07'),(161,1,'achsenUndWerte.speichern','artikel_achsen',142,'{\"achsen_anzahl\":1,\"werte_anzahl\":1}','2026-06-16 18:36:33'),(162,1,'achsenUndWerte.speichern','artikel_achsen',1,'{\"achsen_anzahl\":1,\"werte_anzahl\":7}','2026-06-16 18:50:34'),(163,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-16 20:14:59'),(164,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-16 20:27:23'),(165,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-16 20:30:04'),(166,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-16 20:30:04'),(167,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 07:43:46'),(168,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 07:43:46'),(169,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 09:02:49'),(170,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 09:02:49'),(171,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 09:04:26'),(172,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 09:04:26'),(173,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 09:04:34'),(174,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 09:04:34'),(175,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 09:04:49'),(176,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 09:04:49'),(177,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-17 09:20:06'),(178,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[]}','2026-06-17 09:20:06'),(179,1,'artikel.masse.deaktivieren','artikel',0,'{\"ids\":[98,128]}','2026-06-17 15:55:47'),(180,1,'artikel.masse.aktivieren','artikel',0,'{\"ids\":[98,128]}','2026-06-17 15:55:58'),(181,1,'artikel.masse.deaktivieren','artikel',0,'{\"ids\":[74]}','2026-06-17 15:56:37'),(182,1,'artikel.bearbeiten','artikel',74,'{\"name\":\"Lea\"}','2026-06-17 16:04:42'),(183,1,'artikel.kategorien_aktualisieren','artikel',74,'{\"kategorie_ids\":[83]}','2026-06-17 16:04:42'),(184,1,'artikel.loeschen','artikel',102,NULL,'2026-06-17 16:05:02'),(185,1,'artikel.masse.deaktivieren','artikel',0,'{\"ids\":[102]}','2026-06-17 16:05:02'),(186,1,'artikel.aktivieren','artikel',102,NULL,'2026-06-17 16:05:15'),(187,1,'artikel.masse.aktivieren','artikel',0,'{\"ids\":[102]}','2026-06-17 16:05:15'),(188,1,'artikel.loeschen','artikel',103,NULL,'2026-06-17 16:05:35'),(189,1,'artikel.masse.deaktivieren','artikel',0,'{\"ids\":[103]}','2026-06-17 16:05:35'),(190,1,'artikel.loeschen','artikel',102,NULL,'2026-06-17 16:05:44'),(191,1,'artikel.masse.deaktivieren','artikel',0,'{\"ids\":[102]}','2026-06-17 16:05:44'),(192,1,'artikel.aktivieren','artikel',102,NULL,'2026-06-17 16:05:50'),(193,1,'artikel.masse.aktivieren','artikel',0,'{\"ids\":[102]}','2026-06-17 16:05:50'),(194,1,'artikel.merkmale_aktualisieren','artikel',102,'{\"merkmal_count\":2}','2026-06-17 19:53:09'),(195,1,'artikel.merkmale_aktualisieren','artikel',102,'{\"merkmal_count\":2}','2026-06-17 19:55:54'),(196,1,'preis.kundengruppe.speichern','artikel_preise',102,'{\"kg_id\":1}','2026-06-17 19:56:40'),(197,1,'artikel.seo_aktualisiert','artikel',102,NULL,'2026-06-17 19:56:52'),(198,1,'artikel.merkmale_aktualisieren','artikel',102,'{\"merkmal_count\":2}','2026-06-17 20:08:47'),(199,1,'preis.kundengruppe.speichern','artikel_preise',102,'{\"kg_id\":1}','2026-06-17 20:09:06'),(200,1,'wareneingang.buchen','lagerbestand',24,'{\"artikel_id\":102,\"lager_id\":1,\"menge\":1,\"bestand_nachher\":1}','2026-06-17 20:09:23'),(201,1,'artikel.lieferant.anlegen','artikel_lieferanten',102,'{\"lieferant_id\":1}','2026-06-17 20:10:14'),(202,1,'artikel.merkmale_aktualisieren','artikel',102,'{\"merkmal_count\":2}','2026-06-17 20:10:38'),(203,1,'achse.anlegen','varianten_achsen',3,'{\"name\":\"UNI\",\"code\":\"uni\",\"darstellungsform\":\"dropdown\"}','2026-06-18 07:26:29'),(204,1,'achse.updaten','varianten_achsen',3,'{\"name\":\"UNI\",\"code\":\"uni\",\"darstellungsform\":\"dropdown\"}','2026-06-18 09:22:42'),(205,1,'achse.updaten','varianten_achsen',3,'{\"name\":\"UNI\",\"code\":\"uni\",\"darstellungsform\":\"dropdown\"}','2026-06-18 09:22:49'),(206,1,'achsenUndWerte.speichern','artikel_achsen',98,'{\"achsen_anzahl\":2,\"werte_anzahl\":0}','2026-06-18 10:01:14'),(207,1,'achsenUndWerte.speichern','artikel_achsen',68,'{\"achsen_anzahl\":2,\"werte_anzahl\":0}','2026-06-18 10:04:37'),(208,1,'achsenUndWerte.speichern','artikel_achsen',106,'{\"achsen_anzahl\":2,\"werte_anzahl\":0}','2026-06-18 10:08:31'),(209,1,'achsenUndWerte.speichern','artikel_achsen',106,'{\"achsen_anzahl\":2,\"werte_anzahl\":3}','2026-06-18 10:17:12'),(210,1,'achse.anlegen','varianten_achsen',4,'{\"name\":\"MIX\",\"code\":\"mix\",\"darstellungsform\":\"swatches\"}','2026-06-18 11:02:08'),(211,1,'achsenUndWerte.speichern','artikel_achsen',52,'{\"achsen_anzahl\":2,\"werte_anzahl\":3}','2026-06-18 11:02:46'),(212,1,'achsenUndWerte.speichern','artikel_achsen',52,'{\"achsen_anzahl\":3,\"werte_anzahl\":3}','2026-06-18 11:03:23'),(213,1,'achsenUndWerte.speichern','artikel_achsen',52,'{\"achsen_anzahl\":4,\"werte_anzahl\":4}','2026-06-18 11:05:23'),(214,1,'achse.anlegen','varianten_achsen',5,'{\"name\":\"testachse\",\"code\":\"testachse\",\"darstellungsform\":\"dropdown\"}','2026-06-18 11:08:12'),(215,1,'achse.anlegen','varianten_achsen',6,'{\"name\":\"test-unterachse\",\"code\":\"testunterachse\",\"darstellungsform\":\"swatches\"}','2026-06-18 11:08:49'),(216,1,'achsenUndWerte.speichern','artikel_achsen',52,'{\"achsen_anzahl\":6,\"werte_anzahl\":6}','2026-06-18 11:09:15'),(217,1,'achse.updaten','varianten_achsen',5,'{\"name\":\"testachse\",\"code\":\"testachse\",\"darstellungsform\":\"dropdown\"}','2026-06-18 11:17:12'),(218,1,'achsenUndWerte.speichern','artikel_achsen',52,'{\"achsen_anzahl\":4,\"werte_anzahl\":6}','2026-06-18 11:51:37'),(219,1,'achsenUndWerte.speichern','artikel_achsen',106,'{\"achsen_anzahl\":2,\"werte_anzahl\":3}','2026-06-18 11:58:07'),(220,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-18 12:06:47'),(221,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[95]}','2026-06-18 12:06:47'),(222,1,'artikel.bearbeiten','artikel',102,'{\"name\":\"Tweed Color 4-f\\u00e4dig\"}','2026-06-18 12:07:10'),(223,1,'artikel.kategorien_aktualisieren','artikel',102,'{\"kategorie_ids\":[1,72,73,95]}','2026-06-18 12:07:10'),(224,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":2,\"werte_anzahl\":4}','2026-06-18 13:20:42'),(225,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":3,\"werte_anzahl\":5}','2026-06-18 13:22:14'),(226,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":3,\"werte_anzahl\":5}','2026-06-18 13:22:24'),(227,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":2,\"werte_anzahl\":5}','2026-06-18 13:22:36'),(228,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":2,\"werte_anzahl\":5}','2026-06-18 13:24:53'),(229,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":2,\"werte_anzahl\":7}','2026-06-18 13:26:27'),(230,1,'achsenUndWerte.speichern','artikel_achsen',13,'{\"achsen_anzahl\":2,\"werte_anzahl\":7}','2026-06-18 13:27:57'),(231,1,'achsenUndWerte.speichern','artikel_achsen',14,'{\"achsen_anzahl\":4,\"werte_anzahl\":7}','2026-06-18 13:48:07'),(232,1,'achsenUndWerte.speichern','artikel_achsen',14,'{\"achsen_anzahl\":4,\"werte_anzahl\":7}','2026-06-18 13:48:57'),(233,1,'varkombi.erstellen','artikel',14,'{\"varKombi_anzahl\":2}','2026-06-18 14:07:40'),(234,1,'aktion.anlegen','aktionen',1,'{\"name\":\"Test-Aktion 2026\"}','2026-06-18 16:12:21'),(235,1,'aktion.loeschen','aktionen',1,NULL,'2026-06-18 16:12:34'),(236,1,'kategorie.bearbeiten','kategorien',1,'{\"name\":\"Wolle und Garne\",\"parent_id\":null}','2026-06-18 19:11:39'),(237,1,'kategorie.bearbeiten','kategorien',1,'{\"name\":\"Wolle und Garne\",\"parent_id\":null}','2026-06-18 19:12:34'),(238,1,'kategorie.bearbeiten','kategorien',1,'{\"name\":\"Wolle und Garne\",\"parent_id\":null}','2026-06-18 19:13:08'),(239,1,'aktion.anlegen','aktionen',2,'{\"name\":\"DROPS-TEST\"}','2026-06-18 19:17:09'),(240,1,'aktion.kategorie.hinzufuegen','aktionen',2,'{\"kategorie_id\":1}','2026-06-18 19:17:30'),(241,1,'aktion.preise.speichern','aktionen',2,'{\"gespeichert\":4,\"geloescht\":0,\"kg_id\":1}','2026-06-18 19:26:55'),(242,1,'aktion.starten','aktionen',2,NULL,'2026-06-18 19:27:03'),(243,1,'preis.sale.anlegen','preis_aktionen_positionen',102,'{\"sale_id\":1,\"brutto_vk\":7.5}','2026-06-18 20:41:10'),(244,1,'achsenUndWerte.speichern','artikel_achsen',8,'{\"achsen_anzahl\":4,\"werte_anzahl\":5}','2026-06-18 20:46:44'),(245,1,'varkombi.erstellen','artikel',8,'{\"varKombi_anzahl\":4}','2026-06-18 20:46:55'),(246,1,'artikel.bearbeiten','artikel',69,'{\"name\":\"SCHEEPJES WOOLLY WHIRL\"}','2026-06-19 10:30:42'),(247,1,'artikel.kategorien_aktualisieren','artikel',69,'{\"kategorie_ids\":[79,88]}','2026-06-19 10:30:42'),(248,1,'artikel.bearbeiten','artikel',69,'{\"name\":\"SCHEEPJES WOOLLY WHIRL\"}','2026-06-19 10:31:12'),(249,1,'artikel.kategorien_aktualisieren','artikel',69,'{\"kategorie_ids\":[88]}','2026-06-19 10:31:12'),(250,1,'artikel.merkmale_aktualisieren','artikel',102,'{\"merkmal_count\":2}','2026-06-19 12:48:49'),(251,1,'kategorie.bearbeiten','kategorien',1,'{\"name\":\"Wolle und Garne\",\"parent_id\":null}','2026-06-19 13:04:53'),(252,1,'artikel.bearbeiten','artikel',74,'{\"name\":\"Lea\"}','2026-06-19 13:05:46'),(253,1,'artikel.kategorien_aktualisieren','artikel',74,'{\"kategorie_ids\":[1,83]}','2026-06-19 13:05:46'),(254,1,'aktion.preise.speichern','aktionen',2,'{\"gespeichert\":5,\"geloescht\":0,\"kg_id\":1}','2026-06-19 13:06:35'),(255,1,'aktion.bearbeiten','aktionen',2,'{\"name\":\"DROPS-TEST\"}','2026-06-19 13:06:43'),(256,1,'artikel.bearbeiten','artikel',56,'{\"name\":\"ALPACA SUPERLIGHT#\"}','2026-06-19 13:38:51'),(257,1,'artikel.kategorien_aktualisieren','artikel',56,'{\"kategorie_ids\":[1,75]}','2026-06-19 13:38:51'),(258,1,'aktion.preise.speichern','aktionen',2,'{\"gespeichert\":1,\"geloescht\":0,\"kg_id\":1}','2026-06-19 13:39:23');
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
INSERT INTO `artikel` VALUES (1,'D-1099',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,5.30,0,'2026-05-29 20:38:45','2026-06-15 18:50:47',100.000,1,1,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(4,'Test-001',NULL,1,6,'Testartikel neu',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-05-31 12:31:08','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(6,'Test-002',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-05-31 12:32:56','2026-06-08 17:55:43',100.000,1,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(8,'Test-003',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-05-31 12:36:11','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(9,'Test-005',NULL,1,6,'Testartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-05-31 14:29:02','2026-06-07 15:42:41',100.000,1,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(10,'Test-006',NULL,1,6,'testMitPreis',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-05-31 16:17:33','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(11,'Test-007',NULL,1,6,'testMitPreis',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-05-31 16:18:05','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(12,'J-1010',3,1,4,'Dummyartikel mit Lagerbestand','Test','Testartikel',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AT',NULL,1,0,'neu',NULL,NULL,0,'2026-06-04 20:29:10','2026-06-11 08:09:43',100.000,1,0,NULL,1,0,0,0,1,NULL,NULL,NULL,NULL,NULL),(13,'471122',2,1,1,'Testgarn mit Charge',NULL,NULL,1,2.500,'kg',NULL,NULL,NULL,5.000,5.000,NULL,'AT',NULL,1,0,'neu',NULL,NULL,0,'2026-06-05 13:20:24','2026-06-07 09:41:53',100.000,1,0,NULL,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(14,'chargenartikel',1,1,6,'chargenartikel',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AT',NULL,1,0,'neu',NULL,NULL,0,'2026-06-05 17:16:16','2026-06-07 09:41:53',100.000,1,0,NULL,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(15,'Test-008',2,1,3,'Testartikel',NULL,NULL,2,5.000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-06 12:47:19','2026-06-07 09:41:53',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(16,'esrgrd',NULL,1,6,'grybsgrvbg','EAN-Test',NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-06 17:06:47','2026-06-07 10:31:32',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(17,'syfvsd',3,1,6,'Artikelvater für VarKombis','sgfv','sfsfv ysdxfv',1,NULL,NULL,NULL,NULL,NULL,3.000,3.000,NULL,'AT',NULL,1,0,'neu',NULL,NULL,0,'2026-06-06 18:03:29','2026-06-12 08:20:49',100.000,1,0,NULL,1,0,0,0,0,'ysfv','sdfvyd',NULL,NULL,NULL),(18,'einheitstest',NULL,1,1,'einheitstest',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-07 10:27:31','2026-06-07 10:27:31',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(19,'testzwe',3,1,4,'testzwe',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-07 10:31:59','2026-06-07 10:31:59',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(20,'seo-test',NULL,1,6,'seo-test',NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-08 19:14:24','2026-06-08 19:14:24',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,'ysfvsv','ysdfvysvf','sdrv'),(36,'D-1099-KOPIE',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:04:43','2026-06-10 13:04:43',100.000,0,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(37,'D-1099-KOPIE-KOPIE',1,1,1,'DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:06:45','2026-06-10 13:06:45',100.000,0,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(38,'Test-002-KOPIE',NULL,1,6,'Testartikel-KOPIE',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:19:33','2026-06-10 13:19:33',100.000,1,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(39,'D-1099-KOPIE-KOPIE-kopie',1,1,1,'DROPS Air-KOPIE',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:27:00','2026-06-10 13:27:00',100.000,0,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(40,'KOPIE-D-1099',1,1,1,'KOPIE-DROPS Air',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:27:55','2026-06-10 13:27:55',100.000,0,0,NULL,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(41,'einheitstest-KOPIE',NULL,1,1,'einheitstest-KOPIE',NULL,NULL,5,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-10 13:45:07','2026-06-10 13:45:07',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(43,'D-109906',NULL,1,1,'DROPS Air anthrazit [Mix] (06)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-12 12:28:41','2026-06-14 13:35:05',NULL,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(44,'D-109916',NULL,1,1,'DROPS Air blau [Uni] (16)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-12 12:28:41','2026-06-16 17:35:32',100.000,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(45,'D-109901',NULL,1,1,'DROPS Air natur [Uni] (01)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-12 12:28:41','2026-06-14 13:35:05',NULL,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(46,'D-1099-weizen [Mix] (02)',NULL,1,1,'DROPS Air weizen [Mix] (02)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-12 12:28:41','2026-06-16 17:34:34',100.000,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(47,'D-109903',NULL,1,1,'DROPS Air perlgrau [Mix] (03)',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 10:55:36','2026-06-16 17:35:04',100.000,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(49,'D-1099-Neu1',NULL,1,1,'DROPS Air Testfarbe - Neu1',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,5.30,0,'2026-06-14 11:02:45','2026-06-16 17:24:29',100.000,0,0,1,1,0,1,0,0,NULL,NULL,NULL,NULL,NULL),(50,'test-meterware',2,1,3,'Test Meterware','Test Meterware',NULL,3,0.500,'m',NULL,NULL,NULL,0.200,0.200,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 14:27:54','2026-06-14 14:27:54',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(51,'Test-005-GEB',2,1,6,'gebraucht',NULL,NULL,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'gebraucht',9,NULL,0,'2026-06-14 15:07:39','2026-06-14 15:07:39',100.000,1,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(52,'D-1016',NULL,1,1,'DROPS Nord',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(53,'D-101601',NULL,1,1,'DROPS Nord natur [UNI] (01)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,52,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(54,'D-101602',NULL,1,1,'DROPS Nord schwarz [UNI] (02)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,52,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(55,'D-101603',NULL,1,1,'DROPS Nord perlgrau [MIX] (03)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,52,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(56,'LY-749',NULL,1,1,'ALPACA SUPERLIGHT#',NULL,NULL,1,25.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(57,'LY-749-0003',NULL,1,1,'ALPACA SUPERLIGHT GRAU',NULL,NULL,1,25.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,56,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(58,'LY-749-0004',NULL,1,1,'ALPACA SUPERLIGHT SCHWARZ',NULL,NULL,1,25.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,56,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(59,'LY-749-0006',NULL,1,1,'ALPACA SUPERLIGHT ROYAL',NULL,NULL,1,25.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,56,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(60,'LY-1008',NULL,1,1,'AMANTANI',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(61,'LY-1008-0048',NULL,1,1,'AMANTANI',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,60,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(62,'LY-1008-0060',NULL,1,1,'AMANTANI',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,60,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(63,'LY-1008-0088',NULL,1,1,'AMANTANI',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,60,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(64,'LY-933',NULL,1,1,'AMIRA',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(65,'LY-933-0001',NULL,1,1,'AMIRA WEISS (0001)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,64,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(66,'LY-933-0004',NULL,1,1,'AMIRA SCHWARZ (0004)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,64,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(67,'LY-933-0006',NULL,1,1,'AMIRA ROYAL (0006)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,64,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(68,'DB-63884',NULL,1,1,'SCHEEPJES GARNSCHALE BLACK LEAF',NULL,NULL,1,1.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(69,'DB-1713',NULL,1,1,'SCHEEPJES WOOLLY WHIRL',NULL,NULL,1,220.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(70,'DB-1713-471#',NULL,1,1,'SCHEEPJES WOOLLY WHIRL CHOCOLATE VERMICELLI (471)',NULL,NULL,1,220.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,69,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(71,'DB-1713-472',NULL,1,1,'SCHEEPJES WOOLLY WHIRL SUGAR SIZZLE (472)',NULL,NULL,1,220.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,69,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(72,'DB-1713-473#',NULL,1,1,'SCHEEPJES WOOLLY WHIRL KIWI DRIZZLE (473)',NULL,NULL,1,220.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,69,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(73,'DB-78552',NULL,1,1,'SCHEEPJES GARNSCHALE BLAU',NULL,NULL,1,1.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(74,'CB-Lea',NULL,1,1,'Lea',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-17 16:04:42',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(75,'CB-Lea-007',NULL,1,1,'Lea rosa (7)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,74,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(76,'CB-Lea-028',NULL,1,1,'Lea mittelblau (28)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,74,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(77,'CB-Lea-094',NULL,1,1,'Lea dunkelblau (94)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-19 13:05:46',100.000,0,0,74,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(78,'CB-Quito',NULL,1,1,'Qiuto',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(79,'CB-Quito-004',NULL,1,1,'Qiuto - Fb: 004',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,78,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(80,'CB-Quito-012',NULL,1,1,'Qiuto - Fb: 012',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,78,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(81,'CB-Quito-027',NULL,1,1,'Qiuto - Fb: 027',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,78,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(82,'CB-CountryTweed',NULL,1,1,'Country Tweed',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(83,'CB-CountryTweed-0001',NULL,1,1,'Country Tweed - Fb: 021',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,82,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(84,'CB-CountryTweed-0002',NULL,1,1,'Country Tweed - Fb: 010',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,82,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(85,'CB-CountryTweed-0003',NULL,1,1,'Country Tweed - Fb: 291',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,82,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(86,'BC-011010118',NULL,1,1,'Bio Balance GOTS',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(87,'BC-011010118-01',NULL,1,1,'Bio Balance GOTS natur (01)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,86,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(88,'BC-011010118-03',NULL,1,1,'Bio Balance GOTS gras (03)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,86,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(89,'BC-011010118-04',NULL,1,1,'Bio Balance GOTS Limette (04)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,86,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(90,'BC-012010283',NULL,1,1,'Summer in Kashmir',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(91,'BC-012010283-01',NULL,1,1,'Summer in Kashmir Natur (01)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,90,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(92,'BC-012010283-02',NULL,1,1,'Summer in Kashmir Blassrosa (02)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,90,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(93,'BC-012010283-03',NULL,1,1,'Summer in Kashmir Puder (03)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,90,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(94,'BC-111010288',NULL,1,1,'Semilla GOTS',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(95,'BC-111010288-01',NULL,1,1,'Semilla GOTS Natur (01)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,94,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(96,'BC-111010288-02',NULL,1,1,'Semilla GOTS Pfirsich (02)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,94,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(97,'BC-111010288-03',NULL,1,1,'Semilla GOTS Cream (03)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,94,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(98,'280',NULL,1,1,'6-fädig',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-17 15:55:58',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(99,'280-0327',NULL,1,1,'6-fädig Tanne (0327)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,98,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(100,'280-1994',NULL,1,1,'6-fädig loden (1994)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,98,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(101,'280-2134',NULL,1,1,'6-fädig leinen (2134)',NULL,NULL,1,50.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,98,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(102,'272665',1,1,1,'Tweed Color 4-fädig',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-18 12:06:47',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(103,'272665-07491',NULL,1,1,'Tweed Color 4-fädig zauberwald (07491)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-17 16:05:35',100.000,0,0,102,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(104,'272665-07492',NULL,1,1,'Tweed Color 4-fädig zuckerbäcker (07492)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-17 16:05:50',100.000,0,0,102,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(105,'272665-07495',NULL,1,1,'Tweed Color 4-fädig winterzauber (07495)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-17 16:05:50',100.000,0,0,102,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(106,'272731',NULL,1,1,'Regia Cotton Cocktail 100g',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(107,'272731-2428',NULL,1,1,'Regia Cotton Cocktail 100g Shark Bite (2428)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,106,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(108,'272731-2429',NULL,1,1,'Regia Cotton Cocktail 100g Rainbow (2429)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,106,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(109,'272731-2430',NULL,1,1,'Regia Cotton Cocktail 100g Russian Spring Punch (2430)',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',100.000,0,0,106,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(110,'OP-4061',NULL,1,1,'Opal 4fach Suprise rosa',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(111,'OP-4065',NULL,1,1,'Opal 4fach Suprise lila',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(112,'OP-773',NULL,1,1,'Opal Classic 4f',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(113,'OP-773-9062',NULL,1,1,'Opal Classic 4f 9062 modern',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,112,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(114,'OP-773-9063',NULL,1,1,'Opal Classic 4f 9063 individuell',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,112,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(115,'OP-773-9066',NULL,1,1,'Opal Classic 4f 9066 vornehm',NULL,NULL,1,100.000,'g',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,0,112,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(116,'P-Nat.-S',NULL,1,2,'Seil und Zubehör für Rundstricknadel Natural (KnitPro)',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(117,'P-Nat.-NS',NULL,1,2,'Nadelspitzen NATURAL (KnitPro) lang',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(118,'P-RSN-Kst',NULL,1,2,'Rundstricknadeln Kst.',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(119,'P-RSN-Alu',NULL,1,2,'Rundstricknadeln Alu grau',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(120,'ART-51/2013',NULL,1,2,'Strumpfstricknadeln BAMBUS',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(121,'P-SSN-Kst',NULL,1,2,'Strumpfstricknadeln Kst. grau',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:40','2026-06-14 17:29:40',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(122,'P-SSN-Alu',NULL,1,2,'Strumpfstricknadeln ALU grau',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(123,'D-Romance-Spiel',NULL,1,2,'D-Pro Romance Nadelspiel',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(124,'P-HN-Kst-gr',NULL,1,2,'Wollhäkelnadeln Kst grau',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(125,'PRY-218599',NULL,1,2,'Häkelnadelset POP 5,00-10,00 mm AKTION',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(126,'P-HN-oG',NULL,1,2,'Häkelnadel ohne Griff 14 cm',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(127,'P-HN-SG',NULL,1,2,'Softgriff-Häkelnadel',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(128,'281-7',NULL,1,2,'Addi Knookingnadeln Set (4,6)',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-17 15:55:58',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(129,'284-7',NULL,1,2,'Addi Knookingnadel 4mm',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(130,'286-7',NULL,1,2,'Addi Knookingnadel 6mm',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(131,'KP-Symf-IC',NULL,1,2,'Symfonie Nadelspitzen',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(132,'KP-20617',NULL,1,2,'KP Symfonie Rose Deluxe Set',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(133,'KP-20736',NULL,1,2,'KP Symfonie Rose Häkelnadelset',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(134,'PRY-311595',NULL,1,6,'Jackenknöpfe KST 32\" schwarz 20 mm',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(135,'D-HK_hell',NULL,1,6,'Holzknopf hell',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(136,'D-HK_dunkel',NULL,1,6,'Holzknopf dunkel',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,1,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(137,'D-520',NULL,1,6,'Holzknopf zylinder 30 mm',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(138,'STA33650-02',NULL,1,6,'Plüschband - Schneeleopard',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(139,'STA33650-05',NULL,1,6,'Plüschband - Ocelot',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(140,'STA335899-05',NULL,1,6,'Taschenboden rund klein - naturfarbig',NULL,NULL,4,1.000,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(141,'STA335899-01',NULL,1,6,'Taschengriff naturfarbig',NULL,NULL,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-14 17:29:41','2026-06-14 17:29:41',NULL,0,0,NULL,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(142,'272665-01',NULL,1,1,'Tweed Color 4-fädig uni [01]',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-16 18:25:07','2026-06-17 16:05:50',NULL,0,0,102,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(143,'chargenartikel-f1-3mm',NULL,1,6,'chargenartikel f1 3mm',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 14:07:40','2026-06-18 14:07:40',NULL,0,0,14,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(144,'chargenartikel-f1-4mm',NULL,1,6,'chargenartikel f1 4mm',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 14:07:40','2026-06-18 14:07:40',NULL,0,0,14,1,1,0,0,0,NULL,NULL,NULL,NULL,NULL),(145,'Test-003-m1 MIX-s1',NULL,1,6,'Testartikel m1 MIX s1',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 20:46:55','2026-06-18 20:46:55',NULL,0,0,8,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(146,'Test-003-m2 MIX-s1',NULL,1,6,'Testartikel m2 MIX s1',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 20:46:55','2026-06-18 20:46:55',NULL,0,0,8,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(147,'Test-003-u1 UNI-s1',NULL,1,6,'Testartikel u1 UNI s1',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 20:46:55','2026-06-18 20:46:55',NULL,0,0,8,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL),(148,'Test-003-u2 UNI-s1',NULL,1,6,'Testartikel u2 UNI s1',NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,0,'neu',NULL,NULL,0,'2026-06-18 20:46:55','2026-06-18 20:46:55',NULL,0,0,8,1,0,0,0,0,NULL,NULL,NULL,NULL,NULL);
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
INSERT INTO `artikel_achsen` VALUES (2,15,1,NULL,NULL,0),(3,15,2,NULL,NULL,0),(7,1,1,NULL,NULL,0),(9,98,1,NULL,NULL,0),(10,98,3,NULL,NULL,0),(11,68,1,NULL,NULL,0),(12,68,3,NULL,NULL,0),(32,52,1,NULL,NULL,0),(33,52,4,NULL,NULL,0),(34,52,3,NULL,NULL,0),(35,52,6,NULL,NULL,0),(36,106,1,NULL,NULL,0),(37,106,3,NULL,NULL,0),(52,13,1,NULL,NULL,0),(53,13,4,NULL,NULL,0),(58,14,2,NULL,NULL,0),(59,14,1,NULL,NULL,0),(60,14,4,NULL,NULL,0),(61,14,3,NULL,NULL,0),(62,8,2,NULL,NULL,0),(63,8,1,NULL,NULL,0),(64,8,4,NULL,NULL,0),(65,8,3,NULL,NULL,0);
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
INSERT INTO `artikel_bilder` VALUES (1,1,'bild_1781871924_4821.jpg','air',0,'2026-06-19 12:25:24'),(2,1,'bild_1781871937_3939.jpg','',5,'2026-06-19 12:25:37'),(3,1,'bild_1781871949_3206.jpg','',1,'2026-06-19 12:25:49'),(5,1,'bild_1781871956_5105.jpg','',4,'2026-06-19 12:25:56'),(7,1,'bild_1781872010_2332.jpg','',3,'2026-06-19 12:26:50'),(8,1,'bild_1781872013_4906.jpg','',2,'2026-06-19 12:26:53');
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
INSERT INTO `artikel_codes` VALUES (22,17,'4434567890221','GTIN13',NULL),(24,1,'1234567890123','GTIN13',NULL);
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
INSERT INTO `artikel_kategorien` VALUES (1,1),(1,72),(1,73),(6,1),(8,3),(8,4),(17,1),(36,1),(39,1),(40,1),(52,73),(56,1),(56,75),(57,1),(57,75),(58,1),(58,75),(59,1),(59,75),(60,75),(64,75),(68,79),(69,88),(70,88),(71,88),(72,88),(73,79),(74,1),(74,83),(75,1),(75,83),(76,1),(76,83),(77,1),(77,83),(78,83),(82,83),(86,86),(90,86),(94,86),(98,77),(102,1),(102,72),(102,73),(102,95),(106,77),(110,76),(111,76),(112,76),(116,97),(117,97),(118,97),(119,97),(120,98),(121,98),(122,98),(123,98),(124,99),(125,99),(126,99),(127,99),(128,107),(129,107),(130,107),(131,109),(132,109),(133,109),(134,115),(135,115),(136,115),(137,115),(138,119),(139,119),(140,119),(141,119);
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
INSERT INTO `artikel_lieferanten` VALUES (1,1,1,'',3.20,NULL,'EUR',20,'7071723011379',20,0.00,1,1,'2026-05-30 19:10:44','2026-06-14 13:35:52'),(2,36,1,NULL,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:04:43','2026-06-10 13:04:43'),(3,37,1,NULL,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:06:45','2026-06-10 13:06:45'),(4,39,1,NULL,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:27:00','2026-06-10 13:27:00'),(5,40,1,NULL,NULL,NULL,'EUR',NULL,NULL,NULL,NULL,0,1,'2026-06-10 13:27:55','2026-06-10 13:27:55'),(6,1,2,'test54',6.90,NULL,'EUR',5,NULL,14,5.00,0,NULL,'2026-06-13 21:17:56','2026-06-13 21:18:15'),(7,102,1,'test54',5.50,NULL,'EUR',5,NULL,14,5.00,1,NULL,'2026-06-17 20:10:14','2026-06-17 20:10:14');
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
INSERT INTO `artikel_merkmale` VALUES (9,102,1,2,'2026-06-19 12:48:49'),(10,102,5,5,'2026-06-19 12:48:49');
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
INSERT INTO `artikel_preise` VALUES (1,1,1,5.30,4.42,NULL,NULL,'2026-05-30 16:10:13'),(2,1,2,4.70,3.92,'2026-06-15 00:00:00','2026-06-17 00:00:00','2026-05-30 16:10:13'),(3,1,3,4.95,4.13,NULL,NULL,'2026-05-30 16:10:13'),(4,1,4,5.30,4.42,NULL,NULL,'2026-05-30 16:10:13'),(5,11,1,15.90,13.25,NULL,NULL,'2026-05-31 16:18:05'),(6,10,1,13.50,11.25,NULL,NULL,'2026-05-31 16:21:56'),(7,12,1,19.90,16.58,NULL,NULL,'2026-06-04 20:29:10'),(8,13,1,399.95,333.29,NULL,NULL,'2026-06-05 13:20:24'),(9,14,1,45.90,38.25,NULL,NULL,'2026-06-05 17:16:16'),(10,15,1,3.50,2.92,NULL,NULL,'2026-06-06 12:47:19'),(11,16,1,12.00,10.00,NULL,NULL,'2026-06-06 17:06:47'),(12,17,1,123.00,102.50,NULL,NULL,'2026-06-06 18:03:29'),(13,18,1,125.00,104.17,NULL,NULL,'2026-06-07 10:27:31'),(14,19,1,256.00,213.33,NULL,NULL,'2026-06-07 10:31:59'),(23,36,1,5.30,4.42,NULL,NULL,'2026-06-10 13:04:43'),(24,36,2,4.51,3.76,NULL,NULL,'2026-06-10 13:04:43'),(25,36,3,4.77,3.98,NULL,NULL,'2026-06-10 13:04:43'),(26,36,4,5.30,4.42,NULL,NULL,'2026-06-10 13:04:43'),(30,37,1,5.30,4.42,NULL,NULL,'2026-06-10 13:06:45'),(31,37,2,4.51,3.76,NULL,NULL,'2026-06-10 13:06:45'),(32,37,3,4.77,3.98,NULL,NULL,'2026-06-10 13:06:45'),(33,37,4,5.30,4.42,NULL,NULL,'2026-06-10 13:06:45'),(37,39,1,5.30,4.42,NULL,NULL,'2026-06-10 13:27:00'),(38,39,2,4.51,3.76,NULL,NULL,'2026-06-10 13:27:00'),(39,39,3,4.77,3.98,NULL,NULL,'2026-06-10 13:27:00'),(40,39,4,5.30,4.42,NULL,NULL,'2026-06-10 13:27:00'),(44,40,1,5.30,4.42,NULL,NULL,'2026-06-10 13:27:55'),(45,40,2,4.51,3.76,NULL,NULL,'2026-06-10 13:27:55'),(46,40,3,4.77,3.98,NULL,NULL,'2026-06-10 13:27:55'),(47,40,4,5.30,4.42,NULL,NULL,'2026-06-10 13:27:55'),(51,41,1,125.00,104.17,NULL,NULL,'2026-06-10 13:45:07'),(52,50,1,8.90,7.42,NULL,NULL,'2026-06-14 14:27:54'),(53,51,1,11.90,9.92,NULL,NULL,'2026-06-14 15:07:39'),(54,52,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:40'),(55,53,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:40'),(56,54,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:40'),(57,55,1,3.00,2.50,NULL,NULL,'2026-06-14 17:29:40'),(58,56,1,5.75,4.79,NULL,NULL,'2026-06-14 17:29:40'),(59,57,1,5.75,4.79,NULL,NULL,'2026-06-14 17:29:40'),(60,58,1,5.75,4.79,NULL,NULL,'2026-06-14 17:29:40'),(61,59,1,5.75,4.79,NULL,NULL,'2026-06-14 17:29:40'),(62,60,1,18.95,15.79,NULL,NULL,'2026-06-14 17:29:40'),(63,61,1,18.95,15.79,NULL,NULL,'2026-06-14 17:29:40'),(64,62,1,18.95,15.79,NULL,NULL,'2026-06-14 17:29:40'),(65,63,1,18.95,15.79,NULL,NULL,'2026-06-14 17:29:40'),(66,64,1,6.95,5.79,NULL,NULL,'2026-06-14 17:29:40'),(67,65,1,6.95,5.79,NULL,NULL,'2026-06-14 17:29:40'),(68,66,1,6.95,5.79,NULL,NULL,'2026-06-14 17:29:40'),(69,67,1,6.95,5.79,NULL,NULL,'2026-06-14 17:29:40'),(70,68,1,37.95,31.63,NULL,NULL,'2026-06-14 17:29:40'),(71,69,1,31.45,26.21,NULL,NULL,'2026-06-14 17:29:40'),(72,70,1,31.45,26.21,NULL,NULL,'2026-06-14 17:29:40'),(73,71,1,31.45,26.21,NULL,NULL,'2026-06-14 17:29:40'),(74,72,1,31.45,26.21,NULL,NULL,'2026-06-14 17:29:40'),(75,73,1,30.95,25.79,NULL,NULL,'2026-06-14 17:29:40'),(76,74,1,2.10,1.75,NULL,NULL,'2026-06-14 17:29:40'),(77,75,1,2.10,1.75,NULL,NULL,'2026-06-14 17:29:40'),(78,76,1,2.10,1.75,NULL,NULL,'2026-06-14 17:29:40'),(79,77,1,2.10,1.75,NULL,NULL,'2026-06-14 17:29:40'),(80,78,1,5.70,4.75,NULL,NULL,'2026-06-14 17:29:40'),(81,79,1,5.70,4.75,NULL,NULL,'2026-06-14 17:29:40'),(82,80,1,5.70,4.75,NULL,NULL,'2026-06-14 17:29:40'),(83,81,1,5.70,4.75,NULL,NULL,'2026-06-14 17:29:40'),(84,82,1,3.20,2.67,NULL,NULL,'2026-06-14 17:29:40'),(85,83,1,3.20,2.67,NULL,NULL,'2026-06-14 17:29:40'),(86,84,1,3.20,2.67,NULL,NULL,'2026-06-14 17:29:40'),(87,85,1,3.20,2.67,NULL,NULL,'2026-06-14 17:29:40'),(88,86,1,6.90,5.75,NULL,NULL,'2026-06-14 17:29:40'),(89,87,1,6.90,5.75,NULL,NULL,'2026-06-14 17:29:40'),(90,88,1,6.90,5.75,NULL,NULL,'2026-06-14 17:29:40'),(91,89,1,6.90,5.75,NULL,NULL,'2026-06-14 17:29:40'),(92,90,1,7.50,6.25,NULL,NULL,'2026-06-14 17:29:40'),(93,91,1,7.50,6.25,NULL,NULL,'2026-06-14 17:29:40'),(94,92,1,7.50,6.25,NULL,NULL,'2026-06-14 17:29:40'),(95,93,1,7.50,6.25,NULL,NULL,'2026-06-14 17:29:40'),(96,94,1,7.90,6.58,NULL,NULL,'2026-06-14 17:29:40'),(97,95,1,7.90,6.58,NULL,NULL,'2026-06-14 17:29:40'),(98,96,1,7.90,6.58,NULL,NULL,'2026-06-14 17:29:40'),(99,97,1,7.90,6.58,NULL,NULL,'2026-06-14 17:29:40'),(100,98,1,3.55,2.96,NULL,NULL,'2026-06-14 17:29:40'),(101,99,1,3.55,2.96,NULL,NULL,'2026-06-14 17:29:40'),(102,100,1,3.55,2.96,NULL,NULL,'2026-06-14 17:29:40'),(103,101,1,3.55,2.96,NULL,NULL,'2026-06-14 17:29:40'),(104,102,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(105,103,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(106,104,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(107,105,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(108,106,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(109,107,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(110,108,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(111,109,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(112,110,1,7.45,6.21,NULL,NULL,'2026-06-14 17:29:40'),(113,111,1,7.45,6.21,NULL,NULL,'2026-06-14 17:29:40'),(114,112,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(115,113,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(116,114,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(117,115,1,7.95,6.63,NULL,NULL,'2026-06-14 17:29:40'),(118,116,1,3.50,2.92,NULL,NULL,'2026-06-14 17:29:40'),(119,117,1,6.50,5.42,NULL,NULL,'2026-06-14 17:29:40'),(120,118,1,7.50,6.25,NULL,NULL,'2026-06-14 17:29:40'),(121,119,1,6.30,5.25,NULL,NULL,'2026-06-14 17:29:40'),(122,120,1,7.20,6.00,NULL,NULL,'2026-06-14 17:29:40'),(123,121,1,5.50,4.58,NULL,NULL,'2026-06-14 17:29:40'),(124,122,1,4.95,4.13,NULL,NULL,'2026-06-14 17:29:41'),(125,123,1,6.60,5.50,NULL,NULL,'2026-06-14 17:29:41'),(126,124,1,3.10,2.58,NULL,NULL,'2026-06-14 17:29:41'),(127,125,1,9.50,7.92,NULL,NULL,'2026-06-14 17:29:41'),(128,126,1,2.40,2.00,NULL,NULL,'2026-06-14 17:29:41'),(129,127,1,3.65,3.04,NULL,NULL,'2026-06-14 17:29:41'),(130,128,1,5.50,4.58,NULL,NULL,'2026-06-14 17:29:41'),(131,129,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:41'),(132,130,1,3.50,2.92,NULL,NULL,'2026-06-14 17:29:41'),(133,131,1,8.25,6.88,NULL,NULL,'2026-06-14 17:29:41'),(134,132,1,104.75,87.29,NULL,NULL,'2026-06-14 17:29:41'),(135,133,1,105.95,88.29,NULL,NULL,'2026-06-14 17:29:41'),(136,134,1,2.40,2.00,NULL,NULL,'2026-06-14 17:29:41'),(137,135,1,0.50,0.42,NULL,NULL,'2026-06-14 17:29:41'),(138,136,1,0.60,0.50,NULL,NULL,'2026-06-14 17:29:41'),(139,137,1,0.90,0.75,NULL,NULL,'2026-06-14 17:29:41'),(140,138,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:41'),(141,139,1,2.95,2.46,NULL,NULL,'2026-06-14 17:29:41'),(142,140,1,4.95,4.13,NULL,NULL,'2026-06-14 17:29:41'),(143,141,1,4.95,4.13,NULL,NULL,'2026-06-14 17:29:41'),(144,49,1,5.30,4.42,NULL,NULL,'2026-06-16 17:24:24'),(145,46,1,5.50,4.58,NULL,NULL,'2026-06-16 17:34:30'),(146,47,1,5.50,4.58,NULL,NULL,'2026-06-16 17:35:01'),(147,44,1,5.30,4.42,NULL,NULL,'2026-06-16 17:35:30');
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
INSERT INTO `artikel_staffelpreise` VALUES (1,1,1,20.000,5.10,4.25,'2026-06-14 13:53:52'),(2,49,1,10.000,5.20,4.33,'2026-06-16 17:25:08');
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
INSERT INTO `benutzer_einstellungen` VALUES (1,1,'artikel_liste.spalten','[\"status\",\"shops\",\"bestand\",\"preis\"]','2026-06-18 12:20:24');
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
  `ist_aktions_kategorie` tinyint(1) NOT NULL DEFAULT 0,
  `externe_id` varchar(100) DEFAULT NULL,
  `datenquelle` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_kat_parent_id` (`parent_id`),
  CONSTRAINT `fk_kat_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `kategorien` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kategorien` WRITE;
/*!40000 ALTER TABLE `kategorien` DISABLE KEYS */;
INSERT INTO `kategorien` VALUES (1,NULL,'Wolle und Garne',1,1,1,NULL,NULL),(2,NULL,'Nadeln',2,1,0,NULL,NULL),(3,NULL,'Zubehör',3,1,0,NULL,NULL),(4,NULL,'Bücher und Anleitungen',4,1,0,NULL,NULL),(72,1,'Hersteller',1,1,0,NULL,NULL),(73,72,'Garnstudio DROPS',1,1,0,NULL,NULL),(74,72,'Schachenmayr',2,1,0,NULL,NULL),(75,72,'Lang Yarns',3,1,0,NULL,NULL),(76,72,'Opal',4,1,0,NULL,NULL),(77,72,'Regia',5,1,0,NULL,NULL),(78,72,'Katia',6,1,0,NULL,NULL),(79,72,'Scheepjes',7,1,0,NULL,NULL),(80,72,'Rico Design',8,1,0,NULL,NULL),(81,72,'Ferner Wolle',9,1,0,NULL,NULL),(82,72,'Pro Lana',10,1,0,NULL,NULL),(83,72,'Cheval Blanc',11,1,0,NULL,NULL),(84,72,'Rosy Green Wool',12,1,0,NULL,NULL),(85,72,'Kremke Soul Wool',13,1,0,NULL,NULL),(86,72,'BC Garn',14,1,0,NULL,NULL),(87,72,'Rellana',15,1,0,NULL,NULL),(88,72,'CraSy',16,1,0,NULL,NULL),(89,72,'Hoooked',17,1,0,NULL,NULL),(90,72,'MEALANA',18,1,0,NULL,NULL),(91,72,'Sonstige',99,1,0,NULL,NULL),(92,1,'Pakete / Sets',2,1,0,NULL,NULL),(93,92,'Pakete für Socken',1,1,0,NULL,NULL),(94,92,'Pakete für Tücher/Schals',2,1,0,NULL,NULL),(95,92,'Pakete für Hauben/Handschuhe',3,1,0,NULL,NULL),(96,2,'Nadelart',1,1,0,NULL,NULL),(97,96,'Rundnadeln',1,1,0,NULL,NULL),(98,96,'Nadelspiele',2,1,0,NULL,NULL),(99,96,'Häkelnadeln',3,1,0,NULL,NULL),(100,96,'Stricknadeln',4,1,0,NULL,NULL),(101,96,'Paarnadeln',5,1,0,NULL,NULL),(102,96,'Knooking-Nadeln',6,1,0,NULL,NULL),(103,96,'Tunesische Nadeln',7,1,0,NULL,NULL),(104,96,'Nähnadeln',8,1,0,NULL,NULL),(105,96,'Sets & austauschbare Systeme',9,1,0,NULL,NULL),(106,2,'Hersteller',2,1,0,NULL,NULL),(107,106,'Addi',1,1,0,NULL,NULL),(108,106,'ChiaoGoo',2,1,0,NULL,NULL),(109,106,'KnitPro',3,1,0,NULL,NULL),(110,106,'HiyaHiya',4,1,0,NULL,NULL),(111,106,'DROPS',5,1,0,NULL,NULL),(112,106,'LYKKE',6,1,0,NULL,NULL),(113,106,'PRYM',7,1,0,NULL,NULL),(114,106,'Sonstige',99,1,0,NULL,NULL),(115,3,'Knöpfe',1,1,0,NULL,NULL),(116,3,'Reißverschlüsse',2,1,0,NULL,NULL),(117,3,'Bänder und Kordeln',3,1,0,NULL,NULL),(118,3,'Strick- und Häkelhilfen',4,1,0,NULL,NULL),(119,3,'Taschenzubehör',5,1,0,NULL,NULL),(120,3,'Aufbewahrung und Ordnung',6,1,0,NULL,NULL),(121,3,'Sonstiges',99,1,0,NULL,NULL),(122,4,'Schwerpunkt',20,1,0,NULL,NULL),(123,122,'Stricken',10,1,0,NULL,NULL),(124,122,'Häkeln',30,1,0,NULL,NULL),(125,122,'Socken',40,1,0,NULL,NULL),(126,122,'Tücher und Schals',50,1,0,NULL,NULL),(127,122,'Filzen',60,1,0,NULL,NULL),(128,122,'Amigurumi',20,1,0,NULL,NULL),(129,122,'Sonstiges',70,1,0,NULL,NULL),(130,4,'Hersteller/Verlag',10,1,0,NULL,NULL),(131,130,'DROPS',1,1,0,NULL,NULL),(132,130,'Lang Yarns / FAM',2,1,0,NULL,NULL),(133,130,'Scheepjes',3,1,0,NULL,NULL),(134,130,'Sonstige',99,1,0,NULL,NULL);
/*!40000 ALTER TABLE `kategorien` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kunden` WRITE;
/*!40000 ALTER TABLE `kunden` DISABLE KEYS */;
INSERT INTO `kunden` VALUES (1,'LAUFKUNDE','aktiv',1,0,NULL,NULL,NULL,NULL,'de','kasse',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-19 14:10:34','2026-06-19 14:10:34');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lager_bewegungen` WRITE;
/*!40000 ALTER TABLE `lager_bewegungen` DISABLE KEYS */;
INSERT INTO `lager_bewegungen` VALUES (4,2,NULL,NULL,NULL,'eingang',5.000,0.000,5.000,NULL,NULL,'2026-06-05 08:46:00',9,NULL),(6,3,NULL,NULL,NULL,'eingang',15.000,0.000,15.000,NULL,NULL,'2026-06-05 17:16:53',14,NULL),(7,3,NULL,NULL,'7768','korrektur',4.000,15.000,11.000,NULL,'Charge nachgetragen','2026-06-05 17:17:41',14,NULL),(8,3,NULL,NULL,'00079','korrektur',9.000,11.000,2.000,NULL,'Charge nachgetragen','2026-06-05 17:17:52',14,NULL),(9,2,NULL,NULL,NULL,'eingang',2.000,0.000,2.000,NULL,NULL,'2026-06-06 18:51:37',10,1),(11,2,1,5.5000,NULL,'eingang',10.000,0.000,10.000,NULL,NULL,'2026-06-07 11:26:37',15,1),(12,2,1,6.9000,NULL,'eingang',10.000,0.000,10.000,'test','testbuchung','2026-06-07 11:28:42',13,1),(13,2,1,5.9000,NULL,'eingang',5.000,10.000,15.000,'test','test','2026-06-07 11:30:26',13,1),(14,3,2,55.9000,NULL,'eingang',3.000,0.000,3.000,'test','eingangstest','2026-06-07 11:36:57',17,1),(17,2,2,6.6000,NULL,'eingang',3.000,0.000,3.000,NULL,NULL,'2026-06-08 17:55:43',6,1),(19,1,NULL,NULL,NULL,'eingang',5.000,0.000,5.000,NULL,NULL,'2026-06-14 10:40:01',1,NULL),(20,3,NULL,NULL,NULL,'eingang',1.000,0.000,1.000,NULL,'Testbuchung','2026-06-14 10:45:40',1,1),(21,1,NULL,NULL,'7768','eingang',2.000,0.000,2.000,NULL,'Testbuchung','2026-06-14 10:48:18',1,1),(22,1,NULL,NULL,NULL,'eingang',0.001,0.000,0.001,NULL,NULL,'2026-06-14 11:56:10',6,1),(23,1,NULL,NULL,NULL,'eingang',3.600,0.000,3.600,NULL,'Testbuchung','2026-06-14 14:28:21',50,1),(24,1,NULL,NULL,'0444','eingang',1.000,0.000,1.000,NULL,NULL,'2026-06-17 20:09:23',102,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `lagerbestand` WRITE;
/*!40000 ALTER TABLE `lagerbestand` DISABLE KEYS */;
INSERT INTO `lagerbestand` VALUES (7,2,NULL,'unbekannt',5.000,0,'2026-06-05 08:46:00','2026-06-05 08:46:00',9),(9,3,NULL,'nachzutragen',2.000,0,'2026-06-05 17:16:53','2026-06-05 17:17:52',14),(10,3,'7768','erfasst',4.000,0,'2026-06-05 17:17:41','2026-06-05 17:17:41',14),(11,3,'00079','erfasst',9.000,0,'2026-06-05 17:17:52','2026-06-05 17:17:52',14),(12,2,NULL,NULL,2.000,0,'2026-06-06 18:51:37','2026-06-06 18:51:37',10),(13,2,NULL,NULL,10.000,0,'2026-06-07 11:26:37','2026-06-07 11:26:37',15),(14,2,NULL,'nachzutragen',15.000,0,'2026-06-07 11:28:42','2026-06-07 11:30:26',13),(15,3,'0444','erfasst',3.000,0,'2026-06-07 11:36:57','2026-06-07 11:36:57',17),(18,2,'grdyvdyf','erfasst',3.000,0,'2026-06-08 17:55:43','2026-06-08 17:55:43',6),(20,1,'0444','erfasst',5.000,0,'2026-06-14 10:40:01','2026-06-14 10:40:01',1),(21,3,'666EH9','erfasst',1.000,0,'2026-06-14 10:45:40','2026-06-14 10:45:40',1),(22,1,'7768','erfasst',2.000,0,'2026-06-14 10:48:18','2026-06-14 10:48:18',1),(23,1,NULL,NULL,0.001,0,'2026-06-14 11:56:10','2026-06-14 11:56:10',6),(24,1,NULL,NULL,3.600,0,'2026-06-14 14:28:21','2026-06-14 14:28:21',50),(25,1,'0444','erfasst',1.000,0,'2026-06-17 20:09:23','2026-06-17 20:09:23',102);
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
INSERT INTO `preis_aktionen_positionen` VALUES (1,102,1,7.50,6.25,NULL,NULL,1,NULL,'2026-06-18 20:41:10');
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
INSERT INTO `varianten_achse_werte` VALUES (1,1,1,'natur [Uni] (01)','#F5F0E8',NULL,0.00,2),(2,1,1,'weizen [Mix] (02)','#e8d5a3',NULL,0.00,6),(3,1,1,'perlgrau [Mix] (03)','#c8c4bc',NULL,0.00,3),(4,1,1,'anthrazit [Mix] (06)','#4a4a4a',NULL,0.00,0),(5,1,1,'blau [Uni] (16)','#4A7CB5',NULL,0.00,1),(6,1,1,'TestfarbeNeu1','#16f883',NULL,0.00,4),(7,1,1,'TestfarbeNeu2','#024cf7',NULL,0.00,5),(16,15,1,'rot',NULL,NULL,0.00,0),(17,15,1,'grün',NULL,NULL,0.00,1),(18,15,1,'blau',NULL,NULL,0.00,2),(19,15,2,'2mm',NULL,NULL,0.00,0),(20,15,2,'3mm',NULL,NULL,0.00,1),(22,102,1,'uni [01]',NULL,NULL,0.00,0),(23,102,1,'uni [02]',NULL,NULL,0.00,1),(44,52,2,'3mm',NULL,NULL,0.00,0),(45,52,4,'blau(01)',NULL,NULL,0.00,0),(46,52,4,'grün (02)',NULL,NULL,0.00,1),(47,52,3,'gelb (03)',NULL,NULL,0.00,0),(48,52,5,'wert',NULL,NULL,0.00,0),(49,52,6,'unterachse-wert',NULL,NULL,0.00,0),(50,106,3,'3',NULL,NULL,0.00,0),(51,106,3,'2',NULL,NULL,0.00,1),(52,106,3,'1',NULL,NULL,0.00,2),(84,13,1,'f1',NULL,NULL,0.00,0),(85,13,1,'f2',NULL,NULL,0.00,1),(86,13,1,'f3',NULL,NULL,0.00,2),(87,13,4,'M1',NULL,NULL,0.00,0),(88,13,3,'u1',NULL,NULL,0.00,0),(89,13,3,'u2',NULL,NULL,0.00,1),(90,13,3,'u3',NULL,NULL,0.00,2),(98,14,2,'3mm',NULL,NULL,0.00,0),(99,14,2,'4mm',NULL,NULL,0.00,1),(100,14,1,'f1',NULL,NULL,0.00,0),(101,14,4,'m1',NULL,NULL,0.00,0),(102,14,4,'m2',NULL,NULL,0.00,1),(103,14,3,'U1',NULL,NULL,0.00,0),(104,14,3,'U2',NULL,NULL,0.00,1),(105,8,2,'s1',NULL,NULL,0.00,9001),(106,8,4,'m1',NULL,NULL,0.00,9002),(107,8,4,'m2',NULL,NULL,0.00,9003),(108,8,3,'u1',NULL,NULL,0.00,9004),(109,8,3,'u2',NULL,NULL,0.00,9005);
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
INSERT INTO `varianten_achsen` VALUES (1,'Farbe','farbe','swatches',0,NULL,20,'2026-06-11 11:07:43'),(2,'Stärke','staerke','dropdown',0,NULL,10,'2026-06-11 15:13:47'),(3,'UNI','uni','dropdown',0,1,0,'2026-06-18 07:26:29'),(4,'MIX','mix','swatches',0,1,0,'2026-06-18 11:02:08'),(5,'testachse','testachse','dropdown',0,NULL,30,'2026-06-18 11:08:12'),(6,'test-unterachse','testunterachse','swatches',0,5,0,'2026-06-18 11:08:49');
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
INSERT INTO `varianten_kombination_werte` VALUES (43,4),(44,5),(45,1),(46,2),(47,3),(49,6),(142,22),(143,98),(143,100),(144,99),(144,100),(145,105),(145,106),(146,105),(146,107),(147,105),(147,108),(148,105),(148,109);
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

