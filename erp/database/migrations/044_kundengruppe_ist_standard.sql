ALTER TABLE kundengruppen
    CHANGE COLUMN rabatt_prozent ist_standard TINYINT(1) NOT NULL DEFAULT 0;

UPDATE kundengruppen SET ist_standard = 0;
UPDATE kundengruppen SET ist_standard = 1 WHERE id = 1;
