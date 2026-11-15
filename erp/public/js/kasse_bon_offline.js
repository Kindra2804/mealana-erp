// ── Offline-Kasse: IndexedDB + direkte BFR-Signierung ──────────────────────
// Läuft komplett ohne Server, sobald die Sync-Daten einmal geladen wurden.

const OB_DB_NAME    = 'mealana_messe_kasse';
const OB_DB_VERSION = 1;

let obDb          = null;
let obArtikel     = [];   // In-Memory-Kopie aus IndexedDB (schnellere Suche)
let obKonfig      = null; // { id:'aktuell', syncId, syncToken, kasse:{...}, lagerId, bonNrJahr, bonNrZaehler }
let obWarenkorb   = [];   // [{artikel_id, bezeichnung, ean, menge, einzelpreis_brutto, steuer_prozent, rabatt_prozent, charge, charge_pflicht, block}]
let obZahlart     = 'bar';
let obVorwahl     = 1;    // Mengenvorwahl für den nächsten Artikel

// Mobil-Messe (Sunmi): Belege OHNE Signatur (Belegerteilungspflicht), Nacherfassung später an der Laden-Kasse
function obIstMobil() { return !!(obKonfig && obKonfig.variante === 'mobil'); }
function obLs(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

// ── IndexedDB Helper (Promise-Wrapper) ──────────────────────────────────────

function obIdbOpen() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(OB_DB_NAME, OB_DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains('konfig'))  db.createObjectStore('konfig', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('artikel')) db.createObjectStore('artikel', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('bons'))    db.createObjectStore('bons', { keyPath: 'lokale_id', autoIncrement: true });
        };
        req.onsuccess = () => { obDb = req.result; resolve(obDb); };
        req.onerror   = () => reject(req.error);
    });
}

function obIdbGet(store, key) {
    return new Promise((resolve, reject) => {
        const tx = obDb.transaction(store, 'readonly');
        const req = tx.objectStore(store).get(key);
        req.onsuccess = () => resolve(req.result || null);
        req.onerror   = () => reject(req.error);
    });
}

function obIdbGetAll(store) {
    return new Promise((resolve, reject) => {
        const tx = obDb.transaction(store, 'readonly');
        const req = tx.objectStore(store).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror   = () => reject(req.error);
    });
}

function obIdbPut(store, value) {
    return new Promise((resolve, reject) => {
        const tx = obDb.transaction(store, 'readwrite');
        const req = tx.objectStore(store).put(value);
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
    });
}

function obIdbClear(store) {
    return new Promise((resolve, reject) => {
        const tx = obDb.transaction(store, 'readwrite');
        const req = tx.objectStore(store).clear();
        req.onsuccess = () => resolve();
        req.onerror   = () => reject(req.error);
    });
}

// ── Initialisierung ──────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
    obLayoutInit();
    await obIdbOpen();
    obKonfig = await obIdbGet('konfig', 'aktuell');

    if (obKonfig) {
        obArtikel = await obIdbGetAll('artikel');
        obBereitSchalten();
    } else {
        document.getElementById('ob-vorbereitung-hinweis').style.display = 'block';
        document.getElementById('ob-bereit-text').textContent = 'Keine Sync-Daten';
    }

    await obBonsZaehlerAktualisieren();

    const scanFeld = document.getElementById('ob-scan');
    scanFeld.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        obScannen(scanFeld.value);
        scanFeld.value = '';
        obSucheAusblenden();
        obKachelnRendern('');
    });
    scanFeld.addEventListener('input', () => { obSucheAktualisieren(scanFeld.value); obKachelnRendern(scanFeld.value); });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#ob-such-ergebnisse') && e.target !== scanFeld) obSucheAusblenden();
    });

    document.getElementById('ob-gegeben').addEventListener('input', obRueckgeldBerechnen);
});

async function obVorbereitungLaden() {
    const syncId = window.OB_SYNC_ID;
    const fb = document.getElementById('ob-vorbereitung-feedback');

    if (!syncId) {
        fb.innerHTML = '<div class="ob-feedback fehler">Kein sync_id in der URL — bitte über "Messe vorbereiten" → "Offline-Kasse laden" öffnen.</div>';
        return;
    }

    fb.innerHTML = '<div class="ob-feedback info">Lade…</div>';
    try {
        const resp = await fetch(window.BASE_PATH + '/kasse/ajax_messe.php?aktion=pre_sync_export&sync_id=' + syncId);
        const data = await resp.json();
        if (!data.erfolg) {
            fb.innerHTML = '<div class="ob-feedback fehler">' + (data.fehler || 'Fehler beim Laden') + '</div>';
            return;
        }

        // Mobil: Gerät-Nr. + Belegzähler (nie zurückzählen: lokaler Stand vs. schon hochgeladene Belege)
        const alt = await obIdbGet('konfig', 'aktuell');
        if (alt && alt.syncId !== data.sync_id) {
            const alteBons = await obIdbGetAll('bons');
            if (alteBons.some(b => !b.hochgeladen)) {
                fb.innerHTML = '<div class="ob-feedback fehler">Auf diesem Gerät liegen noch nicht hochgeladene Belege der Messe Nr. '
                    + alt.syncId + ' — bitte zuerst dort „⤴ Belege hochladen“ (Seite mit ?sync_id=' + alt.syncId + ' öffnen).</div>';
                return;
            }
            await obIdbClear('bons');   // Journal der alten Messe (alles hochgeladen) wegräumen
        }
        await obIdbClear('artikel');
        for (const a of data.artikel) {
            await obIdbPut('artikel', a);
        }
        const geraet = (document.getElementById('ob-geraet')?.value || '1').replace(/[^0-9A-Za-z]/g, '').slice(0, 3) || '1';
        let zaehler = data.bon_nr_zaehler;
        if (data.variante === 'mobil') {
            const lokal = (alt && alt.syncId === data.sync_id && alt.geraet === geraet) ? (alt.bonNrZaehler || 0) : 0;
            zaehler = Math.max(lokal, (data.mobil_zaehler || {})[geraet] || 0);
        }
        await obIdbPut('konfig', {
            id: 'aktuell',
            syncId: data.sync_id,
            syncToken: data.sync_token,
            kasse: data.kasse,
            lagerId: data.lager_id,
            bonNrJahr: data.bon_nr_jahr,
            bonNrZaehler: zaehler,
            variante: data.variante || 'elektronisch',
            firma: data.firma || {},
            partner: data.partner || {},
            geraet: geraet,
            diversArtikelId: data.divers_artikel_id || null,
            gruppen: data.gruppen || [], // Kacheln im Freier-Artikel-Dialog
            mengenRegeln: data.mengen_regeln || [], // Mengen-Aktionen (Zeitraum prüft obMengenAnwenden)
        });

        obKonfig  = await obIdbGet('konfig', 'aktuell');
        obArtikel = await obIdbGetAll('artikel');

        fb.innerHTML = '<div class="ob-feedback ok">' + data.artikel.length + ' Artikel geladen — bereit für Offline-Betrieb.</div>';
        obBereitSchalten();
    } catch (e) {
        fb.innerHTML = '<div class="ob-feedback fehler">Netzwerkfehler: ' + e.message + '</div>';
    }
}

function obBereitSchalten() {
    document.getElementById('ob-vorbereitung-hinweis').style.display = 'none';
    document.getElementById('ob-kasse-bereich').style.display = 'block';
    document.getElementById('ob-bereit-dot').className = 'ob-dot bereit';
    document.getElementById('ob-bereit-text').textContent = 'Bereit (offline-fähig)';
    document.getElementById('ob-kasse-label').textContent = obIstMobil()
        ? 'Messe Nr. ' + obKonfig.syncId + ' · Gerät ' + obKonfig.geraet + ' · ' + obArtikel.length + ' Artikel · Belege ohne Signatur'
        : (obKonfig.kasse?.name || 'Kasse') + ' · ' + obArtikel.length + ' Artikel geladen';
    if (obIstMobil()) {
        document.getElementById('ob-journal-btn').style.display = '';
        document.querySelector('.ob-header-title').textContent = '📱 MOBIL-BELEGE';
        document.getElementById('ob-upload-btn').textContent = '⤴ Belege hochladen';
        document.getElementById('ob-autodruck-wrap').style.display = 'flex';
        document.getElementById('ob-autodruck').checked = obLs('ob_autodruck') !== '0';
        document.getElementById('ob-abschluss-btn').textContent = '✓ Beleg erstellen';
    }
    obMengenLeisteRendern();
    obKachelnRendern('');
}

