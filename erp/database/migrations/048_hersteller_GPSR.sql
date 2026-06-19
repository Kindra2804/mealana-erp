-- migration der Hersteller-Tabelle für erforderliche Daten wegen GPSR
-- besser haben und nich befüllen, als gar nicht haben !
-- eventuell sogar Shop-Vorgabe in Zukunft

ALTER TABLE hersteller
  -- Herstelleradresse
  ADD COLUMN strasse      varchar(255)  DEFAULT NULL AFTER land,
  ADD COLUMN plz          varchar(20)   DEFAULT NULL AFTER strasse,
  ADD COLUMN ort          varchar(100)  DEFAULT NULL AFTER plz,
  -- Hersteller E-Mail (GPSR Pflicht)
  ADD COLUMN email        varchar(255)  DEFAULT NULL AFTER ort,
  -- Handelsmarke (optional, für GPSR)
  ADD COLUMN handelsname  varchar(100)  DEFAULT NULL AFTER name,
  -- Logo für Shop später
  ADD COLUMN logo_pfad    varchar(255)  DEFAULT NULL AFTER email,

  -- REO (wenn nötig)
  ADD COLUMN reo_name     varchar(255)  DEFAULT NULL,
  ADD COLUMN reo_strasse  varchar(255)  DEFAULT NULL,
  ADD COLUMN reo_plz      varchar(20)   DEFAULT NULL,
  ADD COLUMN reo_ort      varchar(100)  DEFAULT NULL,
  ADD COLUMN reo_land     char(2)       DEFAULT NULL,
  ADD COLUMN reo_email    varchar(255)  DEFAULT NULL,
  -- zuletzt bearbeitet
  ADD COLUMN aktualisiert_am timestamp NULL DEFAULT NULL ON UPDATE current_timestamp();