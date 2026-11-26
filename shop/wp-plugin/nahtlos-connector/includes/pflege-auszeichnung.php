<?php
/**
 * Pflege-Box + Auszeichnungs-Badges (1.9.0, Jacky 2026-11-26)
 *
 * Das ERP schickt die Merkmale als globale Produktattribute (ShopSyncService::baueMerkmalAttribute()).
 * Pflege- und Auszeichnungs-Werte haben als Term-Slug ihren Symbol-Schlüssel (hand30, nicht_bleichen,
 * gots, bio, handgefaerbt …). Dieses Modul zeichnet daraus:
 *  - Produktseite: Kasten „Pflegehinweise“ mit Symbolen (unter dem Warenkorb-Knopf)
 *  - Produktseite + Produktlisten: Badges GOTS / BIO / HANDGEFÄRBT (Farben wie an den Preisschildern)
 * Die Attribute werden über ihren NAMEN gefunden („Pflege“, „Auszeichnung“), nicht über den Slug –
 * so klappt es auch, wenn es das Attribut im Shop schon vorher mit anderem Slug gab.
 * Symbole: includes/PflegeSymbole.php = Kopie aus dem ERP (src/modules/schilder/), nicht hier ändern.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/PflegeSymbole.php';

/** Farben wie BADGE_FARBEN in PreisschildService (ERP); GOTS mit Rahmen statt Fläche */
const NAHTLOS_AUSZEICHNUNG_FARBEN = ['gots' => '#3b6b52', 'bio' => '#3b6b52', 'handgefaerbt' => '#7d3c98'];

/**
 * Terms eines Merkmal-Attributs (über den Namen gefunden) am Produkt, in der Reihenfolge aus dem ERP.
 * Variationen fragen beim Vater nach.
 *
 * @return WP_Term[]
 */
function nahtlos_merkmal_terms(WC_Product $produkt, string $attributName): array
{
    if ($produkt->is_type('variation')) {
        $vater = wc_get_product($produkt->get_parent_id());
        if (!$vater) return [];
        $produkt = $vater;
    }
    foreach ($produkt->get_attributes() as $taxonomie => $attribut) {
        if (!$attribut instanceof WC_Product_Attribute || !$attribut->is_taxonomy()) continue;
        if (mb_strtolower(wc_attribute_label($taxonomie)) !== mb_strtolower($attributName)) continue;
        $terms = wc_get_product_terms($produkt->get_id(), $taxonomie, ['fields' => 'all']);
        return is_array($terms) ? $terms : [];
    }
    return [];
}

/** HTML der Auszeichnungs-Badges (leer, wenn keine) */
function nahtlos_auszeichnung_html(WC_Product $produkt, string $klasse): string
{
    $html = '';
    foreach (nahtlos_merkmal_terms($produkt, 'Auszeichnung') as $term) {
        $farbe = NAHTLOS_AUSZEICHNUNG_FARBEN[$term->slug] ?? '#555';
        $stil = $term->slug === 'gots'
            ? "color:$farbe;border-color:$farbe;background:#fff"
            : "background:$farbe;border-color:$farbe;color:#fff";
        $html .= '<span class="nahtlos-auszeichnung" style="' . esc_attr($stil) . '">' . esc_html(mb_strtoupper($term->name)) . '</span>';
    }
    return $html !== '' ? '<span class="nahtlos-auszeichnungen ' . esc_attr($klasse) . '">' . $html . '</span>' : '';
}

// Produktlisten: Badges unter dem Preis (über den Preis-Filter wie bei den Mengen-Aktionen – das
// Blocksy-Theme baut seine Produktkarten selbst, der Preis kommt aber immer über get_price_html()).
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (is_admin() && !wp_doing_ajax()) return $html;
    if (!$product instanceof WC_Product || $product->is_type('variation')) return $html;
    if (function_exists('is_product') && is_product() && !wc_get_loop_prop('name')) return $html;   // Produktseite selbst: eigener Platz
    $badges = nahtlos_auszeichnung_html($product, 'ist-liste');
    return $badges !== '' ? $html . $badges : $html;
}, 25, 2);

// Produktseite: Badges unter dem Titel
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if ($product instanceof WC_Product) echo nahtlos_auszeichnung_html($product, 'ist-produktseite');
}, 6);

// Produktseite: Pflegehinweise mit Symbolen unter dem Warenkorb-Knopf
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!$product instanceof WC_Product) return;
    $terms = nahtlos_merkmal_terms($product, 'Pflege');
    if (!$terms) return;
    echo '<div class="nahtlos-pflege"><div class="nahtlos-pflege-titel">Pflegehinweise</div><ul>';
    foreach ($terms as $term) {
        $svg = PflegeSymbole::svg($term->slug);
        // ohne Symbol (z.B. selbst angelegter Wert): leerer Platz, damit die Texte bündig bleiben
        echo '<li><span class="nahtlos-pflege-symbol" aria-hidden="true">' . ($svg ?: '<svg width="36" height="31"></svg>') . '</span>'
            . '<span>' . esc_html($term->name) . '</span></li>';
    }
    echo '</ul></div>';
}, 35);

add_action('wp_enqueue_scripts', function () {
    wp_add_inline_style('nahtlos-menue', '
        .nahtlos-auszeichnungen{display:flex;flex-wrap:wrap;gap:4px;margin:4px 0 0}
        .nahtlos-auszeichnungen.ist-liste{justify-content:inherit}
        .nahtlos-auszeichnungen.ist-produktseite{margin:0 0 .8em}
        .nahtlos-auszeichnung{display:inline-block;padding:1px 8px;border:1.5px solid;border-radius:4px;font-size:.72em;font-weight:700;letter-spacing:.04em;line-height:1.5}
        .nahtlos-pflege{margin:1.2em 0;padding:.8em 1em;border:1px solid rgba(0,0,0,.12);border-radius:8px}
        .nahtlos-pflege-titel{font-weight:700;margin-bottom:.5em}
        .nahtlos-pflege ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:.45em 1em}
        .nahtlos-pflege li{display:flex;align-items:center;gap:.6em;margin:0;font-size:.92em}
        .nahtlos-pflege-symbol{flex:0 0 auto;display:inline-flex}
        .nahtlos-pflege-symbol svg{width:36px;height:31px}');
}, 20);
