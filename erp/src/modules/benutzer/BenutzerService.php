<?php

require_once __DIR__ . '/BenutzerRepository.php';
require_once __DIR__ . '/PasswortResetService.php';

/**
 * BenutzerService – Geschäftslogik für Benutzerverwaltung (Admin-Bereich)
 *
 * Beim Anlegen wählt der Admin zwischen zwei Passwort-Wegen:
 *   "link"   → Platzhalter-Hash (niemand kennt ihn) + Passwort-Setzen-Link per Mail
 *   "direkt" → Admin gibt das Passwort direkt im Formular ein
 *
 * "system" (Jarvis, siehe Migration 105) ist als Benutzername gesperrt —
 * reserviert für automatische Log-Einträge, kein echter Login-Kandidat.
 */
class BenutzerService
{
    private BenutzerRepository $repo;
    private PasswortResetService $resetService;

    public function __construct()
    {
        $this->repo         = new BenutzerRepository();
        $this->resetService = new PasswortResetService();
    }

    public function getAll(): array
    {
        return $this->repo->findAll();
    }

    public function getById(int $id): array|false
    {
        return $this->repo->findById($id);
    }

    public function getAlleRollen(): array
    {
        return $this->repo->findAlleRollen();
    }

    /**
     * Legt einen neuen Benutzer an.
     * Validiert: Formularname/Username/E-Mail/Rolle Pflicht, Username nicht "system"
     * und nicht bereits vergeben, bei Direkt-Passwort zusätzlich Mindestlänge + Bestätigung.
     */
    public function save(array $data): array
    {
        $fehler = $this->validiere($data);
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $modus = $data['passwort_modus'] ?? 'link';
        $passwortHash = $modus === 'direkt'
            ? password_hash($data['passwort'], PASSWORD_BCRYPT)
            // Platzhalter: zufälliger, niemandem bekannter Hash — Login ist erst nach
            // Setzen über den Mail-Link möglich.
            : password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

        $id = $this->repo->insert([
            'username'     => trim($data['username']),
            'passwort_hash'=> $passwortHash,
            'vorname'      => trim($data['vorname'] ?? ''),
            'nachname'     => trim($data['nachname'] ?? ''),
            'formularname' => trim($data['formularname']),
            'email'        => trim($data['email']),
            'aktiv'        => 1,
        ]);

        $this->repo->setRolle($id, (int)$data['rolle_id']);

        if ($modus === 'link') {
            $this->resetService->sendeSetzenLink($id, trim($data['email']));
        }

        return ['erfolg' => true, 'id' => $id];
    }

    /** Aktualisiert Stammdaten + Rolle eines bestehenden Benutzers. Username/Passwort ändern sich hier nicht. */
    public function aktualisiere(array $data): array
    {
        if (empty($data['id'])) {
            return ['erfolg' => false, 'fehler' => ['ID fehlt.']];
        }

        $fehler = [];
        if (empty(trim($data['formularname'] ?? ''))) {
            $fehler[] = 'Formularname ist Pflichtfeld.';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'E-Mail-Adresse ist ungültig.';
        }
        $rollen = array_column($this->repo->findAlleRollen(), 'id');
        if (empty($data['rolle_id']) || !in_array((int)$data['rolle_id'], $rollen, true)) {
            $fehler[] = 'Rolle ist ungültig.';
        }
        if (!empty($fehler)) {
            return ['erfolg' => false, 'fehler' => $fehler];
        }

        $this->repo->update([
            'id'           => (int)$data['id'],
            'vorname'      => trim($data['vorname']  ?? ''),
            'nachname'     => trim($data['nachname'] ?? ''),
            'formularname' => trim($data['formularname']),
            'email'        => trim($data['email']),
            'aktiv'        => !empty($data['aktiv']) ? 1 : 0,
        ]);
        $this->repo->setRolle((int)$data['id'], (int)$data['rolle_id']);

        return ['erfolg' => true];
    }

    /** Setzt den Aktiv-Status eines Benutzers. */
    public function setAktiv(int $id, int $aktiv): array
    {
        $this->repo->setAktiv($id, $aktiv);
        return ['erfolg' => true];
    }

    /** Sendet einem bestehenden Benutzer erneut einen Passwort-Setzen-Link (z.B. wenn der erste Link abgelaufen ist). */
    public function sendeLinkErneut(int $id): array
    {
        $benutzer = $this->repo->findById($id);
        if (!$benutzer) {
            return ['erfolg' => false, 'fehler' => ['Benutzer nicht gefunden.']];
        }
        if (empty($benutzer['email'])) {
            return ['erfolg' => false, 'fehler' => ['Benutzer hat keine E-Mail-Adresse hinterlegt.']];
        }

        $this->resetService->sendeSetzenLink((int)$benutzer['id'], $benutzer['email']);
        return ['erfolg' => true];
    }

    /** Validiert Formularname, Username (Pflicht, nicht "system", eindeutig), E-Mail, Rolle, ggf. Passwort. */
    private function validiere(array $data): array
    {
        $fehler = [];

        if (empty(trim($data['formularname'] ?? ''))) {
            $fehler[] = 'Formularname ist Pflichtfeld.';
        }

        $username = trim($data['username'] ?? '');
        if ($username === '') {
            $fehler[] = 'Benutzername ist Pflichtfeld.';
        } elseif (strtolower($username) === 'system') {
            $fehler[] = 'Der Benutzername "system" ist reserviert (automatische Log-Einträge).';
        } elseif ($this->repo->usernameExistiert($username)) {
            $fehler[] = 'Benutzername ist bereits vergeben.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler[] = 'E-Mail-Adresse ist ungültig.';
        } elseif ($this->repo->findByEmail(trim($data['email']))) {
            $fehler[] = 'Diese E-Mail-Adresse wird bereits verwendet.';
        }

        $rollen = array_column($this->repo->findAlleRollen(), 'id');
        if (empty($data['rolle_id']) || !in_array((int)$data['rolle_id'], $rollen, true)) {
            $fehler[] = 'Rolle ist ungültig.';
        }

        $modus = $data['passwort_modus'] ?? 'link';
        if ($modus === 'direkt') {
            $pw  = $data['passwort']      ?? '';
            $wdh = $data['passwort_wdh']  ?? '';
            if (strlen($pw) < 8) {
                $fehler[] = 'Passwort muss mindestens 8 Zeichen haben.';
            } elseif ($pw !== $wdh) {
                $fehler[] = 'Passwort und Bestätigung stimmen nicht überein.';
            }
        }

        return $fehler;
    }
}
