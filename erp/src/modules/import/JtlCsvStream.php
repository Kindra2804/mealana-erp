<?php

/**
 * JtlCsvStream – liest einen JTL-CSV-Export abschnittsweise ab einer Byte-Stelle
 * (Gegenstück zu JtlCsvReader, der die ganze Datei in den Speicher lädt).
 *
 * Gleiche Robustheit wie JtlCsvReader: UTF-8-BOM, Windows-1252 oder UTF-8, Semikolon oder Tab,
 * leere Endspalte. Die Kodierung wird an den ersten 256 KB erkannt; umgewandelt wird je Feld
 * (Trennzeichen und Anführungszeichen sind in beiden Kodierungen dieselben Bytes).
 *
 * Aufträge: alle Positionszeilen eines Auftrags stehen im JTL-Export direkt hintereinander
 * (geprüft an 39.202 Aufträgen) -- leseGruppen() schneidet deshalb nur zwischen zwei Aufträgen.
 */
class JtlCsvStream
{
    /** @var resource */
    private $f;
    private string $trenner;
    private bool $cp1252;
    private array $kopf;
    private int $datenStart;
    private int $groesse;

    public function __construct(string $pfad)
    {
        $f = @fopen($pfad, 'rb');
        if (!$f) throw new RuntimeException('Datei lässt sich nicht öffnen.');
        $this->f = $f;
        $this->groesse = (int) filesize($pfad);

        $probe = (string) fread($f, 262144);
        $bom = str_starts_with($probe, "\xEF\xBB\xBF") ? 3 : 0;
        $probe = substr($probe, $bom);
        if (strlen($probe) === 262144 - $bom) {           // angeschnittenes Zeichen am Ende weglassen
            $nl = strrpos($probe, "\n");
            if ($nl !== false) $probe = substr($probe, 0, $nl);
        }
        $this->cp1252 = !mb_check_encoding($probe, 'UTF-8');
        $ersteZeile = strtok($probe, "\n");
        $this->trenner = str_contains((string) $ersteZeile, "\t") ? "\t" : ';';

        fseek($f, $bom);
        $kopf = fgetcsv($f, 0, $this->trenner, '"', '');
        if (!$kopf) throw new RuntimeException('Die Datei hat keine Kopfzeile.');
        $this->kopf = array_map(fn($s) => trim($this->text((string) $s)), $kopf);
        $this->datenStart = (int) ftell($f);
    }

    public function __destruct()
    {
        if (is_resource($this->f)) fclose($this->f);
    }

    public function groesse(): int { return $this->groesse; }
    public function kopf(): array { return $this->kopf; }

    /**
     * Bis zu $anzahl Zeilen ab $position (0 = Anfang der Daten).
     * @return array{0: array[], 1: int, 2: bool} [Zeilen, neue Position, Ende erreicht]
     */
    public function leseZeilen(int $position, int $anzahl): array
    {
        fseek($this->f, max($position, $this->datenStart));
        $zeilen = [];
        while (count($zeilen) < $anzahl && ($z = $this->naechste()) !== null) {
            if ($z !== false) $zeilen[] = $z;
        }
        return [$zeilen, (int) ftell($this->f), feof($this->f)];
    }

    /**
     * Bis zu $anzahl ganze Gruppen (aufeinanderfolgende Zeilen mit gleichem Wert in $spalte) ab $position.
     * @return array{0: array<string,array[]>, 1: int, 2: bool} [Wert => Zeilen, neue Position, Ende erreicht]
     */
    public function leseGruppen(int $position, int $anzahl, string $spalte): array
    {
        fseek($this->f, max($position, $this->datenStart));
        $gruppen = [];
        $letzte = null;
        while (true) {
            $vorher = (int) ftell($this->f);
            $z = $this->naechste();
            if ($z === null) return [$gruppen, $vorher, true];
            if ($z === false) continue;
            $wert = trim($z[$spalte] ?? '');
            if ($wert === '') continue;
            if ($wert !== $letzte) {
                if (count($gruppen) >= $anzahl) {      // nächster Auftrag beginnt -> hier schneiden
                    fseek($this->f, $vorher);
                    return [$gruppen, $vorher, false];
                }
                $letzte = $wert;
            }
            $gruppen[$wert][] = $z;
        }
    }

    /** Nächste Zeile als Spalte => Wert; false = Leerzeile, null = Dateiende */
    private function naechste(): array|false|null
    {
        $roh = fgetcsv($this->f, 0, $this->trenner, '"', '');
        if ($roh === false) return null;
        if (count(array_filter($roh, fn($v) => $v !== null && $v !== '')) === 0) return false;
        $row = [];
        foreach ($this->kopf as $i => $spalte) {
            if ($spalte === '') continue;   // leere Endspalte durch das Semikolon am Zeilenende
            $row[$spalte] = isset($roh[$i]) ? trim($this->text($roh[$i])) : '';
        }
        return $row;
    }

    private function text(string $s): string
    {
        // Zeilenumbrüche in Feldern (z.B. Anmerkung) wie JtlCsvReader auf \n vereinheitlichen
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return $this->cp1252 ? mb_convert_encoding($s, 'UTF-8', 'Windows-1252') : $s;
    }
}
