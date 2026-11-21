/* auftraege/zahlungseingaenge.php — Kontencheck: suchen, filtern, Zeile für Zeile buchen.
 * Gebucht wird über zahlung_buchen.php (wie im Auftrag), aber erst nach 8 Sekunden:
 * bis dahin kann „Rückgängig“ gedrückt werden, ohne dass Mail oder Shop-Status rausgehen. */
(function () {
    'use strict';

    const WARTEZEIT = 8;                       // Sekunden bis zur echten Buchung
    const WEGE = { ueberweisung: 'Bank', paypal: 'PayPal', nachnahme: 'Nachnahme', sonstig: 'Sonstig' };
    const body      = document.getElementById('ze-body');
    const suche     = document.getElementById('ze-suche');
    const sortWahl  = document.getElementById('ze-sort');
    const datumAlle = document.getElementById('ze-datum-alle');
    const leer      = document.getElementById('ze-leer');
    let filter = 'alle';
    let wartend = 0;                           // Buchungen in der Rückgängig-Frist

    const zeilen = () => Array.from(body.querySelectorAll('tr.ze-zeile'));
    const sichtbare = () => zeilen().filter(z => !z.hidden);

    // ── Zahlen ────────────────────────────────────────────────────────────
    function zahl(text) {                      // "1.234,50" / "47,9" / "47.90" → 1234.5 / 47.9 / 47.9
        let t = String(text).trim().replace(/[€\s]/g, '');
        if (t === '') return NaN;
        if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
        return /^-?\d+(\.\d+)?$/.test(t) ? parseFloat(t) : NaN;
    }
    const eur = v => v.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ── Suche + Zahlart-Filter ───────────────────────────────────────────
    function anwenden() {
        const q = suche.value.trim().toLowerCase();
        const betrag = zahl(q);
        zeilen().forEach(z => {
            let passt = filter === 'alle'
                || (filter === 'ueberfaellig' ? Number(z.dataset.faelligTage) < 0 : z.dataset.zahlart === filter);
            if (passt && q !== '') {
                const textTreffer = z.dataset.suche.includes(q);
                const betragTreffer = !isNaN(betrag)
                    && (Math.abs(Number(z.dataset.offen) - betrag) < 0.005 || Math.abs(Number(z.dataset.brutto) - betrag) < 0.005);
                passt = textTreffer || betragTreffer;
            }
            z.hidden = !passt || z.dataset.wartet === '1';   // wartende Buchung bleibt zugeklappt
        });
        leer.hidden = sichtbare().length > 0;
        leer.firstElementChild.textContent = zeilen().length === 0
            ? '🎉 Keine offenen Zahlungen mehr.' : 'Keine Treffer für diese Suche.';
    }

    suche.addEventListener('input', anwenden);
    suche.addEventListener('keydown', e => {
        if (e.key === 'Escape') { suche.value = ''; anwenden(); }
        if (e.key === 'Enter' || e.key === 'ArrowDown') {
            e.preventDefault();
            const erste = sichtbare()[0];
            if (erste) fokus(erste);
        }
    });

    document.getElementById('ze-chips').addEventListener('click', e => {
        const chip = e.target.closest('.ze-chip');
        if (!chip) return;
        document.querySelectorAll('.ze-chip').forEach(c => c.classList.toggle('aktiv', c === chip));
        filter = chip.dataset.filter;
        anwenden();
    });

    // ── Sortierung (Meldungszeilen wandern mit ihrer Auftragszeile) ──────
    sortWahl.addEventListener('change', () => {
        const art = sortWahl.value;
        const reihen = { vorkasse: 1, rechnung: 2, paypal: 3, nachnahme: 4, gemischt: 5 };
        const schluessel = z => ({
            faellig:  [z.dataset.faellig, z.dataset.bestellt],
            bestellt: [z.dataset.bestellt, z.dataset.faellig],
            zahlart:  [String(reihen[z.dataset.zahlart] || 9), z.dataset.faellig],
            betrag:   [(1e9 - Number(z.dataset.offen)).toFixed(2).padStart(14, '0'), z.dataset.faellig],
        })[art];
        const sortiert = zeilen().sort((a, b) => {
            const [a1, a2] = schluessel(a), [b1, b2] = schluessel(b);
            return a1 < b1 ? -1 : a1 > b1 ? 1 : a2 < b2 ? -1 : a2 > b2 ? 1 : 0;
        });
        sortiert.forEach(z => {
            const meldung = z.previousElementSibling;
            if (meldung && meldung.classList.contains('ze-meldung')) body.insertBefore(meldung, leer);
            body.insertBefore(z, leer);
        });
    });

    // ── Datum für alle Zeilen vorbelegen (z.B. Auszug von gestern) ──────
    datumAlle.addEventListener('change', () => {
        if (!datumAlle.value) return;
        zeilen().forEach(z => {
            const d = z.querySelector('.ze-datum');
            if (!d.dataset.geaendert) d.value = datumAlle.value;
        });
    });
    body.addEventListener('change', e => {
        if (e.target.classList.contains('ze-datum')) e.target.dataset.geaendert = '1';
    });

    // ── Aktive Zeile, Tastatur ───────────────────────────────────────────
    function fokus(zeile) {
        const inp = zeile.querySelector('.ze-betrag');
        inp.focus();
        inp.select();
    }
    body.addEventListener('focusin', e => {
        const z = e.target.closest('tr.ze-zeile');
        zeilen().forEach(x => x.classList.toggle('ze-aktiv', x === z));
    });
    body.addEventListener('keydown', e => {
        const z = e.target.closest('tr.ze-zeile');
        if (!z) return;
        if (e.key === 'Enter' && !e.target.classList.contains('ze-buchen')) {
            e.preventDefault();
            buchen(z);
        } else if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && e.target.tagName === 'INPUT' && e.target.type !== 'date') {
            e.preventDefault();
            const liste = sichtbare();
            const i = liste.indexOf(z) + (e.key === 'ArrowDown' ? 1 : -1);
            if (i < 0) { suche.focus(); return; }
            if (liste[i]) fokus(liste[i]);
        } else if (e.key === 'Escape') {
            suche.focus();
            suche.select();
        }
    });

    // Teilzahlung / Überzahlung gleich beim Tippen anzeigen
    body.addEventListener('input', e => {
        if (!e.target.classList.contains('ze-betrag')) return;
        betragHinweis(e.target.closest('tr.ze-zeile'));
    });
    function betragHinweis(z) {
        const inp = z.querySelector('.ze-betrag');
        const hinweis = z.querySelector('.ze-hinweis');
        const b = zahl(inp.value), offen = Number(z.dataset.offen);
        hinweis.classList.remove('ueber');
        inp.classList.remove('teil');
        if (!isNaN(b) && b > 0 && b < offen - 0.005) {
            hinweis.textContent = 'Teilzahlung – ' + eur(offen - b) + ' € bleiben offen';
            inp.classList.add('teil');
            hinweis.hidden = false;
        } else if (!isNaN(b) && b > offen + 0.005) {
            hinweis.textContent = 'Überzahlung um ' + eur(b - offen) + ' €';
            hinweis.classList.add('ueber');
            hinweis.hidden = false;
        } else {
            hinweis.hidden = true;
        }
    }

    body.addEventListener('click', e => {
        const knopf = e.target.closest('.ze-buchen');
        if (knopf) buchen(knopf.closest('tr.ze-zeile'));
    });

    // ── Buchen mit Rückgängig-Frist ──────────────────────────────────────
    function naechsteNach(z) {
        const liste = sichtbare();
        const i = liste.indexOf(z);
        return liste[i + 1] || liste[i - 1] || null;
    }

    function buchen(z) {
        const betrag = zahl(z.querySelector('.ze-betrag').value);
        const datum  = z.querySelector('.ze-datum').value;
        const weg    = z.querySelector('.ze-weg').value;
        const offen  = Number(z.dataset.offen);
        if (isNaN(betrag) || betrag <= 0) { alert('Bitte einen gültigen Betrag eingeben.'); fokus(z); return; }
        if (!datum) { alert('Bitte das Buchungsdatum eingeben.'); return; }
        if (betrag > offen + 0.005
            && !confirm('Überzahlung: ' + eur(betrag) + ' € eingegeben, offen sind nur ' + eur(offen) + ' €.\nTrotzdem so buchen?')) {
            fokus(z); return;
        }

        const weiter = naechsteNach(z);
        const datumText = datum.split('-').reverse().join('.');
        const meldung = document.createElement('tr');
        meldung.className = 'ze-meldung';
        meldung.innerHTML = '<td><span class="ze-nr"></span></td><td class="ze-m-kunde"></td>'
            + '<td colspan="7" class="ze-m-text"></td>'
            + '<td><button type="button" class="btn btn-secondary btn-sm ze-undo" style="white-space:nowrap"></button></td>';
        meldung.querySelector('.ze-nr').textContent = z.dataset.nr;
        meldung.querySelector('.ze-m-kunde').textContent = z.children[1].firstChild.textContent.trim();
        const text = meldung.querySelector('.ze-m-text');
        const undo = meldung.querySelector('.ze-undo');
        text.textContent = '⏳ ' + eur(betrag) + ' € (' + WEGE[weg] + ', ' + datumText + ') wird gleich gebucht …';
        z.parentNode.insertBefore(meldung, z);
        z.hidden = true;
        z.dataset.wartet = '1';
        wartend++;
        if (weiter) fokus(weiter); else suche.focus();

        let rest = WARTEZEIT;
        const zeigeRest = () => { undo.textContent = '↩ Rückgängig ' + rest + 's'; };
        zeigeRest();
        const uhr = setInterval(() => {
            rest--;
            if (rest > 0) { zeigeRest(); return; }
            clearInterval(uhr);
            absenden();
        }, 1000);

        undo.addEventListener('click', () => {
            clearInterval(uhr);
            wartend--;
            meldung.remove();
            delete z.dataset.wartet;
            z.hidden = false;                   // auch wenn die Suche inzwischen anders ist
            fokus(z);
        });

        async function absenden() {
            undo.remove();
            text.textContent = '… wird gebucht';
            const fd = new FormData();
            fd.append('auftrag_id', z.dataset.id);
            fd.append('betrag', betrag.toFixed(2));
            fd.append('buchungsdatum', datum);
            fd.append('zahlungsweg', weg);
            let data;
            try {
                const r = await fetch(window.BASE_PATH + '/auftraege/zahlung_buchen.php', { method: 'POST', body: fd });
                data = await r.json();
            } catch (err) {
                data = { erfolg: false, fehler: 'Keine Antwort vom Server — bitte im Auftrag ' + z.dataset.nr + ' nachsehen, ob die Zahlung gebucht wurde.' };
            }
            wartend--;
            delete z.dataset.wartet;

            if (!data.erfolg) {
                meldung.remove();
                z.hidden = false;
                alert(data.fehler || 'Fehler beim Buchen');
                return;
            }

            const restOffen = Math.max(0, Math.round(((data.gesamt || 0) - (data.summe || 0)) * 100) / 100);
            text.textContent = '✓ ' + eur(betrag) + ' € gebucht (' + WEGE[weg] + ', ' + datumText + ')'
                + (restOffen > 0.004 ? ' – ' + eur(restOffen) + ' € bleiben offen' : ' – Auftrag bezahlt');
            if (data.shop_hinweis && data.shop_gemeldet === false) {
                text.textContent += ' · ⚠ Shop nicht erreicht: ' + data.shop_hinweis;
            }
            heuteDazu(z.dataset.nr, weg, betrag);

            if (restOffen > 0.004) {
                // Teilzahlung: Zeile bleibt mit neuem offenen Betrag
                z.dataset.offen = restOffen;
                z.querySelector('.ze-offen').textContent = eur(restOffen);
                z.querySelector('.ze-betrag').value = eur(restOffen);
                betragHinweis(z);
                anwenden();
            } else {
                zaehlerWeg(z);
                z.remove();
                anwenden();
            }
        }
    }

    // ── Zähler aktualisieren ─────────────────────────────────────────────
    function heuteDazu(nr, weg, betrag) {
        const anz = document.getElementById('ze-heute-anzahl');
        const sum = document.getElementById('ze-heute-summe');
        anz.textContent = Number(anz.textContent) + 1;
        sum.textContent = eur((zahl(sum.textContent) || 0) + betrag);
        const zeile = document.createElement('div');
        const jetzt = new Date();
        const links = document.createElement('span');
        links.textContent = String(jetzt.getHours()).padStart(2, '0') + ':' + String(jetzt.getMinutes()).padStart(2, '0')
            + ' · ' + nr + ' · ' + WEGE[weg];
        const rechts = document.createElement('b');
        rechts.textContent = eur(betrag);
        zeile.append(links, rechts);
        document.getElementById('ze-heute-liste').appendChild(zeile);
    }

    function zaehlerWeg(z) {
        const schluessel = ['alle', z.dataset.zahlart];
        if (Number(z.dataset.faelligTage) < 0) schluessel.push('ueberfaellig');
        schluessel.forEach(f => {
            const span = document.querySelector('.ze-chip[data-filter="' + f + '"] span');
            if (span) span.textContent = Math.max(0, Number(span.textContent) - 1);
        });
    }

    document.getElementById('ze-heute-knopf').addEventListener('click', () => {
        document.getElementById('ze-heute-liste').classList.toggle('offen');
    });

    // Wer die Seite während der Rückgängig-Frist verlässt, verliert die Buchung → warnen
    window.addEventListener('beforeunload', e => {
        if (wartend > 0) { e.preventDefault(); e.returnValue = ''; }
    });
})();
