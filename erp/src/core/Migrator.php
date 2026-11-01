<?php

/**
 * Migrator — spielt die offenen SQL-Migrationen (database/migrations/*.sql) ein und vermerkt sie in
 * schema_migrations. Genutzt von database/migrate.php (Kommandozeile) und vom Update (Browser).
 *
 * Achtung: MySQL committet DDL (CREATE/ALTER) sofort — bricht eine Datei mittendrin ab, ist sie halb
 * angewendet und wird NICHT vermerkt. Deshalb macht das Update vorher immer eine Datenbank-Sicherung.
 */
class Migrator
{
    private PDO $pdo;
    private string $ordner;

    public function __construct(?string $ordner = null)
    {
        $this->ordner = $ordner ?? __DIR__ . '/../../database/migrations';
        $c = require __DIR__ . '/../../config/database.php';
        $this->pdo = new PDO("mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}",
            $c['username'], $c['password'] ?? '', [
                PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]);
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                dateiname      VARCHAR(255) NOT NULL,
                angewendet_am  DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_dateiname (dateiname)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    /** @return string[] Dateinamen, die schon vermerkt sind */
    public function angewendet(): array
    {
        return $this->pdo->query("SELECT dateiname FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return string[] volle Pfade der offenen Migrationen, sortiert */
    public function offen(): array
    {
        $dateien = glob($this->ordner . '/*.sql');
        sort($dateien);
        $set = array_flip($this->angewendet());
        return array_values(array_filter($dateien, fn($p) => !isset($set[basename($p)])));
    }

    /** Alle offenen als „bereits angewendet“ vermerken, ohne sie auszuführen (nur für Datenbanken mit nachweislich diesem Stand) */
    public function alsAngewendetVermerken(): array
    {
        $st = $this->pdo->prepare("INSERT INTO schema_migrations (dateiname, angewendet_am) VALUES (?, NOW())");
        $namen = [];
        foreach ($this->offen() as $p) { $st->execute([basename($p)]); $namen[] = basename($p); }
        return $namen;
    }

    /**
     * Offene Migrationen der Reihe nach ausführen; stoppt beim ersten Fehler.
     * @param callable|null $fortschritt fn(string $dateiname, ?string $fehler) — für die Ausgabe
     * @return array{erfolg: bool, angewendet: string[], fehler: ?string, fehler_datei: ?string}
     */
    public function ausfuehren(?callable $fortschritt = null): array
    {
        $st = $this->pdo->prepare("INSERT INTO schema_migrations (dateiname, angewendet_am) VALUES (?, NOW())");
        $ok = [];
        foreach ($this->offen() as $pfad) {
            $name = basename($pfad);
            try {
                $this->pdo->exec(file_get_contents($pfad));
                $st->execute([$name]);
                $ok[] = $name;
                if ($fortschritt) $fortschritt($name, null);
            } catch (PDOException $e) {
                if ($fortschritt) $fortschritt($name, $e->getMessage());
                return ['erfolg' => false, 'angewendet' => $ok, 'fehler' => $e->getMessage(), 'fehler_datei' => $name];
            }
        }
        return ['erfolg' => true, 'angewendet' => $ok, 'fehler' => null, 'fehler_datei' => null];
    }
}
