<?php
/* Woo-Seiten auf Deutsch (2026-11-13): alte englische Adressen 301 auf die neuen weiterleiten –
 * auch Unterseiten des Kundenkontos und Links aus schon verschickten Mails (Query wie ?key=… bleibt).
 * Kopie: shop/wp-snippets/woo_alte_adressen_weiterleiten.php */
add_action('template_redirect', function () {
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $pfad = parse_url($uri, PHP_URL_PATH) ?: '';
    $query = parse_url($uri, PHP_URL_QUERY);
    $seiten = ['/my-account/' => '/mein-konto/', '/checkout/' => '/kasse/', '/cart/' => '/warenkorb/'];
    $endpunkte = [
        'orders/' => 'bestellungen/', 'view-order/' => 'bestellung-ansehen/', 'edit-account/' => 'konto-bearbeiten/',
        'edit-address/' => 'adressen/', 'payment-methods/' => 'zahlungsarten/', 'lost-password/' => 'passwort-vergessen/',
        'customer-logout/' => 'abmelden/', 'order-pay/' => 'bezahlen/', 'order-received/' => 'bestellung-erhalten/',
    ];
    foreach ($seiten as $alt => $neu) {
        if (stripos($pfad, $alt) !== 0) continue;
        $rest = substr($pfad, strlen($alt));
        foreach ($endpunkte as $e => $d) {
            if (stripos($rest, $e) === 0) { $rest = $d . substr($rest, strlen($e)); break; }
        }
        wp_safe_redirect(home_url($neu . $rest) . ($query ? '?' . $query : ''), 301);
        exit;
    }
}, 1);
