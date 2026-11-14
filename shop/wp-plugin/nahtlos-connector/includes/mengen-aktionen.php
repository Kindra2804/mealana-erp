<?php
/**
 * NAHTLOS Connector – Mengen-Aktionen („4 für 12 €“, „3 für 2“, Paketpreise) im Shop.
 *
 * Ablauf:
 *  - Das ERP schickt alle Mengen-Aktionen dieses Shops per POST /wp-json/nahtlos/v1/mengen-aktionen
 *    (MengenAktionService::uebertrageShop). Jede Aktion bringt die Woo-IDs ihrer Produkte UND Variationen mit
 *    und ihren Zeitraum (von/bis als Datum). Gespeichert in der Option nahtlos_mengen_aktionen.
 *  - Der Shop prüft den Zeitraum selbst (Zeitzone Europe/Vienna), weil das ERP von hier nicht erreichbar ist.
 *  - Warenkorb: bei jeder Berechnung (auch Block-Warenkorb/Store API) rechnet der GLEICHE Rechenkern wie im ERP
 *    (includes/MengenAktionRechner.php = Kopie aus erp/src/modules/aktionen/, nicht hier ändern!) und setzt den
 *    reduzierten Stückpreis. Der Block-Warenkorb zeigt den alten Preis dann selbst durchgestrichen.
 *  - Unter jeder Aktions-Zeile: „Aktion 4 für 12 €“ bzw. „noch 1 Stück, dann gilt 4 für 12 €“ (Warenkorb-Hinweis).
 *  - Badge in Produktliste + Produktseite, Bestellposition bekommt „Aktion: …“ als Meta.
 *  - Keine doppelten Rabatte: Artikel im Sale (is_on_sale) nehmen nicht teil.
 * Regeln: Jacky 2026-11-14, siehe ERP-Datei MengenAktionRechner.php.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/MengenAktionRechner.php';

const NAHTLOS_OPTION_MENGEN = 'nahtlos_mengen_aktionen';

add_action('rest_api_init', function () {
    register_rest_route('nahtlos/v1', '/mengen-aktionen', [
        [
            'methods'             => 'POST',
            'permission_callback' => fn() => current_user_can('manage_woocommerce'),
            'callback'            => 'nahtlos_mengen_empfangen',
        ],
        [
            'methods'             => 'GET',
            'permission_callback' => fn() => current_user_can('manage_woocommerce'),
            'callback'            => fn() => get_option(NAHTLOS_OPTION_MENGEN, ['aktionen' => []]),
        ],
    ]);
});

/** ERP → Shop: komplette Liste ersetzen */
function nahtlos_mengen_empfangen(WP_REST_Request $request)
{
    $daten = $request->get_json_params();
    if (!is_array($daten) || !isset($daten['aktionen']) || !is_array($daten['aktionen'])) {
        return new WP_Error('nahtlos_ungueltig', 'Aktionen fehlen', ['status' => 400]);
    }
    $aktionen = [];
    foreach ($daten['aktionen'] as $a) {
        $art = (string)($a['art'] ?? '');
        if (!in_array($art, ['x_fuer_y', 'kauf_x_zahl_y', 'paketpreise'], true)) continue;
        $aktionen[] = [
            'id'          => (int)$a['id'],
            'art'         => $art,
            'menge'       => (int)$a['menge'],
            'zahl_menge'  => isset($a['zahl_menge']) ? (int)$a['zahl_menge'] : null,
            'paketpreis'  => isset($a['paketpreis']) ? (float)$a['paketpreis'] : null,
            'pakete'      => array_map(fn($p) => ['menge' => (int)$p['menge'], 'preis' => (float)$p['preis']], (array)($a['pakete'] ?? [])),
            'badge_text'  => sanitize_text_field((string)($a['badge_text'] ?? '')),
            'von'         => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($a['von'] ?? '')) ? $a['von'] : '2000-01-01',
            'bis'         => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($a['bis'] ?? '')) ? $a['bis'] : null,
            'woo_ids'     => array_values(array_unique(array_map('intval', (array)($a['woo_ids'] ?? [])))),
        ];
    }
    update_option(NAHTLOS_OPTION_MENGEN, ['aktionen' => $aktionen, 'stand' => time()], false);
    nahtlos_cache_leeren();   // Badges auf gecachten Seiten
    return ['erfolg' => true, 'aktionen' => count($aktionen)];
}

