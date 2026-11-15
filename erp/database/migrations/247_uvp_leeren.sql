-- 247: UVP leeren (Jacky 2026-11-15)
-- Bis 2026-11-15 bekam jeder neu angelegte Artikel automatisch UVP = Verkaufspreis, dazu kamen UVPs aus
-- dem JTL-Import. Das waren keine echten Preisempfehlungen der Hersteller. Seit 1.0.15 nutzt der Shop-Sync
-- die UVP nicht mehr (regular_price = Normalpreis), sie ist also nirgends mehr wirksam.
-- Das Feld bleibt (für Kunden mit echten UVPs) und wird ab jetzt nur noch von Hand gesetzt.
-- Läuft auf jedem Stand gefahrlos (bei frischen Installationen gibt es noch keine Artikel).
UPDATE artikel SET uvp = NULL WHERE uvp IS NOT NULL;
