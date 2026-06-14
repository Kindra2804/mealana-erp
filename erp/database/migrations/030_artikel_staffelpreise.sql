CREATE TABLE artikel_staffelpreise (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artikel_id INT UNSIGNED NOT NULL,
    kundengruppen_id INT UNSIGNED NOT NULL,
    menge_ab DECIMAL(8,3) NOT NULL,
    brutto_vk DECIMAL(8,2) NOT NULL,
    netto_vk DECIMAL(8,2) NOT NULL,
    erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_artStaff_artikel_id
    FOREIGN KEY (artikel_id) REFERENCES artikel (id) ON UPDATE CASCADE,
    CONSTRAINT fk_artStaff_kundengruppen_id
    FOREIGN KEY (kundengruppen_id) REFERENCES kundengruppen (id) ON UPDATE CASCADE
);