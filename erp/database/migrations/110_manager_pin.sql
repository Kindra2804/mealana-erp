-- Manager-Override: kurzer PIN für Manager+ (Rang >= 70), damit an Kasse/Packplatz
-- eine Geldgeschäft-Freigabe ohne vollen Login möglich ist (siehe project_rechte_rollen.md).
-- Gespeichert als bcrypt-Hash, niemals im Klartext.
ALTER TABLE benutzer ADD COLUMN manager_pin_hash VARCHAR(255) NULL AFTER passwort;
