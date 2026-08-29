<?php
/**
 * WPCode-Snippet #34171 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

/**
 * MeaLana: Zahlungsart "Bar bei Abholung".
 * Nur waehlbar, wenn im Checkout die (bereits vorhandene, native) WooCommerce-
 * Abholung "Wollboutique" gewaehlt wurde. Kein eigenes Versandart-Snippet mehr
 * noetig -- WooCommerce hat dafuer bereits das eingebaute "Abholung vor Ort"-
 * Feature (Einstellungen -> Versand -> Abholung vor Ort), das bei indra-design.at
 * schon aktiv ist und im blockbasierten Checkout einwandfrei funktioniert.
 * Interne Shipping-Method-ID dieses Bordmittel-Features: "pickup_location"
 * (siehe wp-admin URL "tab=shipping&section=pickup_location").
 *
 * WICHTIG (Fund vom 2026-08-29): Ein klassisches WC_Payment_Gateway ist fuer den
 * BLOCKBASIERTEN Checkout allein NICHT sichtbar -- WooCommerce Blocks liest die
 * anzuzeigenden Zahlungsarten nicht ueber das klassische "woocommerce_available_
 * payment_gateways"-Filter aus, sondern ueber eine eigene "Blocks Payment Method"-
 * Registrierung (PHP-Klasse + JS ueber wc.wcBlocksRegistry.registerPaymentMethod).
 * Ohne diese zweite Registrierung taucht das Gateway im Block-Checkout NIE auf,
 * unabhaengig von jedem serverseitigen Filter -- das war der eigentliche Grund,
 * warum "Bar bei Abholung" trotz korrekt erkannter Abholung nie erschien.
 */

add_filter('woocommerce_payment_gateways', 'mealana_register_barabholung_gateway');
function mealana_register_barabholung_gateway($gateways) {
    $gateways[] = 'Mealana_Bar_Abholung_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'mealana_init_barabholung_gateway_class');
function mealana_init_barabholung_gateway_class() {
    if (!class_exists('WC_Payment_Gateway') || class_exists('Mealana_Bar_Abholung_Gateway')) {
        return;
    }

    class Mealana_Bar_Abholung_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'mealana_barabholung';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = 'Bar bei Abholung';
            $this->method_description = 'Erscheint im Checkout nur, wenn die Abholung "Wollboutique" gewählt wurde.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title', 'Bar bei Abholung');
            $this->description = $this->get_option('description', 'Du bezahlst bar, wenn du deine Bestellung in der Wollboutique abholst.');
            $this->enabled     = $this->get_option('enabled', 'yes');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'   => 'Aktivieren',
                    'type'    => 'checkbox',
                    'label'   => 'Bar bei Abholung aktivieren',
                    'default' => 'yes',
                ],
                'title' => [
                    'title'   => 'Titel',
                    'type'    => 'text',
                    'default' => 'Bar bei Abholung',
                ],
                'description' => [
                    'title'   => 'Beschreibung',
                    'type'    => 'textarea',
                    'default' => 'Du bezahlst bar, wenn du deine Bestellung in der Wollboutique abholst.',
                ],
            ];
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', 'Wartet auf Barzahlung bei Abholung.');
            WC()->cart->empty_cart();

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }
    }
}

// Klassischer Shortcode-Checkout (Absicherung, falls jemals genutzt): nur
// anzeigen, wenn die native WooCommerce-Abholung gewaehlt ist. Fuer den
// tatsaechlich genutzten Block-Checkout ist NICHT dieser Filter zustaendig,
// sondern die JS-canMakePayment-Pruefung weiter unten.
add_filter('woocommerce_available_payment_gateways', 'mealana_barabholung_nur_bei_abholung');
function mealana_barabholung_nur_bei_abholung($gateways) {
    if (!isset($gateways['mealana_barabholung'])) {
        return $gateways;
    }
    if (is_admin() && !wp_doing_ajax()) {
        return $gateways;
    }

    $istAbholung = false;
    if (function_exists('WC') && WC()->session) {
        $gewaehlt = (array) WC()->session->get('chosen_shipping_methods', []);
        $abholungPraefixe = ['pickup_location', 'local_pickup', 'legacy_local_pickup'];
        foreach ($gewaehlt as $methodRateId) {
            foreach ($abholungPraefixe as $praefix) {
                if (strpos((string) $methodRateId, $praefix) === 0) {
                    $istAbholung = true;
                    break 2;
                }
            }
        }
    }

    if (!$istAbholung) {
        unset($gateways['mealana_barabholung']);
    }

    return $gateways;
}

