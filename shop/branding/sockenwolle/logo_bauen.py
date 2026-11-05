"""Sockenwolle-online Logo als Vektor (SVG) nach Babsis Entwurf, Variante A Weinrot (2026-11-05).

Schrift: Andika Regular (SIL OFL) als Pfade + leichte Kontur für die Strichstärke.
Socken + Wollfaden selbst gezeichnet. Maße in px vom Rastervorlage-Ausschnitt
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
WEINROT, TERRA, TERRA_D, CREME_S, FADEN, CREME = '#8e3438', '#d4693f', '#b9552f', '#f6e6cf', '#cbb48f', '#fdf8f2'

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


def socke(x, y, farbe=TERRA, muster=CREME_S, ferse=CREME_S, rippe='#ffffff', id_='s'):
    """Socke wie in Babsis Entwurf: Bündchen mit Rippen, Norweger-Band, Ferse links unten, Fuß nach rechts, Spitze hell.
    Bezug: links oben (x, y). Größe ca. 135 × 293 px."""
    umriss = ('M0,6 Q0,0 6,0 H70 Q76,0 76,6 V178 Q76,196 92,210 L122,240 '
              'Q140,262 124,282 Q106,298 84,286 L32,246 Q0,222 0,186 Z')
    teile = [f'<clipPath id="{id_}"><path d="{umriss}"/></clipPath>',
             f'<path d="{umriss}" fill="{farbe}"/>',
             f'<g clip-path="url(#{id_})">']
    # Bündchen: Rippen
    for rx in range(9, 72, 9):
        teile.append(f'<line x1="{rx}" y1="7" x2="{rx}" y2="46" stroke="{rippe}" stroke-opacity=".75" stroke-width="2.6" stroke-linecap="round"/>')
    teile.append(f'<line x1="0" y1="54" x2="76" y2="54" stroke="{muster}" stroke-width="3"/>')
    # Norweger-Band: Punktreihe, Zickzack, Rauten, Punktreihe
    for px in range(8, 76, 12):
        teile.append(f'<rect x="{px - 2.5}" y="63" width="5" height="5" fill="{muster}"/>')
    zz = ' '.join(f'{zx},{88 if i % 2 == 0 else 76}' for i, zx in enumerate(range(0, 82, 9)))
    teile.append(f'<polyline points="{zz}" fill="none" stroke="{muster}" stroke-width="3" stroke-linejoin="miter"/>')
    for rx in range(10, 76, 19):
        teile.append(f'<path d="M{rx},96 l8,10 l-8,10 l-8,-10 Z" fill="none" stroke="{muster}" stroke-width="2.6"/>')
    for px in range(14, 76, 12):
        teile.append(f'<rect x="{px - 2.5}" y="124" width="5" height="5" fill="{muster}"/>')
    zz2 = ' '.join(f'{zx},{146 if i % 2 == 0 else 136}' for i, zx in enumerate(range(0, 82, 9)))
    teile.append(f'<polyline points="{zz2}" fill="none" stroke="{muster}" stroke-width="2.4"/>')
    # Ferse (links unten) + Spitze (rechts unten)
    teile.append(f'<path d="M-4,196 Q8,214 34,222 Q44,236 40,252 L30,262 Q-4,236 -4,206 Z" fill="{ferse}"/>')
    teile.append(f'<path d="M100,256 Q110,246 126,252 Q142,266 128,284 Q110,300 86,288 Q90,268 100,256 Z" fill="{ferse}"/>')
    teile.append('</g>')
    return f'<g transform="translate({x:.1f},{y:.1f})">' + ''.join(teile) + '</g>'


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


open(ZIEL + 'logo.svg', 'w', encoding='utf-8').write(logo(CREME, WEINROT, TERRA, FADEN))
open(ZIEL + 'logo_transparent.svg', 'w', encoding='utf-8').write(logo(None, WEINROT, TERRA, FADEN))
open(ZIEL + 'logo_weiss.svg', 'w', encoding='utf-8').write(logo(None, '#ffffff', TERRA, '#e9d9c0'))
fav = socke(91, 8, TERRA, CREME_S, CREME_S, id_='f')
open(ZIEL + 'favicon.svg', 'w', encoding='utf-8').write(
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 310"><title>Sockenwolle-online</title>'
    f'<rect width="310" height="310" rx="56" fill="{CREME}"/>{fav}</svg>')
print('ok')
