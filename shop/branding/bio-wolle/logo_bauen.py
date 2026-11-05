"""bio-wolle.at Logo als Vektor (SVG), Variante 1b in „Tanne hell“ (Auswahl Babsi/Jacky 2026-11-05).

Aufbau wie das alte Logo, aber einzeilig: „Bi“ + Baumwollkapsel als „o“ + „-Wolle“.
„Bi“ dunkel, „-Wolle“ in Tanne hell, Kapsel weiß mit dunklem Rand, Blatt in Tanne hell.
Schrift: Josefin Sans Regular (SIL OFL) als Pfade.

Aufruf: python -I logo_bauen.py <JosefinSans-Regular.ttf> <zielordner>
Erzeugt: logo.svg (Creme-Hintergrund), logo_transparent.svg, logo_weiss.svg (für dunklen Grund),
         favicon.svg (Kapsel)
"""
import sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

FONT, ZIEL = sys.argv[1], sys.argv[2].rstrip('/') + '/'
TEXT, TANNE, DUNKEL, HELL, CREME = '#2a2e26', '#3b6b52', '#264a39', '#dbe8df', '#f6f3eb'
BLATT_HELL = '#a9d1b6'               # Blatt auf dunklem Grund

f = TTFont(FONT)
gs, cmap, hmtx = f.getGlyphSet(), f.getBestCmap(), f['hmtx']
EM = f['head'].unitsPerEm
GROESSE = 200                         # Schriftgröße in px (Großbuchstaben ca. 140 px hoch)
S = GROESSE / EM


UNTERSCHNEIDUNG = {('W', 'o'): -70}   # Font-Einheiten; Josefin lässt nach dem W sonst eine Lücke


def text_pfad(text, x0, basis):
    """Gibt (svg-pfad, endx) zurück; Grundlinie bei y=basis."""
    teile, x = [], x0
    for i, ch in enumerate(text):
        if i:
            x += UNTERSCHNEIDUNG.get((text[i - 1], ch), 0) * S
        g = cmap[ord(ch)]
        pen = SVGPathPen(gs)
        gs[g].draw(TransformPen(pen, (S, 0, 0, -S, x, basis)))
        teile.append(pen.getCommands())
        x += hmtx[g][0] * S
    return ' '.join(teile), x


KREISE = [(30, 40, 19), (50, 27, 21), (70, 40, 19), (38, 56, 19), (62, 56, 19)]
BLATT = 'M50,50 C59,60 59,72 50,79 C41,72 41,60 50,50Z'


def kapsel(x, y, k, rand, fuell, blatt):
    """Baumwollkapsel wie im alten Logo: fünf Bögen als gemeinsamer Umriss, darunter drei Blätter mit Stiel.
    Gezeichnet in einer 100er-Box, Bezug links oben (x, y), Faktor k."""
    aussen = ''.join(f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fuell}" stroke="{rand}" stroke-width="4.5"/>' for cx, cy, r in KREISE)
    innen = ''.join(f'<circle cx="{cx}" cy="{cy}" r="{r - 2.2}" fill="{fuell}"/>' for cx, cy, r in KREISE)
    blaetter = (f'<g fill="{blatt}"><path d="{BLATT}"/><path d="{BLATT}" transform="rotate(-62 50 79)"/>'
                f'<path d="{BLATT}" transform="rotate(62 50 79)"/></g>'
                f'<line x1="50" y1="76" x2="50" y2="93" stroke="{blatt}" stroke-width="5" stroke-linecap="round"/>')
    return f'<g transform="translate({x:.1f},{y:.1f}) scale({k})">{aussen}{innen}{blaetter}</g>'


def logo(hg, farbe_bi, farbe_wolle, rand, fuell, blatt):
    basis, x0 = 200, 30
    p1, x1 = text_pfad('Bi', x0, basis)
    k = GROESSE * 0.9 / 100               # Kapsel 0,9 × Schriftgröße breit
    kap = f['OS/2'].sCapHeight * S
    kx, ky = x1 + 6, basis - kap - 0.04 * GROESSE - 6 * k   # Wolkenoberkante knapp über den Großbuchstaben
    p2, x2 = text_pfad('-Wolle', kx + 100 * k + 2, basis)
    breite, hoehe = x2 + 30, max(basis + 30, ky + 96 * k + 20)
    hg_rect = f'<rect width="{breite:.0f}" height="{hoehe:.0f}" fill="{hg}"/>' if hg else ''
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {breite:.0f} {hoehe:.0f}" '
            f'width="{breite:.0f}" height="{hoehe:.0f}"><title>bio-wolle</title>{hg_rect}'
            f'<path d="{p1}" fill="{farbe_bi}"/>{kapsel(kx, ky, k, rand, fuell, blatt)}'
            f'<path d="{p2}" fill="{farbe_wolle}"/></svg>')


open(ZIEL + 'logo.svg', 'w', encoding='utf-8').write(logo(CREME, TEXT, TANNE, TEXT, '#ffffff', TANNE))
open(ZIEL + 'logo_transparent.svg', 'w', encoding='utf-8').write(logo(None, TEXT, TANNE, TEXT, '#ffffff', TANNE))
open(ZIEL + 'logo_weiss.svg', 'w', encoding='utf-8').write(logo(None, '#ffffff', HELL, '#ffffff', '#ffffff', BLATT_HELL))
open(ZIEL + 'favicon.svg', 'w', encoding='utf-8').write(
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 310"><title>bio-wolle</title>'
    f'<rect width="310" height="310" rx="56" fill="{CREME}"/>{kapsel(23, 18, 2.64, TEXT, "#ffffff", TANNE)}</svg>')
print('ok')
