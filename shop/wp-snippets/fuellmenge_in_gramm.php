<?php
/**
 * Füllmenge in Gramm statt kg anzeigen (Claude 2026-11-12, SOW + bio-wolle).
 *
 * Garn bekommt aus dem ERP Einheit kg + Grundpreis je 1 kg (Preisauszeichnungsgesetz).
 * Germanized zeigt deshalb „Produkt enthält: 0,05 kg“. Kunden kennen „50 g“ –
 * darum wird unter 1 kg die Menge in Gramm ausgegeben. Der Grundpreis (€ / kg) bleibt.
 * Filter aus Germanized: WC_GZD_Product::get_unit_product_html().
 */
add_filter('woocommerce_gzd_product_units_html', function ($html, $produkt) {
    if (!$produkt || !is_callable([$produkt, 'has_unit_product']) || !$produkt->has_unit_product()) return $html;
    if (strtolower((string)$produkt->get_unit()) !== 'kg') return $html;
    $menge = (float)$produkt->get_unit_product();
    if ($menge <= 0 || $menge >= 1) return $html;

    $gramm = rtrim(rtrim(number_format($menge * 1000, 1, ',', ''), '0'), ',');
    return wc_gzd_replace_label_shortcodes(get_option('woocommerce_gzd_product_units_text'), [
        '{product_units}' => $gramm,
        '{unit}'          => 'g',
        '{unit_price}'    => $produkt->get_unit_price_html(),
    ]);
}, 10, 2);
