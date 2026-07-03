<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../src/core/Database.php';

$db = Database::getInstance();

// ── Aufträge ────────────────────────────────────────────────────────────────
$auftraegeOffen = (int)$db->query("
    SELECT COUNT(*) FROM auftraege
    WHERE zahlungsstatus NOT IN ('storniert')
      AND lieferstatus NOT IN ('storniert', 'abgeschlossen')
")->fetchColumn();

$auftraegeHeuteNeu = (int)$db->query("
    SELECT COUNT(*) FROM auftraege WHERE DATE(erstellt_am) = CURDATE()
")->fetchColumn();

$picklistenOffen = (int)$db->query("
    SELECT COUNT(*) FROM picklisten WHERE status IN ('offen','gedruckt')
")->fetchColumn();

$bestandsWarnungen = (int)$db->query("
    SELECT COUNT(DISTINCT artikel_id) FROM lagerbestand
    WHERE mindestbestand > 0 AND bestand <= mindestbestand
")->fetchColumn();

// ── Fehlbestand-Zähler (gleiche Logik wie picklisten.php) ───────────────────
// Lieferbereite Aufträge: bezahlt/bar/rechnung + nicht auf offener Pickliste
$lieferbereitRaw = $db->query("
    SELECT a.id
    FROM auftraege a
    WHERE a.lieferstatus IN ('neu','in_bearbeitung','versandbereit','teilgeliefert')
      AND a.zahlungsstatus != 'storniert'
      AND (a.zahlungsstatus = 'bezahlt' OR a.zahlungsart IN ('bar','rechnung','nachnahme'))
      AND a.id NOT IN (
          SELECT pa.auftrag_id FROM pickliste_auftraege pa
          JOIN picklisten pl ON pl.id = pa.pickliste_id
          WHERE pl.status IN ('offen','gedruckt')
      )
")->fetchAll(PDO::FETCH_COLUMN);

$fehlbestandAuftraege = 0;
if (!empty($lieferbereitRaw)) {
    // Positionen für diese Aufträge laden
    $ids      = implode(',', array_map('intval', $lieferbereitRaw));
    $positionen = $db->query("
        SELECT p.auftrag_id, p.artikel_id,
               p.menge - COALESCE(p.menge_geliefert, 0) AS menge
        FROM auftrag_positionen p
        WHERE p.auftrag_id IN ($ids)
          AND p.menge - COALESCE(p.menge_geliefert, 0) > 0
        ORDER BY p.auftrag_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $posByAuftrag = [];
    foreach ($positionen as $p) {
        $posByAuftrag[$p['auftrag_id']][] = $p;
    }

    // Verfügbarer Bestand (Lager minus offene Reservierungen)
    $bestand = $db->query("
        SELECT artikel_id, COALESCE(SUM(bestand), 0) AS ist
        FROM lagerbestand GROUP BY artikel_id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $res = $db->query("
        SELECT artikel_id, SUM(menge) AS res
        FROM reservierungen WHERE status='offen' GROUP BY artikel_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $verfuegbar = $bestand;
    foreach ($res as $r) {
        $verfuegbar[$r['artikel_id']] = max(0, ($verfuegbar[$r['artikel_id']] ?? 0) - (int)$r['res']);
    }

    // Greedy-Allocation: ältester Auftrag zuerst (Reihenfolge aus Query)
    $allocated = [];
    foreach ($lieferbereitRaw as $aid) {
        $pos   = $posByAuftrag[$aid] ?? [];
        $ok    = 0;
        $total = count($pos);
        foreach ($pos as $p) {
            $artId     = (int)$p['artikel_id'];
            $needed    = (int)$p['menge'];
            $available = ($verfuegbar[$artId] ?? 0) - ($allocated[$artId] ?? 0);
            if ($available >= $needed) {
                $ok++;
            }
        }
        if ($ok === $total && $total > 0) {
            // Vollständig lieferbar → allokieren
            foreach ($pos as $p) {
                $allocated[(int)$p['artikel_id']] = ($allocated[(int)$p['artikel_id']] ?? 0) + (int)$p['menge'];
            }
        } elseif ($total > 0) {
            $fehlbestandAuftraege++;
        }
    }
}

// ── Umsatz Kasse HEUTE pro Kasse ────────────────────────────────────────────
$kassenumsatzHeuteRows = $db->query("
    SELECT k.name, k.kasse_nr, k.aktiv,
           COALESCE(SUM(CASE WHEN b.typ='verkauf' AND b.storniert=0
                             AND DATE(b.erstellt_am)=CURDATE()
                        THEN b.bruttobetrag ELSE 0 END), 0) AS umsatz_heute
    FROM kassen k
    LEFT JOIN kassen_bons b ON b.kasse_id = k.id
    GROUP BY k.id, k.name, k.kasse_nr, k.aktiv
    ORDER BY k.kasse_nr
")->fetchAll(PDO::FETCH_ASSOC);

$kasseUmsatzHeuteGesamt = array_sum(array_column($kassenumsatzHeuteRows, 'umsatz_heute'));

$kasseUmsatzGestern = (float)$db->query("
    SELECT COALESCE(SUM(bruttobetrag), 0) FROM kassen_bons
    WHERE typ='verkauf' AND storniert=0 AND DATE(erstellt_am)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)
")->fetchColumn();

$umsatzHeuteGesamt = $kasseUmsatzHeuteGesamt; // Online-Kanäle kommen mit Shop-Sync
$trendHeute = $kasseUmsatzGestern > 0
    ? round(($umsatzHeuteGesamt - $kasseUmsatzGestern) / $kasseUmsatzGestern * 100, 1)
    : null;

// ── Umsatz Kasse MONAT ───────────────────────────────────────────────────────
$kasseUmsatzMonat = (float)$db->query("
    SELECT COALESCE(SUM(bruttobetrag), 0) FROM kassen_bons
    WHERE typ='verkauf' AND storniert=0
      AND YEAR(erstellt_am)=YEAR(CURDATE()) AND MONTH(erstellt_am)=MONTH(CURDATE())
")->fetchColumn();

$kasseUmsatzVormonat = (float)$db->query("
    SELECT COALESCE(SUM(bruttobetrag), 0) FROM kassen_bons
    WHERE typ='verkauf' AND storniert=0
      AND YEAR(erstellt_am)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
      AND MONTH(erstellt_am)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
")->fetchColumn();

$trendMonat = $kasseUmsatzVormonat > 0
    ? round(($kasseUmsatzMonat - $kasseUmsatzVormonat) / $kasseUmsatzVormonat * 100, 1)
    : null;

$monatsName = date('F Y'); // z.B. "June 2026" → wir formatieren auf Deutsch unten

// ── Offene Forderungen ───────────────────────────────────────────────────────
$forderungenRows = $db->query("
    SELECT a.id, a.auftrag_nr, a.bruttobetrag, a.erstellt_am,
           a.zahlungsstatus, a.kunden_snapshot,
           COALESCE(SUM(z.betrag), 0) AS bezahlt,
           r.faellig_am,
           DATEDIFF(CURDATE(), COALESCE(r.faellig_am, DATE_ADD(a.erstellt_am, INTERVAL 14 DAY))) AS tage_ueberfaellig
    FROM auftraege a
    LEFT JOIN auftrag_zahlungen z ON z.auftrag_id = a.id
    LEFT JOIN rechnungen r ON r.auftrag_id = a.id AND r.storniert = 0
    WHERE a.zahlungsstatus IN ('ausstehend','teilbezahlt')
      AND a.lieferstatus != 'storniert'
    GROUP BY a.id, r.faellig_am
    ORDER BY tage_ueberfaellig DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$forderungenGesamt = (float)$db->query("
    SELECT COALESCE(SUM(a.bruttobetrag - COALESCE(z.bezahlt,0)), 0)
    FROM auftraege a
    LEFT JOIN (SELECT auftrag_id, SUM(betrag) AS bezahlt FROM auftrag_zahlungen GROUP BY auftrag_id) z
           ON z.auftrag_id = a.id
    WHERE a.zahlungsstatus IN ('ausstehend','teilbezahlt') AND a.lieferstatus != 'storniert'
")->fetchColumn();

$forderungenAnzahl = (int)$db->query("
    SELECT COUNT(*) FROM auftraege
    WHERE zahlungsstatus IN ('ausstehend','teilbezahlt') AND lieferstatus != 'storniert'
")->fetchColumn();

$forderungenUeberfaellig30 = (int)$db->query("
    SELECT COUNT(*) FROM auftraege a
    LEFT JOIN rechnungen r ON r.auftrag_id = a.id AND r.storniert = 0
    WHERE a.zahlungsstatus IN ('ausstehend','teilbezahlt')
      AND a.lieferstatus != 'storniert'
      AND DATEDIFF(CURDATE(), COALESCE(r.faellig_am, DATE_ADD(a.erstellt_am, INTERVAL 14 DAY))) > 30
")->fetchColumn();

$mahnungenAktiv = (int)$db->query("
    SELECT COUNT(*) FROM mahnungen m
    JOIN auftraege a ON a.id = m.auftrag_id
    WHERE m.typ = 'erinnerung'
      AND a.zahlungsstatus IN ('ausstehend','teilbezahlt')
")->fetchColumn();

// ── Letzte offene Aufträge ───────────────────────────────────────────────────
$letzteAuftraege = $db->query("
    SELECT a.id, a.auftrag_nr, a.kanal, a.zahlungsstatus, a.lieferstatus,
           a.bruttobetrag, a.erstellt_am, a.kunden_snapshot
    FROM auftraege a
    WHERE a.zahlungsstatus NOT IN ('storniert')
      AND a.lieferstatus NOT IN ('storniert', 'abgeschlossen')
    ORDER BY a.erstellt_am ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Bestandswarnungen (Top 5 für Tabelle) ───────────────────────────────────
$warnungenListe = $db->query("
    SELECT ar.name, ar.artikelnummer,
           COALESCE(SUM(lb.bestand), 0) AS bestand_gesamt,
           lb.mindestbestand
    FROM lagerbestand lb
    JOIN artikel ar ON ar.id = lb.artikel_id
    WHERE lb.mindestbestand > 0 AND lb.bestand <= lb.mindestbestand
    GROUP BY ar.id, ar.name, ar.artikelnummer, lb.mindestbestand
    ORDER BY bestand_gesamt ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Log: letzte 3 Aktivitäten ───────────────────────────────────────────────
$logEintraege = $db->query("
    SELECT a.aktion, a.referenz_tabelle, a.erstellt_am,
           b.formularname AS benutzer
    FROM aktivitaeten a
    LEFT JOIN benutzer b ON b.id = a.benutzer_id
    ORDER BY a.erstellt_am DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// ── Hilfsfunktionen ──────────────────────────────────────────────────────────
function kundenName(?string $snapshot, string $fallback = '— Laufkunde —'): string {
    if (!$snapshot) return $fallback;
    $d = json_decode($snapshot, true);
    if (!$d) return $fallback;
    if (!empty($d['firmenname'])) return $d['firmenname'];
    $name = trim(($d['vorname'] ?? '') . ' ' . ($d['nachname'] ?? ''));
    return $name ?: $fallback;
}

function eur(float $betrag): string {
    return '€ ' . number_format($betrag, 2, ',', '.');
}

function trendChip(?float $pct): string {
    if ($pct === null) return '<span style="color:#94a3b8;font-size:12px">— kein Vergleich</span>';
    $farbe = $pct >= 0 ? '#16a34a' : '#dc2626';
    $bg    = $pct >= 0 ? '#f0fdf4' : '#fef2f2';
    $pfeil = $pct >= 0 ? '↑' : '↓';
    return '<span style="background:' . $bg . ';color:' . $farbe . ';padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600">'
         . $pfeil . ' ' . abs($pct) . '%</span>';
}

$monatsNamenDE = ['Januar','Februar','März','April','Mai','Juni',
                  'Juli','August','September','Oktober','November','Dezember'];
$aktuellerMonat = $monatsNamenDE[(int)date('n') - 1] . ' ' . date('Y');
$vormonatName   = $monatsNamenDE[(int)date('n', strtotime('-1 month')) - 1];

// ── Kanal-Balken: max für relative Breiten ───────────────────────────────────
$maxKasseUmsatz = max([1, ...array_column($kassenumsatzHeuteRows, 'umsatz_heute')]);

// ── Seite aufbauen ───────────────────────────────────────────────────────────
$pageTitle    = 'Dashboard';
$activeModule = 'dashboard';
require_once __DIR__ . '/includes/shell_top.php';
?>
<style>
.db-grid-kpi { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:16px; }
.db-grid-mid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
.db-grid-bot { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
.db-card {
    background:white; border:1px solid #e2e8f0; border-radius:8px;
    padding:16px; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.db-card-label {
    font-size:10px; font-weight:700; letter-spacing:.7px;
    color:#64748b; text-transform:uppercase; margin-bottom:6px;
}
.db-card-value {
    font-size:28px; font-weight:800; color:#1e3a5f; line-height:1.1;
}
.db-card-sub { font-size:12px; color:#64748b; margin-top:4px; }
.db-sep { border:none; border-top:1px solid #f1f5f9; margin:10px 0; }
.db-chip {
    display:inline-block; padding:2px 10px; border-radius:10px;
    font-size:11px; font-weight:600;
}
.db-chip-red   { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
.db-chip-amber { background:#fff7ed; color:#f59e0b; border:1px solid #fcd34d; }
.db-chip-green { background:#f0fdf4; color:#16a34a; border:1px solid #86efac; }
.db-chip-blue  { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.db-chip-gray  { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; }
/* Platzhalter Card */
.db-card-placeholder {
    background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;
    padding:16px; display:flex; flex-direction:column; align-items:center;
    justify-content:center; min-height:140px; gap:8px;
}
/* Kanal-Balken */
.db-bar-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.db-bar-label { width:140px; font-size:12px; color:#475569; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.db-bar-track { flex:1; height:12px; background:#e2e8f0; border-radius:6px; overflow:hidden; }
.db-bar-fill  { height:100%; border-radius:6px; }
.db-bar-amt   { width:80px; text-align:right; font-size:12px; font-weight:600; color:#1e3a5f; flex-shrink:0; }
/* Monat-Balken */
.db-mbar-row  { display:grid; grid-template-columns:80px 1fr 90px; gap:8px; align-items:center; margin-bottom:10px; }
.db-mbar-name { font-size:12px; color:#64748b; }
.db-mbar-fill { height:18px; border-radius:4px; }
.db-mbar-val  { font-size:12px; font-weight:600; color:#1e3a5f; }
/* Tabellen */
.db-table { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th { background:#f8fafc; color:#64748b; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:6px 8px; text-align:left; border-bottom:1px solid #e2e8f0; }
.db-table td { padding:8px 8px; border-bottom:1px solid #f1f5f9; color:#1e3a5f; vertical-align:middle; }
.db-table tr:last-child td { border-bottom:none; }
.db-table tr:hover td { background:#f8fafc; }
/* Aging-Balken */
.db-aging-track { display:inline-block; width:120px; height:8px; background:#e2e8f0; border-radius:4px; vertical-align:middle; overflow:hidden; }
.db-aging-fill  { height:100%; border-radius:4px; display:block; }
/* Warning strip */
.db-warn-strip {
    background:#fffbeb; border:1px solid #fcd34d; border-radius:6px;
    padding:8px 14px; margin-bottom:14px;
    display:flex; align-items:center; gap:10px; font-size:13px;
}
/* Log bar */
.db-log-bar {
    background:#1e293b; border-radius:6px; padding:8px 14px;
    display:flex; align-items:center; gap:16px; font-size:11px; font-family:Consolas,monospace;
}
</style>

<?php if ($bestandsWarnungen > 0): ?>
<div class="db-warn-strip">
    <span style="font-size:16px">⚠</span>
    <span style="color:#92400e">
        <strong><?= $bestandsWarnungen ?> Artikel</strong> unter Mindestbestand —
        <a href="<?= BASE_PATH ?>/lager/uebersicht.php" style="color:#b45309">Details im Lager →</a>
    </span>
</div>
<?php endif; ?>

<!-- ── KPI KARTEN ──────────────────────────────────────────────────────────── -->
<div class="db-grid-kpi">

    <!-- Card 1: Aufträge -->
    <div class="db-card">
        <div class="db-card-label">Aufträge</div>
        <div class="db-card-value"><?= $auftraegeOffen ?></div>
        <div class="db-card-sub">
            offen &nbsp;·&nbsp;
            <span style="color:#16a34a;font-weight:600">+<?= $auftraegeHeuteNeu ?> heute</span>
        </div>
        <hr class="db-sep">
        <div style="font-size:12px;color:#64748b;margin-bottom:5px">
            Picklisten offen: <strong style="color:#f59e0b"><?= $picklistenOffen ?></strong>
        </div>
        <?php if ($fehlbestandAuftraege > 0): ?>
        <div style="margin-bottom:5px">
            <a href="<?= BASE_PATH ?>/lager/picklisten.php" style="text-decoration:none">
                <span class="db-chip db-chip-red">⚠ Fehlbestand: <?= $fehlbestandAuftraege ?> →</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($bestandsWarnungen > 0): ?>
        <div style="margin-bottom:5px">
            <a href="<?= BASE_PATH ?>/lager/uebersicht.php" style="text-decoration:none">
                <span class="db-chip db-chip-amber">⚠ Bestandswarn.: <?= $bestandsWarnungen ?> →</span>
            </a>
        </div>
        <?php endif; ?>
        <div style="margin-top:6px">
            <a href="<?= BASE_PATH ?>/auftraege/liste.php" style="font-size:11px;color:#2563eb">→ Alle Aufträge</a>
        </div>
    </div>

    <!-- Card 2: Umsatz Heute -->
    <div class="db-card">
        <div class="db-card-label">Umsatz Heute</div>
        <div style="display:flex;align-items:baseline;gap:8px">
            <div class="db-card-value"><?= eur($umsatzHeuteGesamt) ?></div>
            <?= trendChip($trendHeute) ?>
        </div>
        <div class="db-card-sub" style="margin-bottom:6px">
            Gestern: <?= eur($kasseUmsatzGestern) ?>
        </div>
        <div style="font-size:10px;color:#94a3b8;font-weight:700;margin-bottom:4px">KANAL</div>
        <?php foreach ($kassenumsatzHeuteRows as $row):
            $pct = $maxKasseUmsatz > 0 ? round($row['umsatz_heute'] / $maxKasseUmsatz * 100) : 0;
        ?>
        <div class="db-bar-row">
            <div class="db-bar-label" title="<?= htmlspecialchars($row['name']) ?>">
                🛒 <?= htmlspecialchars($row['name']) ?>
            </div>
            <div class="db-bar-track">
                <div class="db-bar-fill" style="width:<?= $pct ?>%;background:<?= $row['aktiv'] ? '#2563eb' : '#cbd5e1' ?>"></div>
            </div>
            <div class="db-bar-amt"><?= eur((float)$row['umsatz_heute']) ?></div>
        </div>
        <?php endforeach; ?>
        <div class="db-bar-row" style="opacity:.5">
            <div class="db-bar-label">🌐 Online</div>
            <div class="db-bar-track" style="background:repeating-linear-gradient(45deg,#f1f5f9,#f1f5f9 4px,#e2e8f0 4px,#e2e8f0 8px)"></div>
            <div class="db-bar-amt" style="color:#94a3b8;font-size:10px">Shop-Sync</div>
        </div>
    </div>

    <!-- Card 3: Umsatz Monat -->
    <div class="db-card">
        <div class="db-card-label">Umsatz <?= $aktuellerMonat ?></div>
        <div style="display:flex;align-items:baseline;gap:8px">
            <div class="db-card-value"><?= eur($kasseUmsatzMonat) ?></div>
            <?= trendChip($trendMonat) ?>
        </div>
        <div class="db-card-sub"><?= $vormonatName ?>: <?= eur($kasseUmsatzVormonat) ?></div>
        <hr class="db-sep">
        <?php
        $maxMon = max(1, $kasseUmsatzMonat, $kasseUmsatzVormonat);
        $pctAkt  = round($kasseUmsatzMonat  / $maxMon * 100);
        $pctVor  = round($kasseUmsatzVormonat / $maxMon * 100);
        ?>
        <div class="db-mbar-row">
            <div class="db-mbar-name"><?= $vormonatName ?></div>
            <div class="db-mbar-track" style="background:#e2e8f0;border-radius:4px;height:18px;overflow:hidden">
                <div class="db-mbar-fill" style="width:<?= $pctVor ?>%;background:#7ec8e3"></div>
            </div>
            <div class="db-mbar-val"><?= eur($kasseUmsatzVormonat) ?></div>
        </div>
        <div class="db-mbar-row">
            <div class="db-mbar-name" style="font-weight:700;color:#1e3a5f"><?= date('F') ?></div>
            <div class="db-mbar-track" style="background:#e2e8f0;border-radius:4px;height:18px;overflow:hidden">
                <div class="db-mbar-fill" style="width:<?= $pctAkt ?>%;background:#1e3a5f"></div>
            </div>
            <div class="db-mbar-val"><?= eur($kasseUmsatzMonat) ?></div>
        </div>
        <div style="font-size:10px;color:#94a3b8;margin-top:4px">Basis: Kassenbons + erfasste Aufträge</div>
    </div>

    <!-- Card 4: Offene Forderungen -->
    <div class="db-card">
        <div class="db-card-label">Kunden-Rechnungen</div>
        <div class="db-card-value"><?= eur($forderungenGesamt) ?></div>
        <div class="db-card-sub"><?= $forderungenAnzahl ?> offene Rechnungen</div>
        <hr class="db-sep">
        <?php if ($forderungenUeberfaellig30 > 0): ?>
        <div style="margin-bottom:4px">
            <span class="db-chip db-chip-red">⚠ <?= $forderungenUeberfaellig30 ?> × &gt;30 Tage</span>
        </div>
        <?php endif; ?>
        <?php if ($mahnungenAktiv > 0): ?>
        <div style="margin-bottom:4px">
            <span class="db-chip db-chip-amber">Mahnungen: <?= $mahnungenAktiv ?></span>
        </div>
        <?php endif; ?>
        <div style="margin-top:8px">
            <a href="<?= BASE_PATH ?>/auftraege/liste.php?zahlung=ausstehend" style="font-size:11px;color:#2563eb">→ Unbezahlte Aufträge</a>
        </div>
    </div>

    <!-- Card 5: Lieferantenrechnungen — PLATZHALTER (aktivieren mit Buchhaltungsmodul) -->
    <!-- TODO BUCHHALTUNG: lieferanten_rechnungen Tabelle anlegen, diesen Block aktivieren -->
    <div class="db-card-placeholder">
        <div style="font-size:24px;color:#cbd5e1">📊</div>
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-align:center">LIEFERANTEN-RECHNUNGEN</div>
        <div style="font-size:12px;color:#94a3b8;text-align:center">kommt mit dem<br>Buchhaltungs-Modul</div>
    </div>

</div><!-- /db-grid-kpi -->

<!-- ── MITTLERE REIHE: Kanal-Umsatz + Monatsvergleich ──────────────────────── -->
<div class="db-grid-mid">

    <!-- Umsatz Heute nach Kanal (Detail) -->
    <div class="db-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div style="font-weight:700;font-size:13px;color:#1e3a5f">Umsatz Heute nach Kanal</div>
            <div style="font-size:12px;color:#64748b">Gesamt: <strong><?= eur($umsatzHeuteGesamt) ?></strong></div>
        </div>
        <?php foreach ($kassenumsatzHeuteRows as $row):
            $pct = $maxKasseUmsatz > 0 ? round((float)$row['umsatz_heute'] / $maxKasseUmsatz * 100) : 0;
            $farbe = $row['aktiv'] ? '#1e3a5f' : '#94a3b8';
        ?>
        <div class="db-bar-row" style="margin-bottom:12px">
            <div class="db-bar-label" style="width:160px;font-size:13px">
                🛒 <?= htmlspecialchars($row['name']) ?>
            </div>
            <div class="db-bar-track" style="height:16px">
                <div class="db-bar-fill" style="width:<?= $pct ?>%;background:<?= $farbe ?>"></div>
            </div>
            <div class="db-bar-amt" style="font-size:13px"><?= eur((float)$row['umsatz_heute']) ?></div>
        </div>
        <?php endforeach; ?>
        <!-- Online-Kanäle: erscheinen automatisch wenn Shop-Sync aktiv -->
        <div class="db-bar-row" style="margin-bottom:12px;opacity:.45">
            <div class="db-bar-label" style="width:160px;font-size:13px">🌐 Online-Kanäle</div>
            <div class="db-bar-track" style="height:16px;background:repeating-linear-gradient(45deg,#f1f5f9,#f1f5f9 4px,#e2e8f0 4px,#e2e8f0 8px)"></div>
            <div class="db-bar-amt" style="font-size:11px;color:#94a3b8;width:120px">kommt mit Shop-Sync</div>
        </div>
    </div>

    <!-- Monatsvergleich -->
    <div class="db-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <div style="font-weight:700;font-size:13px;color:#1e3a5f">
                <?= $aktuellerMonat ?> vs. <?= $vormonatName ?>
            </div>
            <?= trendChip($trendMonat) ?>
        </div>
        <?php $maxMon2 = max(1, $kasseUmsatzMonat, $kasseUmsatzVormonat); ?>
        <div style="margin-bottom:14px">
            <div class="db-mbar-row" style="grid-template-columns:90px 1fr 110px;margin-bottom:8px">
                <div class="db-mbar-name"><?= $vormonatName ?></div>
                <div style="background:#e2e8f0;border-radius:4px;height:22px;overflow:hidden">
                    <div style="width:<?= round($kasseUmsatzVormonat/$maxMon2*100) ?>%;height:100%;background:#7ec8e3;border-radius:4px"></div>
                </div>
                <div class="db-mbar-val"><?= eur($kasseUmsatzVormonat) ?></div>
            </div>
            <div class="db-mbar-row" style="grid-template-columns:90px 1fr 110px">
                <div class="db-mbar-name" style="font-weight:700;color:#1e3a5f"><?= date('F') ?></div>
                <div style="background:#e2e8f0;border-radius:4px;height:22px;overflow:hidden">
                    <div style="width:<?= round($kasseUmsatzMonat/$maxMon2*100) ?>%;height:100%;background:#1e3a5f;border-radius:4px"></div>
                </div>
                <div class="db-mbar-val"><?= eur($kasseUmsatzMonat) ?></div>
            </div>
        </div>
        <div style="font-size:11px;color:#94a3b8;margin-top:6px">
            Basis: Kassenbons + erfasste Aufträge &nbsp;·&nbsp; Online-Kanäle kommen mit Shop-Sync
        </div>
    </div>

</div><!-- /db-grid-mid -->

<!-- ── UNTERE REIHE: Offene Aufträge + Forderungen Aging ───────────────────── -->
<div class="db-grid-bot">

    <!-- Offene Aufträge -->
    <div class="db-card" style="padding:0">
        <div style="padding:14px 16px 10px;display:flex;justify-content:space-between;align-items:center">
            <div style="font-weight:700;font-size:13px;color:#1e3a5f">Offene Aufträge</div>
            <a href="<?= BASE_PATH ?>/auftraege/liste.php" style="font-size:11px;color:#2563eb">alle <?= $auftraegeOffen ?> →</a>
        </div>
        <table class="db-table">
            <thead>
                <tr>
                    <th>Auftrag</th>
                    <th>Kunde</th>
                    <th style="text-align:right">Betrag</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($letzteAuftraege as $a):
                $zStatus = match($a['zahlungsstatus']) {
                    'bezahlt'      => ['label' => 'bezahlt',    'class' => 'db-chip-green'],
                    'teilbezahlt'  => ['label' => 'teilbez.',   'class' => 'db-chip-amber'],
                    'ausstehend'   => ['label' => 'offen',      'class' => 'db-chip-red'],
                    'storniert'    => ['label' => 'storniert',  'class' => 'db-chip-gray'],
                    default        => ['label' => $a['zahlungsstatus'], 'class' => 'db-chip-gray'],
                };
            ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/auftraege/detail.php?id=<?= $a['id'] ?? '' ?>" style="color:#2563eb;font-weight:600;text-decoration:none">
                    <?= htmlspecialchars($a['auftrag_nr']) ?>
                </a></td>
                <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?php if ($a['kanal'] === 'kasse'): ?>
                        <span style="color:#94a3b8">🛒 Kasse</span>
                    <?php else: ?>
                        <?= htmlspecialchars(kundenName($a['kunden_snapshot'])) ?>
                    <?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:600"><?= eur((float)$a['bruttobetrag']) ?></td>
                <td><span class="db-chip <?= $zStatus['class'] ?>"><?= $zStatus['label'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($letzteAuftraege)): ?>
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Keine offenen Aufträge</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Kunden-Rechnungen Aging -->
    <div class="db-card" style="padding:0">
        <div style="padding:14px 16px 10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">
            <div style="font-weight:700;font-size:13px;color:#1e3a5f">Offene Kundenrechnungen</div>
            <div style="display:flex;gap:6px">
                <?php if ($forderungenUeberfaellig30 > 0): ?>
                <span class="db-chip db-chip-red">&gt;30 Tage: <?= $forderungenUeberfaellig30 ?></span>
                <?php endif; ?>
                <a href="<?= BASE_PATH ?>/auftraege/liste.php?zahlung=ausstehend" style="font-size:11px;color:#2563eb;line-height:20px">alle (<?= $forderungenAnzahl ?>) →</a>
            </div>
        </div>
        <table class="db-table">
            <thead>
                <tr>
                    <th>Kunde</th>
                    <th style="text-align:right">Betrag</th>
                    <th style="text-align:center">Tage</th>
                    <th>Aging</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($forderungenRows as $f):
                $tage = (int)$f['tage_ueberfaellig'];
                if ($tage > 30) {
                    $agingFarbe = '#dc2626'; $agingPct = min(100, 33 + $tage);
                } elseif ($tage > 14) {
                    $agingFarbe = '#f59e0b'; $agingPct = 50;
                } elseif ($tage > 0) {
                    $agingFarbe = '#fb923c'; $agingPct = 25;
                } else {
                    $agingFarbe = '#86efac'; $agingPct = 10;
                }
                $offen = (float)$f['bruttobetrag'] - (float)$f['bezahlt'];
            ?>
            <tr>
                <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars(kundenName($f['kunden_snapshot'], 'Unbekannt')) ?>
                </td>
                <td style="text-align:right;font-weight:600"><?= eur($offen) ?></td>
                <td style="text-align:center;font-weight:700;color:<?= $tage > 30 ? '#dc2626' : ($tage > 14 ? '#f59e0b' : '#64748b') ?>">
                    <?= $tage > 0 ? $tage : '&lt;1' ?>
                </td>
                <td>
                    <span class="db-aging-track">
                        <span class="db-aging-fill" style="width:<?= min(100,$agingPct) ?>%;background:<?= $agingFarbe ?>"></span>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($forderungenRows)): ?>
            <tr><td colspan="4" style="text-align:center;color:#16a34a;padding:20px">✓ Alle Rechnungen beglichen</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- /db-grid-bot -->

<!-- ── LOG BAR (Platzhalter — wird mit Shell-Footer ausgebaut) ─────────────── -->
<div class="db-log-bar">
    <span style="color:#7ec8e3;font-weight:700">▶ LOG</span>
    <?php foreach ($logEintraege as $log):
        $aktion = $log['aktion'] ?? '';
        $farbe  = str_contains($aktion, 'loeschen') ? '#f87171'
                : (str_contains($aktion, 'anlegen')  ? '#4ade80' : '#60a5fa');
    ?>
    <span>
        <span style="color:<?= $farbe ?>">●</span>
        <span style="color:#64748b"><?= date('H:i', strtotime($log['erstellt_am'])) ?></span>
        <span style="color:#a0aec0"><?= htmlspecialchars($aktion) ?></span>
        <span style="color:#64748b">(<?= htmlspecialchars($log['benutzer'] ?? '—') ?>)</span>
    </span>
    <?php endforeach; ?>
    <?php if (empty($logEintraege)): ?>
    <span style="color:#4a5568">Keine Aktivitäten</span>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/shell_bottom.php'; ?>
