# Übersicht zum Gegenlesen: Entwürfe gerendert, geänderte/neue Absätze gelb, entfallene durchgestrichen.
import re, pathlib, html as H
A = pathlib.Path('aktuell'); E = pathlib.Path('entwurf')
absaetze = lambda t: [b.strip() for b in re.findall(r'<!-- wp:[a-z/-]+[^>]*-->(.*?)<!-- /wp:', t, re.S) if b.strip()]
TITEL = {'impressum.html': 'Impressum', 'agb.html': 'AGB', 'widerrufsbelehrung.html': 'Widerrufsbelehrung',
         'versandarten.html': 'Versandkostenübersicht', 'datenschutzerklaerung.html': 'Datenschutzerklärung'}
teile = []
for datei, titel in TITEL.items():
    alt, neu = absaetze((A / datei).read_text(encoding='utf-8')), absaetze((E / datei).read_text(encoding='utf-8'))
    altset, neuset = set(alt), set(neu)
    zeilen = [f'<div class="neu">{b}</div>' if b not in altset else f'<div>{b}</div>' for b in neu]
    weg = [f'<div class="weg">{b}</div>' for b in alt if b not in neuset]
    n = sum(b not in altset for b in neu)
    teile.append(f'<section><h2>{titel} <small>{n} Absätze neu/geändert</small></h2>{"".join(zeilen)}'
                 + (f'<details><summary>Entfallen ({len(weg)})</summary>{"".join(weg)}</details>' if weg else '') + '</section>')
seite = f'''<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>indra Rechtstexte Entwurf</title><style>
body{{font-family:system-ui,Segoe UI,Arial,sans-serif;max-width:860px;margin:0 auto;padding:24px 16px;color:#1f1d1b;background:#f7f2ea;line-height:1.5}}
h1{{color:#1d4076}} h2{{color:#1d4076;border-bottom:2px solid #c49a6c;padding-bottom:4px;margin-top:40px}} small{{font-size:13px;color:#8a6340;font-weight:normal}}
section{{background:#fff;padding:8px 20px 16px;border-radius:8px;margin-bottom:16px}} .neu{{background:#fff3a8;border-left:4px solid #e0b400;padding:2px 8px;margin:4px -8px}}
.weg{{text-decoration:line-through;color:#999;margin:4px 0}} details{{margin-top:12px;font-size:14px}} p{{margin:6px 0}}
</style></head><body><h1>indra-design.at – Rechtstexte für das Einzelunternehmen (Entwurf)</h1>
<p>Gelb = neu oder geändert. „Entfallen“ zeigt die Absätze, die wegfallen. Noch <strong>nicht</strong> im Shop – erst nach eurer Freigabe.</p>
{"".join(teile)}</body></html>'''
pathlib.Path('uebersicht.html').write_text(seite, encoding='utf-8'); print('ok')
