/* Bio-Wolle: Wartungsseite während des Umbaus (503 + Retry-After, Google behält den Index).
 * Angemeldete Redakteure/Admins sehen den Shop normal. REST-API (ERP-Sync), wp-admin und Login sind nicht betroffen.
 * Zum Live-Schalten: dieses Snippet deaktivieren. */
add_action('template_redirect', function () {
    if (is_user_logged_in() && current_user_can('edit_posts')) return;
    status_header(503);
    header('Retry-After: 86400');
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Bio-Wolle – wir bauen um</title><link rel="icon" href="https://bio-wolle.at/wp-content/uploads/2026/11/bio-wolle-favicon_512.png">'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f6f3eb;color:#2a2e26;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center}'
       . 'main{padding:32px 20px;max-width:640px}img{width:100%;max-width:520px;height:auto}h1{color:#3b6b52;font-weight:600;font-size:1.6rem;margin:36px 0 12px}p{font-size:1.05rem;line-height:1.6;margin:0 0 8px}.k{color:#c08a4e;font-weight:600;margin-top:24px}</style></head><body><main>'
       . '<img src="https://bio-wolle.at/wp-content/uploads/2026/11/bio-wolle-logo.png" alt="Bio-Wolle">'
       . '<h1>Wir stricken gerade an unserem neuen Shop</h1>'
       . '<p>Bio-Wolle bekommt ein neues Kleid. In Kürze sind wir mit unseren Bio- und GOTS-Garnen wieder für dich da.</p>'
       . '<p class="k">Wolle, die der Natur gut tut.</p>'
       . '</main></body></html>';
    exit;
}, 0);