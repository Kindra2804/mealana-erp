let msvPositionen = []; // [{artikel_id, bezeichnung, ean, bestand, menge, charge}]
let msvWartenderArtikel = null; // Artikel-Objekt während die Chargen-Auswahl offen ist
let msvChargenListe = []; // [{charge, bestand}] der zuletzt geladenen Chargen-Auswahl

document.addEventListener('DOMContentLoaded', function () {
    const scanFeld = document.getElementById('msv-scan');
    if (!scanFeld) return; // Seite zeigt "keine Offline-Kasse" Hinweis statt Formular

    scanFeld.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        msvScannen(scanFeld.value.trim());
        scanFeld.value = '';
    });

    document.getElementById('msv-submit').addEventListener('click', msvUmbuchungDurchfuehren);
});

function msvFeedback(text, art) {
    const el = document.getElementById('msv-feedback');
    el.textContent = text;
    el.style.color = art === 'fehler' ? '#dc2626' : (art === 'ok' ? '#16a34a' : '#64748b');
}

function msvScannen(code) {
    if (!code) return;
    const lagerId = document.getElementById('msv-von-lager').value;
    fetch(window.BASE_PATH + '/kasse/ajax_artikel.php?code=' + encodeURIComponent(code) + '&lager_id=' + lagerId)
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) {
                msvFeedback(d.fehler || 'Nicht gefunden', 'fehler');
                return;
            }
            if (d.charge_pflicht) {
                msvChargenAuswahlOeffnen(d);
            } else {
                msvHinzufuegen(d, null, d.bestand_physisch || 0);
            }
        })
        .catch(() => msvFeedback('Fehler bei der Suche.', 'fehler'));
}

// ── Chargen-Auswahl (bei charge_pflicht-Artikeln) ───────────────────────────

function msvChargenAuswahlOeffnen(artikel) {
    msvWartenderArtikel = artikel;
    document.getElementById('msv-charge-titel').textContent = 'Charge wählen — ' + artikel.bezeichnung;

    const lagerId = document.getElementById('msv-von-lager').value;
    fetch(window.BASE_PATH + '/packplatz/warenausgang/chargen_ajax.php?artikel_id=' + artikel.id + '&lager_id=' + lagerId)
        .then(r => r.json())
        .then(chargen => {
            msvChargenListe = chargen || [];
            msvChargeListeRender();
            document.getElementById('ov-msv-charge').style.display = 'flex';
        })
        .catch(() => msvFeedback('Fehler beim Laden der Chargen.', 'fehler'));
}

// Wie viel von dieser Charge ist schon im Warenkorb (msvPositionen)?
function msvChargeImWarenkorb(charge) {
    const p = msvPositionen.find(p => p.artikel_id === msvWartenderArtikel.id && p.charge === charge);
    return p ? p.menge : 0;
}

function msvChargeListeRender() {
    const liste = document.getElementById('msv-charge-liste');
    if (!msvChargenListe || msvChargenListe.length === 0) {
        liste.innerHTML = '<div style="color:#dc2626;font-size:13px">Keine Charge mit Bestand im Hauptlager gefunden.</div>';
        return;
    }
    let html = '';
    msvChargenListe.forEach((c, idx) => {
        const imWarenkorb = msvChargeImWarenkorb(c.charge);
        const verbleibend = c.bestand - imWarenkorb;
        html += '<div style="display:flex;align-items:center;gap:10px;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px">';
        html += '  <div style="flex:1">';
        html += '    <div style="font-weight:600;font-size:13px">' + escHtmlMsv(c.charge) + '</div>';
        html += '    <div style="font-size:11px;color:#94a3b8">verfügbar: ' + verbleibend + (imWarenkorb > 0 ? ' &nbsp;·&nbsp; im Warenkorb: ' + imWarenkorb : '') + '</div>';
        html += '  </div>';
        html += '  <button type="button" class="ks-btn ks-btn-secondary" style="padding:5px 12px;font-size:14px;font-weight:700"' + (imWarenkorb <= 0 ? ' disabled' : '') + ' onclick="msvChargeDelta(' + idx + ', -1)">−</button>';
        html += '  <span style="min-width:24px;text-align:center;font-weight:700">' + imWarenkorb + '</span>';
        html += '  <button type="button" class="ks-btn ks-btn-primary" style="padding:5px 12px;font-size:14px;font-weight:700"' + (verbleibend <= 0 ? ' disabled' : '') + ' onclick="msvChargeDelta(' + idx + ', 1)">+</button>';
        html += '</div>';
    });
    liste.innerHTML = html;
}

