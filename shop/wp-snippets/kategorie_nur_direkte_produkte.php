<?php
/**
 * WPCode-Snippet (PHP, überall ausführen) – nur auf indra-design.at.
 * 2026-11-13, Jacky: Auf der Kategorieseite "Schilder" sollen nur die Layouts stehen. Die fertigen Schilder
 * (Unterkategorie "Fertige Schilder") erscheinen nur dort -- WooCommerce zeigt sonst die Produkte aller
 * Unterkategorien auch in der Oberkategorie.
 *
 * Bewusst nur für die hier gelisteten Kategorie-Slugs: In Kategorien, deren Produkte NUR in Unterkategorien
 * stehen (z.B. Wolle bei SOW/bio-wolle), wäre die Oberseite sonst leer.
 * Quelle der Wahrheit bleibt WPCode, diese Datei ist die Repo-Kopie.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'parse_tax_query', function ( $query ) {
	$nur_direkt = array( 'schilder' );   // Slugs der Kategorien ohne Produkte aus Unterkategorien
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_tax( 'product_cat' ) || empty( $query->tax_query->queries ) ) return;
	foreach ( $query->tax_query->queries as $i => $tq ) {
		if ( ! is_array( $tq ) || ( $tq['taxonomy'] ?? '' ) !== 'product_cat' ) continue;
		if ( array_intersect( (array) $tq['terms'], $nur_direkt ) ) {
			$query->tax_query->queries[ $i ]['include_children'] = false;
		}
	}
} );
