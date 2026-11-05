<?php
/**
 * Plugin Name:       NAHTLOS Connector
 * Description:       Verbindung zwischen NAHTLOS ERP und WooCommerce: Hero-Slider, Kategorie-Kacheln, Kategorie-Banner und Hauptmenü, gepflegt im ERP.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            MEALANA KG
 * Text Domain:       nahtlos-connector
 *
 * Quelle: Repo mealana/shop/wp-plugin/nahtlos-connector/ — Änderungen dort machen, nicht in wp-admin.
 *
 * Ablauf Slider:
 *  - Das ERP schickt die komplette Slide-Liste per POST /wp-json/nahtlos/v1/slider
 *    (Anmeldung mit WordPress-Application-Password, Recht manage_woocommerce).
 *    Bilder hat das ERP vorher in die Mediathek geladen, hier stehen nur deren IDs/URLs.
 *  - Das Plugin speichert die Liste in der Option nahtlos_slider und zeigt Slides nur
 *    innerhalb von von/bis an. von/bis kommen als ISO-8601 mit Zeitzone (z. B.
 *    2026-12-01T00:00:00+01:00) und werden als Unix-Zeit gespeichert — unabhängig davon,
 *    welche Zeitzone in WordPress eingestellt ist (indra-design.at läuft auf UTC). Das ERP ist vom Shop aus nicht erreichbar,
 *    darum muss die Zeitsteuerung hier passieren.
 *  - Seiten-Cache (WP Super Cache): gecachte Aufrufe starten WordPress gar nicht, also
 *    auch kein WP-Cron (Test 2026-11-05: Slide erschien nicht). Darum liefert die Seite
 *    künftige Slides schon mit (versteckt, mit data-von/-bis), und der Browser blendet
 *    nach der Uhrzeit ein/aus (slider.js). Das geplante Cache-Leeren bleibt als Zusatz.
 *  - Ausgabe: Shortcode [nahtlos_slider] (Startseite), [nahtlos_kacheln] (Kategorie-Kacheln)
 *    und automatisch oben auf Kategorieseiten (Woo-Kategorie mit eigenen Banner-Slides).
 */

defined('ABSPATH') || exit;

const NAHTLOS_VERSION = '1.4.0';
const NAHTLOS_OPTION_SLIDER = 'nahtlos_slider';

// ── REST-Schnittstelle für das ERP ─────────────────────────────────────────

add_action('rest_api_init', function () {
    $berechtigt = fn() => current_user_can('manage_woocommerce');

    register_rest_route('nahtlos/v1', '/status', [
        'methods'             => 'GET',
        'permission_callback' => $berechtigt,
        'callback'            => fn() => [
            'plugin'    => 'nahtlos-connector',
            'version'   => NAHTLOS_VERSION,
            'zeit'      => current_time('mysql'),
            'zeitzone'  => wp_timezone_string(),
        ],
    ]);

    register_rest_route('nahtlos/v1', '/slider', [
        [
            'methods'             => 'POST',
            'permission_callback' => $berechtigt,
            'callback'            => 'nahtlos_slider_empfangen',
        ],
        [
            'methods'             => 'GET',
            'permission_callback' => $berechtigt,
            'callback'            => fn() => get_option(NAHTLOS_OPTION_SLIDER, ['slides' => []]),
        ],
    ]);

    register_rest_route('nahtlos/v1', '/menue', [
        'methods'             => 'POST',
        'permission_callback' => $berechtigt,
        'callback'            => 'nahtlos_menue_empfangen',
    ]);
});

// ── Hauptmenü aus dem ERP ─────────────────────────────────────────────────
//
// Das ERP schickt eine flache Liste (schluessel/eltern). Daraus entsteht das echte WP-Menü
// „NAHTLOS Hauptmenü“ (bei jeder Übertragung komplett neu befüllt) an den Theme-Orten aus „orte“.
// Dynamisch bei jedem Seitenaufruf (Filter wp_nav_menu_objects):
//  - „Alle Hersteller“: Unterpunkte = alle pa_hersteller-Begriffe mit Artikeln (CSS-Klasse für Spalten)
//  - „nur wenn Artikel drin“: Punkt samt Unterpunkten weg, solange die Kategorie/der Hersteller leer ist

const NAHTLOS_MENUE_NAME = 'NAHTLOS Hauptmenü';

