-- Migration 054: Partner-Typ um 'mietfach' erweitern
--
-- Neu: 'mietfach' = Fachmieter mit Fremdrechnung (Quittungsblock des Fachieters)
-- Bleibt: 'kommission' = eigene Provision/Abrechnung, MeaLana stellt Rechnung
--          'spende'     = Yarnpride-Modell, reiner Durchlaufposten
--          'beides'     = Sonderfälle

ALTER TABLE partner
    MODIFY typ ENUM('mietfach','kommission','spende','beides') NOT NULL DEFAULT 'mietfach';
