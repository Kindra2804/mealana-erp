<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/hersteller/HerstellerService.php';

$service        = new HerstellerService();
$zeigeInaktive  = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$hersteller     = $service->findAll($zeigeInaktive);

$pageTitle        = 'Hersteller';
$activeModule     = 'hersteller';
$actionBarContent = <<<HTML
    <button class="btn btn-primary btn-sm" onclick="modalNeuOeffnen()">+ Neuer Hersteller</button>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';

$EU_ISO = ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK'];

function gpsr_chip(array $h, array $eu): string {
    $land = strtoupper($h['land'] ?? '');
    if (!$land) return '<span style="font-size:11px;color:#aaa">– kein Land</span>';
    if (in_array($land, $eu)) return '<span class="chip chip-aktiv" style="font-size:11px">✓ EU</span>';
    if (!empty($h['reo_name'])) return '<span class="chip chip-aktiv" style="font-size:11px">✓ REO</span>';
    return '<span class="chip" style="font-size:11px;background:#fff3e0;color:#e67e22;border:1px solid #e67e22">⚠ REO fehlt</span>';
}

$laender = [
    'EU-Länder' => [
        'AT'=>'Österreich','BE'=>'Belgien','BG'=>'Bulgarien','CY'=>'Zypern','CZ'=>'Tschechien',
        'DE'=>'Deutschland','DK'=>'Dänemark','EE'=>'Estland','ES'=>'Spanien','FI'=>'Finnland',
        'FR'=>'Frankreich','GR'=>'Griechenland','HR'=>'Kroatien','HU'=>'Ungarn','IE'=>'Irland',
        'IT'=>'Italien','LT'=>'Litauen','LU'=>'Luxemburg','LV'=>'Lettland','MT'=>'Malta',
        'NL'=>'Niederlande','PL'=>'Polen','PT'=>'Portugal','RO'=>'Rumänien','SE'=>'Schweden',
        'SI'=>'Slowenien','SK'=>'Slowakei',
    ],
    'Europa (nicht EU)' => [
        'CH'=>'Schweiz','NO'=>'Norwegen','GB'=>'Vereinigtes Königreich','IS'=>'Island',
        'LI'=>'Liechtenstein','TR'=>'Türkei','UA'=>'Ukraine','RS'=>'Serbien',
        'ME'=>'Montenegro','BA'=>'Bosnien & Herzegowina','AL'=>'Albanien',
    ],
    'Rest der Welt' => [
        'US'=>'USA','CA'=>'Kanada','AU'=>'Australien','NZ'=>'Neuseeland',
        'JP'=>'Japan','CN'=>'China','KR'=>'Südkorea','IN'=>'Indien',
        'PE'=>'Peru','AR'=>'Argentinien','UY'=>'Uruguay','ZA'=>'Südafrika','BR'=>'Brasilien',
    ],
];
?>