// ── Anordnung Hoch-/Querformat (Sunmi hoch, Tablet quer) — Wahl merkt sich das Gerät ──
function obLayoutInit() {
    const gemerkt = obLs('ob_layout');
    const hoch = gemerkt ? gemerkt === 'hoch' : window.innerWidth < 760;
    document.body.classList.toggle('lay-hoch', hoch);
    obPlatzhalter();
}
function obPlatzhalter() {
    const f = document.getElementById('ob-scan');
    if (f) f.placeholder = document.body.classList.contains('lay-hoch')
        ? 'Scannen oder Name suchen'
        : 'EAN / Artikelnummer scannen — oder Artikelname eingeben zum Suchen';
}
function obLayoutWechseln() {
    const hoch = !document.body.classList.contains('lay-hoch');
    document.body.classList.toggle('lay-hoch', hoch);
    document.body.classList.remove('korb-offen');
    obLs('ob_layout', hoch ? 'hoch' : 'quer');
    obPlatzhalter();
}
function obKorbUmschalten(offen) {
    if (offen && !document.body.classList.contains('korb-offen') && obWarenkorb.length === 0) return;
    document.body.classList.toggle('korb-offen', offen);
    window.scrollTo(0, 0);
    obSummenBerechnen();   // Text der Leiste unten
}
// Grüner Knopf unten: auf der Artikel-Seite → Warenkorb, auf der Warenkorb-Seite → abschließen
function obLeisteRechts() {
    if (document.body.classList.contains('korb-offen')) {
        if (!document.getElementById('ob-abschluss-btn').disabled) obVerkaufAbschliessen();
    } else {
        obKorbUmschalten(true);
    }
}
function obAutodruckMerken() { obLs('ob_autodruck', document.getElementById('ob-autodruck').checked ? '1' : '0'); }

// ── Mengenvorwahl ────────────────────────────────────────────────────────────
function obMengenLeisteRendern() {
    const el = document.getElementById('ob-mengen-leiste');
    if (!el) return;
    let html = '<span>Menge:</span>';
    [1, 2, 3, 4, 5, 6, 10].forEach(n => {
        html += '<button type="button" class="' + (obVorwahl === n ? 'aktiv' : '') + '" onclick="obVorwahlSetzen(' + n + ')">' + n + '</button>';
    });
    if (![1, 2, 3, 4, 5, 6, 10].includes(obVorwahl)) html += '<button type="button" class="aktiv">' + obVorwahl + '</button>';
    html += '<button type="button" onclick="obVorwahlFrei()" title="andere Menge">…</button>';
    el.innerHTML = html;
}
function obVorwahlSetzen(n) { obVorwahl = n; obMengenLeisteRendern(); }
function obVorwahlFrei() {
    const n = parseInt(prompt('Menge für den nächsten Artikel:', '12') || '', 10);
    if (n > 0 && n < 1000) obVorwahlSetzen(n);
}
function obVorwahlNehmen() { const n = obVorwahl; if (n !== 1) { obVorwahl = 1; obMengenLeisteRendern(); } return n; }

// ── Artikel-Kacheln (alle mitgenommenen Artikel; Eingabefeld filtert) ─────────
function obKachelnRendern(filter) {
    const el = document.getElementById('ob-kacheln');
    if (!el) return;
    filter = (filter || '').trim().toLowerCase();
    const heute = obHeute();
    const inAktion = {};
    ((obKonfig && obKonfig.mengenRegeln) || [])
        .filter(r => (!r.gueltig_ab || r.gueltig_ab <= heute) && (!r.gueltig_bis || r.gueltig_bis >= heute))
        .forEach(r => (r.artikel_ids || []).forEach(id => { inAktion[id] = inAktion[id] || r.badge_text; }));
    const liste = obArtikel
        .filter(a => !filter || /^[0-9]+$/.test(filter) || (a.bezeichnung || '').toLowerCase().includes(filter)
                     || (a.artikelnummer || '').toLowerCase().includes(filter))
        .slice().sort((a, b) => (a.bezeichnung || '').localeCompare(b.bezeichnung || '', 'de'));
    let html = '';
    liste.forEach(a => {
        const aktion = !a.preis_reduziert && inAktion[a.id];
        html += '<button type="button" class="ob-kachel' + (aktion ? ' aktion' : '') + '" onclick="obKachelTipp(' + a.id + ')">'
            + '<span class="n">' + obEsc(a.bezeichnung) + '</span>'
            + (aktion ? '<span class="a">' + obEsc(aktion) + '</span>' : '')
            + '<span class="p">' + (a.preis_reduziert ? '<s>' + (parseFloat(a.preis_normal) || 0).toFixed(2) + '</s> ' : '')
            + '€ ' + (parseFloat(a.brutto_vk) || 0).toFixed(2) + '</span></button>';
    });
    el.innerHTML = html || '<div style="font-size:13px;color:#94a3b8">Keine Artikel.</div>';
}
function obKachelTipp(id) {
    const a = obArtikel.find(x => x.id === id);
    if (a) obArtikelWaehlen(a);
}

// ── Scan / Textsuche / Warenkorb ─────────────────────────────────────────────

function obScannen(code) {
    code = (code || '').trim();
    if (!code) return;
    const fb = document.getElementById('ob-scan-feedback');

    // Scanner mit US-Belegung: "ß" statt "-", Y/Z vertauscht (Artikelnummern mit "-", z.B. Kuvert-Etikett)
    const gross = s => String(s || '').toUpperCase();
    const korr  = gross(code).replace(/[ßẞ?]/g, '-');
    const yz    = korr.replace(/[YZ]/g, c => (c === 'Y' ? 'Z' : 'Y'));
    const treffer = obArtikel.find(a => a.ean === code || a.artikelnummer === code || String(a.id) === code)
        || obArtikel.find(a => a.artikelnummer && (gross(a.artikelnummer) === korr || gross(a.artikelnummer) === yz));
    if (!treffer) {
        fb.textContent = 'Nicht gefunden: ' + code + ' — ggf. Artikelname eingeben zum Suchen, oder "➕ Freier Artikel" nutzen.';
        fb.style.color = '#dc2626';
        return;
    }
    fb.textContent = '';
    obArtikelWaehlen(treffer);
}

// Zentrale Stelle: Artikel wurde per Scan/Suche ausgewählt.
// Bei Chargenpflicht: Auswahl-Overlay öffnen (nur echte, mitgenommene Chargen).
// Sonst: direkt in den Warenkorb.
function obArtikelWaehlen(artikel) {
    if (artikel.charge_pflicht) {
        obChargeAuswahlOeffnen(artikel);
        return;
    }
    const menge = obVorwahlNehmen();
    obZumWarenkorbHinzufuegen(artikel, null, menge);
    const fb = document.getElementById('ob-scan-feedback');
    fb.textContent = (menge > 1 ? menge + ' × ' : '') + artikel.bezeichnung + ' hinzugefügt.';
    fb.style.color = '#16a34a';
}

