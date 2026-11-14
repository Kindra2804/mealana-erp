// Prüffälle Mengen-Aktionen gegen den JS-Rechenkern (Kasse).  Start: node tests/mengen_aktion_test.js
// Gleiche Fälle für PHP: php tests/mengen_aktion_test.php
const { mengenAktionBerechne } = require('../public/js/mengen_aktion_rechner.js');
const daten = require('./mengen_aktion_faelle.json');

let fehler = 0;
for (const f of daten.faelle) {
    const aktionen = f.aktionen.map(k => daten.aktionen[k]);
    const erg = mengenAktionBerechne(aktionen, f.positionen);
    const probleme = [];

    const ist = Object.keys(erg.positionen).sort(), soll = Object.keys(f.soll).sort();
    if (JSON.stringify(ist) !== JSON.stringify(soll)) probleme.push(`Positionen mit Rabatt: ${JSON.stringify(ist)} statt ${JSON.stringify(soll)}`);
    for (const [key, s] of Object.entries(f.soll)) {
        for (const [feld, wert] of Object.entries(s)) {
            const w = erg.positionen[key]?.[feld];
            if (w === undefined || Math.abs(w - wert) > 0.0001) probleme.push(`${key}.${feld} = ${w} statt ${wert}`);
        }
    }
    if (erg.hinweise.length !== f.hinweise.length) probleme.push(`Anzahl Hinweise ${erg.hinweise.length} statt ${f.hinweise.length}`);
    f.hinweise.forEach((s, i) => {
        for (const [feld, wert] of Object.entries(s)) {
            const w = erg.hinweise[i]?.[feld];
            if (w === undefined || Math.abs(w - wert) > 0.0001) probleme.push(`Hinweis ${i}.${feld} = ${w} statt ${wert}`);
        }
    });
    const alt = Object.values(erg.positionen).reduce((s, p) => s + p.gesamt_alt, 0);
    const neu = Object.values(erg.positionen).reduce((s, p) => s + p.gesamt_neu, 0);
    const spar = erg.hinweise.reduce((s, h) => s + h.ersparnis, 0);
    if (Math.abs(alt - neu - spar) > 0.001) probleme.push(`Summe passt nicht: alt ${alt} − neu ${neu} ≠ Ersparnis ${spar}`);

    console.log((probleme.length ? 'FEHL ' : 'OK   ') + f.name);
    probleme.forEach(p => console.log('       ' + p));
    if (probleme.length) fehler++;
}
console.log(fehler ? `\n${fehler} Fall/Fälle FALSCH` : `\nAlle ${daten.faelle.length} Fälle richtig`);
process.exit(fehler ? 1 : 0);
