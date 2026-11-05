# Logo Sockenwolle-online

Entwurf von Babsi (2026-11-05): Schrift in Farbvariante **A Weinrot**, Socken nach Babsis Canva-Entwurf (kräftiges Rot, Norweger-Band, helle Ferse und Spitze mit Lachs-Rand), alles als Vektor nachgebaut.

| Datei | Wofür |
|---|---|
| `logo.svg` | Logo auf Creme-Hintergrund (Web, Druck, beliebig groß) |
| `logo_transparent.svg` | ohne Hintergrund, für helle Flächen (Shop-Kopf) |
| `logo_weiss.svg` | weiße Schrift, für dunkle Flächen (Footer, Fotos) |
| `favicon.svg` | eine Socke, quadratisch: Browser-Tab, Profilbild |
| `logo_1600.png`, `logo_creme_1600.png`, `logo_weiss_1600.png` | dasselbe als PNG, 1600 px breit (WordPress-Upload) |
| `favicon_512.png` | Website-Symbol für WordPress (Customizer → Website-Icon) |
| `logo_bauen.py` | Skript, das alle SVGs erzeugt |

## Farben

| | Hex |
|---|---|
| Schrift (Weinrot) | `#8e3438` |
| Socken (Rot) | `#f23d39` |
| Muster, Ferse, Spitze | `#f4e5d4` |
| Rand von Ferse und Spitze (Lachs) | `#ef8a72` |
| Faden | `#cbb48f` |
| Hintergrund (Creme) | `#fdf8f2` |

Shop-Palette dazu: Koralle `#c8574a`, Rosé `#e9b8ad`, Senf `#d9a441`, Espresso `#3b2a26`.

## Neu erzeugen

Schrift **Andika Regular** (SIL Open Font License, frei auch kommerziell) von Google Fonts laden, dann:

```
python -I logo_bauen.py Andika-Regular.ttf <zielordner>
```

Der Text ist in den SVGs schon in Pfade umgewandelt, die Schrift muss zum Verwenden des Logos nicht installiert sein.