function obSucheAktualisieren(text) {
    text = (text || '').trim().toLowerCase();
    const box = document.getElementById('ob-such-ergebnisse');
    if (text.length < 2) { obSucheAusblenden(); return; }

    // Reine EAN/Artikelnummer-Eingaben (Scanner tippt oft schnell Ziffern) nicht als Textsuche behandeln
    if (/^[0-9]+$/.test(text)) { obSucheAusblenden(); return; }

    const treffer = obArtikel.filter(a => (a.bezeichnung || '').toLowerCase().includes(text)).slice(0, 8);
    if (treffer.length === 0) {
        box.innerHTML = '<div style="padding:10px;font-size:13px;color:#94a3b8">Keine Treffer.</div>';
        box.style.display = 'block';
        return;
    }

    let html = '';
    treffer.forEach((a, idx) => {
        html += '<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9" '
            + 'onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'\'" '
            + 'onclick="obSucheAuswaehlen(' + idx + ')" data-such-idx="' + idx + '">';
        html += '  <div style="font-size:13px;font-weight:600">' + obEsc(a.bezeichnung) + '</div>';
        html += '  <div style="font-size:11px;color:#94a3b8">' + (a.preis_reduziert ? '<s>€ ' + (parseFloat(a.preis_normal) || 0).toFixed(2) + '</s> ' + obEsc(a.preis_info || '') + ' ' : '')
            + '€ ' + (parseFloat(a.brutto_vk) || 0).toFixed(2) + (a.artikelnummer ? ' · ' + obEsc(a.artikelnummer) : '') + '</div>';
        html += '</div>';
    });
    box.innerHTML = html;
    box.style.display = 'block';
    window._obSucheTreffer = treffer;
}

function obSucheAuswaehlen(idx) {
    const artikel = window._obSucheTreffer[idx];
    obSucheAusblenden();
    document.getElementById('ob-scan').value = '';
    obArtikelWaehlen(artikel);
}

function obSucheAusblenden() {
    const box = document.getElementById('ob-such-ergebnisse');
    if (box) box.style.display = 'none';
}

// ── Chargen-Auswahl (nur echte, zur Messe mitgenommene Chargen) ─────────────

function obChargeBestandVerbleibend(artikelId, charge, chargenListe) {
    const eintrag = (chargenListe || []).find(c => c.charge === charge);
    const original = eintrag ? eintrag.menge : 0;
    const imWarenkorb = obWarenkorb
        .filter(z => z.artikel_id === artikelId && z.charge === charge)
        .reduce((sum, z) => sum + z.menge, 0);
    return original - imWarenkorb;
}

let obChargeWartenderArtikel = null;

function obChargeAuswahlOeffnen(artikel) {
    obChargeWartenderArtikel = artikel;
    document.getElementById('ob-charge-titel').textContent = 'Charge wählen — ' + artikel.bezeichnung;
    const liste = document.getElementById('ob-charge-liste');
    const chargen = artikel.chargen || [];

    if (chargen.length === 0) {
        liste.innerHTML = '<div style="color:#dc2626;font-size:13px">Für diesen Artikel wurde keine Charge zur Messe mitgenommen — Verkauf hier nicht möglich.</div>';
    } else {
        let html = '';
        chargen.forEach((c, idx) => {
            const verbleibend = obChargeBestandVerbleibend(artikel.id, c.charge, chargen);
            const disabled = verbleibend <= 0;
            html += '<div style="display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px' + (disabled ? ';opacity:.45' : '') + '">';
            html += '  <div style="flex:1">';
            html += '    <div style="font-weight:600;font-size:13px">' + obEsc(c.charge) + '</div>';
            html += '    <div style="font-size:11px;color:#94a3b8">noch verfügbar: ' + verbleibend + '</div>';
            html += '  </div>';
            html += '  <button type="button" class="ob-btn ob-btn-primary" style="padding:6px 14px;font-size:12px" ' + (disabled ? 'disabled' : '') + ' onclick="obChargeWaehlen(' + idx + ')">+1</button>';
            html += '</div>';
        });
        liste.innerHTML = html;
    }
    document.getElementById('ob-charge-overlay').classList.add('aktiv');
}

function obChargeWaehlen(idx) {
    const artikel = obChargeWartenderArtikel;
    const c = artikel.chargen[idx];
    obZumWarenkorbHinzufuegen(artikel, c.charge, 1);
    obChargeAuswahlOeffnen(artikel); // Liste neu rendern (verbleibende Menge aktualisiert)
}

function obChargeAbbrechen() {
    document.getElementById('ob-charge-overlay').classList.remove('aktiv');
    obChargeWartenderArtikel = null;
    document.getElementById('ob-scan').focus();
}

// ── Freier Artikel ───────────────────────────────────────────────────────────

// Ablauf wie online (bon.php diversDialog): Gruppe antippen → Bezeichnung + Steuer
// aus der Gruppe vorbelegt (beides änderbar) → Preis über Numpad → Hinzufügen.
// Die Gruppe geht als artikel_gruppe_id mit und bestimmt das Erlöskonto im Export.
// Gruppen kommen aus den Sync-Daten — wurde die Messe noch mit altem Stand geladen
// (keine Gruppen), geht es wie früher ohne Gruppe weiter (Export: Divers-Fallback).
let obFreierGruppeId      = null;
let obFreierSteuer        = 20;
let obFreierNameAutomatik = true; // Bezeichnung = Gruppenname, solange nicht von Hand geändert

function obFreierGruppen() {
    return (obKonfig && obKonfig.gruppen) || [];
}

function obFreierArtikelOeffnen() {
    const gruppen = obFreierGruppen();
    const box = document.getElementById('ob-freier-gruppen');
    box.innerHTML = '';
    gruppen.forEach(g => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = g.name;
        b.dataset.id = g.id;
        b.onclick = () => obFreierGruppeWaehlen(parseInt(g.id));
        box.appendChild(b);
    });
    document.getElementById('ob-freier-gruppen-block').style.display = gruppen.length ? '' : 'none';

    obFreierNameAutomatik = true;
    document.getElementById('ob-freier-bezeichnung').value = '';
    document.getElementById('ob-freier-preis').value = '';
    const start = (gruppen.find(g => g.ist_standard == 1) || gruppen[0] || {}).id;
    obFreierGruppeId = null;
    obFreierSteuerSetzen(20);
    if (start) obFreierGruppeWaehlen(parseInt(start));

    obFreierPruefen();
    document.getElementById('ob-freier-overlay').classList.add('aktiv');
    document.getElementById('ob-freier-preis').focus();
}

function obFreierGruppeWaehlen(id) {
    obFreierGruppeId = id;
    const g = obFreierGruppen().find(x => parseInt(x.id) === id);
    document.querySelectorAll('#ob-freier-gruppen button').forEach(b => {
        b.classList.toggle('aktiv', parseInt(b.dataset.id) === id);
    });
    if (g && obFreierNameAutomatik) {
        document.getElementById('ob-freier-bezeichnung').value = g.name;
    }
    obFreierSteuerSetzen(g && g.standard_steuer_prozent !== null ? parseFloat(g.standard_steuer_prozent) : 20);
    obFreierPruefen();
}

function obFreierSteuerSetzen(satz) {
    obFreierSteuer = satz;
    document.querySelectorAll('#ob-freier-steuer button').forEach(b => {
        b.classList.toggle('aktiv', parseFloat(b.dataset.satz) === satz);
    });
}

function obFreierNp(z) {
    const inp = document.getElementById('ob-freier-preis');
    if (z === ',' && /[,.]/.test(inp.value)) return;
    if (/[,.]\d\d$/.test(inp.value)) return; // max. 2 Nachkommastellen
    inp.value += z;
    obFreierPruefen();
}

function obFreierNpBack() {
    const inp = document.getElementById('ob-freier-preis');
    inp.value = inp.value.slice(0, -1);
    obFreierPruefen();
}

function obFreierPreis() {
    return parseFloat(document.getElementById('ob-freier-preis').value.replace(',', '.')) || 0;
}

