# Erstellt die Entwürfe der indra-Rechtstexte für das Einzelunternehmen (Barbara Indra) aus den aktuellen Seiten.
# Änderungen sind in den Entwurfsdateien unverändert (ohne Markierung); uebersicht.html markiert sie gelb.
import re, html, pathlib
A = pathlib.Path('aktuell'); E = pathlib.Path('entwurf')
P = lambda t: '<!-- wp:paragraph -->\n<p>' + t + '</p>\n<!-- /wp:paragraph -->'
MAIL = '<a href="mailto:office@indra-design.at">office@indra-design.at</a>'
KU = 'Gemäß § 6 Abs. 1 Z 27 UStG (Kleinunternehmerregelung) wird keine Umsatzsteuer berechnet und ausgewiesen.'

impressum = '\n\n'.join([
    P('Betreiber und verantwortlich für die Inhalte dieses Shops ist:<br><br><strong>INDRA DESIGN</strong><br>Barbara Indra'),
    P('Fliegergasse 49<br>2700 Wiener Neustadt<br>Österreich'),
    P('E-Mail: ' + MAIL + '<br>Web: <a href="https://indra-design.at/">www.indra-design.at</a>'),
    P('Rechtsform: Einzelunternehmen (nicht im Firmenbuch eingetragen)<br>Gewerbeinhaberin: Barbara Indra'),
    P('Gewerbe: Erzeugung von kunstgewerblichen Gegenständen<br>GISA-Zahl: 11922407<br>Gewerbebehörde: Magistrat der Stadt Wiener Neustadt'),
    P('Mitglied der Wirtschaftskammer Niederösterreich (WKNÖ), Kunsthandwerke'),
    P('Umsatzsteuer: Kleinunternehmerin gemäß § 6 Abs. 1 Z 27 UStG – keine Umsatzsteuer-Identifikationsnummer (UID).'),
    P('Berufsrecht:<br>Gewerbeordnung: <a href="https://www.ris.bka.gv.at/" target="_blank" rel="noreferrer noopener">www.ris.bka.gv.at</a>'),
])

ersatz_widerruf = [
    ('<p>MEALANA KG<br>Wollboutique<br>z. Hd. Barbara Indra</p>', '<p>INDRA DESIGN<br>Barbara Indra</p>'),
    ('<p>Bahngasse 13<br>2700 Wiener Neustadt<br>Österreich</p>', '<p>Fliegergasse 49<br>2700 Wiener Neustadt<br>Österreich</p>'),
    ('<a href="mailto:office@mealana.at">office@mealana.at</a>', MAIL),
]
ersatz_agb = ersatz_widerruf + [
    ('AGB INDRA DESIGN , ein Unternehmen der MEALANA KG Österreich, FN 400155p', 'AGB INDRA DESIGN – Barbara Indra, Fliegergasse 49, 2700 Wiener Neustadt, Österreich'),
    ('zwischen dem Verkäufer (MEALANA KG) und seinen Kunden', 'zwischen dem Verkäufer (Barbara Indra, INDRA DESIGN) und seinen Kunden'),
    ('Die Preise sind inkl. 20% Mehrwertsteuer angegeben.', 'Die Preise sind Endpreise. ' + KU),
    ('Für jede von MEALANA selbst erstellte Mahnung', 'Für jede vom Verkäufer selbst erstellte Mahnung'),
    ('an der Adresse office@mealana.at', 'an der Adresse office@indra-design.at'),
    ('im Impressum angeführten Adresse office@mealana.at', 'im Impressum angeführten Adresse office@indra-design.at'),
    ('Als Erfüllungsort gilt der Sitz vom Verkäufer (MEALANA KG) mit Stand September 2013, ist dies Wiener Neustadt.',
     'Als Erfüllungsort gilt der Sitz des Verkäufers in Wiener Neustadt.'),
    ('das für den Sitz vom Verkäfuer (MEALANA KG) örtlich', 'das für den Sitz des Verkäufers (Wiener Neustadt) örtlich'),
    # Gewährleistung: Wolle/Garn → Holz/Handfertigung (Vorschlag, Babsi bestätigt)
    ('Es wird darauf hingewiesen, dass es im Rahmen des Produktionspozesses beim jeweiligen Hersteller vorkommen kann, dass sich im Garn oder in der Wolle Knoten befinden; diese sind ua technisch bedingt. Es handelt sich hierbei um keinen Mangel.',
     'Unsere Produkte werden einzeln in Handarbeit bzw. mit dem Laser gefertigt. Holz ist ein Naturmaterial: Abweichungen in Maserung, Farbton und Oberfläche sowie leichte Abweichungen von den Produktfotos sind material- und fertigungsbedingt und stellen keinen Mangel dar.'),
    ('Im Herstellungsprozess der Wolle/des Garns kann es mitunter zu leichten Abstufungen in der Farbe kommen; diese sind ua technisch bedingt; hierbei handelt es sich um keinen Mangel. Der Verkäufer ist bemüht, innerhalb einer Charge bzw. Lot die bestellte Ware zu versenden; es kann dies aber nicht gewährleistet werden.',
     'Farben können je nach Bildschirm von der Darstellung im Shop leicht abweichen; dies stellt keinen Mangel dar.'),
]
ersatz_versand = [
    ('office@mealana.at bzw. +43 (0) 676 453 82 67', 'office@indra-design.at'),
]
verantwortlich = P('<strong>Verantwortlich für die Datenverarbeitung</strong><br>INDRA DESIGN – Barbara Indra<br>Fliegergasse 49, 2700 Wiener Neustadt, Österreich<br>E-Mail: ' + MAIL)

def umbauen(name, ersatz, voran=None):
    t = (A / name).read_text(encoding='utf-8')
    fehlend = [a[:60] for a, n in ersatz if a not in t]
    for a, n in ersatz: t = t.replace(a, n)
    if voran: t = voran + '\n\n' + t
    (E / name).write_text(t, encoding='utf-8')
    rest = sorted(set(re.findall(r'MEALANA|Mealana|mealana|Bahngasse|ATU\d+|FN \d+', t)))
    print(f'{name}: {len(ersatz) - len(fehlend)}/{len(ersatz)} ersetzt', ('NICHT GEFUNDEN: ' + str(fehlend)) if fehlend else '', ('REST: ' + str(rest)) if rest else '')

(E / 'impressum.html').write_text(impressum, encoding='utf-8'); print('impressum.html: neu')
umbauen('agb.html', ersatz_agb)
umbauen('widerrufsbelehrung.html', ersatz_widerruf)
umbauen('versandarten.html', ersatz_versand)
umbauen('datenschutzerklaerung.html', [], voran=verantwortlich)
