<?php
/**
 * WPCode-Snippet #32070 auf indra-design.at (WordPress/WooCommerce, NICHT Teil der PHP-Autoload-Kette des ERP).
 * Export-Datum: 2026-08-29. Quelle der Wahrheit bleibt WPCode (wp-admin) --
 * diese Datei ist eine manuell aktualisierte Kopie fuers Repo, kein automatischer Sync.
 */

add_shortcode('hersteller_liste', function ($atts) {
    $atts = shortcode_atts(['hide_empty' => '1'], $atts);

    $terms = get_terms([
        'taxonomy'   => 'pa_hersteller',
        'hide_empty' => (bool) $atts['hide_empty'],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    $links = '';
    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (is_wp_error($url)) continue;
        $links .= '<a class="hersteller-link" href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
    }

    return '<style>
        .hersteller-menu-liste { column-count: 4; column-gap: 32px; }
        .hersteller-menu-liste .hersteller-link {
            display: block;
            text-decoration: none;
            font-size: 14px;
            padding: 3px 0;
            break-inside: avoid;
        }
        .hersteller-menu-liste .hersteller-link:hover { text-decoration: underline; }
        @media (max-width: 782px) {
            .hersteller-menu-liste { column-count: 2; }
        }
    </style>
    <div class="hersteller-menu-liste">' . $links . '</div>';
});
