<?php
/**
 * WPCode-Snippet "MeaLana: Gutschein (Kauf-Formular + Einlöse-Hinweis)" auf indra-design.at
 * (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Quelle der Wahrheit bleibt WPCode (wp-admin) -- diese Datei ist eine manuell
 * aktualisierte Kopie fuers Repo, kein automatischer Sync.
 *
 * Gegenstuecke im ERP:
 * - ShopSyncService::baueProduktPayload(): Gutschein-Artikel bekommt Meta
 *   _mealana_gutschein_artikel {"mindestbetrag":10}, ist virtuell + steuerfrei
 * - ShopBestellungSyncService::verarbeiteGutscheinKauf(): liest _mealana_gutschein
 *   (JSON) von der Bestellzeile und erzeugt daraus den echten Gutschein-Code + PDF + Mail
 * - GutscheinService::spiegleZuWooCommerce(): jeder ERP-Gutschein ist im Shop ein
 *   Germanized-"Wertgutschein" (is_voucher=yes, free_shipping=Versand inklusive) --
 *   Germanized zieht ihn NACH Steuer als negative Gebuehr ab. Dieses Snippet
 *   benennt nur die Gebuehr um und haengt den Restguthaben-Hinweis an.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Gutschein-Einstellungen eines Produkts (null = kein Gutschein-Artikel). */
function mealana_gs_artikel( $product_id ) {
	$json = get_post_meta( $product_id, '_mealana_gutschein_artikel', true );
	if ( empty( $json ) ) return null;
	$daten = json_decode( $json, true );
	if ( ! is_array( $daten ) ) return null;
	$daten['mindestbetrag'] = max( 1, (float) ( $daten['mindestbetrag'] ?? 10 ) );
	$daten['hoechstbetrag'] = 1000;
	return $daten;
}

/** Liest + prueft die Formularwerte. Gibt [daten, fehler[]] zurueck. */
function mealana_gs_formular_lesen( array $gs ) {
	$p = function ( $k ) { return isset( $_POST[ $k ] ) ? trim( wp_unslash( $_POST[ $k ] ) ) : ''; };
	$betrag = round( (float) str_replace( ',', '.', $p( 'mealana_gs_betrag' ) ), 2 );
	$versandart = $p( 'mealana_gs_versandart' ) === 'versenden' ? 'versenden' : 'selbst_ausdrucken';
	$d = array(
		'betrag'           => $betrag,
		'versandart'       => $versandart,
		'empfaenger_name'  => sanitize_text_field( $p( 'mealana_gs_empfaenger_name' ) ),
		'empfaenger_email' => $versandart === 'versenden' ? sanitize_email( $p( 'mealana_gs_empfaenger_email' ) ) : '',
		'zustellung_am'    => $versandart === 'versenden' ? sanitize_text_field( $p( 'mealana_gs_zustellung_am' ) ) : '',
		'grusstext'        => sanitize_textarea_field( mb_substr( $p( 'mealana_gs_grusstext' ), 0, 500 ) ),
	);
	$fehler = array();
	if ( $betrag < $gs['mindestbetrag'] || $betrag > $gs['hoechstbetrag'] ) {
		$fehler[] = sprintf( 'Bitte einen Gutscheinbetrag zwischen %s und %s wählen.', wc_price( $gs['mindestbetrag'] ), wc_price( $gs['hoechstbetrag'] ) );
	}
	if ( $versandart === 'versenden' ) {
		if ( $d['empfaenger_name'] === '' ) $fehler[] = 'Bitte den Namen des Empfängers angeben.';
		if ( ! is_email( $d['empfaenger_email'] ) ) $fehler[] = 'Bitte eine gültige E-Mail-Adresse des Empfängers angeben.';
		if ( $d['zustellung_am'] !== '' ) {
			$ts = strtotime( $d['zustellung_am'] );
			if ( ! $ts || $d['zustellung_am'] < current_time( 'Y-m-d' ) || $ts > strtotime( '+1 year' ) ) {
				$fehler[] = 'Das Zustelldatum muss zwischen heute und in einem Jahr liegen.';
			} else {
				$d['zustellung_am'] = date( 'Y-m-d', $ts );
				// "heute" = sofort versenden (kein geplanter Versand noetig)
				if ( $d['zustellung_am'] === current_time( 'Y-m-d' ) ) $d['zustellung_am'] = '';
			}
		}
	}
	return array( $d, $fehler );
}

/** Preisanzeige: "ab 10,00 € – Betrag frei wählbar" statt eines festen Preises. */
add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	$gs = mealana_gs_artikel( $product->get_id() );
	if ( ! $gs ) return $html;
	return 'ab ' . wc_price( $gs['mindestbetrag'] ) . ' <small>– Betrag frei wählbar</small>';
}, 20, 2 );

