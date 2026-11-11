-- Artikelgruppen (Erlöskonten) der MEALANA KG -- für eine frisch aufgesetzte 1.0-Datenbank.
-- Die 1.0-Baseline bringt nur die Sondergruppen mit (Messe 4065, Workshops 4070, Partnerware,
-- Mahngebühren 4890, Fachmiete 4800). Die Warengruppen sind je Firma verschieden und fehlen daher.
-- Stand = Dev-DB 2026-11-11. INSERT IGNORE: bereits vorhandene Kontonummern bleiben unverändert.
-- "Gutscheine" und "Versandkosten" werden vom Buchhaltungs-Export über den NAMEN gesucht -> Namen nicht ändern.
SET NAMES utf8mb4;

INSERT IGNORE INTO artikel_gruppen (konto_nr, name, aktiv, an_kasse_waehlbar, standard_steuer_prozent, sortierung) VALUES
('4000', 'Wolle',                    1, 1, NULL,  10),
('4010', 'Nadeln',                   1, 1, NULL,  20),
('4020', 'Stoffe Nähen',             1, 1, NULL,  30),
('4025', 'Knöpfe',                   1, 1, NULL,  40),
('4030', 'Sticken',                  1, 1, NULL,  50),
('4035', 'Spinnen/Weben',            1, 1, NULL,  60),
('4040', 'Sonstiges Zubehör',        1, 1, NULL,  70),
('4050', 'Hefte Bücher Anleitungen', 1, 1, 10.00, 80),
('4060', 'Verkaufshilfen',           1, 1, NULL,  90),
('3230', 'Gutscheine',               1, 0, NULL, 100),
('4090', 'Versandkosten',            1, 0, NULL, 110);

SELECT konto_nr, name, an_kasse_waehlbar, standard_steuer_prozent FROM artikel_gruppen ORDER BY sortierung, konto_nr;
