<?php
/**
 * Sale-Anzeige wie im alten JTL-Shop (1.9.0, Jacky 2026-11-26)
 *  - Sale-Badge mit Prozent: „SALE 36%“, bei Varianten mit unterschiedlichem Rabatt „SALE bis 36%“
 *  - Preis: aktueller Preis groß, darunter „Alter Preis: ~~5,50 €~~“ (statt Woos „~~5,50~~ 3,50“)
 * Der Grundpreis (Germanized) bleibt, wo er ist.
 *
 * Vergleichspreis = regulärer Preis aus dem ERP (Normalpreis). Rechtlich (Preisangaben, „Omnibus“) muss
 * der durchgestrichene Preis der niedrigste der letzten 30 Tage vor der Preissenkung sein – sobald das ERP
 * den liefert, wird er NUR in nahtlos_vergleichspreis() eingesetzt; Badge und Zeile folgen automatisch.
 */

defined('ABSPATH') || exit;

/** Vergleichspreis („Alter Preis“) eines einfachen Produkts bzw. einer Variation, brutto wie angezeigt */
function nahtlos_vergleichspreis(WC_Product $p): float
{
    return (float)wc_get_price_to_display($p, ['price' => $p->get_regular_price()]);
}

/**
 * Rabatt in Prozent (gerundet) und ob er über Varianten schwankt.
 * @return array{0:int,1:bool} [höchster Rabatt, „bis“-Anzeige nötig]
 */
function nahtlos_sale_prozent(WC_Product $produkt): array
{
    $einzeln = $produkt->is_type('variable')
        ? array_filter(array_map('wc_get_product', $produkt->get_visible_children()))
        : [$produkt];
    $werte = [];
    foreach ($einzeln as $p) {
        if (!$p->is_on_sale()) continue;
        $alt = nahtlos_vergleichspreis($p);
        $neu = (float)wc_get_price_to_display($p);
        if ($alt > 0 && $neu < $alt) $werte[] = (int)round(($alt - $neu) / $alt * 100);
    }
    if (!$werte) return [0, false];
    return [max($werte), count(array_unique($werte)) > 1 || count($werte) < count($einzeln)];
}

// Sale-Badge (Woo-Standard „Sale!“ → „SALE 36%“), Produktliste und Produktseite
add_filter('woocommerce_sale_flash', function ($html, $post, $product) {
    if (!$product instanceof WC_Product) return $html;
    [$prozent, $bis] = nahtlos_sale_prozent($product);
    if ($prozent < 1) return $html;
    return '<span class="onsale nahtlos-sale">SALE ' . ($bis ? 'bis ' : '') . $prozent . '%</span>';
}, 20, 3);

// Preis: „3,50 €“ + Zeile „Alter Preis: ~~5,50 €~~“. Nur wenn es EINEN Preis gibt (einfaches Produkt,
// Variation oder Variante mit überall gleichem Preis) – Preisspannen lässt Woo wie gehabt.
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (is_admin() && !wp_doing_ajax()) return $html;
    if (!$product instanceof WC_Product || !$product->is_on_sale()) return $html;

    if ($product->is_type('variable')) {
        $preise = $product->get_variation_prices(true);
        $aktuell = array_unique(array_map('floatval', $preise['price'] ?? []));
        $regulaer = array_unique(array_map('floatval', $preise['regular_price'] ?? []));
        if (count($aktuell) !== 1 || count($regulaer) !== 1) return $html;
        $neu = reset($aktuell);
        $alt = reset($regulaer);
    } else {
        $neu = (float)wc_get_price_to_display($product);
        $alt = nahtlos_vergleichspreis($product);
    }
    if ($alt <= $neu) return $html;

    return '<ins class="nahtlos-sale-preis">' . wc_price($neu) . '</ins>' . $product->get_price_suffix()
        . '<span class="nahtlos-alter-preis">Alter Preis: <del>' . wc_price($alt) . '</del></span>';
}, 15, 2);   // vor den Badges der Mengen-Aktionen (20) und Auszeichnungen (25), die hinten anhängen

add_action('wp_enqueue_scripts', function () {
    wp_add_inline_style('nahtlos-menue', '
        .nahtlos-sale-preis{text-decoration:none;font-weight:700}
        .nahtlos-alter-preis{display:block;font-size:.78em;font-weight:400;opacity:.75;margin-top:2px}
        .nahtlos-alter-preis del{opacity:1}
        .onsale.nahtlos-sale{white-space:nowrap}');
}, 20);
