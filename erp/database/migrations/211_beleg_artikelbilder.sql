-- Migration 211: Artikelbilder auf Belegen und Mails (Jacky 2026-10-28)
--
-- Je Belegtyp einschaltbar unter Einstellungen → System → "Artikelbilder auf Belegen"
-- (siehe src/modules/dokumente/BelegBilder.php). Feste Bildgröße, Vorschaubilder werden
-- in storage/bild_vorschau/ zwischengespeichert (nicht im Git).
-- Standard: Rechnung, Lieferschein, Pickliste und Auftragsbestätigungs-Mail an.

INSERT INTO system_einstellungen (schluessel, wert) VALUES
    ('beleg_bilder_rechnung', '1'),
    ('beleg_bilder_gutschrift', '0'),
    ('beleg_bilder_auftragsbestaetigung', '0'),
    ('beleg_bilder_lieferschein', '1'),
    ('beleg_bilder_pickliste', '1'),
    ('beleg_bilder_abholzettel', '0'),
    ('beleg_bilder_kassenbon_a4', '0'),
    ('beleg_bilder_mail_auftragsbestaetigung', '1'),
    ('beleg_bilder_mail_abholung', '0'),
    ('beleg_bilder_mail_versand', '0')
ON DUPLICATE KEY UPDATE wert = wert;