function nahtlos_menue_empfangen(WP_REST_Request $request)
{
    $daten = $request->get_json_params();
    if (!is_array($daten) || !isset($daten['punkte']) || !is_array($daten['punkte'])) {
        return new WP_Error('nahtlos_ungueltig', 'Menüpunkte fehlen', ['status' => 400]);
    }

    $menue = wp_get_nav_menu_object(NAHTLOS_MENUE_NAME);
    $menueId = $menue ? (int)$menue->term_id : (int)wp_create_nav_menu(NAHTLOS_MENUE_NAME);
    if (!$menueId) return new WP_Error('nahtlos_menue', 'Menü konnte nicht angelegt werden', ['status' => 500]);

    // Bisherige Einträge entfernen, dann in ERP-Reihenfolge neu anlegen
    foreach ((array)wp_get_nav_menu_items($menueId, ['post_status' => 'any']) as $alt) {
        wp_delete_post($alt->ID, true);
    }
    $dbIds = [];
    $pos = 0;
    $fehler = [];
    foreach ($daten['punkte'] as $p) {
        $schluessel = sanitize_key($p['schluessel'] ?? '');
        $eltern = isset($p['eltern']) ? sanitize_key((string)$p['eltern']) : '';
        $titel = sanitize_text_field((string)($p['titel'] ?? ''));
        $klassen = [];
        if (!empty($p['nur_mit_artikeln'])) $klassen[] = 'nahtlos-nur-mit-artikeln';
        $args = [
            'menu-item-title'     => $titel,
            'menu-item-status'    => 'publish',
            'menu-item-position'  => ++$pos,
            'menu-item-parent-id' => $eltern && isset($dbIds[$eltern]) ? $dbIds[$eltern] : 0,
        ];
        switch ($p['typ'] ?? '') {
            case 'kategorie':
            case 'hersteller':
                $tax = $p['typ'] === 'kategorie' ? 'product_cat' : 'pa_hersteller';
                $args += ['menu-item-type' => 'taxonomy', 'menu-item-object' => $tax, 'menu-item-object-id' => (int)$p['ziel']];
                break;
            case 'produkt':
                $args += ['menu-item-type' => 'post_type', 'menu-item-object' => 'product', 'menu-item-object-id' => (int)$p['ziel']];
                break;
            case 'url':
                $ziel = (string)($p['ziel'] ?? '');
                $args += ['menu-item-type' => 'custom', 'menu-item-url' => str_starts_with($ziel, '/') ? home_url($ziel) : esc_url_raw($ziel)];
                break;
            case 'hersteller_alle':
                $args += ['menu-item-type' => 'custom', 'menu-item-url' => '#'];
                $klassen[] = 'nahtlos-hersteller-alle';
                if (!empty($p['spalten'])) $klassen[] = 'nahtlos-spalten';
                break;
            default:
                continue 2;
        }
        $args['menu-item-classes'] = implode(' ', $klassen);
        $id = wp_update_nav_menu_item($menueId, 0, $args);
        if (is_wp_error($id)) { $fehler[] = $titel . ': ' . $id->get_error_message(); continue; }
        $dbIds[$schluessel] = (int)$id;
    }

    // An die Theme-Orte hängen (z. B. Blocksy menu_1); unbekannte Orte melden
    $orte = get_nav_menu_locations();
    $bekannt = array_keys(get_registered_nav_menus());
    $unbekannt = [];
    foreach ((array)($daten['orte'] ?? []) as $ort) {
        $ort = sanitize_key($ort);
        if (in_array($ort, $bekannt, true)) $orte[$ort] = $menueId; else $unbekannt[] = $ort;
    }
    set_theme_mod('nav_menu_locations', $orte);
    nahtlos_cache_leeren();

    return ['erfolg' => !$fehler, 'menue_id' => $menueId, 'punkte' => count($dbIds), 'fehler' => $fehler,
            'unbekannte_orte' => $unbekannt, 'theme_orte' => $bekannt];
}

