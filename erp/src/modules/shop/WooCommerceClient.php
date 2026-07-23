<?php

/**
 * WooCommerceClient – dünner Wrapper um die WooCommerce REST API v3.
 *
 * Ein Client pro Shop (URL+Key+Secret aus `shops`). Auth läuft über HTTP
 * Basic Auth mit Consumer Key/Secret (funktioniert nur über HTTPS -- WC
 * lehnt Basic-Auth-Keys über HTTP explizit ab, das ist WooCommerce-Standard,
 * kein Bug hier).
 */
class WooCommerceClient
{
    private string $siteUrl;
    private string $baseUrl;
    private string $key;
    private string $secret;
    private ?string $wpUsername;
    private ?string $wpAppPassword;

    public function __construct(
        string $url,
        string $key,
        string $secret,
        ?string $wpUsername = null,
        ?string $wpAppPassword = null
    ) {
        $this->siteUrl = rtrim($url, '/');
        $this->baseUrl = $this->siteUrl . '/wp-json/wc/v3';
        $this->key = $key;
        $this->secret = $secret;
        $this->wpUsername = $wpUsername;
        $this->wpAppPassword = $wpAppPassword;
    }

    /** Einfachster Verbindungstest: liefert die Systemstatus-Daten des Shops. */
    public function systemStatus(): array
    {
        return $this->request('GET', '/system_status');
    }

    public function getProdukt(string $externalId): array
    {
        return $this->request('GET', '/products/' . $externalId);
    }

    /**
     * Bestellungen seit einem Zeitpunkt (Polling-Cursor, kein Webhook --
     * unser ERP hat keinen öffentlichen Endpunkt, siehe project_shop_sync.md).
     * `modified_after` ist ein offiziell dokumentierter Parameter der
     * aktuellen WC-REST-API v3 (nicht zu verwechseln mit der alten
     * Legacy-API, die stattdessen `filter[updated_at_min]` verwendet).
     */
    public function listeBestellungen(?string $modifiedAfter, int $proSeite = 50): array
    {
        $query = ['status' => 'any', 'per_page' => $proSeite, 'orderby' => 'modified', 'order' => 'asc'];
        if ($modifiedAfter !== null) {
            $query['modified_after'] = $modifiedAfter;
        }
        return $this->request('GET', '/orders', $query);
    }

    public function listeProdukte(int $proSeite = 10): array
    {
        return $this->request('GET', '/products', ['per_page' => $proSeite]);
    }

    public function erstelleProdukt(array $daten): array
    {
        return $this->request('POST', '/products', [], $daten);
    }

    public function aktualisiereProdukt(string $externalId, array $daten): array
    {
        return $this->request('PUT', '/products/' . $externalId, [], $daten);
    }

    public function erstelleKategorie(array $daten): array
    {
        return $this->request('POST', '/products/categories', [], $daten);
    }

    /** Name/Beschreibung/Oberkategorie einer bereits angelegten Kategorie nachziehen. */
    public function aktualisiereKategorie(string $externeId, array $daten): array
    {
        return $this->request('PUT', '/products/categories/' . $externeId, [], $daten);
    }

    /**
     * Alle globalen Attribute des Shops (z.B. "Farbe", "Nadelstärke").
     * Wird VOR erstelleAttribut() abgefragt, um Duplikate zu vermeiden --
     * WooCommerce liefert bei doppeltem Namen zwar einen Fehler
     * (`woocommerce_rest_invalid_product_attribute_slug_already_exists`),
     * aber (anders als bei Terms) KEINE ID des bestehenden Attributs im
     * Fehler-Body zurück, ein Catch-and-reuse wie bei Terms geht hier also
     * nicht -- deshalb hier "erst nachsehen, dann anlegen".
     */
    public function listeAttribute(): array
    {
        return $this->request('GET', '/products/attributes', ['per_page' => 100]);
    }

    /** Globales Attribut (z.B. "Farbe") -- Basis für Achsen, die als Variation dienen. */
    public function erstelleAttribut(array $daten): array
    {
        return $this->request('POST', '/products/attributes', [], $daten);
    }

    /** Alle Terms (Werte) eines Attributs, z.B. "Rot"/"Blau" unter "Farbe". */
    public function listeAttributTerms(int $attributId): array
    {
        return $this->request('GET', "/products/attributes/$attributId/terms", ['per_page' => 100]);
    }

    /** Attribut-Term (z.B. "Rot" unter "Farbe") anlegen. */
    public function erstelleAttributTerm(int $attributId, array $daten): array
    {
        return $this->request('POST', "/products/attributes/$attributId/terms", [], $daten);
    }