/** Kauf-Formular oberhalb des "In den Warenkorb"-Buttons. */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product ) return;
	$gs = mealana_gs_artikel( $product->get_id() );
	if ( ! $gs ) return;
	$min = $gs['mindestbetrag'];
	$heute = current_time( 'Y-m-d' );
	?>
	<div id="mealana-gs" style="margin:0 0 18px;max-width:420px">
		<p style="margin:0 0 6px"><label for="mealana_gs_betrag" style="font-weight:600">Gewünschter Betrag (€) *</label></p>
		<input type="number" id="mealana_gs_betrag" name="mealana_gs_betrag" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $gs['hoechstbetrag'] ); ?>"
		       step="0.01" value="<?php echo esc_attr( number_format( $min, 2, '.', '' ) ); ?>" required style="width:100%;margin-bottom:6px">
		<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px">
			<?php foreach ( array( 20, 30, 50, 100 ) as $b ) : if ( $b < $min ) continue; ?>
				<button type="button" class="button mealana-gs-schnell" data-betrag="<?php echo (int) $b; ?>" style="padding:4px 12px"><?php echo (int) $b; ?> €</button>
			<?php endforeach; ?>
		</div>

		<p style="margin:0 0 6px"><label for="mealana_gs_versandart" style="font-weight:600">Gutschein versenden</label></p>
		<select id="mealana_gs_versandart" name="mealana_gs_versandart" style="width:100%;margin-bottom:14px">
			<option value="selbst_ausdrucken">Selbst ausdrucken (Gutschein kommt per E-Mail an mich)</option>
			<option value="versenden">Direkt per E-Mail an den Empfänger senden</option>
		</select>

		<p style="margin:0 0 6px"><label for="mealana_gs_empfaenger_name" style="font-weight:600">Name des Empfängers <span class="mealana-gs-pflicht" hidden>*</span></label></p>
		<input type="text" id="mealana_gs_empfaenger_name" name="mealana_gs_empfaenger_name" maxlength="150" style="width:100%;margin-bottom:14px" placeholder="erscheint auf dem Gutschein">

		<div id="mealana-gs-versenden" hidden>
			<p style="margin:0 0 6px"><label for="mealana_gs_empfaenger_email" style="font-weight:600">E-Mail des Empfängers *</label></p>
			<input type="email" id="mealana_gs_empfaenger_email" name="mealana_gs_empfaenger_email" maxlength="150" style="width:100%;margin-bottom:14px">
			<p style="margin:0 0 6px"><label for="mealana_gs_zustellung_am" style="font-weight:600">Zustellung am</label> <small>(leer = sofort nach Zahlungseingang)</small></p>
			<input type="date" id="mealana_gs_zustellung_am" name="mealana_gs_zustellung_am" min="<?php echo esc_attr( $heute ); ?>" style="width:100%;margin-bottom:14px">
		</div>

		<p style="margin:0 0 6px"><label for="mealana_gs_grusstext" style="font-weight:600">Ihr Grußtext</label> <small>(optional)</small></p>
		<textarea id="mealana_gs_grusstext" name="mealana_gs_grusstext" rows="3" maxlength="500" style="width:100%"></textarea>
	</div>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var wrap = document.getElementById('mealana-gs');
		if (!wrap) return;
		var art = document.getElementById('mealana_gs_versandart');
		var box = document.getElementById('mealana-gs-versenden');
		var betrag = document.getElementById('mealana_gs_betrag');
		function umschalten() {
			var versenden = art.value === 'versenden';
			box.hidden = !versenden;
			document.getElementById('mealana_gs_empfaenger_email').required = versenden;
			document.getElementById('mealana_gs_empfaenger_name').required = versenden;
			wrap.querySelector('.mealana-gs-pflicht').hidden = !versenden;
		}
		art.addEventListener('change', umschalten);
		umschalten();
		wrap.querySelectorAll('.mealana-gs-schnell').forEach(function (b) {
			b.addEventListener('click', function () { betrag.value = parseFloat(b.dataset.betrag).toFixed(2); });
		});
	});
	</script>
	<?php
}, 25 );

/** Serverseitige Pruefung beim In-den-Warenkorb-Legen. */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id ) {
	$gs = mealana_gs_artikel( $product_id );
	if ( ! $gs ) return $passed;
	if ( ! isset( $_POST['mealana_gs_betrag'] ) ) {
		wc_add_notice( 'Bitte den Gutscheinbetrag auf der Produktseite wählen.', 'error' );
		return false;
	}
	list( , $fehler ) = mealana_gs_formular_lesen( $gs );
	foreach ( $fehler as $f ) wc_add_notice( $f, 'error' );
	return $passed && empty( $fehler );
}, 10, 2 );

/** Formularwerte am Warenkorb-Eintrag speichern; jeder Gutschein bleibt eine eigene Zeile. */
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id ) {
	$gs = mealana_gs_artikel( $product_id );
	if ( ! $gs || ! isset( $_POST['mealana_gs_betrag'] ) ) return $cart_item_data;
	list( $d, $fehler ) = mealana_gs_formular_lesen( $gs );
	if ( $fehler ) return $cart_item_data;
	$cart_item_data['mealana_gutschein'] = $d;
	$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
	return $cart_item_data;
}, 10, 2 );

