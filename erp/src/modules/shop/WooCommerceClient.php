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
    private string $baseUrl;
    private string $key;
    private string $secret;

    public function __construct(string $url, string $key, string $secret)
    {
        $this->baseUrl = rtrim($url, '/') . '/wp-json/wc/v3';
        $this->key = $key;
        $this->secret = $secret;
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
            $msg = $daten['message'] ?? $antwort;
            throw new RuntimeException("WooCommerce-API-Fehler ($httpCode): $msg");
        }

        return $daten ?? [];
    }
}
