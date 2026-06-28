-- Migration 091: Neue Lieferstatus-Werte: abholbereit + kommissioniert
ALTER TABLE auftraege MODIFY COLUMN lieferstatus
    ENUM('neu','in_bearbeitung','versandbereit','teilgeliefert',
         'zurueckgestellt','versendet','abgeschlossen','storniert',
         'abholbereit','kommissioniert')
    NOT NULL DEFAULT 'neu';
