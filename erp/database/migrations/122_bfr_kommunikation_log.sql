-- Rohdaten-Protokoll jedes BFR-Aufrufs (/state + /register): exaktes Request-XML und
-- exakte Antwort, inkl. Curl-Fehlertext und Dauer. Bisher wurde nur das GEPARSTE Ergebnis
-- (Signatur/QR) bzw. die Ausfall-Episode (Zeitstempel/Typ) gespeichert — für eine präzise
-- Fehlermeldung an den BFR-Hersteller reicht das nicht, es braucht die exakten Bytes.
CREATE TABLE bfr_kommunikation_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kasse_id       INT UNSIGNED DEFAULT NULL,
    endpunkt       VARCHAR(20) NOT NULL,   -- 'state' oder 'register'
    request_body   TEXT DEFAULT NULL,      -- NULL bei GET (/state hat keinen Body)
    response_body  TEXT DEFAULT NULL,      -- NULL wenn gar keine Antwort ankam
    curl_fehler    VARCHAR(255) DEFAULT NULL,
    dauer_ms       INT UNSIGNED DEFAULT NULL,
    erstellt_am    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bkl_kasse (kasse_id),
    KEY idx_bkl_erstellt (erstellt_am),
    CONSTRAINT fk_bkl_kasse FOREIGN KEY (kasse_id) REFERENCES kassen(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
