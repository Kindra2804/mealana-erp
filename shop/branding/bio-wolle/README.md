# Logo bio-wolle.at

Auswahl Babsi/Jacky (2026-11-05): **Variante 1b in „Tanne hell“**. Das ist das bisherige Logo mit der Baumwollkapsel als „o“, aufgeräumt auf eine Zeile, mit kräftigerer Schrift und „-Wolle“ in Grün. Alles ist als Vektor nachgebaut.

| Datei | Wofür |
|---|---|
| `logo.svg` | Logo auf Creme-Hintergrund (Web, Druck, beliebig groß) |
| `logo_transparent.svg` | ohne Hintergrund, für helle Flächen (Shop-Kopf) |
| `logo_weiss.svg` | weiße Schrift, für dunkle Flächen (Footer, Fotos) |
| `favicon.svg` | Kapsel auf Creme, quadratisch: Browser-Tab, Profilbild |
| `logo_1600.png`, `logo_creme_1600.png`, `logo_weiss_1600.png` | dasselbe als PNG, 1600 px breit (WordPress-Upload) |
| `favicon_512.png` | Website-Symbol für WordPress (Customizer → Website-Icon) |
| `logo_bauen.py` | Skript, das alle SVGs erzeugt |

## Farben

| | Hex |
|---|---|
| „Bi“, Kapsel-Rand | `#2a2e26` |
| „-Wolle“, Blatt (Tanne hell) | `#3b6b52` |
| Dunkelgrün (Leiste, Footer) | `#264a39` |
| Hellgrün (Flächen, „-Wolle“ auf dunklem Grund) | `#dbe8df` |
| Blatt auf dunklem Grund | `#a9d1b6` |
| Akzent Holz (Sale, Badges, nicht für weiße Schrift auf Buttons) | `#c08a4e` |
| Hintergrund (Creme) | `#f6f3eb` |

Schriften im Shop: Nunito (Text), Fraunces (Überschriften).

## Neu erzeugen

Schrift **Josefin Sans** (SIL Open Font License, frei auch kommerziell) von Google Fonts laden. Die variable Datei auf Regular (400) festlegen und dann bauen:

```
python -I -m fontTools.varLib.instancer "JosefinSans[wght].ttf" wght=400 -o JosefinSans-Regular.ttf
python -I logo_bauen.py JosefinSans-Regular.ttf <zielordner>
```

Für die PNGs: `node svg_png.js <datei.svg> <ziel.png> <breite>` (liegt in `.claude-browser-tools`).

Der Text ist in den SVGs schon in Pfade umgewandelt, die Schrift muss zum Verwenden des Logos nicht installiert sein.