function obFreierPruefen() {
    // Sobald jemand die Bezeichnung selbst tippt, nicht mehr beim Gruppenwechsel überschreiben
    const name = document.getElementById('ob-freier-bezeichnung').value.trim();
    const g = obFreierGruppen().find(x => parseInt(x.id) === obFreierGruppeId);
    obFreierNameAutomatik = !name || (g && name === g.name);

    const gruppeOk = obFreierGruppeId || !obFreierGruppen().length;
    document.getElementById('ob-freier-ok').disabled = !(name && gruppeOk && obFreierPreis() > 0);
}

function obFreierArtikelSchliessen() {
    document.getElementById('ob-freier-overlay').classList.remove('aktiv');
    document.getElementById('ob-scan').focus();
}

function obFreierArtikelHinzufuegen() {
    const bezeichnung = document.getElementById('ob-freier-bezeichnung').value.trim();
    const preis       = Math.round(obFreierPreis() * 100) / 100;
    const steuer      = obFreierSteuer;

    if (!bezeichnung || !(preis > 0)) {
        alert('Bitte Bezeichnung und einen Preis größer 0 eingeben.');
        return;
    }
    if (!obKonfig.diversArtikelId) {
        alert('Kein Platzhalter-Artikel (99-9999) in den Sync-Daten gefunden — Freier Artikel nicht möglich.');
        return;
    }

    obWarenkorb.push({
        artikel_id: obKonfig.diversArtikelId,
        artikel_gruppe_id: obFreierGruppeId,
        bezeichnung: bezeichnung,
        ean: '',
        menge: 1,
        einzelpreis_brutto: preis,
        steuer_prozent: steuer,
        rabatt_prozent: 0,
        charge: null,
        block: 'normal',
    });
    obFreierArtikelSchliessen();
    obWarenkorbRendern();
}

function obZumWarenkorbHinzufuegen(artikel, charge, mengeNeu) {
    mengeNeu = mengeNeu || 1;
    if (!charge) {
        const bestehend = obWarenkorb.find(z => z.artikel_id === artikel.id && !z.charge);
        if (bestehend) {
            bestehend.menge += mengeNeu;
            obWarenkorbRendern();
            return;
        }
    } else {
        const bestehend = obWarenkorb.find(z => z.artikel_id === artikel.id && z.charge === charge);
        if (bestehend) {
            bestehend.menge += mengeNeu;
            obWarenkorbRendern();
            return;
        }
    }
    obWarenkorb.push({
        artikel_id: artikel.id,
        bezeichnung: artikel.bezeichnung,
        ean: artikel.ean || '',
        menge: mengeNeu,
        einzelpreis_brutto: parseFloat(artikel.brutto_vk) || 0,
        steuer_prozent: parseFloat(artikel.steuer_prozent) || 20,
        rabatt_prozent: 0,
        charge: charge,
        block: 'normal',
        // Partnerware: Fremdrechnung/Spende stehen am Beleg im Abschnitt des Partners
        partner_id: artikel.partner_id || null,
        partner_modus: artikel.partner_modus || null,
        // Sale-/Aktionspreis (kommt so schon aus dem Export) — zählt nicht für Mengen-Aktionen
        preis_reduziert: !!artikel.preis_reduziert,
        preis_normal: parseFloat(artikel.preis_normal) || 0,
        preis_info: artikel.preis_info || '',
    });
    obWarenkorbRendern();
}

// ── Mengen-Aktionen (gleicher Rechenkern wie die Laden-Kasse: js/mengen_aktion_rechner.js) ──
// Es zählen normale Artikelzeilen ohne Rabatt und ohne Sale-/Aktionspreis; der Zeitraum wird am
// Gerät geprüft (Messe über mehrere Tage). Setzt z._mengen und obMengenHinweise.
let obMengenHinweise = [];
function obHeute() {
    const d = new Date(), p = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}
function obMengenAnwenden() {
    obMengenHinweise = [];
    obWarenkorb.forEach(z => { z._mengen = null; });
    const heute = obHeute();
    const regeln = ((obKonfig && obKonfig.mengenRegeln) || [])
        .filter(r => (!r.gueltig_ab || r.gueltig_ab <= heute) && (!r.gueltig_bis || r.gueltig_bis >= heute));
    if (!regeln.length || typeof mengenAktionBerechne !== 'function') return;
    const divers = obKonfig.diversArtikelId;
    const pos = [];
    obWarenkorb.forEach((z, i) => {
        if (z.block !== 'normal' || !z.artikel_id || z.artikel_id === divers || !(z.menge > 0) || z.menge % 1 !== 0) return;
        pos.push({ key: i, artikel_id: z.artikel_id, menge: z.menge, preis: z.einzelpreis_brutto,
                   ist_reduziert: z.rabatt_prozent > 0 || !!z.preis_reduziert });
    });
    const erg = mengenAktionBerechne(regeln, pos);
    Object.keys(erg.positionen).forEach(i => { obWarenkorb[i]._mengen = erg.positionen[i]; });
    obMengenHinweise = erg.hinweise;
}
// Zeilensumme brutto: mit Mengen-Aktion der reduzierte Betrag
function obZeileBrutto(z) {
    if (z._mengen) return z._mengen.gesamt_neu;
    return z.menge * z.einzelpreis_brutto * (1 - z.rabatt_prozent / 100);
}

function obWarenkorbRendern() {
    const liste = document.getElementById('ob-warenkorb-liste');
    obMengenAnwenden();

    if (obWarenkorb.length === 0) {
        liste.innerHTML = '<li style="text-align:center;color:#94a3b8;padding:20px 0">Warenkorb ist leer.</li>';
        obSummenBerechnen();
        return;
    }

    let html = '';
    obWarenkorb.forEach((z, idx) => {
        const gesamt = obZeileBrutto(z);
        html += '<li class="ob-zeile">';
        html += '  <div style="flex:1;min-width:0">';
        html += '    <div class="ob-zeile-name">' + obEsc(z.bezeichnung) + '</div>';
        html += '    <div class="ob-zeile-sub">' + (z.preis_reduziert ? '<s>€ ' + z.preis_normal.toFixed(2) + '</s> ' + obEsc(z.preis_info) + ' ' : '')
            + '€ ' + z.einzelpreis_brutto.toFixed(2) + ' · ' + z.steuer_prozent + '% MwSt' + (z.ean ? ' · ' + obEsc(z.ean) : '') + '</div>';
        if (z._mengen) {
            html += '    <div style="font-size:11px;color:#b45309;font-weight:600">Aktion ' + obEsc(z._mengen.badge_text)
                + (z._mengen.stueck_im_paket < z.menge ? ' (' + z._mengen.stueck_im_paket + ' von ' + z.menge + ' Stk)' : '')
                + ' · statt € ' + z._mengen.gesamt_alt.toFixed(2) + '</div>';
        }
        if (z.charge) {
            html += '    <div style="font-size:11px;color:#2563eb">Charge: ' + obEsc(z.charge) + '</div>';
        }
        html += '  </div>';
        html += '  <div class="ob-zeile-menge">';
        html += '    <button onclick="obMengeAendern(' + idx + ', -1)">−</button>';
        html += '    <span style="min-width:24px;text-align:center">' + z.menge + '</span>';
        html += '    <button onclick="obMengeAendern(' + idx + ', 1)">+</button>';
        html += '  </div>';
        html += '  <div class="ob-zeile-preis">€ ' + gesamt.toFixed(2) + '</div>';
        html += '  <button class="ob-zeile-x" onclick="obEntfernen(' + idx + ')">✕</button>';
        html += '</li>';
    });
    // Hinweise „noch x Stück → Aktion“ und Ersparnis
    obMengenHinweise.forEach(h => {
        if (h.fehlen > 0 && h.stueck > 0) {
            html += '<li style="font-size:12px;color:#b45309;padding:6px 4px">Noch ' + h.fehlen + ' Stk für „' + obEsc(h.badge_text) + '“</li>';
        }
        if (h.ersparnis > 0) {
            html += '<li style="font-size:12px;color:#16a34a;padding:2px 4px">Ersparnis durch Aktion „' + obEsc(h.badge_text) + '“: € ' + h.ersparnis.toFixed(2) + '</li>';
        }
    });
    liste.innerHTML = html;
    obSummenBerechnen();
}

