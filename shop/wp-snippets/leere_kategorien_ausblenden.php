<?php
/**
 * WPCode-Snippet #32095 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

add_filter('wp_get_nav_menu_items', 'nav_remove_empty_category_menu_item', 10, 3);
function nav_remove_empty_category_menu_item($items, $menu, $args) {
    // Menü-Kinder nach Eltern-MENÜPUNKT-ID gruppieren -- visuelle Verschachtelung
    // im Menü-Editor (z.B. "Handarbeitstechnik" -> Häkeln/Sticken/...).
    $kinderNachEltern = [];
    foreach ($items as $item) {
        $kinderNachEltern[(int)$item->menu_item_parent][] = $item;
    }

    $termCache = [];
    // Prüft eine Kategorie samt ALLER echten Taxonomie-Unterkategorien -- deckt
    // Fälle wie "Wolle und Garne" ab, dessen echte Unterkategorien (z.B.
    // "Sockenwolle") gar keinen eigenen Menüpunkt haben.
    $termHatProdukte = function (int $termId) use (&$termHatProdukte, &$termCache) {
        if (isset($termCache[$termId])) {
            return $termCache[$termId];
        }
        $termCache[$termId] = true; // Zyklenschutz, während der Prüfung optimistisch
        $term = get_term($termId, 'product_cat');
        if (is_wp_error($term) || !$term) {
            return $termCache[$termId] = true; // im Zweifel behalten
        }
        if ((int)$term->count > 0) {
            return $termCache[$termId] = true;
        }
        $kinder = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => $termId,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        $ergebnis = false;
        if (!is_wp_error($kinder)) {
            foreach ($kinder as $kindId) {
                if ($termHatProdukte((int)$kindId)) {
                    $ergebnis = true;
                    break;
                }
            }
        }
        return $termCache[$termId] = $ergebnis;
    };

    $itemCache = [];
    // Prüft einen Menüpunkt: eigene Kategorie (samt Taxonomie-Unterbau) ODER
    // irgendein im MENÜ darunter eingerückter Menüpunkt mit Inhalt -- deckt
    // Fälle wie "Handarbeitstechnik" ab, das nur menü-seitig verschachtelt ist.
    $itemHatInhalt = function ($item) use (&$itemHatInhalt, &$kinderNachEltern, &$itemCache, &$termHatProdukte) {
        if (isset($itemCache[$item->ID])) {
            return $itemCache[$item->ID];
        }
        $itemCache[$item->ID] = true; // Zyklenschutz
        $ergebnis = true;
        if ($item->object === 'product_cat') {
            $ergebnis = $termHatProdukte((int)$item->object_id);
        }
        if (!$ergebnis) {
            foreach ($kinderNachEltern[$item->ID] ?? [] as $kind) {
                if ($itemHatInhalt($kind)) {
                    $ergebnis = true;
                    break;
                }
            }
        }
        return $itemCache[$item->ID] = $ergebnis;
    };

    foreach ($items as $key => $item) {
        if ($item->object === 'product_cat' && !$itemHatInhalt($item)) {
            unset($items[$key]);
        }
    }
    return array_values($items);
}
