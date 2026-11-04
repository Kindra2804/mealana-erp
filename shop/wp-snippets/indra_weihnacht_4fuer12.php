<?php
// WPCode-Snippet 34685 auf indra-design.at (PHP, aktiv)
// INDRA DESIGN – Weihnachtsanhänger: 4 Stück (auch gemischt) für 12 € (Claude 2026-11-04)
// Rechnet den Rabatt anteilig in die Positionspreise ein (keine negative Gebühr), damit der ERP-Import die Preise 1:1 übernimmt.
if (!function_exists('indra_xm_ist')) {
    function indra_xm_ist($produktId) { return has_term('weihnachtsanhaenger', 'product_cat', $produktId); }
    function indra_xm_menge() {
        $n = 0; if (!WC()->cart) return 0;
        foreach (WC()->cart->get_cart() as $pos) if (indra_xm_ist($pos['product_id'])) $n += (int)$pos['quantity'];
        return $n;
    }
}
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    $n = 0; $summe = 0.0; $pos = [];
    foreach ($cart->get_cart() as $k => $p) {
        if (!indra_xm_ist($p['product_id'])) continue;
        $orig = (float)wc_get_product($p['data']->get_id())->get_price();
        $n += (int)$p['quantity']; $summe += $orig * (int)$p['quantity']; $pos[] = [$p['data'], $orig];
    }
    $sets = intdiv($n, 4); if (!$sets || $summe <= 0) return;
    $soll = $sets * 12.00 + ($n - $sets * 4) * 3.50;
    if ($soll >= $summe) return;
    $faktor = $soll / $summe;
    foreach ($pos as [$prod, $orig]) $prod->set_price(round($orig * $faktor, 4));
}, 20);
$indra_xm_hinweis = function () {
    $n = indra_xm_menge(); if (!$n) return;
    $rest = (4 - $n % 4) % 4;
    $text = $n >= 4 ? 'Weihnachtsanhänger: der 4-für-12-€-Preis ist schon eingerechnet.' : '';
    if ($rest) $text .= ($text ? ' ' : '') . 'Noch ' . $rest . ' Weihnachtsanhänger dazu, dann bekommst du ' . ($n >= 4 ? 'weitere ' : '') . '4 Stück für 12 € (auch gemischt).';
    wc_print_notice(esc_html($text), 'notice');
};
add_action('woocommerce_before_cart', $indra_xm_hinweis);
add_action('woocommerce_before_checkout_form', $indra_xm_hinweis, 5);
add_action('woocommerce_single_product_summary', function () {
    global $product; if (!$product || !indra_xm_ist($product->get_id())) return;
    echo '<p class="indra-xm-staffel" style="background:#e3f1fa;padding:10px 14px;border-radius:4px;margin:0 0 18px;"><strong>4 Stück für 12 €</strong> – auch gemischt aus Engeln, Schafen und Kugeln. Der Preis wird im Warenkorb automatisch berechnet.</p>';
}, 25);
