"""Sockenwolle-online Logo als Vektor (SVG) nach Babsis Entwurf, Variante A Weinrot (2026-11-05).

Schrift: Andika Regular (SIL OFL) als Pfade + leichte Kontur für die Strichstärke.
Socken nach Babsis Canva-Entwurf nachgezeichnet, Wollfaden selbst gezeichnet. Maße in px vom Rastervorlage-Ausschnitt
(Schrift-Großbuchstabenhöhe 145 px) abgenommen.

Aufruf: python -I sow_logo_vektor.py <schrift.ttf> <zielordner>
Erzeugt: logo.svg (creme Hintergrund), logo_transparent.svg, logo_weiss.svg (für dunklen Grund),
         favicon.svg (eine Socke)
"""
import re
import sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

FONT, ZIEL = sys.argv[1], sys.argv[2].rstrip('/') + '/'
WEINROT, ROT, LACHS, CREME_S, FADEN, CREME = '#8e3438', '#f23d39', '#ef8a72', '#f4e5d4', '#cbb48f', '#fdf8f2'

f = TTFont(FONT)
gs, cmap, hmtx = f.getGlyphSet(), f.getBestCmap(), f['hmtx']
CAP = f['OS/2'].sCapHeight
S = 145 / CAP                       # px je Font-Einheit
KONTUR = 52                          # Font-Einheiten, macht Regular etwas kräftiger (Babsi liegt zwischen 400 und 700)


def text_pfad(text, x0, basis):
    """Gibt (svg-pfad, endx) zurück; Pfad in px, Grundlinie bei y=basis."""
    teile, x = [], x0
    for ch in text:
        g = cmap[ord(ch)]
        pen = SVGPathPen(gs)
        gs[g].draw(TransformPen(pen, (S, 0, 0, -S, x, basis)))
        teile.append(pen.getCommands())
        x += hmtx[g][0] * S
    return ' '.join(teile), x


def socke(x, y, farbe=ROT, muster=CREME_S, ferse=CREME_S, rippe=CREME_S, id_='s'):
    """Socke nach Babsis Canva-Entwurf (2026-11-05): leicht schmaler werdender Schaft, Bündchen mit hellen Rippen,
    Norweger-Band (Punkte, großes Zickzack, Striche), große helle Ferse und Spitze mit Lachs-Rand.
    Gezeichnet in den Pixelmaßen ihrer Vorlage (Socke 189 px hoch), skaliert ×1,55 auf ca. 130 × 293 px.
    Bezug: links oben (x, y)."""
    k = 1.55
    umriss = ('M16,11 Q16,8 19,8 H60 Q63,8 63,11 L60,104 Q60,122 67,134 L88,160 '
              'Q102,178 97,191 Q90,200 74,197 Q64,195 57,187 L31,153 Q16,141 17,126 Q18,114 22,103 Z')
    teile = [f'<clipPath id="{id_}"><path d="{umriss}"/></clipPath>',
             f'<path d="{umriss}" fill="{farbe}"/>',
             f'<g clip-path="url(#{id_})">']
    # Bündchen: 7 helle Rippen, darunter eine Linie
    for rx in (21.5, 27.5, 33.5, 39.5, 45.5, 51.5, 57.5):
        teile.append(f'<line x1="{rx}" y1="12" x2="{rx}" y2="34" stroke="{rippe}" stroke-width="2" stroke-linecap="round"/>')
    teile.append(f'<rect x="10" y="37" width="60" height="2.6" fill="{muster}"/>')
    # Norweger-Band: Punktreihe, großes Zickzack mit Punkten in den Tälern, Strichreihe
    for i, px in enumerate(range(20, 64, 5)):
        teile.append(f'<rect x="{px - 1.4}" y="{43 if i % 2 else 45}" width="2.8" height="2.8" fill="{muster}"/>')
        teile.append(f'<rect x="{px + 1.1}" y="{50 if i % 2 else 48}" width="2.8" height="2.8" fill="{muster}"/>')
    zz = ' '.join(f'{zx},{80 if i % 2 == 0 else 60}' for i, zx in enumerate(range(12, 74, 10)))
    teile.append(f'<polyline points="{zz}" fill="none" stroke="{muster}" stroke-width="3" stroke-linejoin="miter"/>')
    zz2 = ' '.join(f'{zx},{86 if i % 2 == 0 else 66}' for i, zx in enumerate(range(12, 74, 10)))
    teile.append(f'<polyline points="{zz2}" fill="none" stroke="{muster}" stroke-width="1.4" stroke-opacity=".8"/>')
    for zx in range(22, 66, 20):
        teile.append(f'<circle cx="{zx}" cy="59" r="1.6" fill="{muster}"/>')
    for zx in range(32, 66, 20):
        teile.append(f'<circle cx="{zx}" cy="82" r="1.6" fill="{muster}"/>')
    for px in range(20, 64, 4):
        teile.append(f'<line x1="{px}" y1="89" x2="{px}" y2="96" stroke="{muster}" stroke-width="1.6" stroke-linecap="round"/>')
    teile.append(f'<rect x="10" y="99" width="60" height="2.6" fill="{muster}"/>')
    # Ferse (links unten) + Spitze (rechts unten), mit Lachs-Rand wie bei Babsi
    teile.append(f'<ellipse cx="18" cy="131" rx="16" ry="19" fill="{ferse}" stroke="{LACHS}" stroke-width="1.8"/>')
    teile.append(f'<path d="M48,200 L51,185 Q68,163 99,167 L114,200 Z" fill="{ferse}" stroke="{LACHS}" stroke-width="1.8"/>')
    teile.append('</g>')
    teile.append(f'<path d="{umriss}" fill="none" stroke="{LACHS}" stroke-width="1" stroke-opacity=".6"/>')
    return f'<g transform="translate({x - 16 * k:.1f},{y - 8 * k:.1f}) scale({k})">' + ''.join(teile) + '</g>'


