-- Migration 164: JTL-Kunden+Aufträge-Archiv-Import
--
-- jtl_kundennummer: Dedup-Schlüssel für den Kunden-Import (Format "Kd-XXXX" aus JTL) --
-- ein wiederholter Import-Lauf erkennt daran bereits vorhandene Kunden und überspringt sie.
--
-- kanal='jtl_archiv': eigener Auftrags-Kanal für importierte JTL-Altaufträge, klar
-- getrennt von echten neuen ERP-Aufträgen (woocommerce/manuell/kasse).

ALTER TABLE kunden
    ADD COLUMN jtl_kundennummer VARCHAR(20) NULL UNIQUE AFTER kundennummer;

ALTER TABLE kunden
    MODIFY COLUMN kundenherkunft ENUM('shop','messe','empfehlung','walkin','kasse','erp','jtl_archiv')
        NOT NULL DEFAULT 'erp';

ALTER TABLE auftraege
    MODIFY COLUMN kanal ENUM('woocommerce','manuell','kasse','jtl_archiv') NOT NULL DEFAULT 'manuell';
