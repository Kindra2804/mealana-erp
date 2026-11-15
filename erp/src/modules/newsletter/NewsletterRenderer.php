<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Firmenlogo.php';
require_once __DIR__ . '/../dokumente/BelegBilder.php';
require_once __DIR__ . '/NewsletterService.php';

/**
 * Newsletter als Mail-HTML im Design des Shops (Logo, Hausfarbe, Website aus dem Kanal).
 * Tabellen-Layout mit Inline-Styles, 600 px breit — so stellen es auch Outlook & Co. dar.
 * Bilder als data:-URI (Vorschau zeigt sie direkt, der Mailer bettet sie beim Versand als Anhang ein).
 * Platzhalter: {vorname} (leer → fällt samt Leerzeichen weg).
 */
class NewsletterRenderer
{
    private PDO $db;
    private NewsletterService $svc;
    private string $farbe = '#2c3e50';

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->svc = new NewsletterService();
    }

    /** @param array $empfaenger ['vorname' => ?, 'abmelde_link' => ?] */
    public function html(array $n, array $empfaenger = []): string
    {
        $this->farbe = preg_match('/^#[0-9a-f]{6}$/i', (string)($n['design_farbe'] ?? '')) ? $n['design_farbe'] : '#2c3e50';
        $web   = rtrim((string)(($n['website_url'] ?? '') ?: ($n['wc_url'] ?? '')), '/');
        $logo  = Firmenlogo::dataUri(Firmenlogo::fuerShop((int)$n['shop_id']));
        $firma = $this->db->query("SELECT schluessel, wert FROM system_einstellungen WHERE schluessel IN ('firmenname','strasse','plz','ort')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $vorname = trim((string)($empfaenger['vorname'] ?? ''));
        $abmelden = (string)($empfaenger['abmelde_link'] ?? '#');

        $inhalt = '';
        foreach ($n['bausteine'] as $b) {
            $inhalt .= $this->baustein($b, $n, $web, $vorname);
        }
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $adresse = trim(($firma['strasse'] ?? '') . ', ' . trim(($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? '')), ', ');

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $h($n['betreff'] ?? $n['titel']) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $h($n['vorschautext'] ?? '') . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6"><tr><td align="center" style="padding:20px 10px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;border-top:6px solid ' . $this->farbe . '">'
            . '<tr><td align="center" style="padding:22px 24px 10px">'
            . ($logo ? '<a href="' . $h($web ?: '#') . '"><img src="' . $logo . '" alt="' . $h($n['shop_name']) . '" style="max-height:70px;max-width:260px;border:0"></a>'
                     : '<div style="font-size:22px;font-weight:bold;color:' . $this->farbe . '">' . $h($n['shop_name']) . '</div>')
            . '</td></tr>'
            . $inhalt
            . '<tr><td style="padding:22px 24px;background:#f9fafb;font-size:12px;line-height:1.6;color:#6b7280;text-align:center">'
            . ($web ? '<a href="' . $h($web) . '" style="color:' . $this->farbe . ';font-weight:bold;text-decoration:none">' . $h(preg_replace('#^https?://#', '', $web)) . '</a><br>' : '')
            . $h($firma['firmenname'] ?? '') . ($adresse ? ' · ' . $h($adresse) : '') . '<br>'
            . 'Du bekommst diesen Newsletter, weil du dich bei ' . $h($n['shop_name']) . ' angemeldet hast. '
            . '<a href="' . $h($abmelden) . '" style="color:#6b7280">Newsletter abbestellen</a>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    /** Text-Version (für Mailprogramme ohne HTML) */
    public function text(array $n, array $empfaenger = []): string
    {
        $vorname = trim((string)($empfaenger['vorname'] ?? ''));
        $t = '';
        foreach ($n['bausteine'] as $b) {
            foreach (['ueberschrift', 'text'] as $f) if (!empty($b[$f])) $t .= $this->platzhalter($b[$f], $vorname) . "\n\n";
            if (($b['typ'] ?? '') === 'knopf' && !empty($b['link'])) $t .= ($b['text'] ?: 'Link') . ': ' . $b['link'] . "\n\n";
            if (($b['typ'] ?? '') === 'hinweis' && !empty($b['code'])) $t .= 'Code: ' . $b['code'] . "\n\n";
        }
        return trim($t) . "\n\nAbbestellen: " . ($empfaenger['abmelde_link'] ?? '');
    }

    private function baustein(array $b, array $n, string $web, string $vorname): string
    {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $f = $this->farbe;
        $zelle = fn(string $inhalt, string $pad = '10px 24px') => '<tr><td style="padding:' . $pad . '">' . $inhalt . '</td></tr>';
        $titel = fn($t) => $t !== '' ? '<h2 style="margin:0 0 10px;font-size:21px;line-height:1.3;color:' . $f . '">' . $h($this->platzhalter($t, $vorname)) . '</h2>' : '';

        switch ($b['typ'] ?? '') {
            case 'kopfbild':
                $src = $this->bildUri($b['bild'] ?? '');
                if (!$src) return '';
                $img = '<img src="' . $src . '" alt="' . $h($b['alt'] ?? '') . '" width="600" style="display:block;width:100%;height:auto;border:0">';
                return '<tr><td style="padding:6px 0 4px">' . (!empty($b['link']) ? '<a href="' . $h($b['link']) . '">' . $img . '</a>' : $img) . '</td></tr>';

            case 'text':
                return $zelle($titel($b['ueberschrift'] ?? '') . $this->absaetze($this->platzhalter($b['text'] ?? '', $vorname)));

            case 'bild':
                $src = $this->bildUri($b['bild'] ?? '');
                if (!$src) return '';
                $img = '<img src="' . $src . '" alt="' . $h($b['alt'] ?? '') . '" style="display:block;width:100%;height:auto;border:0;border-radius:6px">';
                return $zelle((!empty($b['link']) ? '<a href="' . $h($b['link']) . '">' . $img . '</a>' : $img)
                    . (!empty($b['text']) ? '<div style="font-size:13px;color:#6b7280;margin-top:6px">' . $h($b['text']) . '</div>' : ''));

            case 'knopf':
                $link = ($b['link'] ?? '') ?: $web;
                if (!$link) return '';
                return $zelle('<table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr><td style="background:' . $f . ';border-radius:8px">'
                    . '<a href="' . $h($link) . '" style="display:inline-block;padding:13px 28px;color:#ffffff;font-weight:bold;font-size:16px;text-decoration:none">'
                    . $h(($b['text'] ?? '') ?: 'Zum Shop') . '</a></td></tr></table>', '14px 24px');

            case 'hinweis':
                return $zelle('<div style="border:2px dashed ' . $f . ';border-radius:10px;padding:16px;text-align:center;background:#fafafa">'
                    . $titel($b['ueberschrift'] ?? '') . $this->absaetze($this->platzhalter($b['text'] ?? '', $vorname))
                    . (!empty($b['code']) ? '<div style="font-family:Courier New,monospace;font-size:22px;font-weight:bold;letter-spacing:2px;color:' . $f . ';margin-top:6px">' . $h($b['code']) . '</div>' : '')
                    . '</div>');

            case 'trenner':
                return $zelle('<div style="border-top:1px solid #e5e7eb;height:1px"></div>', '8px 24px');

            case 'artikel':
                return $this->artikelRaster($b, $n, $web, $titel($b['ueberschrift'] ?? ''));
        }
        return '';
    }

    private function artikelRaster(array $b, array $n, string $web, string $titelHtml): string
    {
        $ids = array_slice((array)($b['artikel_ids'] ?? []), 0, 24);
        if (!$ids) return '';
        $spalten = in_array((int)($b['spalten'] ?? 2), [1, 2, 3], true) ? (int)$b['spalten'] : 2;
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $breite = (int)floor(100 / $spalten);
        $zellen = [];
        foreach ($ids as $id) {
            $a = $this->artikelDaten((int)$id, (int)$n['shop_id'], (string)($n['wc_url'] ?? ''), $web);
            if (!$a) continue;
            $bild = $a['bild'] ? '<img src="data:image/jpeg;base64,' . $a['bild'] . '" alt="' . $h($a['name']) . '" style="display:block;width:100%;max-width:260px;height:auto;margin:0 auto 8px;border:0;border-radius:6px">' : '';
            $preis = $a['preis'] !== null
                ? ($a['preis_vorher'] ? '<span style="color:#9ca3af;text-decoration:line-through;font-size:13px">€ ' . number_format($a['preis_vorher'], 2, ',', '.') . '</span> ' : '')
                  . '<strong style="color:' . $this->farbe . '">€ ' . number_format($a['preis'], 2, ',', '.') . '</strong>'
                : '';
            $inhalt = $bild . '<div style="font-size:14px;font-weight:bold;line-height:1.3;margin-bottom:4px">' . $h($a['name']) . '</div><div style="font-size:15px">' . $preis . '</div>';
            $zellen[] = '<td width="' . $breite . '%" valign="top" style="padding:8px;text-align:center">'
                . ($a['link'] ? '<a href="' . $h($a['link']) . '" style="color:#1f2937;text-decoration:none">' . $inhalt . '</a>' : $inhalt) . '</td>';
        }
        if (!$zellen) return '';
        $zeilen = '';
        foreach (array_chunk($zellen, $spalten) as $reihe) {
            while (count($reihe) < $spalten) $reihe[] = '<td width="' . $breite . '%"></td>';
            $zeilen .= '<tr>' . implode('', $reihe) . '</tr>';
        }
        return '<tr><td style="padding:10px 16px">' . ($titelHtml ? '<div style="padding:0 8px">' . $titelHtml . '</div>' : '')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $zeilen . '</table></td></tr>';
    }

    /** Name, Preis, Bild (300 px), Link für einen Artikel im Shop */
    private function artikelDaten(int $id, int $shopId, string $wcUrl, string $web): ?array
    {
        $st = $this->db->prepare("SELECT a.id, a.name, a.artikelnummer,
                (SELECT CONCAT(b.artikel_id, '|', b.dateiname) FROM artikel_bilder b WHERE b.artikel_id = a.id ORDER BY b.position, b.id LIMIT 1) AS bild,
                (SELECT ash.external_id FROM artikel_shops ash WHERE ash.artikel_id = a.id AND ash.shop_id = ? AND ash.aktiv = 1) AS woo_id
            FROM artikel a WHERE a.id = ?");
        $st->execute([$shopId, $id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) return null;
        $bild = null;
        if ($a['bild']) {
            [$besitzer, $datei] = explode('|', $a['bild'], 2);
            $bild = BelegBilder::vorschau((int)$besitzer, $datei, 300);
        }
        // Woo-Shop: Produkt direkt (?p= leitet auf die schöne Adresse um); sonst Shop-Suche nach der Artikelnummer (JTL: ?qs=)
        $link = null;
        if ($wcUrl && $a['woo_id']) $link = rtrim($wcUrl, '/') . '/?p=' . (int)$a['woo_id'];
        elseif ($web) $link = $web . '/?' . ($wcUrl ? 's' : 'qs') . '=' . rawurlencode($a['artikelnummer']);
        return ['name' => $a['name'], 'bild' => $bild, 'link' => $link] + $this->svc->preis($id);
    }

    private function bildUri(string $pfad): string
    {
        if (!preg_match('#^uploads/newsletter/\d+/[A-Za-z0-9._-]+$#', $pfad)) return '';
        $datei = __DIR__ . '/../../../public/' . $pfad;
        if (!is_file($datei)) return '';
        return 'data:' . (str_ends_with($pfad, '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string)file_get_contents($datei));
    }

    /** Absätze (Leerzeile) + Zeilenumbrüche, **fett** */
    private function absaetze(string $text): string
    {
        $text = trim($text);
        if ($text === '') return '';
        $html = '';
        foreach (preg_split('/\n\s*\n/', $text) as $abs) {
            $e = htmlspecialchars($abs, ENT_QUOTES, 'UTF-8');
            $e = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $e);
            $html .= '<p style="margin:0 0 12px;font-size:15px;line-height:1.6">' . nl2br($e) . '</p>';
        }
        return $html;
    }

    private function platzhalter(string $s, string $vorname): string
    {
        return $vorname !== '' ? str_replace('{vorname}', $vorname, $s) : preg_replace('/\s*\{vorname\}/u', '', $s);
    }
}