def faden(farbe, start_x, ende_x):
    """Wollfaden: Schlaufe links, wellig bis an die Ferse der ersten Socke.
    Gezeichnet für x 4..936, die x-Werte werden auf start_x..ende_x umgerechnet (Strichstärke bleibt gleich)."""
    roh = ('M4,232 C2,262 22,286 60,290 C84,292 98,264 78,256 C54,248 52,290 96,293 '
           'C150,298 190,266 250,272 C320,280 360,306 430,300 C520,292 560,262 640,268 '
           'C720,274 760,302 840,296 C880,292 908,276 936,268')
    k = (ende_x - start_x) / 932
    d = re.sub(r'(-?[\d.]+),(-?[\d.]+)', lambda m: f'{start_x + (float(m.group(1)) - 4) * k:.1f},{m.group(2)}', roh)
    return f'<path d="{d}" fill="none" stroke="{farbe}" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'


def logo(hg, schrift, socken_farbe, faden_farbe, muster=CREME_S):
    basis = 230
    p1, x1 = text_pfad('Sockenwo', 44, basis)
    sx = x1 + 22                          # erste Socke direkt nach „o“
    so1 = socke(sx, basis - 186, socken_farbe, muster, muster, id_='a')
    so2 = socke(sx + 104, basis - 186, socken_farbe, muster, muster, id_='b')
    p2, x2 = text_pfad('e online', sx + 104 + 96, basis)
    breite = x2 + 40
    rand = f' stroke="{schrift}" stroke-width="{KONTUR * S:.2f}" stroke-linejoin="round"'
    hg_rect = f'<rect x="0" y="22" width="{breite:.0f}" height="322" fill="{hg}"/>' if hg else ''
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 22 {breite:.0f} 322" width="{breite:.0f}" height="322">'
            f'<title>Sockenwolle-online</title>{hg_rect}'
            + faden(faden_farbe, 14, sx + 16) +
            f'<path d="{p1}" fill="{schrift}"{rand}/>{so1}{so2}<path d="{p2}" fill="{schrift}"{rand}/></svg>')


open(ZIEL + 'logo.svg', 'w', encoding='utf-8').write(logo(CREME, WEINROT, ROT, FADEN))
open(ZIEL + 'logo_transparent.svg', 'w', encoding='utf-8').write(logo(None, WEINROT, ROT, FADEN))
open(ZIEL + 'logo_weiss.svg', 'w', encoding='utf-8').write(logo(None, '#ffffff', ROT, '#e9d9c0'))
fav = socke(91, 8, ROT, CREME_S, CREME_S, id_='f')
open(ZIEL + 'favicon.svg', 'w', encoding='utf-8').write(
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 310"><title>Sockenwolle-online</title>'
    f'<rect width="310" height="310" rx="56" fill="{CREME}"/>{fav}</svg>')
print('ok')
