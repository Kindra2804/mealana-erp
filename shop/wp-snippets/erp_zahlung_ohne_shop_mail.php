<?php
/**
 * WPCode-Snippet #34191 "MeaLana: ERP-Zahlung ohne Shop-Mail" auf indra-design.at
 * (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Quelle der Wahrheit bleibt WPCode (wp-admin) -- diese Datei ist eine manuell
 * aktualisierte Kopie fuers Repo, kein automatischer Sync.
 *
 * Das ERP meldet Zahlung und Versand an die Shop-Bestellung zurueck
 * (ShopBestellungSyncService::meldeStatusAnShop()) und markiert sie dabei:
 *   _mealana_erp_zahlung -> Status "In Bearbeitung" kam vom ERP (Zahlung im ERP gebucht)
 *   _mealana_erp_versand -> Status "Fertiggestellt" kam vom ERP (versendet/abgeholt)
 *   Kunden-Notizen mit unsichtbarer Endung U+200B -> keine WC-Mail "Hinweis zu Ihrer Bestellung"
 * Der Kunde bekommt die passenden Mails bereits vom ERP ("Zahlung eingegangen",
 * "Versandbestätigung", "Fertig zur Abholung") -- die WooCommerce-Mails waeren doppelt
 * und werden hier NUR fuer so markierte Bestellungen unterdrueckt. Normale
 * Shop-Zahlungen (PayPal etc.) bekommen ihre Mails wie bisher.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'woocommerce_email_enabled_customer_processing_order', function ( $enabled, $order ) {
	if ( $order instanceof WC_Order && $order->get_meta( '_mealana_erp_zahlung', true ) ) {
		return false;
	}
	return $enabled;
}, 10, 2 );

add_filter( 'woocommerce_email_enabled_customer_completed_order', function ( $enabled, $order ) {
	if ( $order instanceof WC_Order && $order->get_meta( '_mealana_erp_versand', true ) ) {
		return false;
	}
	return $enabled;
}, 10, 2 );

/**
 * Kunden-Notizen vom ERP ("Zahlung eingegangen …", "Ihre Bestellung wurde … versendet …")
 * sind im Kundenkonto sichtbar, sollen aber KEINE Mail "Hinweis zu Ihrer Bestellung"
 * ausloesen. Erkennbar an der unsichtbaren Endung U+200B (ShopBestellungSyncService::
 * KUNDENNOTIZ_MARKE). Von Hand im wp-admin geschriebene Kunden-Notizen mailen weiterhin.
 */
add_filter( 'woocommerce_email_enabled_customer_note', function ( $enabled, $order, $email = null ) {
	if ( $email && isset( $email->customer_note ) && substr( (string) $email->customer_note, -3 ) === "\u{200B}" ) {
		return false;
	}
	return $enabled;
}, 10, 3 );
