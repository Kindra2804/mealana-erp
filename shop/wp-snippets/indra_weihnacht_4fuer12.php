<?php
// WPCode-Snippet 34685 auf indra-design.at (PHP, aktiv)
// INDRA DESIGN – Weihnachtsanhänger: 3 Stück (auch gemischt) für 12 € (Claude 2026-11-04, geändert 2026-11-11: vorher 4 für 12 / Stk 3,50)
// Der Einzelpreis kommt aus dem ERP (Artikelpreis, derzeit 4,50 €). Hier stehen nur die Paketgröße und der Paketpreis.
// Rechnet den Rabatt anteilig in die Positionspreise ein (keine negative Gebühr), damit der ERP-Import die Preise 1:1 übernimmt.
if (!defined('INDRA_XM_PAKET')) {
    define('INDRA_XM_PAKET', 3);          // so viele Stück ...
    define('INDRA_XM_PAKETPREIS', 12.00); // ... kosten zusammen so viel
    function indra_xm_ist($produktId) { return has_term(['weihnachten', 'weihnachtsanhaenger'], 'product_cat', $produktId); }
    function indra_xm_menge() {
        $n = 0; if (!WC()->cart) return 0;
        foreach (WC()->cart->get_cart() as $pos) if (indra_xm_ist($pos['product_id'])) $n += (int)$pos['quantity'];
        return $n;
    }
    function indra_xm_preistext() { return INDRA_XM_PAKET . ' Stück für ' . number_format(INDRA_XM_PAKETPREIS, 0, ',', '.') . ' €'; }
}
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $n = 0; $summe = 0.0; $pos = [];
    foreach ($cart->get_cart() as $k => $p) {
        if (!indra_xm_ist($p['product_id'])) continue;
        $orig = (float)wc_get_product($p['data']->get_id())->get_price();
        $n += (int)$p['quantity']; $summe += $orig * (int)$p['quantity']; $pos[] = [$p['data'], $orig];
    }
    $sets = intdiv($n, INDRA_XM_PAKET); if (!$sets || $summe <= 0) return;
    $einzel = $summe / $n; // Durchschnitt der Originalpreise (alle gleich, aber sicher bei gemischten Preisen)
    $soll = $sets * INDRA_XM_PAKETPREIS + ($n - $sets * INDRA_XM_PAKET) * $einzel;
    if ($soll >= $summe) return;
    $faktor = $soll / $summe;
    foreach ($pos as [$prod, $orig]) $prod->set_price(round($orig * $faktor, 4));
}, 20);
$indra_xm_hinweis = function () {
    $n = indra_xm_menge(); if (!$n) return;
    $rest = (INDRA_XM_PAKET - $n % INDRA_XM_PAKET) % INDRA_XM_PAKET;
    $text = $n >= INDRA_XM_PAKET ? 'Weihnachtsanhänger: der Preis ' . indra_xm_preistext() . ' ist schon eingerechnet.' : '';
    if ($rest) $text .= ($text ? ' ' : '') . 'Noch ' . $rest . ' Weihnachtsanhänger dazu, dann bekommst du ' . ($n >= INDRA_XM_PAKET ? 'weitere ' : '') . indra_xm_preistext() . ' (auch gemischt).';
    wc_print_notice(esc_html($text), 'notice');
};
add_action('woocommerce_before_cart', $indra_xm_hinweis);
add_action('woocommerce_before_checkout_form', $indra_xm_hinweis, 5);
add_action('woocommerce_single_product_summary', function () {
    global $product; if (!$product || !indra_xm_ist($product->get_id())) return;
    echo '<p class="indra-xm-staffel" style="background:#e3f1fa;padding:10px 14px;border-radius:4px;margin:0 0 18px;"><strong>' . esc_html(indra_xm_preistext()) . '</strong> – auch gemischt aus Engeln, Schafen und Kugeln. Der Preis wird im Warenkorb automatisch berechnet.</p>';
}, 25);