add_filter('wp_nav_menu_objects', function ($items) {
    $hatUnsere = false;
    foreach ($items as $it) {
        if (array_intersect(['nahtlos-hersteller-alle', 'nahtlos-nur-mit-artikeln'], (array)$it->classes)) { $hatUnsere = true; break; }
    }
    if (!$hatUnsere) return $items;

    // 1. „nur wenn Artikel drin“: leere Kategorien/Hersteller samt Unterpunkten entfernen
    $weg = [];
    foreach ($items as $it) {
        if (!in_array('nahtlos-nur-mit-artikeln', (array)$it->classes, true) || $it->type !== 'taxonomy') continue;
        $term = get_term((int)$it->object_id, $it->object);
        if (!$term || is_wp_error($term) || (int)$term->count === 0) $weg[(int)$it->db_id] = true;
    }
    do {
        $neu = 0;
        foreach ($items as $it) {
            if (!isset($weg[(int)$it->db_id]) && isset($weg[(int)$it->menu_item_parent])) { $weg[(int)$it->db_id] = true; $neu++; }
        }
    } while ($neu);
    $items = array_values(array_filter($items, fn($it) => !isset($weg[(int)$it->db_id])));

    // 2. „Alle Hersteller“: Hersteller mit Artikeln als Unterpunkte einfügen (ohne Hersteller: Punkt weg)
    $ergebnis = [];
    foreach ($items as $it) {
        if (!in_array('nahtlos-hersteller-alle', (array)$it->classes, true)) { $ergebnis[] = $it; continue; }
        $terms = get_terms(['taxonomy' => 'pa_hersteller', 'hide_empty' => true, 'orderby' => 'name']);
        if (is_wp_error($terms) || !$terms) continue;
        $it->classes[] = 'menu-item-has-children';
        $ergebnis[] = $it;
        foreach ($terms as $t) {
            $url = get_term_link($t);
            if (is_wp_error($url)) continue;
            $k = clone $it;
            $k->ID = $k->db_id = 2000000000 + (int)$t->term_id; // virtuelle ID, kollidiert nicht mit echten Beiträgen
            $k->menu_item_parent = (string)$it->db_id;
            $k->title = $t->name;
            $k->url = $url;
            $k->type = 'taxonomy';
            $k->object = 'pa_hersteller';
            $k->object_id = (int)$t->term_id;
            $k->classes = ['menu-item', 'nahtlos-hersteller'];
            $k->current = is_tax('pa_hersteller', $t->term_id);
            $ergebnis[] = $k;
        }
    }
    return $ergebnis;
}, 20);

/** Nimmt die Slide-Liste vom ERP an, bereinigt sie und ersetzt den bisherigen Stand komplett. */
function nahtlos_slider_empfangen(WP_REST_Request $request)
{
    $daten = $request->get_json_params();
    if (!is_array($daten) || !isset($daten['slides']) || !is_array($daten['slides'])) {
        return new WP_Error('nahtlos_ungueltig', 'Slide-Liste fehlt', ['status' => 400]);
    }

    $text = fn($v, $max = 200) => $v === null ? '' : mb_substr(sanitize_text_field((string)$v), 0, $max);
    // ISO-8601 mit Zeitzone → Unix-Zeit; ohne Zeitzone gilt die WordPress-Zeitzone
    $zeit = function ($v) {
        if (!is_string($v) || $v === '') return null;
        try { return (new DateTimeImmutable($v, wp_timezone()))->getTimestamp(); } catch (Exception $e) { return null; }
    };
    // Bild: URL vom ERP-Upload, oder nur Medien-ID (Kategoriebild aus der Mediathek) → URL hier ermitteln
    $bild = function ($b) {
        if (!is_array($b)) return null;
        $id = (int)($b['id'] ?? 0);
        $url = !empty($b['url']) ? esc_url_raw($b['url']) : ($id ? (string)wp_get_attachment_image_url($id, 'large') : '');
        return $url ? ['id' => $id, 'url' => $url] : null;
    };

    $slides = [];
    foreach ($daten['slides'] as $s) {
        if (!is_array($s) || !($b = $bild($s['bild'] ?? null))) continue;
        $buttons = [];
        foreach (array_slice((array)($s['buttons'] ?? []), 0, 2) as $btn) {
            $typ = in_array($btn['typ'] ?? '', ['kategorie', 'produkt', 'hersteller', 'url'], true) ? $btn['typ'] : null;
            if (!$typ) continue;
            $buttons[] = [
                'text' => $text($btn['text'] ?? '', 60),
                'typ'  => $typ,
                'ziel' => $typ === 'url' ? $text($btn['ziel'] ?? '', 255) : (int)($btn['ziel'] ?? 0),
            ];
        }
        $slides[] = [
            'id'            => (int)($s['id'] ?? 0),
            'position'      => in_array($s['position'] ?? '', ['kategorie', 'kachel'], true) ? $s['position'] : 'startseite',
            'kategorie'     => isset($s['kategorie']) ? (int)$s['kategorie'] : null,
            'art'           => ($s['art'] ?? '') === 'fertiges_bild' ? 'fertiges_bild' : 'gestaltet',
            'kicker'        => $text($s['kicker'] ?? '', 80),
            'titel'         => $text($s['titel'] ?? '', 150),
            'text'          => mb_substr(sanitize_textarea_field((string)($s['text'] ?? '')), 0, 400),
            'buttons'       => $buttons,
            'text_position' => in_array($s['text_position'] ?? '', ['links', 'mitte', 'rechts'], true) ? $s['text_position'] : 'links',
            'schrift'       => ($s['schrift'] ?? '') === 'hell' ? 'hell' : 'dunkel',
            'bild'          => $b,
            'bild_mobil'    => $bild($s['bild_mobil'] ?? null),
            'alt'           => $text($s['alt'] ?? '', 150),
            'von'           => $zeit($s['von'] ?? null),
            'bis'           => $zeit($s['bis'] ?? null),
        ];
    }

    update_option(NAHTLOS_OPTION_SLIDER, [
        'intervall' => max(3, min(20, (int)($daten['intervall'] ?? 6))),
        'stand'     => $text($daten['stand'] ?? '', 20),
        'slides'    => $slides,
    ], false);

    $geplant = nahtlos_wechsel_planen($slides);
    nahtlos_cache_leeren();

    return ['erfolg' => true, 'slides' => count($slides), 'wechsel_geplant' => $geplant];
}

