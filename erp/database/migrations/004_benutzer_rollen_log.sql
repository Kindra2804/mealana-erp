CREATE TABLE benutzer (
    id INT UNSIGNED AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    passwort VARCHAR(255) NOT NULL,
    vorname VARCHAR(255),
    nachname VARCHAR(255),
    formularname VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    aktiv TINYINT(1) NOT NULL DEFAULT 1 ,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    geaendert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE rollen (
    id INT UNSIGNED AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL UNIQUE, 
    beschreibung TEXT,
    aktiv TINYINT(1)NOT NULL DEFAULT 1, 
    erstellt_am  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE berechtigungen (
    id INT UNSIGNED AUTO_INCREMENT, 
    name VARCHAR(255) NOT NULL UNIQUE, 
    beschreibung TEXT,
    aktiv TINYINT(1) NOT NULL DEFAULT 1, 
    erstellt_am  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE rollen_berechtigungen (
    rolle_id INT UNSIGNED NOT NULL, 
    berechtigung_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (rolle_id, berechtigung_id),
    
    CONSTRAINT fk_rollber_rolle
        FOREIGN KEY (rolle_id) REFERENCES rollen(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_rollber_berechtigung
        FOREIGN KEY (berechtigung_id) REFERENCES berechtigungen(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE benutzer_rollen (
    benutzer_id INT UNSIGNED NOT NULL, 
    rolle_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (rolle_id, benutzer_id),
    
     
    CONSTRAINT fk_benrol_rolle
        FOREIGN KEY (rolle_id) REFERENCES rollen(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_benrol_benutzer
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE aktivitaeten (
    id INT UNSIGNED AUTO_INCREMENT,
    benutzer_id INT UNSIGNED NOT NULL, 
    aktion VARCHAR(255) NOT NULL,
    referenz_tabelle VARCHAR(50),
    referenz_id INT UNSIGNED,
    details JSON,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    
    CONSTRAINT fk_aktivitaeten_benutzer
    FOREIGN KEY (benutzer_id)
    REFERENCES benutzer(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE TABLE sessions (
    id VARCHAR(128),
    benutzer_id INT UNSIGNED NOT NULL, 
    ip_adresse VARCHAR(45),
    user_agent VARCHAR(255),
    letzte_aktivitaet TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    
    CONSTRAINT fk_sessions_benutzer
    FOREIGN KEY (benutzer_id)
    REFERENCES benutzer(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);