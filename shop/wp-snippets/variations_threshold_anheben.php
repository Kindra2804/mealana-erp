<?php
/**
 * WPCode-Snippet #34174 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

add_filter( 'woocommerce_ajax_variation_threshold', function () {
    // WooCommerce laedt Variations-Auswahl bei mehr als diesem Schwellwert nicht
    // mehr als fertiges JSON inline, sondern per AJAX nach jeder Auswahl -- fuehrt
    // bei Artikeln mit vielen Kombinationen (z.B. Rundnadeln, DMC-Garnfarben, aktuell
    // bis zu 499) zu "Auswahl nicht moeglich" bis genug Achsen gesetzt sind. Schwelle
    // grosszuegig angehoben, deckt den aktuell groessten Fall mit Reserve ab.
    return 1000;
} );