// ── Zeitsteuerung + Cache ─────────────────────────────────────────────────

/** Plant zu jedem künftigen von/bis ein Cache-Leeren (alte Planungen werden ersetzt). */
function nahtlos_wechsel_planen(array $slides): int
{
    wp_unschedule_hook('nahtlos_slider_wechsel'); // alle Planungen, egal mit welchem Zeitstempel-Argument
    $jetzt = time();
    $zeiten = [];
    foreach ($slides as $s) {
        foreach (['von', 'bis'] as $k) {
            if (!$s[$k]) continue;
            // bis = letzte sichtbare Sekunde → eine Sekunde später wechseln
            $ts = (int)$s[$k] + ($k === 'bis' ? 1 : 0);
            if ($ts > $jetzt) $zeiten[$ts] = true;
        }
    }
    foreach (array_keys($zeiten) as $ts) {
        wp_schedule_single_event($ts, 'nahtlos_slider_wechsel', [$ts]);
    }
    return count($zeiten);
}

add_action('nahtlos_slider_wechsel', 'nahtlos_cache_leeren');

/** Leert gängige Seiten-Caches, damit geänderte Slides sofort sichtbar sind. */
function nahtlos_cache_leeren(): void
{
    if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();          // WP Super Cache
    if (function_exists('rocket_clean_domain')) rocket_clean_domain();            // WP Rocket
    if (function_exists('w3tc_flush_all')) w3tc_flush_all();                      // W3 Total Cache
    do_action('litespeed_purge_all');                                             // LiteSpeed Cache
}

/** Slides einer Position, die noch nicht abgelaufen sind (künftige inklusive, siehe Kopf). */
function nahtlos_aktuelle_slides(string $position, ?int $kategorie = null): array
{
    $daten = get_option(NAHTLOS_OPTION_SLIDER, ['slides' => []]);
    $jetzt = time();
    return array_values(array_filter($daten['slides'] ?? [], function ($s) use ($position, $kategorie, $jetzt) {
        if ($s['position'] !== $position) return false;
        if ($position === 'kategorie' && (int)$s['kategorie'] !== (int)$kategorie) return false;
        if ($s['bis'] && (int)$s['bis'] < $jetzt) return false;
        return true;
    }));
}

// ── Ausgabe ───────────────────────────────────────────────────────────────

add_shortcode('nahtlos_slider', function ($atts) {
    $atts = shortcode_atts(['position' => 'startseite'], $atts);
    return nahtlos_slider_html(nahtlos_aktuelle_slides($atts['position'] === 'kategorie' ? 'kategorie' : 'startseite'), 'startseite');
});

