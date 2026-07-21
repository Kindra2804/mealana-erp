-- Bild-Sync braucht direkten Byte-Upload in die WordPress-Mediathek
-- (/wp-json/wp/v2/media) -- unser ERP hat keinen öffentlichen Endpunkt,
-- WordPress kann Bild-URLs bei uns also nicht selbst abholen. Dafür ist
-- ein WordPress-Application-Password nötig, unabhängig vom bestehenden
-- WooCommerce-Consumer-Key/Secret (das gilt nur für /wc/v3/-Routen).
ALTER TABLE shops
    ADD COLUMN wp_username     VARCHAR(100) NULL AFTER wc_secret,
    ADD COLUMN wp_app_password VARCHAR(255) NULL AFTER wp_username;