function obMengeAendern(idx, delta) {
    const z = obWarenkorb[idx];
    if (delta > 0 && z.charge) {
        // Bei Chargen darf nicht über den tatsächlich mitgenommenen Bestand hinaus erhöht werden
        const artikel = obArtikel.find(a => a.id === z.artikel_id);
        const verbleibend = obChargeBestandVerbleibend(z.artikel_id, z.charge, artikel ? artikel.chargen : []);
        if (verbleibend <= 0) { alert('Keine weitere Menge dieser Charge verfügbar.'); return; }
    }
    z.menge += delta;
    if (z.menge <= 0) obWarenkorb.splice(idx, 1);
    obWarenkorbRendern();
}

function obEntfernen(idx) {
    obWarenkorb.splice(idx, 1);
    obWarenkorbRendern();
}

// ── Summen / Steuergruppen (gleiche Zuordnung wie BfrService::steuerGruppenAusPositionen) ──

function obSteuerGruppe(steuerProzent) {
    const s = parseFloat(steuerProzent);
    if (s === 20) return 'A';
    if (s === 10) return 'B';
    if (s === 13) return 'C';
    if (s === 0)  return 'D';
    return 'E';
}

function obSummenBerechnen() {
    const steuer = { A: 0, B: 0, C: 0, D: 0, E: 0 };
    let gesamt = 0;

    obWarenkorb.forEach(z => {
        const brutto = obZeileBrutto(z);
        steuer[obSteuerGruppe(z.steuer_prozent)] += brutto;
        gesamt += brutto;
    });

    let html = '';
    ['A', 'B', 'C', 'D', 'E'].forEach(g => {
        if (steuer[g] > 0.001) {
            html += '<div class="ob-summe-row"><span>Steuergr. ' + g + '</span><span>€ ' + steuer[g].toFixed(2) + '</span></div>';
        }
    });
    document.getElementById('ob-steuer-zeilen').innerHTML = html;
    document.getElementById('ob-gesamt-anzeige').textContent = '€ ' + gesamt.toFixed(2);
    const stk = obWarenkorb.reduce((s, z) => s + z.menge, 0);
    const bb = document.getElementById('ob-bb-rechts');
    if (bb) bb.textContent = document.body.classList.contains('korb-offen')
        ? '✓ ' + (obIstMobil() ? 'Beleg' : 'Abschließen') + ' € ' + gesamt.toFixed(2)
        : (stk ? '🛒 ' + stk + ' · € ' + gesamt.toFixed(2) + ' ▶' : '🛒 leer');

    document.getElementById('ob-abschluss-btn').disabled = obWarenkorb.length === 0;
    obRueckgeldBerechnen();

    return { steuer, gesamt };
}

function obZahlartWaehlen(art) {
    obZahlart = art;
    document.querySelectorAll('.ob-zahlart-btn').forEach(b => {
        b.classList.toggle('aktiv', b.dataset.zahlart === art);
    });
    document.getElementById('ob-bar-felder').style.display = art === 'bar' ? 'block' : 'none';
    obRueckgeldBerechnen();
}

function obRueckgeldBerechnen() {
    if (obZahlart !== 'bar') return;
    const { gesamt } = obAktuelleSumme();
    const gegeben = parseFloat(document.getElementById('ob-gegeben').value) || 0;
    const rueckgeld = gegeben - gesamt;
    const el = document.getElementById('ob-rueckgeld-anzeige');
    el.textContent = gegeben > 0 ? 'Rückgeld: € ' + rueckgeld.toFixed(2) : '';
    el.style.color = rueckgeld < 0 ? '#dc2626' : '#16a34a';
}

function obAktuelleSumme() {
    const steuer = { A: 0, B: 0, C: 0, D: 0, E: 0 };
    let gesamt = 0;
    obWarenkorb.forEach(z => {
        const brutto = obZeileBrutto(z);
        steuer[obSteuerGruppe(z.steuer_prozent)] += brutto;
        gesamt += brutto;
    });
    return { steuer, gesamt };
}

// ── Verkauf abschließen + BFR-Signierung ────────────────────────────────────

function obDatumFormat(d, trenner) {
    const p = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
        + trenner + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}