add_shortcode('nahtlos_kacheln', function () {
    return nahtlos_kacheln_html(nahtlos_aktuelle_slides('kachel'));
});

// Banner oben auf Kategorieseiten (nur wenn es für diese Kategorie Slides gibt)
add_action('woocommerce_before_main_content', function () {
    if (!is_product_category()) return;
    $term = get_queried_object();
    if (!$term instanceof WP_Term) return;
    echo nahtlos_slider_html(nahtlos_aktuelle_slides('kategorie', (int)$term->term_id), 'kategorie');
}, 25);

function nahtlos_ziel_url(array $btn): string
{
    $url = match ($btn['typ']) {
        'kategorie'  => get_term_link((int)$btn['ziel'], 'product_cat'),
        'hersteller' => get_term_link((int)$btn['ziel'], 'pa_hersteller'),
        'produkt'    => get_permalink((int)$btn['ziel']),
        'url'        => str_starts_with((string)$btn['ziel'], '/') ? home_url($btn['ziel']) : $btn['ziel'],
        default      => '',
    };
    return is_string($url) ? $url : '';
}

function nahtlos_slider_html(array $slides, string $art): string
{
    if (!$slides) return '';
    nahtlos_assets();
    $daten = get_option(NAHTLOS_OPTION_SLIDER, []);
    $intervall = (int)($daten['intervall'] ?? 6);
    $mehrere = count($slides) > 1;
    $jetzt = time();
    $sichtbar = fn($s) => !$s['von'] || (int)$s['von'] <= $jetzt;
    $erster = 0; // erste jetzt sichtbare Slide = aktiv (+ h1 auf der Startseite)
    foreach ($slides as $i => $s) { if ($sichtbar($s)) { $erster = $i; break; } }

    ob_start(); ?>
    <section class="nahtlos-slider nahtlos-slider--<?php echo esc_attr($art); ?>" aria-roledescription="carousel" aria-label="<?php esc_attr_e('Aktuelles', 'nahtlos-connector'); ?>" data-intervall="<?php echo $intervall; ?>">
        <div class="nahtlos-slider__spur">
        <?php foreach ($slides as $i => $s):
            $klassen = 'nahtlos-slide nahtlos-slide--' . $s['text_position'] . ' nahtlos-slide--' . $s['schrift'] . ($s['art'] === 'fertiges_bild' ? ' nahtlos-slide--fertig' : '') . ($s['bild_mobil'] ? ' nahtlos-slide--mobilbild' : '') . ($i === $erster ? ' ist-aktiv' : '') . ($sichtbar($s) ? '' : ' ist-geplant');
            $bild = '<picture>'
                . ($s['bild_mobil'] ? '<source media="(max-width: 767px)" srcset="' . esc_url($s['bild_mobil']['url']) . '">' : '')
                . '<img src="' . esc_url($s['bild']['url']) . '" alt="' . esc_attr($s['alt']) . '"' . ($i === $erster ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">'
                . '</picture>';
            $link = $s['buttons'] ? nahtlos_ziel_url($s['buttons'][0]) : '';
        ?>
            <div class="<?php echo esc_attr($klassen); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo ($i + 1) . ' / ' . count($slides); ?>" data-von="<?php echo (int)$s['von']; ?>" data-bis="<?php echo (int)$s['bis']; ?>"<?php echo $i !== $erster ? ' aria-hidden="true"' : ''; ?>>
                <?php if ($s['art'] === 'fertiges_bild'): ?>
                    <?php echo $link ? '<a class="nahtlos-slide__bild" href="' . esc_url($link) . '">' . $bild . '</a>' : '<div class="nahtlos-slide__bild">' . $bild . '</div>'; ?>
                <?php else: ?>
                    <div class="nahtlos-slide__bild"><?php echo $bild; ?></div>
                    <div class="nahtlos-slide__inhalt">
                        <?php if ($s['kicker']): ?><p class="nahtlos-slide__kicker"><?php echo esc_html($s['kicker']); ?></p><?php endif; ?>
                        <?php $tag = $art === 'startseite' && $i === $erster ? 'h1' : 'h2'; // erste Startseiten-Slide = Seitenüberschrift ?>
                        <<?php echo $tag; ?> class="nahtlos-slide__titel"><?php echo esc_html($s['titel']); ?></<?php echo $tag; ?>>
                        <?php if ($s['text']): ?><p class="nahtlos-slide__text"><?php echo nl2br(esc_html($s['text'])); ?></p><?php endif; ?>
                        <?php if ($s['buttons']): ?>
                            <div class="nahtlos-slide__buttons">
                            <?php foreach ($s['buttons'] as $n => $btn): $url = nahtlos_ziel_url($btn); if (!$url || $btn['text'] === '') continue; ?>
                                <a class="<?php echo $n === 0 ? 'nahtlos-btn wp-element-button' : 'nahtlos-link'; ?>" href="<?php echo esc_url($url); ?>"<?php echo $i !== $erster ? ' tabindex="-1"' : ''; ?>><?php echo esc_html($btn['text']); ?></a>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <?php if ($mehrere): ?>
            <button type="button" class="nahtlos-slider__pfeil nahtlos-slider__pfeil--zurueck" aria-label="<?php esc_attr_e('Vorherige Slide', 'nahtlos-connector'); ?>">&#8249;</button>
            <button type="button" class="nahtlos-slider__pfeil nahtlos-slider__pfeil--weiter" aria-label="<?php esc_attr_e('Nächste Slide', 'nahtlos-connector'); ?>">&#8250;</button>
            <div class="nahtlos-slider__punkte">
                <?php foreach ($slides as $i => $s): ?>
                    <button type="button" class="nahtlos-slider__punkt<?php echo ($i === $erster ? ' ist-aktiv' : '') . ($sichtbar($s) ? '' : ' ist-geplant'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Slide %d', 'nahtlos-connector'), $i + 1)); ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

// CSS/JS schon im <head> laden, wo der Slider erscheinen kann (sonst kurz ungestylt)
// Menü-CSS (Hersteller in Spalten) auf allen Seiten — das Menü ist überall
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('nahtlos-menue', plugin_dir_url(__FILE__) . 'assets/menue.css', [], NAHTLOS_VERSION);
});

