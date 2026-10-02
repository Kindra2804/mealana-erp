-- Herkunftsland "NO" bei DROPS-Artikeln entfernen (Jacky 2026-10-02): stammt aus dem alten
-- Demo-Import-Skript (gen_demo_artikel.ps1), nicht aus echten Angaben, und wurde über die
-- Vater→Kind-Vererbung auf alle Varianten verteilt. Falsch ist schlimmer als leer — das
-- richtige Ursprungsland wird später (vor dem Live-Gang) mit Babsi gepflegt.
UPDATE artikel a
JOIN hersteller h ON h.id = a.hersteller_id
SET a.herkunftsland = NULL
WHERE a.herkunftsland = 'NO'
  AND h.name = 'DROPS Design';
