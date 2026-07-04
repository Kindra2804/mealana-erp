// Service Worker für die Offline-Kasse (Messe).
//
// Zweck: bon_offline.php + kasse_bon_offline.js müssen auch dann neu ladbar
// sein, wenn der Browser abstürzt oder der Tab geschlossen wird, während am
// Messestand KEINE Serververbindung besteht. Ohne diesen Cache würde ein
// Neuladen dort komplett fehlschlagen (siehe docs/offline_kasse_anleitung.md).
//
// Strategie: "Netzwerk zuerst, Cache als Rückfalloption" NUR für die App-Hülle
// selbst (die Seite + ihr Script). Alle anderen Anfragen (ajax_messe.php,
// die direkte BFR-Signierung) werden bewusst NICHT abgefangen — die müssen
// entweder wirklich funktionieren oder ehrlich fehlschlagen, kein Fake-Cache.

const CACHE_NAME = 'mealana-messe-kasse-v1';
const APP_SHELL = [
    './bon_offline.php',
    '../js/kasse_bon_offline.js',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(APP_SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Nur GET auf die App-Hülle selbst behandeln (unabhängig vom ?sync_id=X in
    // der URL) — alles andere (POST-Aufrufe, ajax_messe.php, BFR-Gerät)
    // unverändert durchreichen, nicht abfangen.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    const istAppShell = url.pathname.endsWith('/bon_offline.php') || url.pathname.endsWith('/kasse_bon_offline.js');
    if (!istAppShell) return;

    event.respondWith(
        fetch(req)
            .then((resp) => {
                // Online: frische Version verwenden + Cache aktualisieren
                const kopie = resp.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(req, kopie));
                return resp;
            })
            .catch(() => caches.match(req, { ignoreSearch: true }))
    );
});
