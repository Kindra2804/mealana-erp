<?php
/**
 * WPCode-Snippet #32085 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    if (!$product->is_purchasable() || !$product->is_in_stock()) {
        return __('Ausverkauft', 'woocommerce'); // oder was auch immer du willst
    }
    return $text;
}, 10, 2);