function obEsc(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function obEscXml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function obBaueSignierXml(bon) {
    const d = new Date(bon.erstellt_am_iso);
    let xml = '<?xml version="1.0"?>\n<Tra><ESR D="' + obDatumFormat(d, 'T') + '" TN="' + obEscXml(bon.bon_nr) + '" T="' + bon.bruttobetrag.toFixed(2) + '"><TaxA>';
    ['A', 'B', 'C', 'D', 'E'].forEach(g => {
        xml += '<Tax TaxG="' + g + '" Amt="' + (bon.steuer[g] || 0).toFixed(2) + '"/>';
    });
    xml += '</TaxA></ESR></Tra>';
    return xml;
}

// ── RKSV: BFR-Erreichbarkeit prüfen, BEVOR ein Bon entsteht ─────────────────
// "Kein Dienst, keine Kasse" (Empfehlung des BFR-Herstellers) — verhindert das
// alte Duplicate-/Reihenfolge-Risiko der früheren "später nachsignieren"-Logik.
let obBfrFehlschlagAnzahl = 0;

// Die in obKonfig.kasse.bfr_url gespeicherte Adresse ist die serverseitig
// konfigurierte LAN-IP (für die Online-Signierung gedacht). Diese Offline-Kasse
// und ihr BFR laufen aber immer auf demselben Gerät — bei echter Netztrennung
// (Messe ohne Verbindung ins Heimnetz) wäre die LAN-IP unerreichbar, obwohl BFR
// direkt daneben läuft. Deshalb hier IMMER 127.0.0.1 ansprechen, nur den Port
// aus der konfigurierten URL übernehmen.
function obBfrUrlLokal() {
    const konfiguriert = obKonfig.kasse.bfr_url;
    if (!konfiguriert) return konfiguriert;
    try {
        const port = new URL(konfiguriert).port || '8787';
        return 'http://127.0.0.1:' + port;
    } catch (e) {
        return konfiguriert;
    }
}

// Browser -> BFR DIREKT wird immer von CORS blockiert (BFR schickt keine
// Access-Control-Allow-Origin-Header, bestätigt 2026-07-09). Deshalb läuft jeder
// BFR-Zugriff über den lokalen Proxy (bfr_lokal_proxy.ps1, fester Port 8788,
// muss vor dem Messe-Einsatz gestartet werden) — der macht den eigentlichen
// Call serverseitig (PowerShell unterliegt keinem CORS) und reicht ihn mit
// korrekten CORS-Headern zurück.
const OB_PROXY_URL = 'http://127.0.0.1:8788';

async function obBfrProxyFetch(method, zielUrl, body) {
    const options = { method: method };
    if (body) {
        options.headers = { 'Content-Type': 'text/xml' };
        options.body = body;
    }
    const resp = await fetch(OB_PROXY_URL + '/proxy?url=' + encodeURIComponent(zielUrl), options);
    const daten = await resp.json();
    if (!daten.ok) throw new Error(daten.fehler || 'BFR nicht erreichbar');
    return daten.body;
}

async function obPruefeBfrErreichbar(bfrUrl) {
    try {
        await obBfrProxyFetch('GET', bfrUrl.replace(/\/$/, '') + '/state', null);
        return true;
    } catch (e) {
        return false;
    }
}

async function obPruefeBfrMitKurzRetry(bfrUrl) {
    for (let i = 0; i < 3; i++) {
        if (await obPruefeBfrErreichbar(bfrUrl)) return true;
        if (i < 2) await new Promise(r => setTimeout(r, 500));
    }
    return false;
}

function obZeigeBfrPopup() {
    obBfrFehlschlagAnzahl++;
    const stufe2 = obBfrFehlschlagAnzahl >= 2;
    document.getElementById('ob-bfr-stufe1').style.display = stufe2 ? 'none' : 'block';
    document.getElementById('ob-bfr-stufe2').style.display = stufe2 ? 'block' : 'none';
    document.getElementById('ob-bfr-retry-btn').textContent = stufe2
        ? 'Überprüft — Dienst sollte wieder laufen'
        : 'Erneut versuchen';
    document.getElementById('ob-bfr-overlay').classList.add('aktiv');
}

async function obBfrErneutVersuchen() {
    const bfrUrl = obBfrUrlLokal();
    if (await obPruefeBfrErreichbar(bfrUrl)) {
        obBfrFehlschlagAnzahl = 0;
        document.getElementById('ob-bfr-overlay').classList.remove('aktiv');
        obVerkaufAbschliessen();
    } else {
        obZeigeBfrPopup();
    }
}

async function obVerkaufAbschliessen() {
    if (obWarenkorb.length === 0) return;

    const { steuer, gesamt } = obAktuelleSumme();
    const gegeben = obZahlart === 'bar' ? (parseFloat(document.getElementById('ob-gegeben').value) || 0) : null;
    if (obZahlart === 'bar' && gegeben < gesamt) {
        alert('Gegebener Betrag ist kleiner als die Summe.');
        return;
    }

    document.getElementById('ob-abschluss-btn').disabled = true;

    const bfrUrl = obIstMobil() ? null : obBfrUrlLokal();   // Mobil: keine Signatur (Nacherfassung im Laden)
    if (bfrUrl && !(await obPruefeBfrMitKurzRetry(bfrUrl))) {
        document.getElementById('ob-abschluss-btn').disabled = false;
        obZeigeBfrPopup();
        return;
    }
    obBfrFehlschlagAnzahl = 0;

    obKonfig.bonNrZaehler++;
    // Mobil: M<Messe-Nr>-<Gerät>-<laufend>, fortlaufend je Gerät (steht so am Beleg und bei der Nacherfassung)
    const bonNr = obIstMobil()
        ? 'M' + obKonfig.syncId + '-' + obKonfig.geraet + '-' + String(obKonfig.bonNrZaehler).padStart(4, '0')
        : obKonfig.kasse.kasse_nr + '-' + obKonfig.bonNrJahr + '-' + String(obKonfig.bonNrZaehler).padStart(6, '0');
    const jetzt = new Date();

    const bon = {
        bon_nr: bonNr,
        erstellt_am_iso: jetzt.toISOString(),
        erstellt_am: obDatumFormat(jetzt, ' '),
        zahlungsart: obZahlart,
        bruttobetrag: gesamt,
        gegeben: obZahlart === 'bar' ? gegeben : null,
        rueckgeld: obZahlart === 'bar' ? (gegeben - gesamt) : null,
        bar_betrag: obZahlart === 'bar' ? gesamt : null,
        karten_betrag: obZahlart === 'karte_extern' ? gesamt : null,
        gutschein_code: null,
        gutschein_betrag: null,
        steuer: steuer,
        positionen: obWarenkorb.map(z => ({
            block: z.block,
            artikel_id: z.artikel_id,
            artikel_gruppe_id: z.artikel_gruppe_id || null,
            bezeichnung: z.bezeichnung,
            ean: z.ean,
            menge: z.menge,
            // Mit Mengen-Aktion: reduzierter Einzelpreis (4 Nachkommastellen) + Preis vorher, wie online
            einzelpreis_brutto: z._mengen ? z._mengen.einzelpreis_neu : z.einzelpreis_brutto,
            preis_vorher_brutto: z._mengen ? z.einzelpreis_brutto : null,
            mengen_aktion_id: z._mengen ? z._mengen.aktion_id : null,
            aktion_hinweis: z._mengen ? z._mengen.badge_text : null,
            zeile_brutto: Math.round(obZeileBrutto(z) * 100) / 100,   // nur für die Quittung
            rabatt_prozent: z.rabatt_prozent,
            steuer_prozent: z.steuer_prozent,
            charge: z.charge || null,
            partner_id: z.partner_id || null,
            partner_modus: z.partner_modus || null,
        })),
        rksv_signatur: null,
        rksv_qr: null,
        mobil: obIstMobil(),
        geraet: obIstMobil() ? obKonfig.geraet : null,
    };

    // ── Direkte RKSV-Signierung gegen den lokalen BFR ──────────────────────
    // /state war soeben erfolgreich — laut Hersteller-API kommt von /register
    // garantiert eine Antwort (RC ist immer "OK", nur <Link> unterscheidet
    // echte Signatur von "Sicherheitseinrichtung ausgefallen"). Bleibt sie
    // trotzdem aus (das enge Zeitfenster dazwischen), wird das defensiv genauso
    // behandelt — der Verkauf ist an dieser Stelle bereits nicht mehr rückgängig
    // zu machen, siehe BfrService::parseRegisterAntwort() auf dem Server.
    if (bfrUrl) {
        try {
            const xml  = obBaueSignierXml(bon);
            const text = await obBfrProxyFetch('POST', bfrUrl.replace(/\/$/, '') + '/register', xml);
            const doc  = new DOMParser().parseFromString(text, 'text/xml');
            const link = doc.querySelector('Fis > Link')?.textContent || '';
            if (link && link !== 'Sicherheitseinrichtung ausgefallen') {
                bon.rksv_signatur = link;
                bon.rksv_qr       = doc.querySelector('Fis > Code')?.textContent || null;
            } else {
                bon.rksv_signatur = 'Sicherheitseinrichtung ausgefallen';
                bon.rksv_qr       = doc.querySelector('Fis > Code')?.textContent || null;
            }
        } catch (e) {
            bon.rksv_signatur = 'Sicherheitseinrichtung ausgefallen';
        }
    }

    await obIdbPut('bons', bon);
    await obIdbPut('konfig', obKonfig);

    obQuittungAnzeigen(bon);
    if (obIstMobil() && document.getElementById('ob-autodruck').checked) {
        setTimeout(() => window.print(), 300);
    }

    obWarenkorb = [];
    document.getElementById('ob-gegeben').value = '';
    document.body.classList.remove('korb-offen');
    obWarenkorbRendern();
    await obBonsZaehlerAktualisieren();
    document.getElementById('ob-abschluss-btn').disabled = false;
}

// ── Quittung ─────────────────────────────────────────────────────────────────

// Beleg der Mobil-Messe (Belegerteilungspflicht, § 132a BAO): Unternehmen, fortlaufende Nummer, Datum,
// Menge + handelsübliche Bezeichnung, Betrag — keine Signatur, wird im Laden nacherfasst.
function obMobilBelegHtml(bon) {
    const f = obKonfig.firma || {};
    let h = '<div class="z b" style="font-size:14px">' + obEsc(f.firmenname || '') + '</div>';
    const adr = [f.strasse, [f.plz, f.ort].filter(Boolean).join(' ')].filter(Boolean).join(', ');
    if (adr) h += '<div class="z" style="font-size:11px">' + obEsc(adr) + '</div>';
    if (f.uid_nummer) h += '<div class="z" style="font-size:11px">UID ' + obEsc(f.uid_nummer) + '</div>';
    h += '<div class="l"></div>';
    h += '<div class="r"><span>Beleg Nr.</span><span class="b">' + obEsc(bon.bon_nr) + '</span></div>';
    h += '<div class="r"><span>Datum</span><span>' + obEsc(bon.erstellt_am.slice(8, 10) + '.' + bon.erstellt_am.slice(5, 7) + '.'
        + bon.erstellt_am.slice(0, 4) + ' ' + bon.erstellt_am.slice(11, 16)) + '</span></div>';
    h += '<div class="l"></div>';
    const fremd = p => p.partner_modus === 'fremdrechnung' || p.partner_modus === 'spende';
    const zeile = p => {
        const gs = p.zeile_brutto ?? (p.menge * p.einzelpreis_brutto);
        return '<div>' + p.menge + ' × ' + obEsc(p.bezeichnung) + '</div>'
            + '<div class="r k"><span>&nbsp;à ' + (p.preis_vorher_brutto ?? p.einzelpreis_brutto).toFixed(2)
            + (p.aktion_hinweis ? ' Aktion ' + obEsc(p.aktion_hinweis) : '') + '</span><span class="b">' + gs.toFixed(2) + '</span></div>';
    };
    const ustListe = (pos) => {
        const ust = {};
        pos.forEach(p => { const s = parseFloat(p.steuer_prozent) || 0; ust[s] = (ust[s] || 0) + (p.zeile_brutto ?? p.menge * p.einzelpreis_brutto); });
        let x = '';
        Object.keys(ust).sort((a, b) => b - a).forEach(s => {
            const b = ust[s], st = b - b / (1 + s / 100);
            x += '<div class="r k"><span>inkl. ' + s + ' % USt</span><span>' + st.toFixed(2) + '</span></div>';
        });
        return x;
    };
    // Eigene Ware (unser Umsatz, USt nur darüber)
    const eigene = bon.positionen.filter(p => !fremd(p));
    eigene.forEach(p => { h += zeile(p); });
    if (eigene.length) h += ustListe(eigene);
    // Partnerware: je Partner ein Abschnitt „im Namen und auf Rechnung von“ bzw. „Spende für“ (wie Laden-Bon)
    const gruppen = {};
    bon.positionen.filter(fremd).forEach(p => { (gruppen[p.partner_id] = gruppen[p.partner_id] || []).push(p); });
    Object.keys(gruppen).forEach(pid => {
        const pa = (obKonfig.partner || {})[pid] || { name: 'Partner ' + pid, titel: 'Verkauft im Namen und auf Rechnung von' };
        h += '<div class="l"></div><div class="k">' + obEsc(pa.titel) + ':</div><div class="b">' + obEsc(pa.name) + '</div>';
        if (pa.adresse) h += '<div class="k">' + obEsc(pa.adresse) + '</div>';
        if (pa.uid) h += '<div class="k">UID ' + obEsc(pa.uid) + '</div>';
        if (pa.zvr) h += '<div class="k">ZVR ' + obEsc(pa.zvr) + '</div>';
        gruppen[pid].forEach(p => { h += zeile(p); });
        if (pa.ku_text) h += '<div class="k">' + obEsc(pa.ku_text) + '</div>';
        else if (pa.modus === 'fremdrechnung') h += ustListe(gruppen[pid]);
        else h += '<div class="k">ohne USt</div>';
    });
    h += '<div class="l"></div>';
    h += '<div class="r b g"><span>SUMME EUR</span><span>' + bon.bruttobetrag.toFixed(2) + '</span></div>';
    if (bon.zahlungsart === 'bar') {
        h += '<div class="r"><span>Bar gegeben</span><span>' + bon.gegeben.toFixed(2) + '</span></div>';
        h += '<div class="r"><span>Rückgeld</span><span>' + bon.rueckgeld.toFixed(2) + '</span></div>';
    } else {
        h += '<div class="r"><span>Bezahlt mit</span><span>Karte</span></div>';
    }
    h += '<div class="l"></div>';
    h += '<div class="z" style="font-size:10px">Beleg außerhalb der Betriebsstätte —<br>wird in der Registrierkasse nacherfasst.</div>';
    h += '<div class="z" style="margin-top:6px">Danke für Ihren Einkauf!</div>';
    return h;
}

// ── Journal + Tagesübersicht (Mobil-Messe) ──────────────────────────────────
// Keine Kassenabschluss-Funktion (die Belege sind Paragons; abgeschlossen wird in der Registrierkasse
// bei der Nacherfassung) — die Übersicht dient zum Abzählen der Kassa am Stand.
let obJournalBons = [];
async function obJournalOeffnen() {
    const prefix = 'M' + obKonfig.syncId + '-';
    obJournalBons = (await obIdbGetAll('bons')).filter(b => (b.bon_nr || '').startsWith(prefix))
        .sort((a, b) => a.erstellt_am.localeCompare(b.erstellt_am));
    const tage = [...new Set(obJournalBons.map(b => b.erstellt_am.slice(0, 10)))].reverse();
    const sel = document.getElementById('ob-journal-tag');
    sel.innerHTML = tage.length
        ? tage.map(t => '<option value="' + t + '">' + t.slice(8, 10) + '.' + t.slice(5, 7) + '.' + t.slice(0, 4) + '</option>').join('')
        : '<option value="">keine Belege</option>';
    obJournalRendern();
    document.getElementById('ob-journal-overlay').classList.add('aktiv');
}
function obJournalTagBons() {
    const tag = document.getElementById('ob-journal-tag').value;
    return obJournalBons.filter(b => b.erstellt_am.slice(0, 10) === tag);
}
function obJournalRendern() {
    const bons = obJournalTagBons();
    const s = obTagesSummen(bons);
    document.getElementById('ob-journal-summe').innerHTML = bons.length
        ? bons.length + ' Beleg(e) · Bar € ' + s.bar.toFixed(2) + ' · Karte € ' + s.karte.toFixed(2) + ' · <strong>Gesamt € ' + s.gesamt.toFixed(2) + '</strong>'
        : 'Noch keine Belege.';
    document.getElementById('ob-journal-liste').innerHTML = bons.slice().reverse().map(b =>
        '<div class="ob-jz" onclick="obJournalNachdruck(' + b.lokale_id + ')"><span><strong>' + obEsc(b.bon_nr) + '</strong> <small>'
        + b.erstellt_am.slice(11, 16) + (b.hochgeladen ? ' ✓' : '') + '</small></span><span>' + (b.zahlungsart === 'bar' ? 'Bar' : 'Karte')
        + ' <strong>€ ' + b.bruttobetrag.toFixed(2) + '</strong></span></div>').join('');
}
function obJournalNachdruck(lokaleId) {
    const bon = obJournalBons.find(b => b.lokale_id === lokaleId);
    if (!bon) return;
    document.getElementById('ob-journal-overlay').classList.remove('aktiv');
    document.getElementById('ob-quittung-inhalt').innerHTML = obMobilBelegHtml(bon)
        + '<div class="z b" style="margin-top:6px">*** NACHDRUCK ***</div>';
    document.getElementById('ob-quittung-overlay').classList.add('aktiv');
    setTimeout(() => window.print(), 300);
}
function obTagesSummen(bons) {
    const s = { bar: 0, karte: 0, gesamt: 0, ust: {}, partner: {} };
    const fremd = p => p.partner_modus === 'fremdrechnung' || p.partner_modus === 'spende';
    bons.forEach(b => {
        if (b.zahlungsart === 'bar') s.bar += b.bruttobetrag; else s.karte += b.bruttobetrag;
        s.gesamt += b.bruttobetrag;
        b.positionen.forEach(p => {
            const gs = p.zeile_brutto ?? (p.menge * p.einzelpreis_brutto);
            if (fremd(p)) {
                const n = ((obKonfig.partner || {})[p.partner_id] || {}).name || ('Partner ' + p.partner_id);
                s.partner[n] = (s.partner[n] || 0) + gs;
            } else {
                const satz = parseFloat(p.steuer_prozent) || 0;
                s.ust[satz] = (s.ust[satz] || 0) + gs;
            }
        });
    });
    return s;
}
function obTagesuebersichtDrucken() {
    const bons = obJournalTagBons();
    if (!bons.length) return;
    const s = obTagesSummen(bons), tag = bons[0].erstellt_am.slice(0, 10);
    const f = obKonfig.firma || {};
    let h = '<div class="z b">' + obEsc(f.firmenname || '') + '</div>';
    h += '<div class="z b g">TAGESÜBERSICHT</div><div class="z">Mobil-Belege · Messe Nr. ' + obKonfig.syncId + ' · Gerät ' + obEsc(obKonfig.geraet) + '</div>';
    h += '<div class="l"></div>';
    h += '<div class="r"><span>Datum</span><span>' + tag.slice(8, 10) + '.' + tag.slice(5, 7) + '.' + tag.slice(0, 4) + '</span></div>';
    h += '<div class="r"><span>Belege</span><span>' + bons.length + '</span></div>';
    h += '<div class="r k"><span>von</span><span>' + obEsc(bons[0].bon_nr) + '</span></div>';
    h += '<div class="r k"><span>bis</span><span>' + obEsc(bons[bons.length - 1].bon_nr) + '</span></div>';
    h += '<div class="l"></div>';
    h += '<div class="r"><span>Bar</span><span>' + s.bar.toFixed(2) + '</span></div>';
    h += '<div class="r"><span>Karte</span><span>' + s.karte.toFixed(2) + '</span></div>';
    h += '<div class="r b g"><span>GESAMT EUR</span><span>' + s.gesamt.toFixed(2) + '</span></div>';
    const saetze = Object.keys(s.ust).sort((a, b) => b - a);
    if (saetze.length) {
        h += '<div class="l"></div><div class="b">Eigene Ware</div>';
        saetze.forEach(satz => {
            const b = s.ust[satz], st = b - b / (1 + satz / 100);
            h += '<div class="r k"><span>' + satz + ' % brutto</span><span>' + b.toFixed(2) + '</span></div>';
            h += '<div class="r k"><span>&nbsp;davon USt</span><span>' + st.toFixed(2) + '</span></div>';
        });
    }
    const partner = Object.keys(s.partner);
    if (partner.length) {
        h += '<div class="l"></div><div class="b">Partnerware (kein eigener Umsatz)</div>';
        partner.forEach(n => { h += '<div class="r k"><span>' + obEsc(n) + '</span><span>' + s.partner[n].toFixed(2) + '</span></div>'; });
    }
    h += '<div class="l"></div>';
    const jetzt = obDatumFormat(new Date(), ' ');
    h += '<div class="r k"><span>Gedruckt</span><span>' + jetzt.slice(8, 10) + '.' + jetzt.slice(5, 7) + '.' + jetzt.slice(0, 4) + ' ' + jetzt.slice(11, 16) + '</span></div>';
    h += '<div class="z k" style="margin-top:4px">Kein Kassenabschluss — die Belege<br>werden in der Registrierkasse nacherfasst.</div>';
    document.getElementById('ob-journal-overlay').classList.remove('aktiv');
    document.getElementById('ob-quittung-inhalt').innerHTML = h;
    document.getElementById('ob-quittung-overlay').classList.add('aktiv');
    setTimeout(() => window.print(), 300);
}

function obQuittungAnzeigen(bon) {
    if (bon.mobil) {
        document.getElementById('ob-quittung-inhalt').innerHTML = obMobilBelegHtml(bon);
        document.getElementById('ob-quittung-overlay').classList.add('aktiv');
        return;
    }
    const firma = obKonfig.kasse.name || 'MeaLana';
    let h = '<div class="z b" style="font-size:16px">' + obEsc(firma) + '</div>';
    h += '<div class="z" style="font-size:11px;color:#666">Messe-Verkauf (offline)</div>';
    h += '<div class="l"></div>';
    h += '<div class="r"><span>Bon-Nr.:</span><span class="b">' + obEsc(bon.bon_nr) + '</span></div>';
    h += '<div class="r"><span>Datum:</span><span>' + bon.erstellt_am + '</span></div>';
    h += '<div class="l"></div>';
    bon.positionen.forEach(p => {
        const gs = p.zeile_brutto ?? (p.menge * p.einzelpreis_brutto);
        h += '<div class="r"><span>' + obEsc(p.bezeichnung) + '</span><span class="b">€ ' + gs.toFixed(2) + '</span></div>';
        h += '<div style="font-size:11px;color:#666;padding-left:6px">' + p.menge + '× €' + (p.preis_vorher_brutto ?? p.einzelpreis_brutto).toFixed(2) + ' · ' + p.steuer_prozent + '% MwSt' + (p.charge ? ' · Partie: ' + obEsc(p.charge) : '') + '</div>';
        if (p.aktion_hinweis) h += '<div style="font-size:11px;color:#666;padding-left:6px">Aktion ' + obEsc(p.aktion_hinweis) + '</div>';
    });
    h += '<div class="l"></div>';
    h += '<div class="r b" style="font-size:15px"><span>GESAMT</span><span>€ ' + bon.bruttobetrag.toFixed(2) + '</span></div>';
    if (bon.zahlungsart === 'bar') {
        h += '<div class="r"><span>Gegeben:</span><span>€ ' + bon.gegeben.toFixed(2) + '</span></div>';
        h += '<div class="r b"><span>Rückgeld:</span><span>€ ' + bon.rueckgeld.toFixed(2) + '</span></div>';
    } else {
        h += '<div class="r"><span>Zahlungsart:</span><span>Karte</span></div>';
    }
    h += '<div class="l"></div>';
    if (bon.rksv_signatur) {
        h += '<div style="font-size:10px;word-break:break-all">RKSV: ' + obEsc(bon.rksv_signatur) + '</div>';
        if (bon.rksv_qr) {
            h += '<div style="font-size:9px;color:#666;word-break:break-all">' + obEsc(bon.rksv_qr) + '</div>';
        }
    }
    h += '<div class="z" style="margin-top:8px">Danke für Ihren Einkauf!</div>';

    document.getElementById('ob-quittung-inhalt').innerHTML = h;
    document.getElementById('ob-quittung-overlay').classList.add('aktiv');
}

function obQuittungSchliessen() {
    document.getElementById('ob-quittung-overlay').classList.remove('aktiv');
    document.getElementById('ob-scan').focus();
}

// ── Bons hochladen (Post-Sync) ───────────────────────────────────────────────

async function obBonsZaehlerAktualisieren() {
    const bons = obDb ? (await obIdbGetAll('bons')).filter(b => !b.hochgeladen) : [];
    document.getElementById('ob-bons-zaehler').textContent = bons.length + (obIstMobil() ? ' Beleg(e) offen' : ' Bon(s) offen');
    document.getElementById('ob-upload-btn').disabled = bons.length === 0;
}

async function obBonsHochladen() {
    const bons = (await obIdbGetAll('bons')).filter(b => !b.hochgeladen);
    if (bons.length === 0) return;
    if (!obKonfig) return;

    const btn = document.getElementById('ob-upload-btn');
    btn.disabled = true;
    btn.textContent = 'Lade hoch…';

    try {
        const payload = { sync_token: obKonfig.syncToken, bons: bons };
        const fd = new FormData();
        fd.append('aktion', obIstMobil() ? 'mobil_upload' : 'post_sync');
        fd.append('payload', JSON.stringify(payload));

        const resp = await fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.erfolg) {
            if (obIstMobil()) {
                // Mobil: Belege bleiben fürs Journal/Nachdruck am Gerät, nur als hochgeladen markiert
                for (const b of bons) { b.hochgeladen = true; await obIdbPut('bons', b); }
            } else {
                await obIdbClear('bons');
            }
            alert(obIstMobil()
                ? data.bon_count + ' Beleg(e) hochgeladen — jetzt an der Laden-Kasse nacherfassen.'
                : data.bon_count + ' Bon(s) hochgeladen — Umsatz € ' + Number(data.umsatz).toFixed(2));
        } else {
            alert('Fehler beim Hochladen: ' + (data.fehler || 'unbekannt'));
        }
    } catch (e) {
        alert('Kein Netzwerk — bitte später erneut versuchen, sobald wieder online.');
    }

    btn.textContent = obIstMobil() ? '⤴ Belege hochladen' : '⤴ Bons hochladen';
    await obBonsZaehlerAktualisieren();
}
