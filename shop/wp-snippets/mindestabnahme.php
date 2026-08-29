<?php
/**
 * WPCode-Snippet #34156 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

/**
 * Mindestabnahme + Abnahmeintervall (WPCode-Snippet, "Nur PHP", Ausführungsort "Run Everywhere").
 *
 * Meta-Felder, die das ERP beim Sync mitschickt (leer = keine Mindestabnahme):
 *   _mealana_mindestabnahme            Stückzahl fürs Mengenfeld (min_value)
 *   _mealana_abnahmeintervall          Stückzahl fürs Mengenfeld (step)
 *   _mealana_mindestabnahme_anzeige    Menschlich lesbarer Wert, z.B. "2" (für den Hinweistext)
 *   _mealana_abnahmeintervall_anzeige  dito für das Intervall
 *   _mealana_je_stueck_anzeige         wie viel EIN Stück in der Anzeige-Einheit ist (z.B. "1")
 *   _mealana_anzeige_einheit           z.B. "cm" oder "m"
 *
 * Die "_anzeige"-Felder sind bewusst GETRENNT von der rohen Stückzahl: 1 Stück im Mengenfeld
 * entspricht je nach Artikel unterschiedlich viel Zentimeter/Meter -- das ERP rechnet das serverseitig
 * einmal sauber um, das Snippet muss nichts raten oder umrechnen.
 */

