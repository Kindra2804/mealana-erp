// ── Offline-Kasse: IndexedDB + direkte BFR-Signierung ──────────────────────
// Läuft komplett ohne Server, sobald die Sync-Daten einmal geladen wurden.

const OB_DB_NAME    = 'mealana_messe_kasse';
const OB_DB_VERSION = 1;

let obDb          = null;
let obArtikel     = [];   // In-Memory-Kopie aus IndexedDB (schnellere Suche)
let obKonfig      = null; // { id:'aktuell', syncId, syncToken, kasse:{...}, lagerId, bonNrJahr, bonNrZaehler }
let obWarenkorb   = [];   // [{artikel_id, bezeichnung, ean, menge, einzelpreis_brutto, steuer_prozent, rabatt_prozent, charge, charge_pflicht, block}]
let obZahlart     = 'bar';

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
    });
    scanFeld.addEventListener('input', () => obSucheAktualisieren(scanFeld.value));
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

        await obIdbClear('artikel');
        for (const a of data.artikel) {
            await obIdbPut('artikel', a);
        }
        await obIdbPut('konfig', {
            id: 'aktuell',
            syncId: data.sync_id,
            syncToken: data.sync_token,
            kasse: data.kasse,
            lagerId: data.lager_id,
            bonNrJahr: data.bon_nr_jahr,
            bonNrZaehler: data.bon_nr_zaehler,
            diversArtikelId: data.divers_artikel_id || null,
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
    document.getElementById('ob-kasse-label').textContent =
        (obKonfig.kasse?.name || 'Kasse') + ' · ' + obArtikel.length + ' Artikel geladen';
}

// ── Scan / Textsuche / Warenkorb ─────────────────────────────────────────────

function obScannen(code) {
    code = (code || '').trim();
    if (!code) return;
    const fb = document.getElementById('ob-scan-feedback');

    const treffer = obArtikel.find(a => a.ean === code || a.artikelnummer === code || String(a.id) === code);
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
    obZumWarenkorbHinzufuegen(artikel, null, 1);
    const fb = document.getElementById('ob-scan-feedback');
    fb.textContent = artikel.bezeichnung + ' hinzugefügt.';
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
        html += '  <div style="font-size:11px;color:#94a3b8">€ ' + (parseFloat(a.brutto_vk) || 0).toFixed(2) + (a.artikelnummer ? ' · ' + obEsc(a.artikelnummer) : '') + '</div>';
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

function obFreierArtikelOeffnen() {
    document.getElementById('ob-freier-bezeichnung').value = '';
    document.getElementById('ob-freier-preis').value = '';
    document.getElementById('ob-freier-overlay').classList.add('aktiv');
    document.getElementById('ob-freier-bezeichnung').focus();
}

function obFreierArtikelSchliessen() {
    document.getElementById('ob-freier-overlay').classList.remove('aktiv');
    document.getElementById('ob-scan').focus();
}

function obFreierArtikelHinzufuegen() {
    const bezeichnung = document.getElementById('ob-freier-bezeichnung').value.trim();
    const preis       = parseFloat(document.getElementById('ob-freier-preis').value);
    const steuer      = parseFloat(document.getElementById('ob-freier-steuer').value);

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
    });
    obWarenkorbRendern();
}

