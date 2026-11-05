-- 228: Ungeklärte Widerrufe je Shop (Jacky 2026-11-05)
-- Widerruft ein Gast über die Seite „Vertrag widerrufen“ mit falscher/fehlender Bestellnummer oder
-- anderer E-Mail, speichert Germanized den Antrag OHNE Bestellung -- die Bestellung ändert sich nicht,
-- der normale Abgleich sieht nichts. Der Cron fragt darum je Shop die offenen Anträge beim Plugin
-- NAHTLOS Connector (≥ 1.6.0, GET nahtlos/v1/widerrufe/offen) ab und merkt sich die Zahl der
-- ungeklärten hier (Badge Verkauf → Widerrufe, Dashboard). Zugeordnet/abgelehnt wird in Verkauf → Widerrufe.

ALTER TABLE shops
    ADD COLUMN widerrufe_ungeklaert INT NOT NULL DEFAULT 0 AFTER bestellungen_letzter_sync;
