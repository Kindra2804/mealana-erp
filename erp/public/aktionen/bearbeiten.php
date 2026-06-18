<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/aktionen/AktionenService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$service  = new AktionenService();
$artikelService = new ArtikelService();
$id       = (int)($_GET['id'] ?? 0);
$aktion   = $id ? $service->findById($id) : null;
$istNeu   = ($aktion === null && $id === 0);

if ($id && !$aktion) {
    header('Location: /mealana/aktionen/liste.php');
    exit;
}

$aktionsKategorien = $service->getAktionsKategorienFuerAuswahl();
$kategorienBaum = $artikelService->getKategorienBaum();

$kundengruppen  = $service->getAlleKundengruppen();
$standardKgId   = 1;
foreach ($kundengruppen as $kg) {
    if ($kg['ist_standard']) {
        $standardKgId = $kg['id'];
        break;
    }
}

$pageTitle    = $istNeu ? 'Neue Aktion' : 'Aktion: ' . htmlspecialchars($aktion['name']);
$activeModule = 'artikel';
$actionBarContent = $istNeu ? '' : match ($aktion['status']) {
    'entwurf', 'geplant' =>
    '<button onclick="aktionStarten()" class="btn btn-primary btn-sm">▶ Aktion starten</button>',
    'aktiv' =>
    '<span style="color:#107c10;font-weight:600;font-size:13px">⏰ Aktiv</span>'
        . '&nbsp;&nbsp;<button onclick="aktionStoppen()" class="btn btn-secondary btn-sm">⏸ Stoppen</button>',
    'abgelaufen' =>
    '<span style="color:#999;font-size:13px">✗ Abgelaufen</span>',
    default => ''
};

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($istNeu): ?>
    <!-- ── Neu-Formular ─────────────────────────────────────────────────── -->
    <div class="card" style="max-width:560px">
        <div style="padding:var(--space-md)">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:var(--space-md);color:var(--color-nav)">Neue Aktion anlegen</h2>
            <form method="POST" action="/mealana/aktionen/aktion_speichern.php">
                <input type="hidden" name="modus" value="neu">
                <div class="form-row">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="erp-input" style="width:100%" autofocus
                        placeholder="z.B. DROPS Frühjahr 2026">
                </div>
                <div class="form-row">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="beschreibung" class="erp-input" rows="2" style="width:100%;resize:vertical"
                        placeholder="Optionale Notiz zur Aktion"></textarea>
                </div>
                <div id="neu-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px"></div>
                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:var(--space-md)">
                    <a href="/mealana/aktionen/liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
                    <button type="submit" class="btn btn-primary btn-sm">Anlegen & weiter</button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ── Bearbeiten-Seite ──────────────────────────────────────────────── -->

    <div id="banner" style="display:none;padding:8px 16px;border-radius:4px;margin-bottom:12px;font-size:13px"></div>

    <!-- Stammdaten -->
    <div class="card" style="margin-bottom:12px">
        <div style="padding:var(--space-md)">
            <div style="display:flex;gap:12px;align-items:flex-start">
                <div style="flex:1">
                    <div class="form-row" style="margin-bottom:8px">
                        <label class="form-label">Name *</label>
                        <input type="text" id="akt-name" class="erp-input" style="width:100%"
                            value="<?= htmlspecialchars($aktion['name']) ?>">
                    </div>
                    <div class="form-row" style="margin-bottom:0">
                        <label class="form-label">Beschreibung</label>
                        <textarea id="akt-beschreibung" class="erp-input" rows="2" style="width:100%;resize:vertical"><?= htmlspecialchars($aktion['beschreibung'] ?? '') ?></textarea>
                    </div>
                </div>
                <button onclick="stammdatenSpeichern()" class="btn btn-primary btn-sm" style="margin-top:22px;white-space:nowrap">
                    Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Aktions-Kategorien -->
    <div class="card" style="margin-bottom:12px">
        <div style="padding:var(--space-md)">
            <div style="font-size:13px;font-weight:700;color:var(--color-nav);margin-bottom:10px">
                AKTIONS-KATEGORIEN
            </div>
            <table class="erp-table" id="kat-tabelle" style="margin-bottom:10px">
                <thead>
                    <tr>
                        <th>KATEGORIE</th>
                        <th>AKTIV VON</th>
                        <th>AKTIV BIS</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="kat-tbody">
                    <?php foreach ($aktion['kategorien'] as $k): ?>
                        <tr data-akid="<?= $k['ak_id'] ?>">
                            <td style="font-weight:500"><?= htmlspecialchars($k['kat_name']) ?></td>
                            <td style="font-size:12px"><?= date('d.m.Y', strtotime($k['gueltig_ab'])) ?></td>
                            <td style="font-size:12px"><?= date('d.m.Y', strtotime($k['gueltig_bis'])) ?></td>
                            <td style="text-align:right">
                                <button onclick="katEntfernen(<?= $k['ak_id'] ?>, this)"
                                    class="btn btn-danger btn-xs">✕ Entfernen</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($aktion['kategorien'])): ?>
                        <tr id="kat-leer-zeile">
                            <td colspan="4" style="color:var(--color-text-muted);font-size:12px">Noch keine Kategorien zugewiesen</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Hinzufügen-Zeile -->
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <select id="kat-neu-id" class="erp-select" style="min-width:180px">
                    <option value="">— Kategorie wählen —</option>
                    <?php foreach ($aktionsKategorien as $ak): ?>
                        <option value="<?= $ak['id'] ?>"><?= htmlspecialchars($ak['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" id="kat-neu-von" class="erp-input" style="width:140px" title="Aktiv von">
                <span style="font-size:12px;color:var(--color-text-muted)">bis</span>
                <input type="date" id="kat-neu-bis" class="erp-input" style="width:140px" title="Aktiv bis">
                <button onclick="katHinzufuegen()" class="btn btn-secondary btn-sm">+ Hinzufügen</button>
            </div>
            <div id="kat-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:4px"></div>

            <?php if (empty($aktionsKategorien)): ?>
                <p style="font-size:12px;color:var(--color-text-muted);margin-top:8px">
                    ⚠ Keine Aktions-Kategorien vorhanden. Bitte zuerst in der
                    <a href="/mealana/artikel/kategorien_verwalten.php">Kategorieverwaltung</a>
                    Kategorien als Aktions-Kategorie markieren.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preiseingabe -->
    <div class="card" id="preise-card" <?= empty($aktion['kategorien']) ? 'style="display:none"' : '' ?>>
        <div style="padding:var(--space-md)">
            <div style="font-size:13px;font-weight:700;color:var(--color-nav);margin-bottom:10px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
                PREISEINGABE
                <div style="display:flex;gap:8px;align-items:center;font-weight:400">
                    <label style="font-size:12px;color:var(--color-text-muted)">Kategorie:</label>
                    <select id="preis-kat-id" class="erp-select" style="min-width:160px" onchange="artikelLaden()">
                        <option value="">— wählen —</option>
                        <?php foreach ($aktion['kategorien'] as $k): ?>
                            <option value="<?= $k['kategorie_id'] ?>"><?= htmlspecialchars($k['kat_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label style="font-size:12px;color:var(--color-text-muted)">Kundengruppe:</label>
                    <select id="preis-kg-id" class="erp-select" style="min-width:150px" onchange="artikelLaden()">
                        <?php foreach ($kundengruppen as $kg): ?>
                            <option value="<?= $kg['id'] ?>" <?= $kg['id'] == $standardKgId ? 'selected' : '' ?>>
                                <?= $kg['ist_standard'] ? '⭐ ' : '' ?><?= htmlspecialchars($kg['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="preis-inhalt">
                <p style="color:var(--color-text-muted);font-size:12px">
                    Bitte Kategorie wählen um Artikel anzuzeigen.
                </p>
            </div>
        </div>
    </div>

<?php endif; // !$istNeu 
?>

<script>
    var AKTION_ID = <?= $id ?>;

    // ── Banner ───────────────────────────────────────────────────────────
    function zeigeBanner(text, erfolg) {
        var b = document.getElementById('banner');
        if (!b) return;
        b.textContent = text;
        b.style.display = 'block';
        b.style.background = erfolg ? '#e8f5e8' : '#fde8e8';
        b.style.color = erfolg ? '#107c10' : '#c42b1c';
        b.style.border = erfolg ? '1px solid #c3e6c3' : '1px solid #f5b8b8';
        setTimeout(function() {
            b.style.display = 'none';
        }, 3000);
    }

    // ── Stammdaten ───────────────────────────────────────────────────────
    function stammdatenSpeichern() {
        var name = document.getElementById('akt-name').value.trim();
        var beschreibung = document.getElementById('akt-beschreibung').value.trim();
        if (!name) {
            zeigeBanner('Name ist Pflichtfeld', false);
            return;
        }
        fetch('/mealana/aktionen/aktion_speichern.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'modus=update&id=' + AKTION_ID + '&name=' + encodeURIComponent(name) + '&beschreibung=' + encodeURIComponent(beschreibung)
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                zeigeBanner(d.erfolg ? 'Gespeichert' : (d.fehler || 'Fehler'), d.erfolg);
            });
    }

    // ── Aktion starten/stoppen ───────────────────────────────────────────
    function aktionStarten() {
        fetch('/mealana/aktionen/aktion_starten_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + AKTION_ID + '&aktion=starten'
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.erfolg) {
                    window.location.reload();
                } else {
                    zeigeBanner(d.fehler || 'Fehler', false);
                }
            });
    }

    function aktionStoppen() {
        fetch('/mealana/aktionen/aktion_starten_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + AKTION_ID + '&aktion=stoppen'
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.erfolg) {
                    window.location.reload();
                } else {
                    zeigeBanner(d.fehler || 'Fehler', false);
                }
            });
    }

    // ── Kategorien ───────────────────────────────────────────────────────
    function katHinzufuegen() {
        var katId = document.getElementById('kat-neu-id').value;
        var von = document.getElementById('kat-neu-von').value;
        var bis = document.getElementById('kat-neu-bis').value;
        var fehler = document.getElementById('kat-fehler');
        if (!katId) {
            fehler.textContent = 'Bitte Kategorie wählen';
            return;
        }
        if (!von || !bis) {
            fehler.textContent = 'Von und Bis sind Pflichtfelder';
            return;
        }
        fehler.textContent = '';

        fetch('/mealana/aktionen/aktion_kategorie_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'aktion=hinzufuegen&aktion_id=' + AKTION_ID + '&kategorie_id=' + katId + '&von=' + von + '&bis=' + bis
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.erfolg) {
                    window.location.reload();
                } else {
                    fehler.textContent = d.fehler || 'Fehler beim Hinzufügen';
                }
            });
    }

    function katEntfernen(akId, btn) {
        if (!confirm('Kategorie-Zuweisung entfernen?')) return;
        btn.disabled = true;
        fetch('/mealana/aktionen/aktion_kategorie_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'aktion=entfernen&ak_id=' + akId
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.erfolg) {
                    window.location.reload();
                } else {
                    btn.disabled = false;
                    zeigeBanner(d.fehler || 'Fehler', false);
                }
            });
    }

    // ── Preiseingabe ─────────────────────────────────────────────────────
    var preiseDaten = [];

    function artikelLaden() {
        var katId = document.getElementById('preis-kat-id').value;
        var kgId = document.getElementById('preis-kg-id').value;
        var inhalt = document.getElementById('preis-inhalt');
        if (!katId) {
            inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Bitte Kategorie wählen um Artikel anzuzeigen.</p>';
            return;
        }
        inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Lade Artikel…</p>';

        fetch('/mealana/aktionen/aktion_artikel_laden.php?aktion_id=' + AKTION_ID + '&kategorie_id=' + katId + '&kg_id=' + kgId)
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (!d.erfolg) {
                    inhalt.innerHTML = '<p style="color:var(--color-danger)">' + (d.fehler || 'Fehler') + '</p>';
                    return;
                }
                preiseDaten = d.artikel;
                renderPreisTabelle(d.artikel, kgId);
            });
    }

    function renderPreisTabelle(artikel, kgId) {
        var inhalt = document.getElementById('preis-inhalt');
        if (!artikel.length) {
            inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Keine Vater-Artikel in dieser Kategorie.</p>';
            return;
        }

        var html = '<table class="erp-table" style="margin-bottom:10px"><thead><tr>' +
            '<th style="width:35%">ARTIKEL</th>';

        // Spalten aus Sub-Achsen ableiten (union aller Sub-Achsen-Namen)
        var achsenNamen = {};
        artikel.forEach(function(a) {
            if (a.sub_achsen && a.sub_achsen.length) {
                a.sub_achsen.forEach(function(sa) {
                    achsenNamen[sa.achse_id] = sa.achse_name;
                });
            }
        });
        var hatSubAchsen = Object.keys(achsenNamen).length > 0;

        if (hatSubAchsen) {
            Object.values(achsenNamen).forEach(function(n) {
                html += '<th style="text-align:right;white-space:nowrap">' + escH(n) + '</th>';
            });
        } else {
            html += '<th style="text-align:right">AKTIONSPREIS</th>';
        }
        html += '</tr></thead><tbody>';

        artikel.forEach(function(a) {
            var normalVkText = a.normal_vk ? ' <span style="font-size:11px;color:var(--color-text-muted);font-weight:400">Normal: ' + parseFloat(a.normal_vk).toFixed(2).replace('.', ',') + ' €</span>' : '';
            html += '<tr>';
            html += '<td style="font-weight:500">' + escH(a.name) + normalVkText +
                '<div style="font-size:11px;color:var(--color-text-muted)">' + escH(a.artikelnummer) + '</div></td>';

            if (hatSubAchsen) {
                Object.keys(achsenNamen).forEach(function(achseId) {
                    var sa = (a.sub_achsen || []).find(function(s) {
                        return String(s.achse_id) === String(achseId);
                    });
                    var brt = sa && sa.preis ? parseFloat(sa.preis.brutto_vk).toFixed(2) : '';
                    var net = sa && sa.preis ? parseFloat(sa.preis.netto_vk).toFixed(4) : '';
                    var inputKey = 'preis_' + a.id + '_' + achseId;
                    html += '<td style="text-align:right">' +
                        preisZelle(inputKey, a.id, achseId, a.mwst_satz || 20, brt, net) +
                        '</td>';
                });
            } else {
                var brt = a.preis ? parseFloat(a.preis.brutto_vk).toFixed(2) : '';
                var net = a.preis ? parseFloat(a.preis.netto_vk).toFixed(4) : '';
                html += '<td style="text-align:right">' +
                    preisZelle('preis_' + a.id + '_0', a.id, '', a.mwst_satz || 20, brt, net) +
                    '</td>';
            }
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<div style="display:flex;align-items:center;gap:12px;justify-content:flex-end">' +
            '<span id="preis-save-info" style="font-size:12px;color:var(--color-text-muted)"></span>' +
            '<button onclick="preiseSpeichern(' + kgId + ')" class="btn btn-primary btn-sm">Preise speichern</button>' +
            '</div>';

        inhalt.innerHTML = html;
    }

    function preiseSpeichern(kgId) {
        var inputs = document.querySelectorAll('.preis-input');
        var preise = [];
        inputs.forEach(function(inp) {
            var nettoEl = document.querySelector('[data-brutto-id="' + inp.id + '"]');
            preise.push({
                artikel_id: inp.dataset.artikelId,
                sub_achse_id: inp.dataset.subAchseId,
                brutto_vk: inp.value,
                netto_vk: nettoEl ? nettoEl.value : '',
                mwst_satz: inp.dataset.mwst
            });
        });

        var info = document.getElementById('preis-save-info');
        info.textContent = 'Speichern…';

        fetch('/mealana/aktionen/aktion_preise_speichern.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    aktion_id: AKTION_ID,
                    kg_id: kgId,
                    preise: preise
                })
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.erfolg) {
                    info.textContent = d.gespeichert + ' Preis(e) gespeichert' + (d.geloescht ? ', ' + d.geloescht + ' gelöscht' : '');
                    info.style.color = 'var(--color-success, #107c10)';
                } else {
                    info.textContent = d.fehler || 'Fehler';
                    info.style.color = 'var(--color-danger)';
                }
            });
    }

    function preisZelle(id, artikelId, subAchseId, mwst, brt, net) {
        return '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px">' +
            '<input type="text" class="erp-input preis-input" style="width:90px;text-align:right"' +
            ' data-artikel-id="' + artikelId + '" data-sub-achse-id="' + subAchseId + '" data-mwst="' + mwst + '"' +
            ' id="' + id + '" value="' + brt + '" placeholder="brutto €"' +
            ' oninput="syncNetto(this)">' +
            '<input type="text" class="erp-input preis-netto-input" style="width:90px;text-align:right;font-size:11px;color:var(--color-text-muted)"' +
            ' data-brutto-id="' + id + '" data-mwst="' + mwst + '"' +
            ' value="' + net + '" placeholder="netto €"' +
            ' oninput="syncBrutto(this)">' +
            '</div>';
    }

    function syncNetto(bruttoInput) {
        var brutto = parseFloat(String(bruttoInput.value).replace(',', '.'));
        var mwst = parseFloat(bruttoInput.dataset.mwst) || 20;
        var id = bruttoInput.id;
        var nettoEl = document.querySelector('[data-brutto-id="' + id + '"]');
        if (nettoEl) {
            nettoEl.value = isNaN(brutto) || brutto === 0 ? '' : (brutto / (1 + mwst / 100)).toFixed(4);
        }
    }

    function syncBrutto(nettoInput) {
        var netto = parseFloat(String(nettoInput.value).replace(',', '.'));
        var mwst = parseFloat(nettoInput.dataset.mwst) || 20;
        var bruttoEl = document.getElementById(nettoInput.dataset.bruttoId);
        if (bruttoEl) {
            bruttoEl.value = isNaN(netto) || netto === 0 ? '' : (netto * (1 + mwst / 100)).toFixed(2);
        }
    }

    function escH(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>