<?php
// Bewusst OHNE auth_check.php — Kiosk-Tablet neben der Kasse, nie eingeloggt.
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../src/core/Database.php';

// Darf nie gecacht werden — Kiosk-Tablet muss bei jedem (Neu-)Laden garantiert die
// aktuelle Version bekommen, nicht eine alte (evtl. fehlerhafte) aus dem Browser-Cache.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Aufruf per Kassen-Nummer (z.B. ?kasse=K1) statt interner ID — die Nummer steht
// links oben im Kasse-Header und ist beim Tablet-Einrichten leichter abzulesen/zu tippen.
$kasseNr = trim($_GET['kasse'] ?? '');
$kasseId = 0;

$db = Database::getInstance();

if ($kasseNr !== '') {
    $stmt = $db->prepare("SELECT id FROM kassen WHERE kasse_nr = :nr");
    $stmt->execute([':nr' => $kasseNr]);
    $kasseId = (int)$stmt->fetchColumn();
}

$einstellungen = $db->query("
    SELECT schluessel, wert FROM system_einstellungen
    WHERE schluessel IN ('firmenname', 'kundenanzeige_willkommenstext')
")->fetchAll(PDO::FETCH_KEY_PAIR);

$firmenname = $einstellungen['firmenname'] ?? 'MEALANA KG';
$willkommenstext = $einstellungen['kundenanzeige_willkommenstext'] ?? '';
if ($willkommenstext === '') {
    $willkommenstext = 'Herzlich willkommen bei ' . $firmenname . '!';
}

$shop = $db->query("SELECT logo_pfad FROM shops WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$logoUrl = BASE_PATH . '/' . ($shop['logo_pfad'] ?? 'img/logos/mealana.png');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Kundenanzeige</title>
<link rel="manifest" href="manifest.php?kasse=<?= urlencode($kasseNr) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#0f172a">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; overflow:hidden; }
    body {
        font-family: -apple-system, Segoe UI, Arial, sans-serif;
        background: #0f172a; color: #f1f5f9;
        display:flex; align-items:stretch; justify-content:stretch;
    }
    #kd-root { width:100vw; height:100vh; display:flex; }

    /* ── Zustand: Idle ── */
    .kd-idle { width:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:28px; text-align:center; padding:40px; }
    .kd-idle img { max-width:340px; max-height:160px; }
    .kd-idle h1 { font-size:38px; font-weight:700; color:#f1f5f9; }

    /* ── Zwei-Spalten-Skelett (Warenkorb + Abrechnen-geteilt) ── */
    .kd-split { width:100%; display:flex; }
    .kd-links, .kd-rechts { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; }
    .kd-links { border-right:1px solid #1e293b; }

    .kd-artikelbild { max-width:80%; max-height:50vh; object-fit:contain; border-radius:12px; margin-bottom:24px; }
    .kd-artikelbild-platzhalter { width:220px; height:220px; border-radius:12px; background:#1e293b; display:flex; align-items:center; justify-content:center; font-size:64px; margin-bottom:24px; }
    .kd-artikel-name { font-size:28px; font-weight:700; text-align:center; margin-bottom:8px; }
    .kd-artikel-meta { font-size:18px; color:#94a3b8; text-align:center; }
    .kd-artikel-preis { font-size:24px; font-weight:700; color:#4ade80; margin-top:10px; }

    .kd-bonliste { width:100%; max-width:560px; }
    .kd-bonliste-kopf { display:flex; justify-content:space-between; font-size:13px; color:#64748b; text-transform:uppercase; padding:0 4px 8px; border-bottom:2px solid #334155; }
    .kd-bonzeilen { max-height:52vh; overflow-y:auto; }
    .kd-bon-auftrag-kopf { font-size:15px; font-weight:700; color:#93c5fd; padding:10px 4px 4px; }
    .kd-bon-trenner { font-size:12px; color:#64748b; text-align:center; padding:10px 4px 4px; }
    .kd-bonzeile { display:flex; justify-content:space-between; align-items:baseline; padding:10px 4px; border-bottom:1px solid #1e293b; font-size:18px; }
    .kd-bonzeile-name { flex:1; }
    .kd-bonzeile-menge { width:50px; text-align:center; color:#94a3b8; }
    .kd-bonzeile-summe { width:100px; text-align:right; font-weight:600; }
    .kd-summen { margin-top:14px; padding-top:14px; border-top:2px solid #334155; }
    .kd-summen-zeile { display:flex; justify-content:space-between; font-size:16px; color:#94a3b8; padding:2px 4px; }
    .kd-summen-gesamt { display:flex; justify-content:space-between; font-size:30px; font-weight:800; padding:10px 4px 0; }

    .kd-qr-platzhalter { width:220px; height:220px; border:3px dashed #475569; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:60px; margin-bottom:20px; color:#64748b; }
    .kd-qr-hinweis { font-size:16px; color:#94a3b8; text-align:center; max-width:280px; }

    /* ── Zustand: Abrechnen zentriert ── */
    .kd-abrechnen-zentriert { width:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; text-align:center; }
    .kd-abrechnen-label { font-size:24px; color:#94a3b8; }
    .kd-abrechnen-betrag { font-size:72px; font-weight:800; }
    .kd-abrechnen-zeile { font-size:28px; margin-top:6px; }
    .kd-abrechnen-rueckgeld { color:#4ade80; font-weight:700; }
    .kd-dank { margin-top:22px; font-size:22px; color:#94a3b8; }
</style>
</head>
<body>

<?php if (!$kasseId): ?>
    <div class="kd-idle"><h1>Kasse fehlt oder unbekannt — Aufruf z.B. mit ?kasse=K1 (Kassen-Nr. aus dem Kasse-Header)</h1></div>
<?php else: ?>
    <div id="kd-root"></div>

    <script>
    const KASSE_ID  = <?= $kasseId ?>;
    const LOGO_URL  = <?= json_encode($logoUrl) ?>;
    const WILLKOMMEN = <?= json_encode($willkommenstext) ?>;
    const STATUS_URL = <?= json_encode(BASE_PATH . '/kundenanzeige/ajax_status.php') ?>;

    const root = document.getElementById('kd-root');
    let letzterAktualisiertAm = null;
    let abrechnenTimer = null;
    let localIdleOverride = false;

    function fmt(n) { return (parseFloat(n) || 0).toFixed(2).replace('.', ','); }
    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function renderIdle() {
        root.innerHTML = '<div class="kd-idle">'
            + '<img src="' + LOGO_URL + '" onerror="this.style.display=\'none\'">'
            + '<h1>' + esc(WILLKOMMEN) + '</h1>'
            + '</div>';
    }

    function renderWarenkorb(p) {
        const bildHtml = p.artikel_bild
            ? '<img class="kd-artikelbild" src="' + esc(p.artikel_bild) + '">'
            : '<div class="kd-artikelbild-platzhalter">🧶</div>';

        const positionen  = p.positionen || [];
        const hatAuftrag   = positionen.some(function(z) { return z.vonAuftrag; });
        const hatNormal    = positionen.some(function(z) { return !z.vonAuftrag; });
        let sepGesetzt = false, hdrGesetzt = false;

        const zeilenHtml = positionen.map(function(z) {
            let praefix = '';
            if (z.vonAuftrag && !hdrGesetzt && p.auftrag_nr) {
                praefix += '<div class="kd-bon-auftrag-kopf">📦 ' + esc(p.auftrag_nr) + '</div>';
                hdrGesetzt = true;
            }
            if (hatAuftrag && hatNormal && !z.vonAuftrag && !sepGesetzt) {
                praefix += '<div class="kd-bon-trenner">weitere Artikel</div>';
                sepGesetzt = true;
            }
            return praefix + '<div class="kd-bonzeile">'
                + '<div class="kd-bonzeile-name">' + esc(z.bezeichnung) + '</div>'
                + '<div class="kd-bonzeile-menge">' + z.menge + '×</div>'
                + '<div class="kd-bonzeile-summe">€ ' + fmt(z.summe) + '</div>'
                + '</div>';
        }).join('');

        root.innerHTML = '<div class="kd-split">'
            + '<div class="kd-links">'
            +   bildHtml
            +   '<div class="kd-artikel-name">' + esc(p.artikel_name || '') + '</div>'
            +   (p.artikel_variante ? '<div class="kd-artikel-meta">' + esc(p.artikel_variante) + '</div>' : '')
            +   '<div class="kd-artikel-preis">€ ' + fmt(p.artikel_einzelpreis) + ' / Stk</div>'
            + '</div>'
            + '<div class="kd-rechts">'
            +   '<div class="kd-bonliste">'
            +     '<div class="kd-bonliste-kopf"><span>Artikel</span><span>Ihr Einkauf</span></div>'
            +     '<div class="kd-bonzeilen">' + zeilenHtml + '</div>'
            +     '<div class="kd-summen">'
            +       '<div class="kd-summen-zeile"><span>MwSt. inkludiert</span><span></span></div>'
            +       '<div class="kd-summen-gesamt"><span>Gesamt</span><span>€ ' + fmt(p.gesamt) + '</span></div>'
            +     '</div>'
            +   '</div>'
            + '</div>'
            + '</div>';
    }

    function abrechnenInhalt(p) {
        let html = '<div class="kd-abrechnen-label">Zu zahlen</div>'
            + '<div class="kd-abrechnen-betrag">€ ' + fmt(p.betrag) + '</div>';
        if (p.gegeben !== null && p.gegeben !== undefined) {
            html += '<div class="kd-abrechnen-zeile">Gegeben: € ' + fmt(p.gegeben) + '</div>';
        }
        if (p.rueckgeld !== null && p.rueckgeld !== undefined) {
            html += '<div class="kd-abrechnen-zeile kd-abrechnen-rueckgeld">Rückgeld: € ' + fmt(p.rueckgeld) + '</div>';
        }
        if (p.abgeschlossen) {
            html += '<div class="kd-dank">Vielen Dank für Ihren Einkauf!</div>';
        }
        return html;
    }

    function renderAbrechnen(p, qrAktiv) {
        if (!qrAktiv) {
            root.innerHTML = '<div class="kd-abrechnen-zentriert">' + abrechnenInhalt(p) + '</div>';
            return;
        }
        root.innerHTML = '<div class="kd-split">'
            + '<div class="kd-links">'
            +   '<div class="kd-qr-platzhalter">▦</div>'
            +   '<div class="kd-qr-hinweis">Bon/Rechnung per QR-Code abholen<br><small>(folgt mit dem Paperless-Rechnung-Modul)</small></div>'
            + '</div>'
            + '<div class="kd-rechts"><div class="kd-abrechnen-zentriert" style="gap:14px">' + abrechnenInhalt(p) + '</div></div>'
            + '</div>';
    }

    function render(data) {
        if (data.zustand === 'warenkorb') {
            renderWarenkorb(data.payload);
        } else if (data.zustand === 'abrechnen') {
            renderAbrechnen(data.payload, !!data.qr_aktiv);
        } else {
            renderIdle();
        }
    }

    function poll() {
        fetch(STATUS_URL + '?kasse_id=' + KASSE_ID)
            .then(r => r.json())
            .then(function(data) {
                if (data.aktualisiert_am !== letzterAktualisiertAm) {
                    // Echte Änderung an der Kasse → jede lokale Idle-Übersteuerung verwerfen
                    letzterAktualisiertAm = data.aktualisiert_am;
                    localIdleOverride = false;
                    if (abrechnenTimer) { clearTimeout(abrechnenTimer); abrechnenTimer = null; }

                    render(data);

                    if (data.zustand === 'abrechnen' && data.payload && data.payload.abgeschlossen) {
                        abrechnenTimer = setTimeout(function() {
                            localIdleOverride = true;
                            renderIdle();
                        }, 30000);
                    }
                }
                // Unverändert seit letztem Poll → nichts tun, aktuelle Anzeige bleibt (inkl. evtl. lokaler Idle-Übersteuerung)
            })
            .catch(function() { /* nächster Poll versucht es erneut */ });
    }

    renderIdle();
    poll();
    setInterval(poll, 1000);
    </script>
<?php endif; ?>

</body>
</html>
