-- 1. Varianten: neuen Key anlegen + alten entfernen (ein Statement!)
ALTER TABLE lagerbestand 
    ADD UNIQUE KEY uk_variante_lager_charge (artikel_varianten_id, lager_id, charge),
    DROP INDEX uk_variante_lager;

-- 2. Standalone: gleiche Technik
ALTER TABLE lagerbestand 
    ADD UNIQUE KEY uq_lb_artikel_lager_charge (artikel_id, lager_id, charge),
    DROP INDEX uq_lb_artikel_variante;