/** Preis = gewaehlter Betrag (serverseitig aus den gespeicherten, geprueften Werten). */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	foreach ( $cart->get_cart() as $item ) {
		if ( ! empty( $item['mealana_gutschein']['betrag'] ) ) {
			$item['data']->set_price( (float) $item['mealana_gutschein']['betrag'] );
		}
	}
}, 20, 1 );

/** Angaben im Warenkorb/Checkout anzeigen. */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	if ( empty( $cart_item['mealana_gutschein'] ) ) return $item_data;
	$d = $cart_item['mealana_gutschein'];
	if ( $d['empfaenger_name'] !== '' ) $item_data[] = array( 'key' => 'Für', 'value' => $d['empfaenger_name'] );
	if ( $d['versandart'] === 'versenden' ) {
		$item_data[] = array( 'key' => 'Versand an', 'value' => $d['empfaenger_email'] );
		$item_data[] = array( 'key' => 'Zustellung', 'value' => $d['zustellung_am'] !== '' ? date_i18n( 'd.m.Y', strtotime( $d['zustellung_am'] ) ) : 'sofort nach Zahlungseingang' );
	} else {
		$item_data[] = array( 'key' => 'Versand', 'value' => 'per E-Mail an Sie zum Ausdrucken' );
	}
	if ( $d['grusstext'] !== '' ) $item_data[] = array( 'key' => 'Grußtext', 'value' => $d['grusstext'] );
	return $item_data;
}, 10, 2 );

/** Bestellzeile: _mealana_gutschein-JSON fuers ERP + lesbare Angaben fuer Kunde/Admin. */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['mealana_gutschein'] ) ) return;
	$d = $values['mealana_gutschein'];
	$item->add_meta_data( '_mealana_gutschein', wp_json_encode( array(
		'version'          => 1,
		'betrag'           => (float) $d['betrag'],
		'versandart'       => $d['versandart'],
		'empfaenger_name'  => $d['empfaenger_name'] !== '' ? $d['empfaenger_name'] : null,
		'empfaenger_email' => $d['empfaenger_email'] !== '' ? $d['empfaenger_email'] : null,
		'zustellung_am'    => $d['zustellung_am'] !== '' ? $d['zustellung_am'] : null,
		'grusstext'        => $d['grusstext'] !== '' ? $d['grusstext'] : null,
	) ) );
	if ( $d['empfaenger_name'] !== '' ) $item->add_meta_data( 'Für', $d['empfaenger_name'] );
	if ( $d['versandart'] === 'versenden' ) {
		$item->add_meta_data( 'Versand an', $d['empfaenger_email'] );
		if ( $d['zustellung_am'] !== '' ) $item->add_meta_data( 'Zustellung am', date_i18n( 'd.m.Y', strtotime( $d['zustellung_am'] ) ) );
	}
}, 10, 4 );

/** Gutscheine koennen nicht mit einem Gutschein bezahlt werden (wie an der Kasse). */
add_filter( 'woocommerce_coupon_is_valid', function ( $valid, $coupon ) {
	if ( ! $valid || $coupon->get_meta( 'is_voucher', true ) !== 'yes' || ! WC()->cart ) return $valid;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( ! empty( $item['mealana_gutschein'] ) ) {
			throw new Exception( 'Gutscheine können nicht mit einem Gutschein bezahlt werden.' );
		}
	}
	return $valid;
}, 20, 2 );

/** Germanized-Gebuehr "Wertgutschein: mea-xxxx" -> "Gutschein MEA-XXXX". */
add_filter( 'woocommerce_gzd_voucher_name', function ( $name, $code ) {
	return 'Gutschein ' . strtoupper( $code );
}, 10, 2 );

/**
 * Teileinloesung: deckt der Gutschein mehr als diese Bestellung (inkl. Versand), bekommt
 * die Gebuehr den Hinweis auf den Restbetrag. Der Rest kommt nach der Bestellung als NEUER
 * Code per Mail (der alte Code ist einmalig -- usage_limit=1, siehe ERP GutscheinService).
 * Laeuft nach jeder Summenberechnung, die Gebuehren werden davor immer frisch angelegt.
 */
add_action( 'woocommerce_after_calculate_totals', function ( $cart ) {
	foreach ( $cart->get_fees() as $fee ) {
		if ( strpos( (string) $fee->id, 'voucher_' ) !== 0 ) continue;
		$code = substr( $fee->id, strlen( 'voucher_' ) );
		$coupon = new WC_Coupon( $code );
		if ( ! $coupon->get_id() || $coupon->get_meta( 'is_voucher', true ) !== 'yes' ) continue;
		$rest = round( (float) $coupon->get_amount() - abs( (float) $fee->total ), 2 );
		if ( $rest > 0.009 && strpos( $fee->name, 'Rest' ) === false ) {
			$fee->name .= ' – Rest ' . html_entity_decode( wp_strip_all_tags( wc_price( $rest ) ) ) . ' kommt per E-Mail als neuer Code';
		}
	}
}, 20 );