function msvChargeDelta(idx, delta) {
    const c = msvChargenListe[idx];
    if (!c) return;
    if (delta > 0) {
        const imWarenkorb = msvChargeImWarenkorb(c.charge);
        if (imWarenkorb >= c.bestand) return; // nicht mehr verfügbar
        msvHinzufuegen(msvWartenderArtikel, c.charge, c.bestand, 1);
    } else {
        const p = msvPositionen.find(p => p.artikel_id === msvWartenderArtikel.id && p.charge === c.charge);
        if (!p) return;
        if (p.menge <= 1) {
            msvPositionen.splice(msvPositionen.indexOf(p), 1);
        } else {
            p.menge -= 1;
        }
        msvRender();
    }
    msvChargeListeRender();
}

function msvChargeAbbrechen() {
    document.getElementById('ov-msv-charge').style.display = 'none';
    msvWartenderArtikel = null;
    msvChargenListe = [];
}

// ── Positionsliste ───────────────────────────────────────────────────────────

function msvHinzufuegen(artikel, charge, bestand, mengeNeu) {
    mengeNeu = mengeNeu || 1;
    const bestehend = msvPositionen.find(p => p.artikel_id === artikel.id && p.charge === charge);
    if (bestehend) {
        bestehend.menge += mengeNeu;
    } else {
        msvPositionen.push({
            artikel_id: artikel.id,
            bezeichnung: artikel.bezeichnung,
            ean: artikel.ean || '',
            bestand: bestand || 0,
            menge: mengeNeu,
            charge: charge,
        });
    }
    msvFeedback(artikel.bezeichnung + (charge ? ' (' + charge + ')' : '') + ' hinzugefügt.', 'ok');
    msvRender();
}

function msvRender() {
    const tbody = document.getElementById('msv-liste');
    const submitBtn = document.getElementById('msv-submit');

    if (msvPositionen.length === 0) {
        tbody.innerHTML = '<tr id="msv-leer"><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Noch keine Artikel gescannt.</td></tr>';
        submitBtn.disabled = true;
        return;
    }

    submitBtn.disabled = false;
    let html = '';
    msvPositionen.forEach((p, idx) => {
        const zuHoch = p.menge > p.bestand;
        html += '<tr>';
        html += '  <td>' + escHtmlMsv(p.bezeichnung);
        if (p.charge) html += '<div style="font-size:11px;color:#2563eb">Charge: ' + escHtmlMsv(p.charge) + '</div>';
        html += (p.ean ? '<div style="font-size:11px;color:#94a3b8">' + escHtmlMsv(p.ean) + '</div>' : '') + '</td>';
        html += '  <td style="text-align:right' + (zuHoch ? ';color:#dc2626;font-weight:700' : '') + '">' + p.bestand + '</td>';
        html += '  <td style="text-align:right"><input type="number" min="1" max="' + (p.charge ? p.bestand : '') + '" value="' + p.menge + '" class="ks-input" style="width:70px;text-align:right;padding:4px 6px" onchange="msvMengeAendern(' + idx + ', this.value)"></td>';
        html += '  <td><button type="button" class="ks-btn ks-btn-secondary" style="padding:4px 8px" onclick="msvEntfernen(' + idx + ')">✕</button></td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

function msvMengeAendern(idx, wert) {
    const menge = parseInt(wert, 10);
    msvPositionen[idx].menge = (menge > 0) ? menge : 1;
    msvRender();
}

function msvEntfernen(idx) {
    msvPositionen.splice(idx, 1);
    msvRender();
}

function msvUmbuchungDurchfuehren() {
    if (msvPositionen.length === 0) return;

    // Nur bei chargenlosen Artikeln ist "Menge > Bestand" evtl. gewollt (Überverkauf-Fall);
    // bei Chargen ist das <input max="..."> hart begrenzt.
    const zuHoch = msvPositionen.filter(p => !p.charge && p.menge > p.bestand);
    if (zuHoch.length > 0) {
        if (!confirm('Bei ' + zuHoch.length + ' Artikel(n) übersteigt die Menge den Lagerbestand. Trotzdem umbuchen?')) return;
    }

    const kasseId = document.getElementById('msv-kasse').value;
    const lagerId = document.getElementById('msv-lager').value;
    const vonLagerId = document.getElementById('msv-von-lager').value;

    const fd = new FormData();
    fd.append('aktion', 'umbuchung_zur_messe');
    fd.append('kasse_id', kasseId);
    fd.append('von_lager_id', vonLagerId);
    fd.append('nach_lager_id', lagerId);
    fd.append('positionen', JSON.stringify(msvPositionen.map(p => ({
        artikel_id: p.artikel_id,
        bezeichnung: p.bezeichnung,
        ean: p.ean,
        menge: p.menge,
        charge: p.charge,
    }))));

    document.getElementById('msv-submit').disabled = true;

    fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) {
                msvFeedback('Fehler: ' + (d.fehler || 'Unbekannt'), 'fehler');
                document.getElementById('msv-submit').disabled = false;
                return;
            }
            location.reload();
        })
        .catch(() => {
            msvFeedback('Netzwerkfehler bei der Umbuchung.', 'fehler');
            document.getElementById('msv-submit').disabled = false;
        });
}

function escHtmlMsv(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
