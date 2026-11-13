<?php
/**
 * WPCode-Snippet #34170 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29; 2026-11-11 um Verfuegbarkeit (ERP 1.0.4, Migr. 232) ergaenzt;
 * 2026-11-13 Shop-Uebersicht "Konfiguration waehlen" + Ablehnen ohne Konfiguration. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

/**
 * MeaLana Konfigurator - Options-Picker fuer Produkte mit _mealana_konfigurator-Meta.
 * Gegenstueck zum ERP-Sync (ShopSyncService::baueKonfiguratorFelder()) und zum
 * Bestellungs-Rueckweg (ShopBestellungSyncService::leseKonfigurationAusLineItem()).
 *
 * Preisregel (MUSS mit KonfiguratorService::berechnePreis() im ERP uebereinstimmen):
 * Gesamtpreis = basis_brutto + Summe gesamt_aufpreis der gewaehlten Werte.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Liest + dekodiert die Preis-Matrix eines Produkts. Gibt null zurueck wenn nicht konfigurierbar. */
function mealana_konfig_matrix( $product_id ) {
	$json = get_post_meta( $product_id, '_mealana_konfigurator', true );
	if ( empty( $json ) ) return null;
	$daten = json_decode( $json, true );
	if ( ! is_array( $daten ) || empty( $daten['achsen'] ) ) return null;
	return $daten;
}

/** Serverseitige, autoritative Preisberechnung aus gewaehlten Wert-IDs (nie dem Client trauen). */
function mealana_konfig_berechne_preis( array $matrix, array $gewaehlteWertIds ) {
	$wertIds = array_map( 'intval', $gewaehlteWertIds );
	$summe = 0.0;
	$beschreibung = array();
	foreach ( $matrix['achsen'] as $achse ) {
		if ( ! empty( $achse['bedingung'] ) ) {
			$bedErfuellt = in_array( (int) $achse['bedingung']['wert_id'], $wertIds, true );
			if ( ! $bedErfuellt ) continue;
		}
		foreach ( $achse['werte'] as $wert ) {
			if ( in_array( (int) $wert['wert_id'], $wertIds, true ) ) {
				$summe += (float) $wert['gesamt_aufpreis'];
				$beschreibung[] = $achse['name'] . ': ' . $wert['label'];
				break;
			}
		}
	}
	$brutto = round( (float) $matrix['basis_brutto'] + $summe, 2 );
	return array( 'brutto' => $brutto, 'beschreibung' => implode( ' · ', $beschreibung ) );
}