    /** Attribut-Term nachträglich ändern (z.B. Hersteller-Beschreibung für GPSR-Kontaktdaten). */
    public function aktualisiereAttributTerm(int $attributId, string $termId, array $daten): array
    {
        return $this->request('PUT', "/products/attributes/$attributId/terms/$termId", [], $daten);
    }

    /**
     * Natives WooCommerce-"Hersteller" (Produktsicherheit-Panel) -- eine eigene
     * Entität, unabhängig vom "Hersteller"-Produktattribut. Hat eigene
     * Adress-/EU-Vertreter-Felder (formatted_address/formatted_eu_address) und
     * erzeugt automatisch eine Archivseite (/hersteller/{slug}/) sobald ein
     * Produkt per manufacturer.id zugewiesen wird.
     */
    public function listeHersteller(): array
    {
        return $this->request('GET', '/products/manufacturers', ['per_page' => 100]);
    }

    public function erstelleHersteller(array $daten): array
    {
        return $this->request('POST', '/products/manufacturers', [], $daten);
    }

    public function aktualisiereHersteller(string $externeId, array $daten): array
    {
        return $this->request('PUT', '/products/manufacturers/' . $externeId, [], $daten);
    }

    public function erstelleVariation(string $parentId, array $daten): array
    {
        return $this->request('POST', "/products/$parentId/variations", [], $daten);
    }

    public function aktualisiereVariation(string $parentId, string $variationId, array $daten): array
    {
        return $this->request('PUT', "/products/$parentId/variations/$variationId", [], $daten);
    }

    /**
     * Lädt eine Bild-Datei in die WordPress-Mediathek hoch.
     *
     * Läuft über die WordPress-KERN-REST-API (`/wp-json/wp/v2/media`), NICHT
     * über WooCommerce (`/wc/v3/...`) -- Consumer-Key/Secret gilt nur für
     * WC-Routen. Braucht ein WordPress-Application-Password, weil unser ERP
     * keinen öffentlichen Endpunkt hat und WordPress Bild-URLs bei uns darum
     * nicht selbst abholen kann ("sideload") -- nur direkter Byte-Upload geht.
     *
     * @throws RuntimeException wenn kein Application-Password hinterlegt ist,
     *         die Datei nicht lesbar ist, oder der Upload fehlschlägt
     */
    public function ladeBildHoch(string $dateiPfad, string $dateiname, string $altText = ''): array
    {
        if (!$this->wpUsername || !$this->wpAppPassword) {
            throw new RuntimeException('Kein WordPress-Application-Password für diesen Shop hinterlegt');
        }
        if (!is_readable($dateiPfad)) {
            throw new RuntimeException("Bilddatei nicht lesbar: $dateiPfad");
        }
        $mime = mime_content_type($dateiPfad) ?: 'application/octet-stream';

        $url = $this->siteUrl . '/wp-json/wp/v2/media';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            // multipart/form-data (nicht raw binary + Content-Disposition) --
            // so kann alt_text im selben Request mitgeschickt werden statt
            // eines zusätzlichen PATCH danach.
            CURLOPT_POSTFIELDS     => [
                'file'     => new CURLFile($dateiPfad, $mime, $dateiname),
                'alt_text' => $altText,
            ],
            CURLOPT_USERPWD => $this->wpUsername . ':' . $this->wpAppPassword,
            CURLOPT_TIMEOUT => 30,
        ]);

        $antwort = curl_exec($ch);
        $fehler = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($antwort === false) {
            throw new RuntimeException("WordPress-Medien-Upload fehlgeschlagen: $fehler");
        }

        $daten = json_decode($antwort, true);
        if ($httpCode >= 400) {
            $msg = $daten['message'] ?? $antwort;
            throw new RuntimeException("WordPress-Medien-API-Fehler ($httpCode): $msg");
        }

        return $daten ?? [];
    }

    /** @throws RuntimeException bei HTTP-Fehler oder Verbindungsproblem */
    private function request(string $methode, string $pfad, array $query = [], ?array $body = null): array
    {
        $query['consumer_key'] = $this->key;
        $query['consumer_secret'] = $this->secret;
        $url = $this->baseUrl . $pfad . '?' . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $antwort = curl_exec($ch);
        $fehler = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($antwort === false) {
            throw new RuntimeException("WooCommerce-Verbindung fehlgeschlagen: $fehler");
        }

        $daten = json_decode($antwort, true);
        if ($httpCode >= 400) {
            $code = $daten['code'] ?? '';
            $msg = $daten['message'] ?? $antwort;
            throw new RuntimeException("WooCommerce-API-Fehler ($httpCode, $code): $msg");
        }

        return $daten ?? [];
    }
}
