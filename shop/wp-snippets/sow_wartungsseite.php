/* Sockenwolle-online: Wartungsseite während des Umbaus (503 + Retry-After, Google behält den Index).
 * Angemeldete Redakteure/Admins sehen den Shop normal. REST-API (ERP-Sync), wp-admin und Login sind nicht betroffen.
 * Zum Live-Schalten: dieses Snippet deaktivieren. */
add_action('template_redirect', function () {
    if (is_user_logged_in() && current_user_can('edit_posts')) return;
    status_header(503);
    header('Retry-After: 86400');
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Sockenwolle-online – wir bauen um</title><link rel="icon" href="https://sockenwolle-online.at/wp-content/uploads/2026/11/sockenwolle-favicon_512.png">'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fdf8f2;color:#3b2a26;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}'
       . 'main{padding:32px 20px;max-width:640px}img{width:100%;max-width:520px;height:auto}h1{color:#8e3438;font-weight:600;font-size:1.6rem;margin:36px 0 12px}p{font-size:1.05rem;line-height:1.6;margin:0 0 8px}.k{color:#c8574a;font-weight:600;margin-top:24px}</style></head><body><main>'
       . '<img src="https://sockenwolle-online.at/wp-content/uploads/2026/11/sockenwolle-logo.png" alt="Sockenwolle-online">'
       . '<h1>Wir stricken gerade an unserem neuen Shop</h1>'
       . '<p>Sockenwolle-online bekommt ein neues Kleid. In Kürze sind wir mit gewohntem Sortiment wieder für dich da.</p>'
       . '<p class="k">Hier gibt es bald wieder alles für Sockenstricker.</p>'
       . '</main></body></html>';
    exit;
}, 0);