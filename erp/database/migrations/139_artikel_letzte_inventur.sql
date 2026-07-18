-- Migration 139: "Letzte Inventur"-Datum am Artikel
-- Wird beim Inventur-Abschluss gesetzt, sobald mindestens eine Position dieses
-- Artikels in einem Lager gezählt wurde — unabhängig davon ob sich der Bestand
-- geändert hat oder nicht (aktiviert auch den seit Langem vorbereiteten
-- Spalten-Picker-Platzhalter "letzte_inventur" in der Artikelliste).

ALTER TABLE artikel
    ADD COLUMN letzte_inventur_am DATE NULL AFTER meldebestand;