function obWarenkorbRendern() {
    const liste = document.getElementById('ob-warenkorb-liste');

    if (obWarenkorb.length === 0) {
        liste.innerHTML = '<li style="text-align:center;color:#94a3b8;padding:20px 0">Warenkorb ist leer.</li>';
        obSummenBerechnen();
        return;
    }

    let html = '';
    obWarenkorb.forEach((z, idx) => {
        const gesamt = z.menge * z.einzelpreis_brutto * (1 - z.rabatt_prozent / 100);
        html += '<li class="ob-zeile">';
        html += '  <div style="flex:1;min-width:0">';
        html += '    <div class="ob-zeile-name">' + obEsc(z.bezeichnung) + '</div>';
        html += '    <div class="ob-zeile-sub">€ ' + z.einzelpreis_brutto.toFixed(2) + ' · ' + z.steuer_prozent + '% MwSt' + (z.ean ? ' · ' + obEsc(z.ean) : '') + '</div>';
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
        const brutto = z.menge * z.einzelpreis_brutto * (1 - z.rabatt_prozent / 100);
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
        const brutto = z.menge * z.einzelpreis_brutto * (1 - z.rabatt_prozent / 100);
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

async function obPruefeBfrErreichbar(bfrUrl) {
    try {
        const resp = await fetch(bfrUrl.replace(/\/$/, '') + '/state', { method: 'GET' });
        return resp.ok;
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
    const bfrUrl = obKonfig.kasse.bfr_url;
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

    const bfrUrl = obKonfig.kasse.bfr_url;
    if (bfrUrl && !(await obPruefeBfrMitKurzRetry(bfrUrl))) {
        document.getElementById('ob-abschluss-btn').disabled = false;
        obZeigeBfrPopup();
        return;
    }
    obBfrFehlschlagAnzahl = 0;

    obKonfig.bonNrZaehler++;
    const bonNr = obKonfig.kasse.kasse_nr + '-' + obKonfig.bonNrJahr + '-' + String(obKonfig.bonNrZaehler).padStart(6, '0');
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
            bezeichnung: z.bezeichnung,
            ean: z.ean,
            menge: z.menge,
            einzelpreis_brutto: z.einzelpreis_brutto,
            rabatt_prozent: z.rabatt_prozent,
            steuer_prozent: z.steuer_prozent,
            charge: z.charge || null,
        })),
        rksv_signatur: null,
        rksv_qr: null,
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
            const resp = await fetch(bfrUrl.replace(/\/$/, '') + '/register', {
                method: 'POST',
                headers: { 'Content-Type': 'text/xml' },
                body: xml,
            });
            const text = await resp.text();
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

    obWarenkorb = [];
    document.getElementById('ob-gegeben').value = '';
    obWarenkorbRendern();
    await obBonsZaehlerAktualisieren();
    document.getElementById('ob-abschluss-btn').disabled = false;
}

// ── Quittung ─────────────────────────────────────────────────────────────────

function obQuittungAnzeigen(bon) {
    const firma = obKonfig.kasse.name || 'MeaLana';
    let h = '<div class="z b" style="font-size:16px">' + obEsc(firma) + '</div>';
    h += '<div class="z" style="font-size:11px;color:#666">Messe-Verkauf (offline)</div>';
    h += '<div class="l"></div>';
    h += '<div class="r"><span>Bon-Nr.:</span><span class="b">' + obEsc(bon.bon_nr) + '</span></div>';
    h += '<div class="r"><span>Datum:</span><span>' + bon.erstellt_am + '</span></div>';
    h += '<div class="l"></div>';
    bon.positionen.forEach(p => {
        const gs = p.menge * p.einzelpreis_brutto;
        h += '<div class="r"><span>' + obEsc(p.bezeichnung) + '</span><span class="b">€ ' + gs.toFixed(2) + '</span></div>';
        h += '<div style="font-size:11px;color:#666;padding-left:6px">' + p.menge + '× €' + p.einzelpreis_brutto.toFixed(2) + ' · ' + p.steuer_prozent + '% MwSt' + (p.charge ? ' · Partie: ' + obEsc(p.charge) : '') + '</div>';
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
    const bons = obDb ? await obIdbGetAll('bons') : [];
    document.getElementById('ob-bons-zaehler').textContent = bons.length + ' Bon(s) offen';
    document.getElementById('ob-upload-btn').disabled = bons.length === 0;
}

async function obBonsHochladen() {
    const bons = await obIdbGetAll('bons');
    if (bons.length === 0) return;
    if (!obKonfig) return;

    const btn = document.getElementById('ob-upload-btn');
    btn.disabled = true;
    btn.textContent = 'Lade hoch…';

    try {
        const payload = { sync_token: obKonfig.syncToken, bons: bons };
        const fd = new FormData();
        fd.append('aktion', 'post_sync');
        fd.append('payload', JSON.stringify(payload));

        const resp = await fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.erfolg) {
            await obIdbClear('bons');
            alert(data.bon_count + ' Bon(s) hochgeladen — Umsatz € ' + Number(data.umsatz).toFixed(2));
        } else {
            alert('Fehler beim Hochladen: ' + (data.fehler || 'unbekannt'));
        }
    } catch (e) {
        alert('Kein Netzwerk — bitte später erneut versuchen, sobald wieder online.');
    }

    btn.textContent = '⤴ Bons hochladen';
    await obBonsZaehlerAktualisieren();
}