/** Options-Picker unterhalb der Preisanzeige, oberhalb des "In den Warenkorb"-Buttons. */
add_action( 'woocommerce_before_add_to_cart_button', function () {
	global $product;
	if ( ! $product ) return;
	$matrix = mealana_konfig_matrix( $product->get_id() );
	if ( ! $matrix ) return;

	echo '<div id="mealana-konfig" data-basis="' . esc_attr( $matrix['basis_brutto'] ) . '">';
	foreach ( $matrix['achsen'] as $achse ) {
		$bedAttr = '';
		if ( ! empty( $achse['bedingung'] ) ) {
			$bedAttr = ' data-bedingung-achse="' . (int) $achse['bedingung']['achse_id'] . '" data-bedingung-wert="' . (int) $achse['bedingung']['wert_id'] . '"';
		}
		echo '<div class="mealana-konfig-achse" data-achse-id="' . (int) $achse['achse_id'] . '"' . $bedAttr . ' style="margin-bottom:14px;' . ( $bedAttr ? 'display:none;' : '' ) . '">';
		echo '<label style="display:block;font-weight:600;margin-bottom:4px">' . esc_html( $achse['name'] ) . '</label>';
		echo '<select class="mealana-konfig-select" data-achse-id="' . (int) $achse['achse_id'] . '" style="width:100%;max-width:320px;padding:6px">';
		echo '<option value="">Bitte wählen…</option>';
		foreach ( $achse['werte'] as $wert ) {
			$aufpreisText = $wert['gesamt_aufpreis'] > 0 ? ' (+' . number_format( $wert['gesamt_aufpreis'], 2, ',', '.' ) . ' €)' : '';
			// ERP 1.0.4 (Migr. 232): Rohmaterial leer -> sichtbar, aber nicht waehlbar
			$nichtVerfuegbar = isset( $wert['verfuegbar'] ) && $wert['verfuegbar'] === false;
			echo '<option value="' . (int) $wert['wert_id'] . '" data-aufpreis="' . esc_attr( $wert['gesamt_aufpreis'] ) . '"' . ( $nichtVerfuegbar ? ' disabled' : '' ) . '>'
				. esc_html( $wert['label'] . $aufpreisText . ( $nichtVerfuegbar ? ' – derzeit nicht verfügbar' : '' ) ) . '</option>';
		}
		echo '</select>';
		echo '</div>';
	}
	echo '<div id="mealana-konfig-preis" style="font-size:20px;font-weight:700;margin:12px 0">' . wc_price( $matrix['basis_brutto'] ) . '</div>';
	echo '<input type="hidden" name="mealana_konfig_werte" id="mealana-konfig-werte-input" value="">';
	echo '</div>';
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		// Muss auf DOMContentLoaded warten: dieses Skript wird von
		// woocommerce_before_add_to_cart_button ausgegeben, der eigentliche
		// "In den Warenkorb"-Button existiert zu diesem Zeitpunkt im HTML-Quelltext
		// noch gar nicht (kommt erst danach) -- ein sofortiger querySelector()
		// darauf liefert sonst still null und der Button bleibt nie gesperrt.
		var wrap = document.getElementById('mealana-konfig');
		if (!wrap) return;
		var selects = wrap.querySelectorAll('.mealana-konfig-select');
		var preisEl = document.getElementById('mealana-konfig-preis');
		var inputEl = document.getElementById('mealana-konfig-werte-input');
		var addBtn  = document.querySelector('.single_add_to_cart_button');
		var basis   = parseFloat(wrap.dataset.basis) || 0;

		function auswahl() {
			var m = {};
			selects.forEach(function (s) {
				if (s.value) m[s.dataset.achseId] = s.value;
			});
			return m;
		}

		function sichtbarkeitAktualisieren() {
			wrap.querySelectorAll('.mealana-konfig-achse').forEach(function (div) {
				if (!div.dataset.bedingungAchse) return;
				var sel = wrap.querySelector('.mealana-konfig-select[data-achse-id="' + div.dataset.bedingungAchse + '"]');
				var erfuellt = sel && sel.value === div.dataset.bedingungWert;
				div.style.display = erfuellt ? '' : 'none';
				if (!erfuellt) {
					var eigenerSelect = div.querySelector('.mealana-konfig-select');
					if (eigenerSelect) eigenerSelect.value = '';
				}
			});
		}

		function preisAktualisieren() {
			var summe = 0;
			var vollstaendig = true;
			wrap.querySelectorAll('.mealana-konfig-achse').forEach(function (div) {
				if (div.style.display === 'none') return;
				var sel = div.querySelector('.mealana-konfig-select');
				if (sel && sel.value && sel.selectedOptions[0]) {
					summe += parseFloat(sel.selectedOptions[0].dataset.aufpreis) || 0;
				} else if (sel) {
					vollstaendig = false;
				}
			});
			var gesamt = basis + summe;
			preisEl.textContent = gesamt.toFixed(2).replace('.', ',') + ' €';
			if (addBtn) addBtn.disabled = !vollstaendig;
			inputEl.value = JSON.stringify(auswahl());
		}

		selects.forEach(function (s) {
			s.addEventListener('change', function () {
				sichtbarkeitAktualisieren();
				preisAktualisieren();
			});
		});
		sichtbarkeitAktualisieren();
		preisAktualisieren();
	});
	</script>
	<?php
}, 25 );

/*
 * 2026-11-13: Shop-Übersicht -- konfigurierbare Produkte haben dort keinen Direkt-Kauf-Knopf, sondern
 * "Konfiguration wählen" (Link auf die Produktseite), wie WooCommerce es bei variablen Produkten macht.
 * Vorher landete das Layout per AJAX-Klick ganz ohne Auswahl im Warenkorb.
 */
add_filter( 'woocommerce_product_supports', function ( $unterstuetzt, $feature, $product ) {
	if ( $feature === 'ajax_add_to_cart' && $product && mealana_konfig_matrix( $product->get_id() ) ) return false;
	return $unterstuetzt;
}, 10, 3 );

add_filter( 'woocommerce_product_add_to_cart_url', function ( $url, $product ) {
	return ( $product && mealana_konfig_matrix( $product->get_id() ) ) ? get_permalink( $product->get_id() ) : $url;
}, 10, 2 );

add_filter( 'woocommerce_product_add_to_cart_text', function ( $text, $product ) {
	return ( $product && mealana_konfig_matrix( $product->get_id() ) ) ? 'Konfiguration wählen' : $text;
}, 10, 2 );

/** Store-API (Block-Warenkorb/-Kacheln): ohne Konfiguration ablehnen. Die Store-API kennt kein Auswahlfeld. */
add_action( 'woocommerce_store_api_validate_add_to_cart', function ( $product ) {
	if ( $product && mealana_konfig_matrix( $product->get_id() ) && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'mealana_konfig_fehlt', 'Bitte das Schild zuerst auf der Produktseite konfigurieren.', 400 );
	}
}, 10, 1 );

/**
 * Serverseitige Prüfung beim In-den-Warenkorb-Legen:
 * - ohne Konfiguration (Direkt-Link ?add-to-cart=, alter AJAX-Knopf) -> ablehnen (2026-11-13)
 * - ERP 1.0.4: Werte mit leerem Rohmaterial ablehnen (disabled im Browser ist kein Schutz).
 */
