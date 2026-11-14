/**
 * Mengen-Aktionen – Rechenkern für die Kasse (JS-Zwilling von src/modules/aktionen/MengenAktionRechner.php).
 * Bei Änderungen BEIDE nachziehen und die Prüffälle laufen lassen:
 *   php tests/mengen_aktion_test.php · node tests/mengen_aktion_test.js
 *
 * mengenAktionBerechne(aktionen, positionen)
 *   aktionen:   [{id, art, menge, zahl_menge, paketpreis, badge_text, artikel_ids: [..]}]
 *   positionen: [{key, artikel_id, menge, preis, ist_reduziert}]
 *   → {positionen: {key: {aktion_id, badge_text, stueck_im_paket, gesamt_alt, gesamt_neu, einzelpreis_neu, rabatt_prozent}},
 *      hinweise:   [{aktion_id, badge_text, pakete, stueck, fehlen, ersparnis}]}
 * Regeln siehe PHP-Datei (teuerste zuerst / günstigstes gratis / Paketpreise günstigste Zerlegung,
 * Rabatt gleichmäßig, ganze Cent, eine Aktion je Artikel).
 */
function mengenAktionBerechne(aktionen, positionen) {
    var erg = { positionen: {}, hinweise: [] };
    var vergeben = {};
    var r2 = function (v) { return Math.round((v + Number.EPSILON) * 100) / 100; };

    (aktionen || []).forEach(function (a) {
        var x = parseInt(a.menge, 10);
        if (!(x >= 2)) return;
        var inAktion = {};
        (a.artikel_ids || []).forEach(function (id) { inAktion[parseInt(id, 10)] = true; });

        var stuecke = [];
        positionen.forEach(function (p) {
            var key = String(p.key);
            if (vergeben[key] || p.ist_reduziert || !inAktion[parseInt(p.artikel_id, 10)]) return;
            var cent = Math.round(parseFloat(p.preis) * 100), menge = parseInt(p.menge, 10);
            if (!(cent > 0) || !(menge > 0)) return;
            vergeben[key] = true;
            for (var i = 0; i < menge; i++) stuecke.push({ key: key, cent: cent });
        });
        if (!stuecke.length) return;

        stuecke.forEach(function (s, i) { s.i = i; });
        stuecke.sort(function (u, v) { return (v.cent - u.cent) || (u.i - v.i); });

        var n = stuecke.length, pakete, imPaketAnzahl, fehlen, paketZiel = 0;
        if (a.art === 'paketpreise') {
            var plan = mengenAktionPaketPlan(a.pakete || [], stuecke.map(function (u) { return u.cent; }));
            imPaketAnzahl = plan[0]; paketZiel = plan[1]; pakete = plan[2];
            fehlen = mengenAktionPaketFehlen(a.pakete || [], n);
        } else {
            pakete = Math.floor(n / x);
            imPaketAnzahl = pakete * x;
            fehlen = x - n % x;
        }
        var hinweis = { aktion_id: parseInt(a.id, 10), badge_text: a.badge_text, pakete: pakete, stueck: n, fehlen: fehlen, ersparnis: 0 };

        if (pakete > 0) {
            var imPaket = stuecke.slice(0, imPaketAnzahl);
            var normal = imPaket.reduce(function (s, u) { return s + u.cent; }, 0);
            var ziel;
            if (a.art === 'paketpreise') {
                ziel = paketZiel;
            } else if (a.art === 'x_fuer_y') {
                ziel = pakete * Math.round(parseFloat(a.paketpreis) * 100);
            } else {
                var gratis = x - parseInt(a.zahl_menge, 10);
                ziel = normal;
                for (var k = 0; k < pakete; k++) {
                    var paket = imPaket.slice(k * x, k * x + x);
                    for (var g = 0; g < gratis; g++) ziel -= paket[x - 1 - g].cent;
                }
            }

            if (ziel < normal) {
                var jePos = {}, reihenfolge = [];
                imPaket.forEach(function (s) {
                    if (!jePos[s.key]) { jePos[s.key] = { stueck: 0, alt: 0 }; reihenfolge.push(s.key); }
                    jePos[s.key].stueck++;
                    jePos[s.key].alt += s.cent;
                });
                var verteilt = 0;
                reihenfolge.forEach(function (key) {
                    jePos[key].neu = Math.round(jePos[key].alt * ziel / normal);
                    verteilt += jePos[key].neu;
                });
                if (verteilt !== ziel) {
                    // größte Position (bei Gleichstand die erste) bekommt den Cent-Rest – wie uasort in PHP (stabil)
                    var groesste = reihenfolge.slice().sort(function (u, v) { return jePos[v].alt - jePos[u].alt; })[0];
                    jePos[groesste].neu += ziel - verteilt;
                }

                positionen.forEach(function (p) {
                    var key = String(p.key);
                    if (!jePos[key]) return;
                    var menge = parseInt(p.menge, 10);
                    var altGesamt = Math.round(parseFloat(p.preis) * 100) * menge;
                    var neuGesamt = altGesamt - jePos[key].alt + jePos[key].neu;
                    erg.positionen[key] = {
                        aktion_id: parseInt(a.id, 10),
                        badge_text: a.badge_text,
                        stueck_im_paket: jePos[key].stueck,
                        gesamt_alt: altGesamt / 100,
                        gesamt_neu: neuGesamt / 100,
                        einzelpreis_neu: Math.round(neuGesamt / menge * 100) / 10000,
                        rabatt_prozent: r2((1 - neuGesamt / altGesamt) * 100)
                    };
                });
                hinweis.ersparnis = (normal - ziel) / 100;
            }
        }
        erg.hinweise.push(hinweis);
    });
    return erg;
}

/** paketpreise: [k Stück in Paketen, Paketpreis in Cent, Anzahl Pakete] – wie paketPlan() in PHP */
function mengenAktionPaketPlan(pakete, cents) {
    var n = cents.length, groessen = [];
    pakete.forEach(function (p) {
        var m = parseInt(p.menge, 10);
        if (m >= 2) groessen.push([m, Math.round(parseFloat(p.preis) * 100)]);
    });
    var best = [[0, 0]];
    for (var m = 1; m <= n; m++) {
        best[m] = null;
        groessen.forEach(function (g) {
            if (g[0] > m || best[m - g[0]] === null) return;
            var kand = [best[m - g[0]][0] + g[1], best[m - g[0]][1] + 1];
            if (best[m] === null || kand[0] < best[m][0]) best[m] = kand;
        });
    }
    var rest = cents.reduce(function (s, c) { return s + c; }, 0), wahl = [0, 0, 0], bestGesamt = rest;
    for (var k = 1; k <= n; k++) {
        rest -= cents[k - 1];
        if (best[k] === null) continue;
        if (best[k][0] + rest < bestGesamt) { bestGesamt = best[k][0] + rest; wahl = [k, best[k][0], best[k][1]]; }
    }
    return wahl;
}

/** paketpreise: Stück bis zur nächsten Paketgröße – wie paketFehlen() in PHP */
function mengenAktionPaketFehlen(pakete, n) {
    var g = pakete.map(function (p) { return parseInt(p.menge, 10); }).sort(function (a, b) { return a - b; });
    if (!g.length) return 0;
    var r = n % g[g.length - 1];
    for (var i = 0; i < g.length; i++) if (g[i] > r) return g[i] - r;
    return 0;
}

if (typeof module !== 'undefined') module.exports = { mengenAktionBerechne: mengenAktionBerechne };