/** Heute gültige Aktionen im Format des Rechenkerns (artikel_ids = Woo-IDs) */
function nahtlos_mengen_regeln(): array
{
    static $regeln = null;
    if ($regeln !== null) return $regeln;
    $heute = (new DateTime('now', new DateTimeZone('Europe/Vienna')))->format('Y-m-d');
    $regeln = [];
    foreach (get_option(NAHTLOS_OPTION_MENGEN, ['aktionen' => []])['aktionen'] ?? [] as $a) {
        if ($a['von'] > $heute || ($a['bis'] !== null && $a['bis'] < $heute)) continue;
        $a['artikel_ids'] = $a['woo_ids'];
        $regeln[] = $a;
    }
    return $regeln;
}

/** Aktion, an der ein Produkt (oder eine seiner Variationen) teilnimmt – für Badge/Produktseite */
function nahtlos_mengen_aktion_fuer_produkt(int $produktId): ?array
{
    foreach (nahtlos_mengen_regeln() as $a) {
        if (in_array($produktId, $a['woo_ids'], true)) return $a;
    }
    return null;
}

/**
 * Lohnt sich die Aktion beim aktuellen Stückpreis überhaupt? (z.B. „3 für 12 €“ bei 3,50 € je Stück: nein)
 * Sonst kein Badge und kein „noch x Stück“-Hinweis – das wäre irreführend.
 */
function nahtlos_mengen_lohnt(array $a, float $preis): bool
{
    if ($preis <= 0) return false;
    if ($a['art'] === 'x_fuer_y') return $a['menge'] * $preis > (float)$a['paketpreis'] + 0.004;
    if ($a['art'] === 'paketpreise') {
        foreach ($a['pakete'] as $p) if ($p['menge'] * $preis > $p['preis'] + 0.004) return true;
        return false;
    }
    return true;   // kauf_x_zahl_y spart immer
}

// ── Warenkorb ──────────────────────────────────────────────────────────────

/** Ergebnis der letzten Berechnung: ['positionen' => [cart_item_key => …], 'hinweise' => […]] */
function nahtlos_mengen_ergebnis(?array $neu = null): array
{
    static $erg = ['positionen' => [], 'hinweise' => []];
    if ($neu !== null) $erg = $neu;
    return $erg;
}

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST)) return;
    $regeln = nahtlos_mengen_regeln();
    if (!$regeln) { nahtlos_mengen_ergebnis(['positionen' => [], 'hinweise' => []]); return; }

    $positionen = [];
    foreach ($cart->get_cart() as $key => $item) {
        // Preis immer frisch vom Produkt (Berechnung läuft je Aufruf öfter -> nie auf schon reduziertem Preis rechnen)
        $id = (int)($item['variation_id'] ?: $item['product_id']);
        $produkt = wc_get_product($id);
        if (!$produkt) continue;
        $preis = (float)$produkt->get_price('edit');
        $item['data']->set_price($preis);
        $positionen[] = [
            'key'           => $key,
            'artikel_id'    => $id,
            'menge'         => (int)$item['quantity'],
            'preis'         => $preis,
            'ist_reduziert' => $produkt->is_on_sale('edit'),
        ];
    }
    $erg = MengenAktionRechner::berechne($regeln, $positionen);
    foreach ($erg['positionen'] as $key => $r) {
        if (isset($cart->cart_contents[$key])) $cart->cart_contents[$key]['data']->set_price($r['einzelpreis_neu']);
    }
    // Hinweise je Aktion den Zeilen zuordnen ("noch x Stück" auch bei Zeilen ohne Rabatt)
    $erg['aktion_je_zeile'] = [];
    foreach ($positionen as $p) {
        foreach ($regeln as $a) {
            if (in_array($p['artikel_id'], $a['woo_ids'], true) && !$p['ist_reduziert']) {
                if (isset($erg['positionen'][$p['key']]) || nahtlos_mengen_lohnt($a, $p['preis'])) $erg['aktion_je_zeile'][$p['key']] = $a['id'];
                break;
            }
        }
    }
    nahtlos_mengen_ergebnis($erg);
}, 20);

