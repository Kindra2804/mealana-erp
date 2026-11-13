<?php
/**
 * WPCode-Snippet (PHP, überall ausführen) – Unterkategorien getrennt von den Produkten anzeigen.
 * Für indra-design.at, sockenwolle-online.at, bio-wolle.at (Blocksy, klassisches Woo-Archiv).
 * 2026-11-13, Jacky: In Kategorien mit Unterkategorien standen die Unterkategorie-Kacheln in derselben
 * Zeile wie die ersten Produkte. Jetzt: oben eine eigene Zeile nur mit Unterkategorien, darunter eine
 * Trennlinie, dann die Produkte.
 *
 * Technik: WooCommerce hängt die Unterkategorien per Filter woocommerce_product_loop_start in das
 * Produkt-<ul>. Den Filter entfernen und dieselbe Ausgabe (woocommerce_maybe_show_product_subcategories,
 * beachtet die Woo-Einstellung "Kategorieanzeige" und zeigt nur auf Seite 1) vor der Produktliste in
 * einer eigenen Liste ausgeben. Quelle der Wahrheit bleibt WPCode, diese Datei ist die Repo-Kopie.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
	remove_filter( 'woocommerce_product_loop_start', 'woocommerce_maybe_show_product_subcategories' );
} );

// nach Ergebnis-Anzahl/Sortierung (Priorität 20/30), direkt vor der Produktliste
add_action( 'woocommerce_before_shop_loop', function () {
	if ( ! function_exists( 'woocommerce_maybe_show_product_subcategories' ) ) return;
	$kategorien = woocommerce_maybe_show_product_subcategories( '' );
	if ( trim( $kategorien ) === '' ) return;
	// Gerüst über WooCommerce selbst, damit das Theme (Blocksy: data-products="type-1") sein Raster setzt
	echo '<div class="nahtlos-unterkategorien" style="clear:both">';
	echo woocommerce_product_loop_start( false ) . $kategorien . woocommerce_product_loop_end( false );
	echo '</div>';
	echo '<hr class="nahtlos-kat-trenner" style="clear:both;border:0;border-top:1px solid rgba(0,0,0,.15);margin:8px 0 32px">';
}, 40 );
