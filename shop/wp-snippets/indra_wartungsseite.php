<?php
/* indra-design: Wartungsseite (kurz, z.B. Umstellung auf das Einzelunternehmen / Shop-Tests) – 503 + Retry-After.
 * Angemeldete Redakteure/Admins sehen den Shop normal. REST-API (ERP-Sync), wp-admin und Login sind nicht betroffen.
 * Ein-/Ausschalten: dieses Snippet aktivieren bzw. deaktivieren (danach WP Super Cache leeren). */
add_action('template_redirect', function () {
    if (is_user_logged_in() && current_user_can('edit_posts')) return;
    status_header(503);
    header('Retry-After: 7200');
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>INDRA DESIGN – gleich wieder da</title><link rel="icon" href="https://indra-design.at/wp-content/uploads/2026/11/indra_favicon_schwarz-300x300.png">'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f7f2ea;color:#1f1d1b;font-family:Jost,system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}'
       . 'main{padding:32px 20px;max-width:640px}img{width:100%;max-width:440px;height:auto}h1{color:#1d4076;font-weight:500;font-size:1.6rem;margin:36px 0 12px}p{font-size:1.05rem;line-height:1.6;margin:0 0 8px}.k{color:#8a6340;font-weight:500;margin-top:24px}</style></head><body><main>'
       . '<img src="https://indra-design.at/wp-content/uploads/2026/11/indra_logo_quer_schwarz.png" alt="INDRA DESIGN">'
       . '<h1>Wir sind gleich wieder für dich da</h1>'
       . '<p>Der Shop wird gerade kurz überarbeitet. Schau in ein paar Stunden wieder vorbei – deine Lieblingsstücke warten auf dich.</p>'
       . '<p class="k">Handgemacht mit Laser und Liebe.</p>'
       . '</main></body></html>';
    exit;
}, 0);