<div class="card">
    <div class="filter-bar" style="margin-bottom:16px">
        <div style="display:flex;gap:8px;align-items:center">
            <?php if ($zeigeInaktive): ?>
                <a href="liste.php" class="btn btn-secondary btn-sm">Nur aktive anzeigen</a>
            <?php else: ?>
                <a href="liste.php?inaktive=1" class="btn btn-secondary btn-sm">Auch deaktivierte</a>
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--color-text-muted);margin-top:6px">
            <?= count($hersteller) ?> Hersteller
        </div>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:60px">LOGO</th>
                <th>NAME</th>
                <th style="width:60px">LAND</th>
                <th style="width:120px">GPSR</th>
                <th style="width:80px">STATUS</th>
                <th style="width:70px"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($hersteller as $h): ?>
            <tr <?= $h['aktiv'] ? '' : 'style="opacity:.55"' ?>>
                <td>
                    <?php if ($h['logo_pfad']): ?>
                        <img src="/mealana/img/hersteller/<?= htmlspecialchars($h['logo_pfad']) ?>"
                             style="width:44px;height:44px;object-fit:contain;border-radius:4px">
                    <?php else: ?>
                        <div style="width:44px;height:44px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:20px">🏭</div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= htmlspecialchars($h['name']) ?></strong>
                    <?php if ($h['handelsname']): ?>
                        <br><span style="font-size:11px;color:var(--color-text-muted)"><?= htmlspecialchars($h['handelsname']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($h['land'] ?? '') ?></td>
                <td><?= gpsr_chip($h, $EU_ISO) ?></td>
                <td>
                    <?php if ($h['aktiv']): ?>
                        <span class="chip chip-aktiv">Aktiv</span>
                    <?php else: ?>
                        <span class="chip chip-inaktiv">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="modalBearbeiten(<?= $h['id'] ?>)" title="Bearbeiten">✏️</button>
                    <a href="loeschen.php?id=<?= $h['id'] ?>"
                       onclick="return confirm('Hersteller «<?= htmlspecialchars($h['name']) ?>» wirklich deaktivieren?')"
                       title="Deaktivieren" style="text-decoration:none;margin-left:2px">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($hersteller)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:32px">
                Noch keine Hersteller angelegt.
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Modal ──────────────────────────────────────────────────── -->
<div id="h-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:40px 16px">
    <div style="background:#fff;border-radius:8px;width:100%;max-width:560px;box-shadow:0 4px 32px rgba(0,0,0,.2);margin:auto">

        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #eee">
            <div id="h-modal-titel" style="font-weight:700;font-size:15px;color:var(--color-nav)">Neuer Hersteller</div>
            <button onclick="modalSchliessen()" style="background:none;border:none;cursor:pointer;font-size:18px;color:#999;line-height:1">✕</button>
        </div>

        <form id="h-form" enctype="multipart/form-data" style="padding:20px">
            <input type="hidden" name="id" id="h-id">

            <!-- Grunddaten -->
            <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);letter-spacing:.06em;margin-bottom:10px">GRUNDDATEN</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Name *</label>
                    <input type="text" name="name" id="h-name" class="erp-input" style="width:100%" placeholder="z.B. Drops Design">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Handelsmarke</label>
                    <input type="text" name="handelsname" id="h-handelsname" class="erp-input" style="width:100%" placeholder="z.B. Garnstudio AS">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Land *</label>
                    <select name="land" id="h-land" class="erp-select" style="width:100%" onchange="updateReoSichtbarkeit()">
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($laender as $gruppe => $iso): ?>
                            <optgroup label="<?= htmlspecialchars($gruppe) ?>">
                            <?php foreach ($iso as $code => $name): ?>
                                <option value="<?= $code ?>"><?= $code ?> – <?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">E-Mail (GPSR Pflicht)</label>
                    <input type="email" name="email" id="h-email" class="erp-input" style="width:100%" placeholder="kontakt@hersteller.com">
                </div>
            </div>
            <div style="margin-bottom:16px">
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Website</label>
                <input type="text" name="webseite" id="h-webseite" class="erp-input" style="width:100%" placeholder="www.hersteller.com">
            </div>

            <!-- Adresse -->
            <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);letter-spacing:.06em;margin-bottom:10px">ADRESSE (GPSR Pflicht)</div>
            <div style="margin-bottom:10px">
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Straße</label>
                <input type="text" name="strasse" id="h-strasse" class="erp-input" style="width:100%" placeholder="Musterstraße 1">
            </div>
            <div style="display:grid;grid-template-columns:120px 1fr;gap:10px;margin-bottom:16px">
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">PLZ</label>
                    <input type="text" name="plz" id="h-plz" class="erp-input" style="width:100%">
                </div>
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Ort</label>
                    <input type="text" name="ort" id="h-ort" class="erp-input" style="width:100%">
                </div>
            </div>

            <!-- REO -->
            <div id="h-reo-section" style="display:none;background:#fff8f0;border:1px solid #ffe0b2;border-radius:6px;padding:14px;margin-bottom:16px">
                <div style="font-size:11px;font-weight:700;color:#e67e22;letter-spacing:.06em;margin-bottom:10px">
                    ⚠ REO – EU-VERANTWORTLICHER (Pflicht für Nicht-EU-Hersteller)
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div style="grid-column:1/-1">
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Name / Firmenname *</label>
                        <input type="text" name="reo_name" id="h-reo-name" class="erp-input" style="width:100%" placeholder="z.B. Drops Deutschland GmbH">
                    </div>
                    <div style="grid-column:1/-1">
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Straße *</label>
                        <input type="text" name="reo_strasse" id="h-reo-strasse" class="erp-input" style="width:100%">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">PLZ *</label>
                        <input type="text" name="reo_plz" id="h-reo-plz" class="erp-input" style="width:100%">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Ort *</label>
                        <input type="text" name="reo_ort" id="h-reo-ort" class="erp-input" style="width:100%">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Land (ISO) *</label>
                        <input type="text" name="reo_land" id="h-reo-land" class="erp-input" style="width:100%" maxlength="2" placeholder="z.B. DE">
                    </div>
                    <div>
                        <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">E-Mail *</label>
                        <input type="email" name="reo_email" id="h-reo-email" class="erp-input" style="width:100%">
                    </div>
                </div>
            </div>

            <!-- Logo & Sonstiges -->
            <div style="font-size:11px;font-weight:700;color:var(--color-text-muted);letter-spacing:.06em;margin-bottom:10px">LOGO & SONSTIGES</div>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
                <img id="h-logo-vorschau" src="" alt="" style="display:none;width:60px;height:60px;object-fit:contain;border:1px solid #eee;border-radius:4px">
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Logo hochladen</label>
                    <input type="file" name="logo" id="h-logo" accept="image/*" onchange="logoVorschau(this)" class="erp-input">
                    <div style="font-size:11px;color:#aaa;margin-top:2px">JPG/PNG/WebP, max 200×200px (wird automatisch skaliert)</div>
                </div>
            </div>
            <div style="margin-bottom:10px">
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Notizen</label>
                <textarea name="notizen" id="h-notizen" class="erp-input" style="width:100%;height:60px;resize:vertical"></textarea>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                <input type="checkbox" name="aktiv" id="h-aktiv" value="1">
                <label for="h-aktiv" style="font-size:13px;cursor:pointer">Aktiv</label>
            </div>
        </form>

        <div style="padding:12px 20px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
            <div id="h-fehler" style="font-size:12px;color:var(--color-danger);max-width:360px"></div>
            <div style="display:flex;gap:8px">
                <button onclick="modalSchliessen()" class="btn btn-secondary btn-sm">Abbrechen</button>
                <button onclick="modalSpeichern()" id="h-speichern-btn" class="btn btn-primary btn-sm">Speichern</button>
            </div>
        </div>
    </div>
</div>

<script>window.HERSTELLER_EU_ISO = <?= $service->getEuLaenderJson() ?>;
window.HERSTELLER_DATEN = <?= json_encode($hersteller, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/mealana/js/hersteller_liste.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
