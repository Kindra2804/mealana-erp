-- PLC Item-IDs als konfigurierbare Einstellungen (bisher hardcoded in EasyPakExporter)
INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES
('plc_item_at',            '430101'),
('plc_item_at_express',    '430107'),
('plc_item_eu',            '430106'),
('plc_item_international', '430104');
