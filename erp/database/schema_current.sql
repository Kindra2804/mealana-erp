SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE aktivitaeten (
  id int(10) UNSIGNED NOT NULL,
  benutzer_id int(10) UNSIGNED NOT NULL,
  aktion varchar(255) NOT NULL,
  referenz_tabelle varchar(50) DEFAULT NULL,
  referenz_id int(10) UNSIGNED DEFAULT NULL,
  details longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(details)),
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO aktivitaeten (id, benutzer_id, aktion, referenz_tabelle, referenz_id, details, erstellt_am) VALUES
(1, 1, 'artikel.bearbeiten', 'artikel', 8, '{\"name\":\"Testartikel\"}', '2026-06-04 19:41:57'),
(2, 1, 'artikel.kategorien_aktualisieren', 'artikel', 8, '{\"kategorie_ids\":[4,3]}', '2026-06-04 19:41:57'),
(3, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-04 19:42:20'),
(4, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-04 19:42:20'),
(5, 1, 'lieferant.bearbeiten', 'lieferanten', 1, '{\"name\":\"DROPS Design A\\/S\"}', '2026-06-04 22:25:59'),
(6, 1, 'wareneingang.buchen', 'lagerbestand', 2, '{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"5\",\"bestand_nachher\":5}', '2026-06-04 22:27:32'),
(7, 1, 'artikel.anlegen', 'artikel', 12, '{\"name\":\"Dummyartikel mit Lagerbestand\"}', '2026-06-04 22:29:10'),
(8, 1, 'wareneingang.buchen', 'lagerbestand', 3, '{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"2\",\"bestand_nachher\":7}', '2026-06-05 10:45:33'),
(9, 1, 'wareneingang.buchen', 'lagerbestand', 4, '{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":5}', '2026-06-05 10:46:00'),
(10, 1, 'artikel.anlegen', 'artikel', 13, '{\"name\":\"Testgarn mit Charge\"}', '2026-06-05 15:20:24'),
(11, 1, 'artikel.bearbeiten', 'artikel', 13, '{\"name\":\"Testgarn mit Charge\"}', '2026-06-05 15:20:39'),
(12, 1, 'artikel.kategorien_aktualisieren', 'artikel', 13, '{\"kategorie_ids\":[]}', '2026-06-05 15:20:39'),
(13, 1, 'artikel.bearbeiten', 'artikel', 13, '{\"name\":\"Testgarn mit Charge\"}', '2026-06-05 15:23:47'),
(14, 1, 'artikel.kategorien_aktualisieren', 'artikel', 13, '{\"kategorie_ids\":[]}', '2026-06-05 15:23:47'),
(15, 1, 'wareneingang.buchen', 'lagerbestand', 5, '{\"artikel_varianten_id\":\"7\",\"lager_id\":\"1\",\"menge\":\"4\",\"bestand_nachher\":4}', '2026-06-05 19:14:29'),
(16, 1, 'artikel.bearbeiten', 'artikel', 13, '{\"name\":\"Testgarn mit Charge\"}', '2026-06-05 19:15:09'),
(17, 1, 'artikel.kategorien_aktualisieren', 'artikel', 13, '{\"kategorie_ids\":[]}', '2026-06-05 19:15:09'),
(18, 1, 'artikel.anlegen', 'artikel', 14, '{\"name\":\"chargenartikel\"}', '2026-06-05 19:16:16'),
(19, 1, 'wareneingang.buchen', 'lagerbestand', 6, '{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"15\",\"bestand_nachher\":15}', '2026-06-05 19:16:53'),
(20, 1, 'lager.charge_nachtragen', 'lagerbestand', 9, '{\"charge\":\"7768\"}', '2026-06-05 19:17:41'),
(21, 1, 'lager.charge_nachtragen', 'lagerbestand', 9, '{\"charge\":\"00079\"}', '2026-06-05 19:17:52'),
(22, 1, 'artikel.bearbeiten', 'artikel', 14, '{\"name\":\"chargenartikel\"}', '2026-06-05 19:21:59'),
(23, 1, 'artikel.kategorien_aktualisieren', 'artikel', 14, '{\"kategorie_ids\":[]}', '2026-06-05 19:21:59'),
(24, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 3, '{\"nachname\":\"Indra\"}', '2026-06-06 13:30:31'),
(25, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 3, '{\"nachname\":\"Indra\"}', '2026-06-06 13:30:42'),
(26, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 4, '{\"nachname\":\"Indra\"}', '2026-06-06 13:31:09'),
(27, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 4, NULL, '2026-06-06 13:31:13'),
(28, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 5, '{\"nachname\":\"Indra\"}', '2026-06-06 13:33:21'),
(29, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 5, NULL, '2026-06-06 13:33:26'),
(30, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 6, '{\"nachname\":\"Indra\"}', '2026-06-06 13:34:24'),
(31, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 6, '{\"nachname\":\"Indra\"}', '2026-06-06 13:34:33'),
(32, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 6, NULL, '2026-06-06 13:34:38'),
(33, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 7, '{\"nachname\":\"Indra\"}', '2026-06-06 13:36:08'),
(34, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 7, '{\"nachname\":\"Indra\"}', '2026-06-06 13:36:15'),
(35, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 7, '{\"nachname\":\"Indra\"}', '2026-06-06 13:45:49'),
(36, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 7, NULL, '2026-06-06 14:22:12'),
(37, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 3, '{\"nachname\":\"Indra\"}', '2026-06-06 14:32:19'),
(38, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 3, NULL, '2026-06-06 14:32:25'),
(39, 1, 'vertreter.anlegen', 'lieferanten_vertreter', 8, '{\"nachname\":\"Indra\"}', '2026-06-06 14:35:35'),
(40, 1, 'vertreter.bearbeiten', 'lieferanten_vertreter', 8, '{\"nachname\":\"Indra\"}', '2026-06-06 14:35:39'),
(41, 1, 'vertreter.loeschen', 'lieferanten_vertreter', 8, NULL, '2026-06-06 14:35:41'),
(42, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-06 14:45:50'),
(43, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-06 14:45:50'),
(44, 1, 'artikel.bearbeiten', 'artikel', 12, '{\"name\":\"Dummyartikel mit Lagerbestand\"}', '2026-06-06 14:46:10'),
(45, 1, 'artikel.kategorien_aktualisieren', 'artikel', 12, '{\"kategorie_ids\":[]}', '2026-06-06 14:46:10'),
(46, 1, 'artikel.anlegen', 'artikel', 15, '{\"name\":\"Testartikel\"}', '2026-06-06 14:47:19'),
(47, 1, 'artikel.anlegen', 'artikel', 16, '{\"name\":\"grybsgrvbg\"}', '2026-06-06 19:06:47'),
(48, 1, 'artikel.kategorien_aktualisieren', 'artikel', 16, '{\"kategorie_ids\":[]}', '2026-06-06 19:52:21'),
(49, 1, 'artikel.bearbeiten', 'artikel', 16, '{\"name\":\"grybsgrvbg\"}', '2026-06-06 19:56:10'),
(50, 1, 'artikel.kategorien_aktualisieren', 'artikel', 16, '{\"kategorie_ids\":[]}', '2026-06-06 19:56:10'),
(51, 1, 'artikel.bearbeiten', 'artikel', 16, '{\"name\":\"grybsgrvbg\"}', '2026-06-06 19:56:31'),
(52, 1, 'artikel.kategorien_aktualisieren', 'artikel', 16, '{\"kategorie_ids\":[]}', '2026-06-06 19:56:31'),
(53, 1, 'artikel.bearbeiten', 'artikel', 16, '{\"name\":\"grybsgrvbg\"}', '2026-06-06 19:57:51'),
(54, 1, 'artikel.kategorien_aktualisieren', 'artikel', 16, '{\"kategorie_ids\":[]}', '2026-06-06 19:57:51'),
(55, 1, 'artikel.anlegen', 'artikel', 17, '{\"name\":\"sdfvsdg sg \"}', '2026-06-06 20:03:29'),
(56, 1, 'artikel.bearbeiten', 'artikel', 17, '{\"name\":\"sdfvsdg sg \"}', '2026-06-06 20:04:14'),
(57, 1, 'artikel.kategorien_aktualisieren', 'artikel', 17, '{\"kategorie_ids\":[]}', '2026-06-06 20:04:14'),
(58, 1, 'artikel.bearbeiten', 'artikel', 17, '{\"name\":\"sdfvsdg sg \"}', '2026-06-06 20:04:41'),
(59, 1, 'artikel.kategorien_aktualisieren', 'artikel', 17, '{\"kategorie_ids\":[]}', '2026-06-06 20:04:41'),
(60, 1, 'artikel.bearbeiten', 'artikel', 17, '{\"name\":\"sdfvsdg sg \"}', '2026-06-06 20:04:57'),
(61, 1, 'artikel.kategorien_aktualisieren', 'artikel', 17, '{\"kategorie_ids\":[]}', '2026-06-06 20:04:57'),
(62, 1, 'wareneingang.buchen', 'lagerbestand', 9, '{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"2\",\"bestand_nachher\":2}', '2026-06-06 20:51:37'),
(63, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-07 12:24:27'),
(64, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-07 12:24:27'),
(65, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-07 12:24:38'),
(66, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-07 12:24:38'),
(67, 1, 'artikel.anlegen', 'artikel', 18, '{\"name\":\"einheitstest\"}', '2026-06-07 12:27:31'),
(68, 1, 'artikel.bearbeiten', 'artikel', 18, '{\"name\":\"einheitstest\"}', '2026-06-07 12:27:41'),
(69, 1, 'artikel.kategorien_aktualisieren', 'artikel', 18, '{\"kategorie_ids\":[]}', '2026-06-07 12:27:41'),
(70, 1, 'artikel.bearbeiten', 'artikel', 16, '{\"name\":\"grybsgrvbg\"}', '2026-06-07 12:31:32'),
(71, 1, 'artikel.kategorien_aktualisieren', 'artikel', 16, '{\"kategorie_ids\":[]}', '2026-06-07 12:31:32'),
(72, 1, 'artikel.anlegen', 'artikel', 19, '{\"name\":\"testzwe\"}', '2026-06-07 12:31:59'),
(73, 1, 'wareneingang.buchen', 'lagerbestand', 10, '{\"artikel_varianten_id\":\"1\",\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":9}', '2026-06-07 13:25:05'),
(74, 1, 'wareneingang.buchen', 'lagerbestand', 11, '{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}', '2026-06-07 13:26:37'),
(75, 1, 'wareneingang.buchen', 'lagerbestand', 12, '{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"10\",\"bestand_nachher\":10}', '2026-06-07 13:28:42'),
(76, 1, 'wareneingang.buchen', 'lagerbestand', 13, '{\"artikel_varianten_id\":null,\"lager_id\":\"2\",\"menge\":\"5\",\"bestand_nachher\":15}', '2026-06-07 13:30:26'),
(77, 1, 'wareneingang.buchen', 'lagerbestand', 14, '{\"artikel_varianten_id\":null,\"lager_id\":\"3\",\"menge\":\"3\",\"bestand_nachher\":3}', '2026-06-07 13:36:57'),
(78, 1, 'artikel.variante_bearbeiten', 'artikel_varianten', 4, '{\"farbe\":\"anthrazit [Mix] (06)\"}', '2026-06-07 16:13:56'),
(79, 1, 'artikel.bearbeiten', 'artikel', 9, '{\"name\":\"Testartikel\"}', '2026-06-07 17:42:41'),
(80, 1, 'artikel.kategorien_aktualisieren', 'artikel', 9, '{\"kategorie_ids\":[]}', '2026-06-07 17:42:41'),
(81, 1, 'artikel.variante_bearbeiten', 'artikel_varianten', 2, '{\"farbe\":\"weizen [Mix] (02)\"}', '2026-06-07 17:43:04'),
(82, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-07 20:39:11'),
(83, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-07 20:39:11'),
(84, 1, 'artikel.bearbeiten', 'artikel', 1, '{\"name\":\"DROPS Air\"}', '2026-06-07 20:39:22'),
(85, 1, 'artikel.kategorien_aktualisieren', 'artikel', 1, '{\"kategorie_ids\":[1]}', '2026-06-07 20:39:22'),
(86, 1, 'wareneingang.buchen', 'lagerbestand', 15, '{\"artikel_varianten_id\":\"2\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}', '2026-06-07 20:42:58'),
(87, 1, 'artikel.variante_bearbeiten', 'artikel_varianten', 4, '{\"farbe\":\"anthrazit [Mix] (06)\"}', '2026-06-07 20:54:34'),
(88, 2, 'variante_artikel_aktiv.geaendert', 'artikel_varianten', 4, '{\"aktiv\":0,\"id\":4,\"artikel_id\":1,\"artikelnummer\":\"D-109906\"}', '2026-06-07 21:01:29'),
(89, 1, 'wareneingang.buchen', 'lagerbestand', 16, '{\"artikel_varianten_id\":\"4\",\"lager_id\":\"3\",\"menge\":\"5\",\"bestand_nachher\":5}', '2026-06-07 21:01:29');

CREATE TABLE artikel (
  id int(10) UNSIGNED NOT NULL,
  artikelnummer varchar(30) NOT NULL,
  hersteller_id int(10) UNSIGNED DEFAULT NULL,
  steuerklasse_id int(10) UNSIGNED NOT NULL,
  artikeltyp_id int(10) UNSIGNED NOT NULL,
  name varchar(255) NOT NULL,
  beschreibung_kurz varchar(255) DEFAULT NULL,
  beschreibung_lang text DEFAULT NULL,
  einheit_id int(10) UNSIGNED NOT NULL,
  inhalt_menge decimal(8,3) DEFAULT NULL,
  inhalt_einheit varchar(20) DEFAULT NULL,
  gewicht_artikel decimal(8,3) DEFAULT NULL,
  gewicht_versand decimal(8,3) DEFAULT NULL,
  herkunftsland char(2) DEFAULT NULL,
  taric_code varchar(20) DEFAULT NULL,
  aktiv tinyint(1) DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  varianten_darstellung varchar(50) NOT NULL DEFAULT 'swatches',
  grundpreis_bezugsmenge decimal(8,3) DEFAULT NULL,
  grundpreis_anzeigen tinyint(1) DEFAULT 0,
  ist_vater tinyint(1) NOT NULL DEFAULT 0,
  charge_pflicht tinyint(1) NOT NULL DEFAULT 0,
  ist_auslaufartikel tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel (id, artikelnummer, hersteller_id, steuerklasse_id, artikeltyp_id, name, beschreibung_kurz, beschreibung_lang, einheit_id, inhalt_menge, inhalt_einheit, gewicht_artikel, gewicht_versand, herkunftsland, taric_code, aktiv, erstellt_am, geaendert_am, varianten_darstellung, grundpreis_bezugsmenge, grundpreis_anzeigen, ist_vater, charge_pflicht, ist_auslaufartikel) VALUES
(1, 'D-1099', 1, 1, 1, 'DROPS Air', NULL, NULL, 1, 50.000, 'g', NULL, NULL, NULL, NULL, 1, '2026-05-29 22:38:45', '2026-06-07 20:35:15', 'swatches', 100.000, 0, 1, 0, 1),
(4, 'Test-001', NULL, 1, 6, 'Testartikel neu', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-05-31 14:31:08', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(6, 'Test-002', NULL, 1, 6, 'Testartikel', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-05-31 14:32:56', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(8, 'Test-003', NULL, 1, 6, 'Testartikel', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-05-31 14:36:11', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(9, 'Test-005', NULL, 1, 6, 'Testartikel', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-05-31 16:29:02', '2026-06-07 17:42:41', 'swatches', 100.000, 1, 0, 0, 1),
(10, 'Test-006', NULL, 1, 6, 'testMitPreis', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-05-31 18:17:33', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(11, 'Test-007', NULL, 1, 6, 'testMitPreis', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-05-31 18:18:05', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(12, 'J-1010', 3, 1, 4, 'Dummyartikel mit Lagerbestand', 'Test', 'Testartikel', 1, NULL, NULL, NULL, NULL, 'AT', NULL, 1, '2026-06-04 22:29:10', '2026-06-07 11:41:53', 'bilder', 100.000, 1, 0, 0, 0),
(13, '471122', 2, 1, 1, 'Testgarn mit Charge', NULL, NULL, 1, 2.500, 'kg', 5.000, 5.000, 'AT', NULL, 1, '2026-06-05 15:20:24', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 1, 0),
(14, 'chargenartikel', 1, 1, 6, 'chargenartikel', NULL, NULL, 1, NULL, NULL, NULL, NULL, 'AT', NULL, 1, '2026-06-05 19:16:16', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 1, 0),
(15, 'Test-008', 2, 1, 3, 'Testartikel', NULL, NULL, 2, 5.000, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-06 14:47:19', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(16, 'esrgrd', NULL, 1, 6, 'grybsgrvbg', 'EAN-Test', NULL, 4, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-06 19:06:47', '2026-06-07 12:31:32', 'swatches', 100.000, 1, 0, 0, 0),
(17, 'syfvsd', NULL, 1, 6, 'sdfvsdg sg ', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-06 20:03:29', '2026-06-07 11:41:53', 'swatches', 100.000, 1, 0, 0, 0),
(18, 'einheitstest', NULL, 1, 1, 'einheitstest', NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-07 12:27:31', '2026-06-07 12:27:31', 'swatches', 100.000, 1, 0, 0, 0),
(19, 'testzwe', 3, 1, 4, 'testzwe', NULL, NULL, 5, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-07 12:31:59', '2026-06-07 12:31:59', 'swatches', 100.000, 1, 0, 0, 0);

CREATE TABLE artikel_codes (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED DEFAULT NULL,
  code varchar(50) NOT NULL,
  typ enum('GTIN13','GTIN8','ITF14','GS1128','ISBN','INTERN') NOT NULL,
  beschreibung varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_codes (id, artikel_id, code, typ, beschreibung) VALUES
(5, 17, '4434567890221', 'GTIN13', NULL);

CREATE TABLE artikel_externe_referenzen (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED DEFAULT NULL,
  datenquelle varchar(50) NOT NULL,
  externe_id varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE artikel_kategorien (
  artikel_id int(10) UNSIGNED NOT NULL,
  kategorie_id int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_kategorien (artikel_id, kategorie_id) VALUES
(1, 1),
(8, 3),
(8, 4);

CREATE TABLE artikel_lieferanten (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED DEFAULT NULL,
  lieferant_id int(10) UNSIGNED DEFAULT NULL,
  artikelnummer_lieferant varchar(255) DEFAULT NULL,
  netto_ek decimal(8,2) DEFAULT NULL,
  waehrung char(3) DEFAULT NULL,
  vpe_menge int(10) UNSIGNED DEFAULT NULL,
  vpe_ean char(13) DEFAULT NULL,
  lieferzeit_tage int(10) UNSIGNED DEFAULT NULL,
  mindestabnahme decimal(6,2) DEFAULT NULL,
  standard_lieferant tinyint(1) DEFAULT NULL,
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_lieferanten (id, artikel_id, lieferant_id, artikelnummer_lieferant, netto_ek, waehrung, vpe_menge, vpe_ean, lieferzeit_tage, mindestabnahme, standard_lieferant, aktiv, erstellt_am, geaendert_am) VALUES
(1, 1, 1, NULL, 2.57, 'EUR', 20, '7071723011379', 20, NULL, 1, 1, '2026-05-30 21:10:44', '2026-05-30 21:10:44');

CREATE TABLE artikel_merkmale (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED DEFAULT NULL,
  merkmal_id int(10) UNSIGNED DEFAULT NULL,
  wert_text varchar(255) DEFAULT NULL,
  wert_zahl decimal(8,2) DEFAULT NULL,
  wert_bool tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_merkmale (id, artikel_id, merkmal_id, wert_text, wert_zahl, wert_bool, erstellt_am) VALUES
(1, 1, 3, 'C', NULL, NULL, '2026-05-30 20:12:39'),
(2, 1, 4, NULL, 5.00, NULL, '2026-05-30 20:12:39'),
(3, 1, 5, NULL, 6.00, NULL, '2026-05-30 20:12:39'),
(4, 1, 6, '10x10cm = 17 Maschen x 22 Reihen', NULL, NULL, '2026-05-30 20:12:39');

CREATE TABLE artikel_preise (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED NOT NULL,
  kundengruppen_id int(10) UNSIGNED NOT NULL,
  brutto_vk decimal(8,2) NOT NULL,
  netto_vk decimal(8,2) NOT NULL,
  gueltig_ab datetime DEFAULT NULL,
  gueltig_bis datetime DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_preise (id, artikel_id, kundengruppen_id, brutto_vk, netto_vk, gueltig_ab, gueltig_bis, erstellt_am) VALUES
(1, 1, 1, 5.30, 4.42, NULL, NULL, '2026-05-30 18:10:13'),
(2, 1, 2, 4.51, 3.76, NULL, NULL, '2026-05-30 18:10:13'),
(3, 1, 3, 4.77, 3.98, NULL, NULL, '2026-05-30 18:10:13'),
(4, 1, 4, 5.30, 4.42, NULL, NULL, '2026-05-30 18:10:13'),
(5, 11, 1, 15.90, 13.25, NULL, NULL, '2026-05-31 18:18:05'),
(6, 10, 1, 13.50, 11.25, NULL, NULL, '2026-05-31 18:21:56'),
(7, 12, 1, 19.90, 16.58, NULL, NULL, '2026-06-04 22:29:10'),
(8, 13, 1, 399.95, 333.29, NULL, NULL, '2026-06-05 15:20:24'),
(9, 14, 1, 45.90, 38.25, NULL, NULL, '2026-06-05 19:16:16'),
(10, 15, 1, 3.50, 2.92, NULL, NULL, '2026-06-06 14:47:19'),
(11, 16, 1, 12.00, 10.00, NULL, NULL, '2026-06-06 19:06:47'),
(12, 17, 1, 123.00, 102.50, NULL, NULL, '2026-06-06 20:03:29'),
(13, 18, 1, 125.00, 104.17, NULL, NULL, '2026-06-07 12:27:31'),
(14, 19, 1, 256.00, 213.33, NULL, NULL, '2026-06-07 12:31:59');

CREATE TABLE artikel_typen (
  id int(10) UNSIGNED NOT NULL,
  code varchar(50) NOT NULL,
  name varchar(100) NOT NULL,
  hat_varianten tinyint(1) NOT NULL DEFAULT 1,
  hat_lagerstand tinyint(1) NOT NULL DEFAULT 1,
  ist_download tinyint(1) NOT NULL DEFAULT 0,
  ist_set tinyint(1) NOT NULL DEFAULT 0,
  sortierung int(10) UNSIGNED NOT NULL DEFAULT 0,
  aktiv tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_typen (id, code, name, hat_varianten, hat_lagerstand, ist_download, ist_set, sortierung, aktiv) VALUES
(1, 'GARN', 'Garn', 1, 1, 0, 0, 1, 1),
(2, 'NADEL', 'Nadel', 1, 1, 0, 0, 2, 1),
(3, 'METERWARE', 'Meterware', 1, 1, 0, 0, 3, 1),
(4, 'DOWNLOAD', 'Download', 0, 0, 1, 0, 4, 1),
(5, 'SET', 'Set', 0, 1, 0, 1, 5, 1),
(6, 'STANDARD', 'Standard', 1, 1, 0, 0, 6, 1);

CREATE TABLE artikel_varianten (
  id int(10) UNSIGNED NOT NULL,
  artikel_id int(10) UNSIGNED NOT NULL,
  artikelnummer varchar(20) NOT NULL,
  gtin char(13) DEFAULT NULL,
  farbe_name varchar(50) DEFAULT NULL,
  farbe_hex varchar(7) DEFAULT NULL,
  bild_url text DEFAULT NULL,
  brutto_vk decimal(6,2) DEFAULT NULL,
  aktiv tinyint(1) DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  ist_auslaufartikel tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO artikel_varianten (id, artikel_id, artikelnummer, gtin, farbe_name, farbe_hex, bild_url, brutto_vk, aktiv, erstellt_am, geaendert_am, ist_auslaufartikel) VALUES
(1, 1, 'D-109901', '7071723011379', 'natur [Uni] (01)', '#F5F0E8', NULL, 5.30, 1, '2026-05-30 17:07:07', '2026-05-30 17:07:07', 0),
(2, 1, 'D-109902', '7071723011386', 'weizen [Mix] (02)', '#e8d5a3', NULL, 5.30, 1, '2026-05-30 17:07:07', '2026-06-07 17:43:04', 1),
(3, 1, 'D-109903', '7071723011393', 'perlgrau [Mix] (03)', '#C8C4BC', NULL, 5.30, 1, '2026-05-30 17:07:07', '2026-05-30 17:07:07', 0),
(4, 1, 'D-109906', '7071723011423', 'anthrazit [Mix] (06)', '#4a4a4a', NULL, 5.30, 1, '2026-05-30 17:07:07', '2026-06-07 21:01:29', 1),
(5, 1, 'D-109916', '7071723013922', 'blau [Uni] (16)', '#4A7CB5', NULL, 5.30, 1, '2026-05-30 17:07:07', '2026-05-30 17:07:07', 0),
(6, 1, 'D-1099xx', '1231231231231', 'TestfarbeNeu1', '#16f883', NULL, 8.00, 1, '2026-05-31 19:13:13', '2026-05-31 20:25:49', 0),
(7, 1, '4711', '1231231231231', 'TestfarbeNeu2', '#024cf7', NULL, 8.00, 1, '2026-05-31 19:13:55', '2026-05-31 19:13:55', 0),
(8, 1, '445', '1231231231231', 'TestfarbeNeu3', '#44f604', NULL, 6.99, 1, '2026-05-31 19:15:53', '2026-05-31 19:15:53', 0);

CREATE TABLE benutzer (
  id int(10) UNSIGNED NOT NULL,
  username varchar(255) NOT NULL,
  passwort varchar(255) NOT NULL,
  vorname varchar(255) DEFAULT NULL,
  nachname varchar(255) DEFAULT NULL,
  formularname varchar(255) NOT NULL,
  email varchar(255) DEFAULT NULL,
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO benutzer (id, username, passwort, vorname, nachname, formularname, email, aktiv, erstellt_am, geaendert_am) VALUES
(1, 'admin', '$2y$10$Apn.W3t.e9RPE/8B7I7JQungWu/6MyQDl70iwNOmgqLAUqld9BjR2', 'Admin', NULL, 'Administrator', 'indy1@gmx.at', 1, '2026-06-01 22:33:14', '2026-06-01 22:33:14'),
(2, 'system', '!', 'Jarvis', 'Worker', 'Jarvis', NULL, 0, '2026-06-07 17:04:17', '2026-06-07 19:41:03');

CREATE TABLE benutzer_rollen (
  benutzer_id int(10) UNSIGNED NOT NULL,
  rolle_id int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES
(1, 1);

CREATE TABLE berechtigungen (
  id int(10) UNSIGNED NOT NULL,
  name varchar(255) NOT NULL,
  beschreibung text DEFAULT NULL,
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO berechtigungen (id, name, beschreibung, aktiv, erstellt_am) VALUES
(1, 'artikel.anzeigen', 'artikel anzeigen', 1, '2026-06-01 22:27:22'),
(2, 'artikel.bearbeiten', 'artikel bearbeiten', 1, '2026-06-01 22:27:22'),
(3, 'artikel.anlegen', 'artikel anlegen', 1, '2026-06-01 22:27:22'),
(4, 'artikel.loeschen', 'artikel löschen', 1, '2026-06-01 22:27:22'),
(5, 'varianten.anzeigen', 'varianten anzeigen', 1, '2026-06-01 22:27:22'),
(6, 'varianten.bearbeiten', 'varianten bearbeiten', 1, '2026-06-01 22:27:22'),
(7, 'varianten.anlegen', 'varianten anlegen', 1, '2026-06-01 22:27:22'),
(8, 'varianten.loeschen', 'varianten löschen', 1, '2026-06-01 22:27:22'),
(9, 'lager.anzeigen', 'lager anzeigen', 1, '2026-06-01 22:27:22'),
(10, 'lager.bearbeiten', 'lager bearbeiten', 1, '2026-06-01 22:27:22'),
(11, 'lager.anlegen', 'lager anlegen', 1, '2026-06-01 22:27:22'),
(12, 'lager.loeschen', 'lager löschen', 1, '2026-06-01 22:27:22'),
(13, 'wareneingang.buchen', 'wareneingang buchen', 1, '2026-06-01 22:27:22'),
(14, 'wareneingang.bearbeiten', 'wareneingang bearbeiten', 1, '2026-06-01 22:27:22'),
(15, 'bestand.anzeigen', 'bestand anzeigen', 1, '2026-06-01 22:27:22'),
(16, 'bestand.bearbeiten', 'bestand bearbeiten', 1, '2026-06-01 22:27:22'),
(17, 'bestand.korrigieren', 'bestand korrigieren', 1, '2026-06-01 22:27:22'),
(18, 'bestand.loeschen', 'bestand löschen', 1, '2026-06-01 22:27:22'),
(19, 'lieferanten.anzeigen', 'lieferanten anzeigen', 1, '2026-06-01 22:27:22'),
(20, 'lieferanten.bearbeiten', 'lieferanten bearbeiten', 1, '2026-06-01 22:27:22'),
(21, 'lieferanten.anlegen', 'lieferanten anlegen', 1, '2026-06-01 22:27:22'),
(22, 'lieferanten.loeschen', 'lieferanten löschen', 1, '2026-06-01 22:27:22'),
(23, 'inventur.anzeigen', 'inventur anzeigen', 1, '2026-06-01 22:27:22'),
(24, 'inventur.bearbeiten', 'inventur bearbeiten', 1, '2026-06-01 22:27:22'),
(25, 'inventur.anlegen', 'inventur anlegen', 1, '2026-06-01 22:27:22'),
(26, 'inventur.loeschen', 'inventur löschen', 1, '2026-06-01 22:27:22'),
(27, 'inventurpositionen.anzeigen', 'inventurpositionen anzeigen', 1, '2026-06-01 22:27:22'),
(28, 'inventurpositionen.bearbeiten', 'inventurpositionen bearbeiten', 1, '2026-06-01 22:27:22'),
(29, 'inventurpositionen.anlegen', 'inventurpositionen anlegen', 1, '2026-06-01 22:27:22'),
(30, 'inventurpositionen.loeschen', 'inventurpositionen löschen', 1, '2026-06-01 22:27:22'),
(31, 'benutzer.anlegen', 'benutzer anlegen', 1, '2026-06-01 22:27:22'),
(32, 'benutzer.bearbeiten', 'benutzer bearbeiten', 1, '2026-06-01 22:27:22'),
(33, 'benutzer.loeschen', 'benutzer löschen', 1, '2026-06-01 22:27:22'),
(34, 'api.zugriff', 'API Zugriff', 1, '2026-06-01 22:27:22'),
(35, 'berichte.anzeigen', 'berichte anzeigen', 1, '2026-06-01 22:27:22'),
(36, 'berichte.bearbeiten', 'berichte bearbeiten', 1, '2026-06-01 22:27:22'),
(37, 'berichte.anlegen', 'berichte anlegen', 1, '2026-06-01 22:27:22'),
(38, 'berichte.loeschen', 'berichte löschen', 1, '2026-06-01 22:27:22'),
(39, 'berichte.drucken', 'berichte drucken', 1, '2026-06-01 22:27:22'),
(40, 'shopabgleich.starten', 'shopabgleich starten', 1, '2026-06-01 22:27:22'),
(41, 'shopabgleich.stoppen', 'shopabgleich stoppen', 1, '2026-06-01 22:27:22'),
(42, 'packplatz.starten', 'packplatz starten', 1, '2026-06-01 22:27:22'),
(43, 'packplatz.stoppen', 'packplatz stoppen', 1, '2026-06-01 22:27:22'),
(44, 'kasse.starten', 'kasse starten', 1, '2026-06-01 22:27:22'),
(45, 'kasse.stoppen', 'kasse stoppen', 1, '2026-06-01 22:27:22');

CREATE TABLE einheiten (
  id int(10) UNSIGNED NOT NULL,
  name varchar(100) NOT NULL,
  kuerzel varchar(10) DEFAULT NULL,
  sortierung int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO einheiten (id, name, kuerzel, sortierung) VALUES
(1, 'Knäuel', 'Kn', 0),
(2, 'Meter', 'm', 0),
(3, 'Gramm', 'g', 0),
(4, 'Stk', 'Stk', 0),
(5, 'Set', 'Set', 0);

CREATE TABLE hersteller (
  id int(10) UNSIGNED NOT NULL,
  name varchar(100) NOT NULL,
  webseite varchar(255) DEFAULT NULL,
  land varchar(50) DEFAULT NULL,
  notizen text DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO hersteller (id, name, webseite, land, notizen, erstellt_am) VALUES
(1, 'DROPS Design', 'www.garnstudio.com', 'NO', NULL, '2026-05-29 22:03:50'),
(2, 'Schachenmayr', 'www.schachenmayr.com', 'DE', NULL, '2026-05-29 22:03:50'),
(3, 'Lang Yarns', 'www.langyarns.com', 'CH', NULL, '2026-05-29 22:03:50');

CREATE TABLE kategorien (
  id int(10) UNSIGNED NOT NULL,
  parent_id int(10) UNSIGNED DEFAULT NULL,
  name varchar(100) NOT NULL,
  sortierung int(10) UNSIGNED NOT NULL DEFAULT 0,
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  externe_id varchar(100) DEFAULT NULL,
  datenquelle varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO kategorien (id, parent_id, name, sortierung, aktiv, externe_id, datenquelle) VALUES
(1, NULL, 'Wolle und Garne', 1, 1, NULL, NULL),
(2, NULL, 'Nadeln', 2, 1, NULL, NULL),
(3, NULL, 'Zubehör', 3, 1, NULL, NULL),
(4, NULL, 'Bücher und Anleitungen', 4, 1, NULL, NULL),
(5, NULL, 'Testkategorie', 0, 1, NULL, NULL);

CREATE TABLE kundengruppen (
  id int(10) UNSIGNED NOT NULL,
  name varchar(50) NOT NULL,
  rabatt_prozent decimal(4,2) DEFAULT NULL,
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  typ enum('endkunde','haendler','vertriebspartner','intern') NOT NULL DEFAULT 'endkunde'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO kundengruppen (id, name, rabatt_prozent, aktiv, erstellt_am, typ) VALUES
(1, 'Endkunden', 0.00, 1, '2026-05-30 18:10:03', 'endkunde'),
(2, 'Händler', 15.00, 1, '2026-05-30 18:10:03', 'endkunde'),
(3, 'Kleingewerblich-Künstler', 10.00, 1, '2026-05-30 18:10:03', 'endkunde'),
(4, 'Endkunden-Rechnung', 0.00, 1, '2026-05-30 18:10:03', 'endkunde');

CREATE TABLE lager (
  id int(10) UNSIGNED NOT NULL,
  name varchar(50) NOT NULL,
  typ enum('ladengeschaeft','messe','extern','lager') NOT NULL DEFAULT 'ladengeschaeft',
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO lager (id, name, typ, aktiv, erstellt_am) VALUES
(1, 'Ladengeschäft', 'ladengeschaeft', 1, '2026-05-30 18:54:27'),
(2, 'Messestand', 'messe', 1, '2026-05-30 18:54:27'),
(3, 'Privathaus-Keller', 'lager', 1, '2026-05-30 18:54:27');

CREATE TABLE lagerbestand (
  id int(10) UNSIGNED NOT NULL,
  artikel_varianten_id int(10) UNSIGNED DEFAULT NULL,
  lager_id int(10) UNSIGNED DEFAULT NULL,
  charge varchar(20) DEFAULT NULL,
  charge_status enum('erfasst','unbekannt','nachzutragen') DEFAULT 'unbekannt',
  bestand decimal(8,3) UNSIGNED DEFAULT NULL,
  mindestbestand int(10) UNSIGNED DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  artikel_id int(10) UNSIGNED DEFAULT NULL
) ;

INSERT INTO lagerbestand (id, artikel_varianten_id, lager_id, charge, charge_status, bestand, mindestbestand, erstellt_am, geaendert_am, artikel_id) VALUES
(1, 1, 1, '0444', 'erfasst', 12.000, 0, '2026-05-30 18:54:27', '2026-05-31 21:22:23', NULL),
(2, 2, 1, NULL, 'unbekannt', 0.000, 0, '2026-05-30 18:54:27', '2026-06-07 20:42:11', NULL),
(3, 1, 2, NULL, NULL, 9.000, 0, '2026-05-30 18:54:27', '2026-06-07 13:25:05', NULL),
(5, 7, 1, '9993', 'erfasst', 7.000, 0, '2026-06-04 22:27:32', '2026-06-05 10:45:33', NULL),
(7, NULL, 2, NULL, 'unbekannt', 5.000, 0, '2026-06-05 10:46:00', '2026-06-05 10:46:00', 9),
(8, 7, 1, NULL, NULL, 4.000, 0, '2026-06-05 19:14:29', '2026-06-05 19:14:29', NULL),
(9, NULL, 3, NULL, 'nachzutragen', 2.000, 0, '2026-06-05 19:16:53', '2026-06-05 19:17:52', 14),
(10, NULL, 3, '7768', 'erfasst', 4.000, 0, '2026-06-05 19:17:41', '2026-06-05 19:17:41', 14),
(11, NULL, 3, '00079', 'erfasst', 9.000, 0, '2026-06-05 19:17:52', '2026-06-05 19:17:52', 14),
(12, NULL, 2, NULL, NULL, 2.000, 0, '2026-06-06 20:51:37', '2026-06-06 20:51:37', 10),
(13, NULL, 2, NULL, NULL, 10.000, 0, '2026-06-07 13:26:37', '2026-06-07 13:26:37', 15),
(14, NULL, 2, NULL, 'nachzutragen', 15.000, 0, '2026-06-07 13:28:42', '2026-06-07 13:30:26', 13),
(15, NULL, 3, '0444', 'erfasst', 3.000, 0, '2026-06-07 13:36:57', '2026-06-07 13:36:57', 17),
(16, 2, 3, '991177', 'erfasst', 5.000, 0, '2026-06-07 20:42:58', '2026-06-07 20:42:58', NULL),
(17, 4, 3, '99887711', 'erfasst', 5.000, 0, '2026-06-07 21:01:29', '2026-06-07 21:01:29', NULL);

CREATE TABLE lager_bewegungen (
  id int(10) UNSIGNED NOT NULL,
  artikel_varianten_id int(10) UNSIGNED DEFAULT NULL,
  lager_id int(10) UNSIGNED NOT NULL,
  lieferant_id int(10) UNSIGNED DEFAULT NULL,
  ek_preis decimal(10,4) DEFAULT NULL,
  charge varchar(20) DEFAULT NULL,
  bewegungstyp enum('eingang','ausgang','korrektur','inventur') DEFAULT NULL,
  menge decimal(8,3) NOT NULL,
  bestand_vorher decimal(8,3) NOT NULL,
  bestand_nachher decimal(8,3) NOT NULL,
  referenz varchar(100) DEFAULT NULL,
  notiz text DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  artikel_id int(10) UNSIGNED DEFAULT NULL,
  benutzer_id int(10) UNSIGNED DEFAULT NULL
) ;

INSERT INTO lager_bewegungen (id, artikel_varianten_id, lager_id, lieferant_id, ek_preis, charge, bewegungstyp, menge, bestand_vorher, bestand_nachher, referenz, notiz, erstellt_am, artikel_id, benutzer_id) VALUES
(1, 1, 1, NULL, NULL, '0444', 'eingang', 5.000, 7.000, 12.000, NULL, NULL, '2026-05-31 21:22:23', NULL, NULL),
(2, 7, 1, NULL, NULL, '4711', 'eingang', 5.000, 0.000, 5.000, NULL, 'testnotiz', '2026-06-04 22:27:32', NULL, NULL),
(3, 7, 1, NULL, NULL, '9993', 'eingang', 2.000, 5.000, 7.000, NULL, NULL, '2026-06-05 10:45:33', NULL, NULL),
(4, NULL, 2, NULL, NULL, NULL, 'eingang', 5.000, 0.000, 5.000, NULL, NULL, '2026-06-05 10:46:00', 9, NULL),
(5, 7, 1, NULL, NULL, NULL, 'eingang', 4.000, 0.000, 4.000, NULL, NULL, '2026-06-05 19:14:29', NULL, NULL),
(6, NULL, 3, NULL, NULL, NULL, 'eingang', 15.000, 0.000, 15.000, NULL, NULL, '2026-06-05 19:16:53', 14, NULL),
(7, NULL, 3, NULL, NULL, '7768', 'korrektur', 4.000, 15.000, 11.000, NULL, 'Charge nachgetragen', '2026-06-05 19:17:41', 14, NULL),
(8, NULL, 3, NULL, NULL, '00079', 'korrektur', 9.000, 11.000, 2.000, NULL, 'Charge nachgetragen', '2026-06-05 19:17:52', 14, NULL),
(9, NULL, 2, NULL, NULL, NULL, 'eingang', 2.000, 0.000, 2.000, NULL, NULL, '2026-06-06 20:51:37', 10, 1),
(10, 1, 2, 1, 3.2000, NULL, 'eingang', 5.000, 4.000, 9.000, NULL, NULL, '2026-06-07 13:25:05', NULL, 1),
(11, NULL, 2, 1, 5.5000, NULL, 'eingang', 10.000, 0.000, 10.000, NULL, NULL, '2026-06-07 13:26:37', 15, 1),
(12, NULL, 2, 1, 6.9000, NULL, 'eingang', 10.000, 0.000, 10.000, 'test', 'testbuchung', '2026-06-07 13:28:42', 13, 1),
(13, NULL, 2, 1, 5.9000, NULL, 'eingang', 5.000, 10.000, 15.000, 'test', 'test', '2026-06-07 13:30:26', 13, 1),
(14, NULL, 3, 2, 55.9000, NULL, 'eingang', 3.000, 0.000, 3.000, 'test', 'eingangstest', '2026-06-07 13:36:57', 17, 1),
(15, 2, 3, 1, 5.9000, NULL, 'eingang', 5.000, 0.000, 5.000, NULL, NULL, '2026-06-07 20:42:58', NULL, 1),
(16, 4, 3, 1, 5.9000, NULL, 'eingang', 5.000, 0.000, 5.000, 'test', 'Artikel durch Jarvis aktivieren lassen', '2026-06-07 21:01:29', NULL, 1);

CREATE TABLE lieferanten (
  id int(10) UNSIGNED NOT NULL,
  name varchar(255) NOT NULL,
  land char(2) NOT NULL,
  website varchar(255) DEFAULT NULL,
  email varchar(255) DEFAULT NULL,
  telefon varchar(255) DEFAULT NULL,
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO lieferanten (id, name, land, website, email, telefon, aktiv, erstellt_am, geaendert_am) VALUES
(1, 'DROPS Design A/S', 'NO', NULL, 'info@garnstudio.com', NULL, 1, '2026-05-30 21:10:44', '2026-06-04 22:25:59'),
(2, 'Schachenmayr', 'DE', NULL, 'info@schachenmayr.com', NULL, 1, '2026-05-30 21:10:44', NULL);

CREATE TABLE lieferanten_vertreter (
  id int(10) UNSIGNED NOT NULL,
  lieferant_id int(10) UNSIGNED NOT NULL,
  vorname varchar(255) DEFAULT NULL,
  nachname varchar(255) DEFAULT NULL,
  telefon varchar(255) DEFAULT NULL,
  email varchar(255) DEFAULT NULL,
  mobil varchar(255) DEFAULT NULL,
  notizen text DEFAULT NULL,
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp(),
  geaendert_am timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO lieferanten_vertreter (id, lieferant_id, vorname, nachname, telefon, email, mobil, notizen, aktiv, erstellt_am, geaendert_am) VALUES
(1, 1, 'Lars', 'Hansen', '+47123456789', NULL, NULL, 'Kommt jeden ersten Dienstag', 1, '2026-05-30 21:10:44', '2026-05-30 21:10:44'),
(2, 1, 'Anna', 'Berg', '+47987654321', NULL, NULL, 'Zuständig für Österreich', 1, '2026-05-30 21:10:44', '2026-05-30 21:10:44'),
(3, 2, 'Karl', 'Indra', '06764538267', 'indy1@gmx.at', '669054445212211', '                                das soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werdendas soll ein langer Text werden3333', 0, '2026-06-06 13:30:31', '2026-06-06 14:32:25'),
(4, 2, 'Karl', 'Indra', '06764538267', 'karl.indra@mealana.at', NULL, '                zweiter test zum löschen\r\n', 0, '2026-06-06 13:31:09', '2026-06-06 13:31:13'),
(5, 2, 'Karl', 'Indra', '06764538267', 'karl.indra@mealana.at', '669054445212211', '                zweiter Text zum löschen', 0, '2026-06-06 13:33:21', '2026-06-06 13:33:26'),
(6, 2, 'Karl', 'Indra', '06764538267', 'karl.indra@mealana.at', '669054445212211', '                                zweiter test 2', 0, '2026-06-06 13:34:24', '2026-06-06 13:34:38'),
(7, 2, 'Karl', 'Indra', '06764538267', 'karl.indra@mealana.at', '669054445212211', '                                                22334455', 0, '2026-06-06 13:36:08', '2026-06-06 14:22:12'),
(8, 2, 'Karl', 'Indra', '06764538267', 'indy1@gmx.at', '669054445212211', 'testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttesttest', 0, '2026-06-06 14:35:35', '2026-06-06 14:35:41');

CREATE TABLE merkmale (
  id int(10) UNSIGNED NOT NULL,
  merkmal_gruppen_id int(10) UNSIGNED DEFAULT NULL,
  name varchar(50) NOT NULL,
  einheit varchar(50) NOT NULL,
  datentyp enum('text','zahl','bool') DEFAULT NULL,
  filterbar tinyint(1) DEFAULT NULL,
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO merkmale (id, merkmal_gruppen_id, name, einheit, datentyp, filterbar, aktiv, erstellt_am) VALUES
(1, 1, 'Gewicht/Länge', 'g/m', 'text', 0, 1, '2026-05-30 20:12:39'),
(2, 1, 'Zusammensetzung', '', 'text', 0, 1, '2026-05-30 20:12:39'),
(3, 1, 'Garngruppe', '', 'text', 1, 1, '2026-05-30 20:12:39'),
(4, 2, 'Nadelstärke von', 'mm', 'zahl', 1, 1, '2026-05-30 20:12:39'),
(5, 2, 'Nadelstärke bis', 'mm', 'zahl', 1, 1, '2026-05-30 20:12:39'),
(6, 2, 'Maschenprobe', '', 'text', 0, 1, '2026-05-30 20:12:39');

CREATE TABLE merkmal_gruppen (
  id int(10) UNSIGNED NOT NULL,
  name varchar(50) NOT NULL,
  aktiv tinyint(1) DEFAULT NULL,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO merkmal_gruppen (id, name, aktiv, erstellt_am) VALUES
(1, 'Garninfo', 1, '2026-05-30 20:12:39'),
(2, 'Verarbeitung', 1, '2026-05-30 20:12:39'),
(3, 'Pflege', 1, '2026-05-30 20:12:39');

CREATE TABLE rollen (
  id int(10) UNSIGNED NOT NULL,
  name varchar(255) NOT NULL,
  beschreibung text DEFAULT NULL,
  aktiv tinyint(1) NOT NULL DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO rollen (id, name, beschreibung, aktiv, erstellt_am) VALUES
(1, 'superadmin', 'Zugriff auf Alles + API-Zugriff + Benutzerverwaltung', 1, '2026-06-01 21:47:17'),
(2, 'admin', 'Administrator Zugang zu Artikel, Lager, Lieferanten, Berichte', 1, '2026-06-01 21:47:17'),
(3, 'mitarbeiter', 'Lager, Kasse, Packplatz', 1, '2026-06-01 21:47:17');

CREATE TABLE rollen_berechtigungen (
  rolle_id int(10) UNSIGNED NOT NULL,
  berechtigung_id int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(1, 45),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(2, 22),
(2, 23),
(2, 24),
(2, 25),
(2, 26),
(2, 27),
(2, 28),
(2, 29),
(2, 30),
(2, 31),
(2, 32),
(2, 35),
(2, 36),
(2, 37),
(2, 38),
(2, 39),
(2, 42),
(2, 43),
(2, 44),
(2, 45),
(3, 1),
(3, 5),
(3, 9),
(3, 13),
(3, 15),
(3, 17),
(3, 19),
(3, 23),
(3, 27),
(3, 35),
(3, 39),
(3, 42),
(3, 43),
(3, 44),
(3, 45);

CREATE TABLE sessions (
  id varchar(128) NOT NULL,
  benutzer_id int(10) UNSIGNED NOT NULL,
  ip_adresse varchar(45) DEFAULT NULL,
  user_agent varchar(255) DEFAULT NULL,
  letzte_aktivitaet timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE steuerklassen (
  id int(10) UNSIGNED NOT NULL,
  name varchar(50) NOT NULL,
  satz decimal(5,2) NOT NULL,
  land char(2) NOT NULL DEFAULT 'AT',
  aktiv tinyint(1) DEFAULT 1,
  erstellt_am timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO steuerklassen (id, name, satz, land, aktiv, erstellt_am) VALUES
(1, 'Normaler Steuersatz', 20.00, 'AT', 1, '2026-05-29 22:03:50'),
(2, 'Ermäßigter Steuersatz', 10.00, 'AT', 1, '2026-05-29 22:03:50'),
(3, 'Steuerfrei', 0.00, 'AT', 1, '2026-05-29 22:03:50');


ALTER TABLE aktivitaeten
  ADD PRIMARY KEY (id),
  ADD KEY fk_aktivitaeten_benutzer (benutzer_id);

ALTER TABLE artikel
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY artikelnummer (artikelnummer),
  ADD KEY fk_artikel_hersteller (hersteller_id),
  ADD KEY fk_artikel_steuerklasse (steuerklasse_id),
  ADD KEY fk_artikel_artikeltyp (artikeltyp_id),
  ADD KEY fk_artikel_einheitId (einheit_id);

ALTER TABLE artikel_codes
  ADD PRIMARY KEY (id),
  ADD KEY fk_artikel_codes (artikel_id);

ALTER TABLE artikel_externe_referenzen
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uq_datenquelle_externe_id (datenquelle,externe_id),
  ADD KEY fk_aer_artikel_id (artikel_id);

ALTER TABLE artikel_kategorien
  ADD PRIMARY KEY (artikel_id,kategorie_id),
  ADD KEY fk_ak_kategorie_id (kategorie_id);

ALTER TABLE artikel_lieferanten
  ADD PRIMARY KEY (id),
  ADD KEY fk_artlief_artikel_id (artikel_id),
  ADD KEY fk_artlief_lieferant_id (lieferant_id);

ALTER TABLE artikel_merkmale
  ADD PRIMARY KEY (id),
  ADD KEY fk_artikel_id (artikel_id),
  ADD KEY fk_merkmal_id (merkmal_id);

ALTER TABLE artikel_preise
  ADD PRIMARY KEY (id),
  ADD KEY fk_preise_artikel (artikel_id),
  ADD KEY fk_kundengruppen (kundengruppen_id);

ALTER TABLE artikel_typen
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY code (code);

ALTER TABLE artikel_varianten
  ADD PRIMARY KEY (id),
  ADD KEY fk_varianten_artikel (artikel_id);

ALTER TABLE benutzer
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY username (username),
  ADD UNIQUE KEY email (email);

ALTER TABLE benutzer_rollen
  ADD PRIMARY KEY (rolle_id,benutzer_id),
  ADD KEY fk_benrol_benutzer (benutzer_id);

ALTER TABLE berechtigungen
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY name (name);

ALTER TABLE einheiten
  ADD PRIMARY KEY (id);

ALTER TABLE hersteller
  ADD PRIMARY KEY (id);

ALTER TABLE kategorien
  ADD PRIMARY KEY (id),
  ADD KEY fk_kat_parent_id (parent_id);

ALTER TABLE kundengruppen
  ADD PRIMARY KEY (id);

ALTER TABLE lager
  ADD PRIMARY KEY (id);

ALTER TABLE lagerbestand
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uk_variante_lager_charge (artikel_varianten_id,lager_id,charge),
  ADD UNIQUE KEY uq_lb_artikel_lager_charge (artikel_id,lager_id,charge),
  ADD KEY fk_lager_id (lager_id);

ALTER TABLE lager_bewegungen
  ADD PRIMARY KEY (id),
  ADD KEY fk_lager_bewegungen_artikel_varianten_id (artikel_varianten_id),
  ADD KEY fk_lager_bewegungen_lager_id (lager_id),
  ADD KEY fk_lbew_artikel_id (artikel_id),
  ADD KEY fk_lbew_benutzerId (benutzer_id),
  ADD KEY fk_lbew_lieferantId (lieferant_id);

ALTER TABLE lieferanten
  ADD PRIMARY KEY (id);

ALTER TABLE lieferanten_vertreter
  ADD PRIMARY KEY (id),
  ADD KEY fk_vertreter_lieferant_id (lieferant_id);

ALTER TABLE merkmale
  ADD PRIMARY KEY (id),
  ADD KEY fk_merkmal_gruppen_id (merkmal_gruppen_id);

ALTER TABLE merkmal_gruppen
  ADD PRIMARY KEY (id);

ALTER TABLE rollen
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY name (name);

ALTER TABLE rollen_berechtigungen
  ADD PRIMARY KEY (rolle_id,berechtigung_id),
  ADD KEY fk_rollber_berechtigung (berechtigung_id);

ALTER TABLE sessions
  ADD PRIMARY KEY (id),
  ADD KEY fk_sessions_benutzer (benutzer_id);

ALTER TABLE steuerklassen
  ADD PRIMARY KEY (id);


ALTER TABLE aktivitaeten
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_codes
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_externe_referenzen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_lieferanten
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_merkmale
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_preise
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_typen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE artikel_varianten
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE benutzer
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE berechtigungen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE einheiten
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE hersteller
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE kategorien
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE kundengruppen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE lager
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE lagerbestand
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE lager_bewegungen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE lieferanten
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE lieferanten_vertreter
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE merkmale
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE merkmal_gruppen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE rollen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE steuerklassen
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE aktivitaeten
  ADD CONSTRAINT fk_aktivitaeten_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON UPDATE CASCADE;

ALTER TABLE artikel
  ADD CONSTRAINT fk_artikel_artikeltyp FOREIGN KEY (artikeltyp_id) REFERENCES artikel_typen (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_artikel_einheitId FOREIGN KEY (einheit_id) REFERENCES einheiten (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_artikel_hersteller FOREIGN KEY (hersteller_id) REFERENCES hersteller (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_artikel_steuerklasse FOREIGN KEY (steuerklasse_id) REFERENCES steuerklassen (id) ON UPDATE CASCADE;

ALTER TABLE artikel_codes
  ADD CONSTRAINT fk_artikel_codes FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE;

ALTER TABLE artikel_externe_referenzen
  ADD CONSTRAINT fk_aer_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE;

ALTER TABLE artikel_kategorien
  ADD CONSTRAINT fk_ak_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_ak_kategorie_id FOREIGN KEY (kategorie_id) REFERENCES kategorien (id) ON UPDATE CASCADE;

ALTER TABLE artikel_lieferanten
  ADD CONSTRAINT fk_artlief_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_artlief_lieferant_id FOREIGN KEY (lieferant_id) REFERENCES lieferanten (id) ON UPDATE CASCADE;

ALTER TABLE artikel_merkmale
  ADD CONSTRAINT fk_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_merkmal_id FOREIGN KEY (merkmal_id) REFERENCES merkmale (id) ON UPDATE CASCADE;

ALTER TABLE artikel_preise
  ADD CONSTRAINT fk_kundengruppen FOREIGN KEY (kundengruppen_id) REFERENCES kundengruppen (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_preise_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE;

ALTER TABLE artikel_varianten
  ADD CONSTRAINT fk_varianten_artikel FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE;

ALTER TABLE benutzer_rollen
  ADD CONSTRAINT fk_benrol_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_benrol_rolle FOREIGN KEY (rolle_id) REFERENCES rollen (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE kategorien
  ADD CONSTRAINT fk_kat_parent_id FOREIGN KEY (parent_id) REFERENCES kategorien (id) ON UPDATE CASCADE;

ALTER TABLE lagerbestand
  ADD CONSTRAINT fk_artikel_varianten FOREIGN KEY (artikel_varianten_id) REFERENCES artikel_varianten (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lager_id FOREIGN KEY (lager_id) REFERENCES lager (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lb_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE;

ALTER TABLE lager_bewegungen
  ADD CONSTRAINT fk_lager_bewegungen_artikel_varianten_id FOREIGN KEY (artikel_varianten_id) REFERENCES artikel_varianten (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lager_bewegungen_lager_id FOREIGN KEY (lager_id) REFERENCES lager (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lbew_artikel_id FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lbew_benutzerId FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_lbew_lieferantId FOREIGN KEY (lieferant_id) REFERENCES lieferanten (id) ON UPDATE CASCADE;

ALTER TABLE lieferanten_vertreter
  ADD CONSTRAINT fk_vertreter_lieferant_id FOREIGN KEY (lieferant_id) REFERENCES lieferanten (id) ON UPDATE CASCADE;

ALTER TABLE merkmale
  ADD CONSTRAINT fk_merkmal_gruppen_id FOREIGN KEY (merkmal_gruppen_id) REFERENCES merkmal_gruppen (id) ON UPDATE CASCADE;

ALTER TABLE rollen_berechtigungen
  ADD CONSTRAINT fk_rollber_berechtigung FOREIGN KEY (berechtigung_id) REFERENCES berechtigungen (id) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rollber_rolle FOREIGN KEY (rolle_id) REFERENCES rollen (id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE sessions
  ADD CONSTRAINT fk_sessions_benutzer FOREIGN KEY (benutzer_id) REFERENCES benutzer (id) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