add_filter( 'woocommerce_add_to_cart_validation', function ( $ok, $product_id ) {
	$matrix = mealana_konfig_matrix( $product_id );
	if ( ! $ok || ! $matrix ) return $ok;
	$werte = empty( $_POST['mealana_konfig_werte'] ) ? null : json_decode( wp_unslash( $_POST['mealana_konfig_werte'] ), true );
	if ( ! is_array( $werte ) || empty( $werte ) ) {
		wc_add_notice( sprintf( 'Bitte „%s“ zuerst konfigurieren.', get_the_title( $product_id ) ), 'error' );
		return false;
	}
	$wertIds = array_map( 'intval', $werte );
	foreach ( $matrix['achsen'] as $achse ) {
		foreach ( $achse['werte'] as $wert ) {
			if ( isset( $wert['verfuegbar'] ) && $wert['verfuegbar'] === false && in_array( (int) $wert['wert_id'], $wertIds, true ) ) {
				wc_add_notice( sprintf( '„%s: %s“ ist derzeit leider nicht verfügbar. Bitte eine andere Auswahl treffen.', $achse['name'], $wert['label'] ), 'error' );
				return false;
			}
		}
	}
	return $ok;
}, 10, 2 );

/** Gewaehlte Werte + serverseitig berechneten Preis am Warenkorb-Item speichern (Client-Preis wird verworfen). */
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id ) {
	$matrix = mealana_konfig_matrix( $product_id );
	if ( ! $matrix || empty( $_POST['mealana_konfig_werte'] ) ) return $cart_item_data;

	$werte = json_decode( wp_unslash( $_POST['mealana_konfig_werte'] ), true );
	if ( ! is_array( $werte ) || empty( $werte ) ) return $cart_item_data;

	$wertIds = array_values( array_map( 'intval', $werte ) );
	$ergebnis = mealana_konfig_berechne_preis( $matrix, $wertIds );

	$cart_item_data['mealana_konfig'] = array(
		'wert_ids'     => $wertIds,
		'preis_brutto' => $ergebnis['brutto'],
		'beschreibung' => $ergebnis['beschreibung'],
	);
	// Sorgt dafuer, dass zwei unterschiedlich konfigurierte Produkte nie zu einer Zeile verschmelzen
	$cart_item_data['unique_key'] = md5( microtime() . wp_rand() );
	return $cart_item_data;
}, 10, 2 );

/** Preis serverseitig neu setzen -- der Client-Wert wird NIE uebernommen (Manipulationsschutz). */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( empty( $cart_item['mealana_konfig'] ) ) continue;
		$matrix = mealana_konfig_matrix( $cart_item['product_id'] );
		if ( ! $matrix ) continue;
		$ergebnis = mealana_konfig_berechne_preis( $matrix, $cart_item['mealana_konfig']['wert_ids'] );
		$cart_item['data']->set_price( $ergebnis['brutto'] );
	}
}, 20, 1 );

/** Auswahl im Warenkorb/Checkout anzeigen (Klartext, ein Eintrag pro Achse). */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	if ( empty( $cart_item['mealana_konfig'] ) ) return $item_data;
	$matrix = mealana_konfig_matrix( $cart_item['product_id'] );
	if ( ! $matrix ) return $item_data;
	foreach ( $matrix['achsen'] as $achse ) {
		foreach ( $achse['werte'] as $wert ) {
			if ( in_array( (int) $wert['wert_id'], $cart_item['mealana_konfig']['wert_ids'], true ) ) {
				$item_data[] = array( 'key' => $achse['name'], 'value' => $wert['label'] );
			}
		}
	}
	return $item_data;
}, 10, 2 );

/**
 * Bestellzeile: _mealana_konfig-JSON (fuers ERP, siehe ShopBestellungSyncService)
 * + Klartext-Keys pro Achse (fuer Kunde/Admin, ohne Unterstrich-Praefix -- WooCommerce
 * blendet _-Keys in Mails/Admin standardmaessig aus).
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['mealana_konfig'] ) ) return;
	$matrix = mealana_konfig_matrix( $values['product_id'] );
	if ( ! $matrix ) return;

	$item->add_meta_data( '_mealana_konfig', wp_json_encode( array(
		'version'      => 1,
		'werte'        => $values['mealana_konfig']['wert_ids'],
		'preis_brutto' => $values['mealana_konfig']['preis_brutto'],
	) ) );

	foreach ( $matrix['achsen'] as $achse ) {
		foreach ( $achse['werte'] as $wert ) {
			if ( in_array( (int) $wert['wert_id'], $values['mealana_konfig']['wert_ids'], true ) ) {
				$item->add_meta_data( $achse['name'], $wert['label'] );
			}
		}
	}
}, 10, 4 );