// Block-Checkout: eigene Payment-Method-Registrierung, ohne die erscheint das
// Gateway im blockbasierten Checkout gar nicht erst in der Liste.
add_action('woocommerce_blocks_loaded', 'mealana_barabholung_register_blocks_support');
function mealana_barabholung_register_blocks_support() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }
    if (class_exists('Mealana_Barabholung_Blocks_Support')) {
        return;
    }

    class Mealana_Barabholung_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        protected $name = 'mealana_barabholung';

        public function initialize() {
            $this->settings = get_option('woocommerce_mealana_barabholung_settings', []);
        }

        public function is_active() {
            // Fehlender Schluessel = Formular-Default "yes" (WC_Settings_API-Verhalten
            // der klassischen Gateway-Klasse), nicht "inaktiv" -- sonst bleibt die
            // Blocks-Registrierung stumm aus, solange niemand die Zahlungsart-
            // Einstellungsseite einmal manuell geoeffnet und gespeichert hat.
            $enabled = $this->settings['enabled'] ?? 'yes';
            return 'yes' === $enabled;
        }

        public function get_payment_method_script_handles() {
            wp_register_script('mealana-barabholung-blocks', false, [
                'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n',
            ], '1.0.1', true);

            $js = <<<'JS'
( function() {
    var settings = window.wc.wcSettings.getSetting( 'mealana_barabholung_data', {} );
    var label = settings.title || 'Bar bei Abholung';
    var decode = window.wp.htmlEntities.decodeEntities;
    var el = window.wp.element.createElement;

    var Content = function() {
        return el( 'div', {}, decode( settings.description || '' ) );
    };

    // Fragt den echten Warenkorb-Store (dieselben Daten wie die Store-API,
    // Feld "rate_id" beginnt bei nativer Abholung mit "pickup_location").
    function abholungAusgewaehlt() {
        try {
            var select = window.wp && window.wp.data && window.wp.data.select;
            if ( ! select ) return false;
            var cartStore = select( 'wc/store/cart' );
            if ( ! cartStore || ! cartStore.getShippingRates ) return false;
            var packages = cartStore.getShippingRates() || [];
            for ( var i = 0; i < packages.length; i++ ) {
                var rates = packages[ i ].shipping_rates || [];
                for ( var j = 0; j < rates.length; j++ ) {
                    var r = rates[ j ];
                    if ( r.selected && typeof r.rate_id === 'string' &&
                        ( r.rate_id.indexOf( 'pickup_location' ) === 0 ||
                          r.rate_id.indexOf( 'local_pickup' ) === 0 ||
                          r.rate_id.indexOf( 'legacy_local_pickup' ) === 0 ) ) {
                        return true;
                    }
                }
            }
        } catch ( e ) {}
        return false;
    }

    window.wc.wcBlocksRegistry.registerPaymentMethod( {
        name: 'mealana_barabholung',
        label: label,
        content: el( Content, null ),
        edit: el( Content, null ),
        ariaLabel: label,
        canMakePayment: abholungAusgewaehlt,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );
} )();
JS;

            wp_add_inline_script('mealana-barabholung-blocks', $js);

            return ['mealana-barabholung-blocks'];
        }

        public function get_payment_method_data() {
            return [
                'title'       => $this->get_setting('title', 'Bar bei Abholung'),
                'description' => $this->get_setting('description', ''),
                'supports'    => ['products'],
            ];
        }
    }

    add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
        $registry->register(new Mealana_Barabholung_Blocks_Support());
    });
}