add_action('wp_enqueue_scripts', function () {
    $inhalt = is_singular() ? (string)get_post_field('post_content', get_queried_object_id()) : '';
    if (is_front_page() || (function_exists('is_product_category') && is_product_category())
        || has_shortcode($inhalt, 'nahtlos_slider') || has_shortcode($inhalt, 'nahtlos_kacheln')) {
        nahtlos_assets();
    }
});

/** Kategorie-Kacheln: Bild, kleine Zeile, Titel; die ganze Kachel ist der Link. Zeitsteuerung wie beim Slider (slider.js). */
function nahtlos_kacheln_html(array $kacheln): string
{
    if (!$kacheln) return '';
    nahtlos_assets();
    $jetzt = time();
    $html = '<div class="nahtlos-kacheln">';
    foreach ($kacheln as $k) {
        $link = $k['buttons'] ? nahtlos_ziel_url($k['buttons'][0]) : '';
        $geplant = $k['von'] && (int)$k['von'] > $jetzt ? ' ist-geplant' : '';
        $html .= '<a class="nahtlos-kachel' . $geplant . '" href="' . esc_url($link ?: '#') . '" data-von="' . (int)$k['von'] . '" data-bis="' . (int)$k['bis'] . '">'
            . '<span class="nahtlos-kachel__bild"><img src="' . esc_url($k['bild']['url']) . '" alt="' . esc_attr($k['alt']) . '" loading="lazy" decoding="async"></span>'
            . '<span class="nahtlos-kachel__text">'
            . ($k['kicker'] ? '<span class="nahtlos-kachel__kicker">' . esc_html($k['kicker']) . '</span>' : '')
            . '<span class="nahtlos-kachel__titel">' . esc_html($k['titel']) . '</span></span></a>';
    }
    return $html . '</div>';
}

function nahtlos_assets(): void
{
    static $geladen = false;
    if ($geladen) return;
    $geladen = true;
    $basis = plugin_dir_url(__FILE__);
    wp_enqueue_style('nahtlos-slider', $basis . 'assets/slider.css', [], NAHTLOS_VERSION);
    wp_enqueue_script('nahtlos-slider', $basis . 'assets/slider.js', [], NAHTLOS_VERSION, true);
}

// Beim Deaktivieren geplante Wechsel entfernen (die Slides selbst bleiben gespeichert)
register_deactivation_hook(__FILE__, fn() => wp_unschedule_hook('nahtlos_slider_wechsel'));
