<?php
/**
 * NAHTLOS Connector — Newsletter-Anmeldeformular (ab 1.8.0)
 *
 * Shortcode: [nahtlos_newsletter portal="https://beleg.mealana.at" shop="sockenwolle" name="sockenwolle-online.at"
 *             datenschutz="/datenschutz/" titel="Newsletter" text="…"]
 * Das Formular schickt direkt an die Belegseite (beleg.<domain>/newsletter.php). Dort landet die Anmeldung in einer
 * Warteschlange, das ERP holt sie ab und schickt die Bestätigungs-Mail (Double-Opt-in). Danach springt die Belegseite
 * hierher zurück (?newsletter=danke) und das Formular zeigt „Fast geschafft“.
 * Den fertigen Shortcode für den jeweiligen Shop zeigt das ERP unter Kunden → Newsletter → Anmeldeformular.
 */
if (!defined('ABSPATH')) exit;

add_shortcode('nahtlos_newsletter', function ($atts) {
    $a = shortcode_atts([
        'portal'      => '',
        'shop'        => '',
        'name'        => get_bloginfo('name'),
        'datenschutz' => function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '',
        'titel'       => 'Newsletter',
        'text'        => 'Neuheiten, Aktionen und Anleitungen — etwa einmal im Monat in deinem Postfach.',
        'knopf'       => 'Anmelden',
    ], $atts, 'nahtlos_newsletter');
    if ($a['portal'] === '' || $a['shop'] === '') {
        return current_user_can('manage_options') ? '<p><em>[nahtlos_newsletter]: portal und shop fehlen.</em></p>' : '';
    }
    $id = 'nl-' . wp_generate_password(6, false);
    $zurueck = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $danke = isset($_GET['newsletter']) && $_GET['newsletter'] === 'danke';
    $zustimmung = 'Ja, ich möchte den Newsletter von ' . $a['name'] . ' per E-Mail erhalten. Abmelden kann ich mich jederzeit über den Link in jedem Newsletter.';

    ob_start(); ?>
    <div class="nahtlos-newsletter" id="<?php echo esc_attr($id); ?>">
        <style>
            .nahtlos-newsletter { max-width: 560px; }
            .nahtlos-newsletter h3 { margin: 0 0 6px; }
            .nahtlos-newsletter .nl-zeile { display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0; }
            .nahtlos-newsletter input[type=email], .nahtlos-newsletter input[type=text] { flex: 1 1 180px; padding: 10px 12px; }
            .nahtlos-newsletter label.nl-ok { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; line-height: 1.5; }
            .nahtlos-newsletter .nl-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
            .nahtlos-newsletter .nl-danke { padding: 12px 14px; border-radius: 8px; background: rgba(0,0,0,.04); }
        </style>
        <?php if ($danke): ?>
            <div class="nl-danke"><strong>Fast geschafft!</strong> In den nächsten Minuten bekommst du eine E-Mail —
                bitte klicke dort auf den Link, um die Anmeldung zu bestätigen. (Nichts bekommen? Bitte auch im Spam-Ordner schauen.)</div>
        <?php else: ?>
            <?php if ($a['titel'] !== ''): ?><h3><?php echo esc_html($a['titel']); ?></h3><?php endif; ?>
            <?php if ($a['text'] !== ''): ?><p><?php echo esc_html($a['text']); ?></p><?php endif; ?>
            <form method="post" action="<?php echo esc_url(rtrim($a['portal'], '/') . '/newsletter.php'); ?>">
                <input type="hidden" name="shop" value="<?php echo esc_attr($a['shop']); ?>">
                <input type="hidden" name="zurueck" value="<?php echo esc_url($zurueck); ?>">
                <div class="nl-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="nl-zeile">
                    <input type="text" name="vorname" placeholder="Vorname (optional)" autocomplete="given-name" maxlength="100">
                    <input type="email" name="email" placeholder="E-Mail-Adresse" required autocomplete="email" maxlength="200">
                    <button type="submit" class="button wp-element-button"><?php echo esc_html($a['knopf']); ?></button>
                </div>
                <label class="nl-ok"><input type="checkbox" name="zustimmung" value="<?php echo esc_attr($zustimmung); ?>" required>
                    <span><?php echo esc_html($zustimmung); ?>
                    <?php if ($a['datenschutz'] !== ''): ?> Mehr in der <a href="<?php echo esc_url($a['datenschutz']); ?>">Datenschutzerklärung</a>.<?php endif; ?></span>
                </label>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