add_action('wp_head', function () {
    echo '<style>
        .mealana-mab-hinweis { margin-top:10px; padding:10px 14px; background:#f3f4f6; border-radius:6px; font-size:14px; color:#374151; }
        .mealana-mab-hinweis div:empty { display:none; }
    </style>';
});

// Standalone-Produkt: Mengenfeld-Attribute
add_filter('woocommerce_quantity_input_args', function ($args, $product) {
    $min = mealana_meta_float($product->get_id(), '_mealana_mindestabnahme');
    $intervall = mealana_meta_float($product->get_id(), '_mealana_abnahmeintervall');
    if ($min !== null) {
        $args['min_value'] = $min;
        $args['input_value'] = $min;
    }
    if ($intervall !== null) {
        $args['step'] = $intervall;
    }
    return $args;
}, 10, 2);

// Standalone-Produkt: Hinweistext unterm Warenkorb-Button
add_action('woocommerce_after_add_to_cart_button', function () {
    global $product;
    if (!$product) return;

    $anzeigeMin = mealana_meta_str($product->get_id(), '_mealana_mindestabnahme_anzeige');
    $anzeigeIntervall = mealana_meta_str($product->get_id(), '_mealana_abnahmeintervall_anzeige');
    $einheit = mealana_meta_str($product->get_id(), '_mealana_anzeige_einheit');

    if ($anzeigeMin === '') return; // keine Mindestabnahme fuer dieses Produkt

    echo '<div id="mealana-mab-hinweis" class="mealana-mab-hinweis">';
    echo '<div class="mealana-mab-zeile-min">' . esc_html(sprintf('Bitte beachten Sie die Mindestabnahme von %s %s.', $anzeigeMin, $einheit)) . '</div>';
    if ($anzeigeIntervall !== '') {
        echo '<div class="mealana-mab-zeile-intervall">' . esc_html(sprintf('Bitte beachten Sie das Abnahmeintervall von %s %s.', $anzeigeIntervall, $einheit)) . '</div>';
    }
    echo '</div>';
});

// Variable Product: alle Werte in die per AJAX geladenen Variations-Daten einhängen --
// variation.js (WooCommerce-Core) übernimmt min_qty/step automatisch, den Rest holt sich
// unser eigenes JS unten aus denselben Daten.
add_filter('woocommerce_available_variation', function ($data, $product, $variation) {
    $min = mealana_meta_float($variation->get_id(), '_mealana_mindestabnahme');
    $intervall = mealana_meta_float($variation->get_id(), '_mealana_abnahmeintervall');
    if ($min !== null) {
        $data['min_qty'] = $min;
    }
    if ($intervall !== null) {
        $data['qty_step'] = $intervall;
    }
    $data['mab_min_anzeige'] = mealana_meta_str($variation->get_id(), '_mealana_mindestabnahme_anzeige');
    $data['mab_intervall_anzeige'] = mealana_meta_str($variation->get_id(), '_mealana_abnahmeintervall_anzeige');
    $data['mab_einheit'] = mealana_meta_str($variation->get_id(), '_mealana_anzeige_einheit');
    return $data;
}, 10, 3);

// Variable Product braucht den Hinweis-Container schon im HTML (leer/versteckt),
// JS befüllt ihn erst sobald eine Variation gewählt ist.
add_action('woocommerce_after_add_to_cart_button', function () {
    global $product;
    if ($product && $product->is_type('variable')) {
        echo '<div id="mealana-mab-hinweis" class="mealana-mab-hinweis" style="display:none">'
            . '<div class="mealana-mab-zeile-min"></div><div class="mealana-mab-zeile-intervall"></div></div>';
    }
});

add_action('woocommerce_after_add_to_cart_button', function () {
    ?>
    <script>
    jQuery(function ($) {
        $(document).on('found_variation', 'form.variations_form', function (event, variation) {
            var $qty = $(this).find('input.qty');
            if (variation.qty_step) { $qty.attr('step', variation.qty_step); }
            if (variation.min_qty) { $qty.attr('min', variation.min_qty).val(variation.min_qty); }

            var $hinweis = $('#mealana-mab-hinweis');
            if (variation.mab_min_anzeige) {
                $hinweis.find('.mealana-mab-zeile-min').text(
                    'Bitte beachten Sie die Mindestabnahme von ' + variation.mab_min_anzeige + ' ' + variation.mab_einheit + '.'
                );
                $hinweis.find('.mealana-mab-zeile-intervall').text(
                    variation.mab_intervall_anzeige
                        ? ('Bitte beachten Sie das Abnahmeintervall von ' + variation.mab_intervall_anzeige + ' ' + variation.mab_einheit + '.')
                        : ''
                );
                $hinweis.show();
            } else {
                $hinweis.hide();
            }
        });
        $(document).on('reset_data', 'form.variations_form', function () {
            $('#mealana-mab-hinweis').hide();
        });
    });
    </script>
    <?php
});

// Server-seitige Validierung -- Pflicht, da min/step am Feld clientseitig umgangen werden kann.
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = 0) {
    $ziel_id = $variation_id ?: $product_id;
    $min = mealana_meta_float($ziel_id, '_mealana_mindestabnahme');
    $intervall = mealana_meta_float($ziel_id, '_mealana_abnahmeintervall');
    $anzeigeMin = mealana_meta_str($ziel_id, '_mealana_mindestabnahme_anzeige');
    $einheit = mealana_meta_str($ziel_id, '_mealana_anzeige_einheit');

    if ($min === null) {
        return $passed;
    }

    if ($quantity < $min) {
        wc_add_notice(sprintf('Mindestabnahme: %s %s.', $anzeigeMin, $einheit), 'error');
        return false;
    }

    if ($intervall && $intervall > 0) {
        $schritte = ($quantity - $min) / $intervall;
        if (abs($schritte - round($schritte)) > 0.001) {
            $anzeigeIntervall = mealana_meta_str($ziel_id, '_mealana_abnahmeintervall_anzeige');
            wc_add_notice(sprintf('Bitte in Schritten von %s %s bestellen (ab %s %s).', $anzeigeIntervall, $einheit, $anzeigeMin, $einheit), 'error');
            return false;
        }
    }

    return $passed;
}, 10, 4);

function mealana_meta_float($produkt_id, $key) {
    $wert = get_post_meta($produkt_id, $key, true);
    return ($wert !== '' && $wert !== false) ? (float) $wert : null;
}

function mealana_meta_str($produkt_id, $key) {
    $wert = get_post_meta($produkt_id, $key, true);
    return ($wert !== false) ? (string) $wert : '';
}