/** Text unter der Warenkorb-Zeile (klassisch + Block-Warenkorb über item_data) */
add_filter('woocommerce_get_item_data', function ($daten, $item) {
    $erg = nahtlos_mengen_ergebnis();
    $key = $item['key'] ?? '';
    $aktionId = $erg['aktion_je_zeile'][$key] ?? null;
    if (!$aktionId) return $daten;
    foreach ($erg['hinweise'] as $h) {
        if ($h['aktion_id'] !== $aktionId) continue;
        $text = $h['badge_text'];
        if ($h['fehlen'] > 0 && $h['stueck'] > 0 && $h['pakete'] === 0) {
            $text .= ' – noch ' . $h['fehlen'] . ' Stück, dann wird es günstiger';
        } elseif ($h['fehlen'] > 0 && $h['pakete'] > 0) {
            $text .= ' – mit ' . $h['fehlen'] . ' Stück mehr gibt es noch ein Paket';
        }
        $daten[] = ['key' => 'Aktion', 'value' => $text, 'display' => esc_html($text)];
        break;
    }
    return $daten;
}, 10, 2);

/** Bestellposition: Aktion vermerken (sieht man in der Bestellung, Mail und im ERP-Import) */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cartItemKey) {
    $r = nahtlos_mengen_ergebnis()['positionen'][$cartItemKey] ?? null;
    if ($r) $item->add_meta_data('Aktion', $r['badge_text'], true);
}, 10, 2);

// ── Badge ──────────────────────────────────────────────────────────────────

function nahtlos_mengen_badge_html(int $produktId, string $klasse): string
{
    $a = nahtlos_mengen_aktion_fuer_produkt($produktId);
    if (!$a || $a['badge_text'] === '') return '';
    $produkt = wc_get_product($produktId);
    if (!$produkt || $produkt->is_on_sale() || !nahtlos_mengen_lohnt($a, (float)$produkt->get_price())) return '';
    $titel = $a['art'] === 'paketpreise'
        ? implode(' · ', array_map(fn($p) => $p['menge'] . ' für ' . wc_price($p['preis']), $a['pakete']))
        : '';
    return '<span class="nahtlos-mengen-badge ' . esc_attr($klasse) . '"' . ($titel ? ' title="' . esc_attr(wp_strip_all_tags($titel)) . '"' : '') . '>'
        . esc_html($a['badge_text']) . '</span>';
}

// Produktliste + Produkt-Blöcke: Badge direkt neben dem Preis (Jacky 2026-11-14: oberhalb wirkte es verloren).
// Auf der eigenen Produktseite nicht – dort steht der Hinweis-Kasten unter dem Preis.
add_filter('woocommerce_get_price_html', function ($html, $product) {
    if (is_admin() && !wp_doing_ajax()) return $html;
    // Produktseite selbst (auch Variationspreis): kein Badge; Listen darauf (ähnliche Produkte) schon
    if (function_exists('is_product') && is_product() && !wc_get_loop_prop('name')) return $html;
    $badge = nahtlos_mengen_badge_html($product->get_id(), 'ist-liste');
    return $badge ? $html . ' ' . $badge : $html;
}, 20, 2);

// Produktseite: unter dem Preis, bei Paketpreisen mit allen Größen
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!$product) return;
    $a = nahtlos_mengen_aktion_fuer_produkt($product->get_id());
    if (!$a || $product->is_on_sale() || !nahtlos_mengen_lohnt($a, (float)$product->get_price())) return;
    $text = match ($a['art']) {
        'x_fuer_y'      => 'Aktion: ' . $a['menge'] . ' Stück für ' . wp_strip_all_tags(wc_price($a['paketpreis'])) . ' – auch gemischt aus der Aktion',
        'kauf_x_zahl_y' => 'Aktion: ' . $a['menge'] . ' kaufen, ' . $a['zahl_menge'] . ' bezahlen – auch gemischt aus der Aktion',
        default         => 'Mengenpreise: ' . implode(' · ', array_map(fn($p) => $p['menge'] . ' Stück ' . wp_strip_all_tags(wc_price($p['preis'])), $a['pakete'])) . ' – auch gemischt',
    };
    echo '<p class="nahtlos-mengen-hinweis">' . esc_html($text) . '</p>';
}, 11);

add_action('wp_enqueue_scripts', function () {
    wp_add_inline_style('nahtlos-menue', '
        .nahtlos-mengen-badge{display:inline-block;margin:0 0 0 6px;vertical-align:middle;padding:1px 9px;border-radius:999px;font-size:.8em;font-weight:700;
            background:var(--theme-palette-color-1,#b45309);color:#fff;line-height:1.6}
        .nahtlos-mengen-hinweis{margin:.5em 0 1em;padding:.5em .8em;border-radius:6px;font-weight:600;
            background:rgba(180,83,9,.08);border-left:3px solid var(--theme-palette-color-1,#b45309)}');
}, 20);
