<?php

/**
 * Sortiment der erfundenen „Garnstube Muster GmbH“. Alle Hersteller, Lieferanten und Produkte
 * sind ausgedacht (keine echten Marken). Preise brutto.
 *
 * Neue Artikelart für eine neue ERP-Funktion? Hier ergänzen.
 */
class DemoKatalog
{
    /** Hersteller = zugleich Lieferant (+ ein Großhändler) */
    public const HERSTELLER = [
        'bergschaf'  => ['name' => 'Bergschaf Garne',        'land' => 'AT', 'ort' => 'Innsbruck', 'plz' => '6020', 'strasse' => 'Wollweg 3'],
        'fjord'      => ['name' => 'Fjordwolle Muster',      'land' => 'DE', 'ort' => 'Kiel',      'plz' => '24103', 'strasse' => 'Hafenstraße 12'],
        'naturfaser' => ['name' => 'Naturfaser Beispiel',    'land' => 'DE', 'ort' => 'Augsburg',  'plz' => '86150', 'strasse' => 'Spinnereiweg 8'],
        'stoffhaus'  => ['name' => 'Stoffhaus Muster',       'land' => 'DE', 'ort' => 'Krefeld',   'plz' => '47798', 'strasse' => 'Webergasse 21'],
        'nadelwerk'  => ['name' => 'Nadelmanufaktur Muster', 'land' => 'DE', 'ort' => 'Iserlohn',  'plz' => '58636', 'strasse' => 'Drahtzug 5'],
        'verlag'     => ['name' => 'Maschenverlag Muster',   'land' => 'AT', 'ort' => 'Graz',      'plz' => '8010', 'strasse' => 'Lesegasse 7'],
    ];

    public const KATEGORIEN = [
        'Garne'                 => ['Sockenwolle', 'Merino & Schurwolle', 'Baumwolle & Leinen', 'Alpaka & Mohair'],
        'Stoffe'                => ['Baumwollstoffe', 'Jersey', 'Musselin'],
        'Kurzwaren'             => ['Stricknadeln', 'Häkelnadeln', 'Knöpfe', 'Zubehör'],
        'Bücher & Anleitungen'  => [],
    ];

    /** Farbpaletten (Name => Hex) */
    private const F_SOCKE = ['Schiefer' => '#5b6770', 'Beere' => '#8e2a4f', 'Moos' => '#5d7240', 'Ozean' => '#1f5f8b', 'Senf' => '#c9a227',
                             'Ziegel' => '#a64b2a', 'Natur' => '#e8dcc4', 'Anthrazit' => '#33363a', 'Himmel' => '#7fb2d9', 'Rosé' => '#d99aa5',
                             'Tanne' => '#2f5244', 'Herbstwald' => '#7a4b25'];
    private const F_MERINO = ['Wollweiß' => '#f2ece0', 'Sand' => '#d8c3a0', 'Camel' => '#b0804f', 'Rost' => '#a44a2c', 'Kirsche' => '#9c1f33',
                              'Altrosa' => '#c98b8f', 'Lavendel' => '#9a8fc4', 'Petrol' => '#1e6670', 'Salbei' => '#9cae8c', 'Flaschengrün' => '#24513d',
                              'Marine' => '#1f2f52', 'Jeans' => '#4a6c95', 'Grau meliert' => '#9a9a9a', 'Graphit' => '#4b4f54', 'Schwarz' => '#1c1c1c'];
    private const F_BAUMWOLLE = ['Weiß' => '#fafafa', 'Creme' => '#f1e6cc', 'Zitrone' => '#efd84a', 'Mandarine' => '#f08a35', 'Koralle' => '#ec6b5c',
                                 'Pink' => '#e0508b', 'Flieder' => '#b99ad4', 'Türkis' => '#2bb3b1', 'Mint' => '#a6dcc5', 'Kornblume' => '#5d7fd1',
                                 'Grasgrün' => '#6caa3c', 'Taupe' => '#8f7f72'];
    private const F_PASTELL = ['Wolke' => '#f4f1ec', 'Puder' => '#efcfd2', 'Babyblau' => '#c7dcef', 'Mint' => '#cfeadf', 'Vanille' => '#f5e7b8',
                               'Pfirsich' => '#f6c9ab', 'Flieder' => '#d9cbe8', 'Hellgrau' => '#d2d4d6'];
    private const F_STOFF = ['Rot' => '#b8312f', 'Dunkelblau' => '#22345c', 'Senf' => '#d1a533', 'Altrosa' => '#d4a0a4', 'Salbei' => '#95a989',
                             'Ocker' => '#c4862f', 'Grau' => '#8a8d91', 'Petrol' => '#226c73'];

    /**
     * Garne: Vater + Farb-Kinder. gr = Knäuelgewicht in g, m = Lauflänge, vk/ek = Preise brutto/netto je Knäuel,
     * nadel, mp = Maschenprobe-Merkmalwert, gg = Garngruppe, lb = Lauflänge-Bereich, pflege = Symbole
     */
    public static function garne(): array
    {
        return [
            ['nr' => 'GS-1001', 'name' => 'Alpin Sockengarn 4-fach', 'kat' => 'Sockenwolle', 'herst' => 'bergschaf', 'gr' => 100, 'm' => 420, 'vk' => 9.90, 'ek' => 4.40,
             'zus' => '75 % Schurwolle (superwash), 25 % Polyamid', 'quali' => 'Schurwolle', 'nadel' => '2,5–3', 'mp' => 'mp_30', 'gg' => 'gg_a', 'lb' => 'lb_450',
             'pflege' => ['wasch40', 'nicht_bleichen', 'liegend'], 'farben' => self::F_SOCKE, 'shops' => [1, 3], 'renner' => 3],
            ['nr' => 'GS-1002', 'name' => 'Alpin Sockengarn 6-fach', 'kat' => 'Sockenwolle', 'herst' => 'bergschaf', 'gr' => 150, 'm' => 375, 'vk' => 14.90, 'ek' => 6.70,
             'zus' => '75 % Schurwolle (superwash), 25 % Polyamid', 'quali' => 'Schurwolle', 'nadel' => '3–3,5', 'mp' => 'mp_25', 'gg' => 'gg_b', 'lb' => 'lb_250',
             'pflege' => ['wasch40', 'nicht_bleichen', 'liegend'], 'farben' => array_slice(self::F_SOCKE, 0, 7, true), 'shops' => [1, 3], 'renner' => 2],
            ['nr' => 'GS-1003', 'name' => 'Socken Ringel Color', 'kat' => 'Sockenwolle', 'herst' => 'fjord', 'gr' => 100, 'm' => 420, 'vk' => 11.50, 'ek' => 5.10,
             'zus' => '75 % Schurwolle (superwash), 25 % Polyamid', 'quali' => 'Schurwolle', 'nadel' => '2,5–3', 'mp' => 'mp_30', 'gg' => 'gg_a', 'lb' => 'lb_450',
             'pflege' => ['wasch40', 'nicht_bleichen', 'liegend'], 'farben' => ['Fjord' => '#3f6f9e', 'Polarlicht' => '#4fa37c', 'Herbst' => '#c26a2e', 'Beeren' => '#8a2d5c', 'Regenbogen' => '#d9483b', 'Nordsee' => '#566d7e'],
             'muster' => 'ringel', 'shops' => [1, 3], 'renner' => 2],
            ['nr' => 'GS-1101', 'name' => 'Merino Soft', 'kat' => 'Merino & Schurwolle', 'herst' => 'fjord', 'gr' => 50, 'm' => 125, 'vk' => 6.50, 'ek' => 2.90,
             'zus' => '100 % Merinowolle extrafein (superwash)', 'quali' => 'Merino', 'nadel' => '3,5–4', 'mp' => 'mp_25', 'gg' => 'gg_c', 'lb' => 'lb_350',
             'pflege' => ['wasch30_schon', 'nicht_bleichen', 'liegend', 'kein_trockner'], 'farben' => self::F_MERINO, 'shops' => [1], 'renner' => 3],
            ['nr' => 'GS-1102', 'name' => 'Merino Chunky', 'kat' => 'Merino & Schurwolle', 'herst' => 'fjord', 'gr' => 100, 'm' => 80, 'vk' => 9.90, 'ek' => 4.50,
             'zus' => '100 % Merinowolle', 'quali' => 'Merino', 'nadel' => '8–10', 'mp' => 'mp_10', 'gg' => 'gg_f', 'lb' => 'lb_150',
             'pflege' => ['handwaesche', 'nicht_bleichen', 'liegend'], 'farben' => array_slice(self::F_MERINO, 0, 8, true), 'shops' => [1], 'renner' => 1],
            ['nr' => 'GS-1103', 'name' => 'Bio-Merino GOTS', 'kat' => 'Merino & Schurwolle', 'herst' => 'naturfaser', 'gr' => 50, 'm' => 135, 'vk' => 7.90, 'ek' => 3.70,
             'zus' => '100 % Merinowolle aus kontrolliert biologischer Tierhaltung', 'quali' => 'Merino', 'nadel' => '3,5–4', 'mp' => 'mp_25', 'gg' => 'gg_c', 'lb' => 'lb_350',
             'pflege' => ['handwaesche', 'nicht_bleichen', 'liegend'], 'farben' => ['Natur' => '#ece3cf', 'Schafgrau' => '#9b958b', 'Walnuss' => '#6b4a32', 'Indigo' => '#2e3e75', 'Krapp' => '#a33b2d', 'Reseda' => '#c2b74a', 'Schwarzschaf' => '#3a3532', 'Waid' => '#6d8fa8'],
             'auszeichnung' => ['bio', 'gots'], 'shops' => [1], 'renner' => 1],
            ['nr' => 'GS-1104', 'name' => 'Highland Tweed', 'kat' => 'Merino & Schurwolle', 'herst' => 'bergschaf', 'gr' => 50, 'm' => 110, 'vk' => 5.90, 'ek' => 2.60,
             'zus' => '90 % Schurwolle, 10 % Viskose (Noppen)', 'quali' => 'Schurwolle', 'nadel' => '4–4,5', 'mp' => 'mp_20', 'gg' => 'gg_c', 'lb' => 'lb_250',
             'pflege' => ['handwaesche', 'nicht_bleichen', 'liegend'], 'farben' => ['Heide' => '#7d5a74', 'Moor' => '#4d4a37', 'Granit' => '#6f7173', 'Distel' => '#6d6aa0', 'Torf' => '#6a4a33', 'Loden' => '#4f5d3a'],
             'muster' => 'tweed', 'shops' => [1], 'renner' => 1],
            ['nr' => 'GS-1201', 'name' => 'Sommerbaumwolle', 'kat' => 'Baumwolle & Leinen', 'herst' => 'naturfaser', 'gr' => 50, 'm' => 115, 'vk' => 4.90, 'ek' => 2.10,
             'zus' => '100 % Baumwolle (mercerisiert)', 'quali' => 'Baumwolle', 'nadel' => '3–3,5', 'mp' => 'mp_25', 'gg' => 'gg_b', 'lb' => 'lb_250',
             'pflege' => ['wasch30', 'nicht_bleichen', 'liegend'], 'farben' => self::F_BAUMWOLLE, 'shops' => [1], 'renner' => 2, 'saison' => 'sommer'],
            ['nr' => 'GS-1202', 'name' => 'Leinen Natur', 'kat' => 'Baumwolle & Leinen', 'herst' => 'naturfaser', 'gr' => 50, 'm' => 140, 'vk' => 6.90, 'ek' => 3.20,
             'zus' => '60 % Leinen, 40 % Baumwolle', 'quali' => 'Leinen', 'nadel' => '3,5', 'mp' => 'mp_25', 'gg' => 'gg_b', 'lb' => 'lb_350',
             'pflege' => ['wasch30', 'nicht_bleichen', 'liegend'], 'farben' => ['Flachs' => '#cdbb98', 'Weiß' => '#f4f2ec', 'Salbei' => '#a3b09a', 'Terrakotta' => '#b8603e', 'Taubenblau' => '#7b8fa6', 'Sand' => '#d9c7a6'],
             'shops' => [1], 'renner' => 1, 'saison' => 'sommer'],
            ['nr' => 'GS-1301', 'name' => 'Alpaka Wolke', 'kat' => 'Alpaka & Mohair', 'herst' => 'bergschaf', 'gr' => 25, 'm' => 200, 'vk' => 8.90, 'ek' => 4.10,
             'zus' => '70 % Superkid-Mohair, 30 % Seide', 'quali' => 'Mohair', 'nadel' => '3,5–5', 'mp' => 'mp_20', 'gg' => 'gg_c', 'lb' => 'lb_mehr',
             'pflege' => ['handwaesche', 'nicht_bleichen', 'liegend', 'kein_trockner'], 'farben' => array_slice(self::F_PASTELL, 0, 6, true) + ['Nebel' => '#a9adb3', 'Pflaume' => '#6e3b5c'],
             'auszeichnung' => ['handgefaerbt'], 'muster' => 'flausch', 'shops' => [1], 'renner' => 2],
            ['nr' => 'GS-1302', 'name' => 'Baby Alpaka', 'kat' => 'Alpaka & Mohair', 'herst' => 'fjord', 'gr' => 50, 'm' => 150, 'vk' => 7.50, 'ek' => 3.40,
             'zus' => '100 % Baby-Alpaka', 'quali' => 'Alpaka', 'nadel' => '3,5–4', 'mp' => 'mp_25', 'gg' => 'gg_c', 'lb' => 'lb_350',
             'pflege' => ['handwaesche', 'nicht_bleichen', 'liegend'], 'farben' => self::F_PASTELL, 'shops' => [1], 'renner' => 1],
        ];
    }

    /** Stoffe = Meterware: 1 Stück = 1 cm, vk/ek je Meter (VK volle Euro, Preis wird je cm gespeichert), Mindestabnahme 20 cm über den Artikeltyp */
    public static function stoffe(): array
    {
        return [
            ['nr' => 'ST-2001', 'name' => 'Baumwollstoff Uni', 'kat' => 'Baumwollstoffe', 'herst' => 'stoffhaus', 'vk' => 13.00, 'ek' => 5.20, 'breite' => 145, 'gsm' => 140,
             'zus' => '100 % Baumwolle, Öko-Tex Standard 100', 'muster' => 'uni', 'farben' => self::F_STOFF, 'renner' => 2],
            ['nr' => 'ST-2002', 'name' => 'Baumwollstoff Tupfen', 'kat' => 'Baumwollstoffe', 'herst' => 'stoffhaus', 'vk' => 15.00, 'ek' => 6.10, 'breite' => 145, 'gsm' => 140,
             'zus' => '100 % Baumwolle, Öko-Tex Standard 100', 'muster' => 'tupfen', 'farben' => array_slice(self::F_STOFF, 0, 6, true), 'renner' => 2],
            ['nr' => 'ST-2003', 'name' => 'Baumwollstoff Vichy-Karo', 'kat' => 'Baumwollstoffe', 'herst' => 'stoffhaus', 'vk' => 14.00, 'ek' => 5.70, 'breite' => 150, 'gsm' => 130,
             'zus' => '100 % Baumwolle', 'muster' => 'karo', 'farben' => ['Rot' => '#b8312f', 'Dunkelblau' => '#22345c', 'Grün' => '#3f7a46', 'Schwarz' => '#2a2a2a', 'Rosa' => '#dd8ea0'], 'renner' => 1],
            ['nr' => 'ST-2101', 'name' => 'Jersey Streifen', 'kat' => 'Jersey', 'herst' => 'stoffhaus', 'vk' => 17.00, 'ek' => 7.20, 'breite' => 160, 'gsm' => 220,
             'zus' => '95 % Baumwolle, 5 % Elasthan', 'muster' => 'streifen', 'farben' => ['Marine/Weiß' => '#22345c', 'Rot/Weiß' => '#b8312f', 'Senf/Weiß' => '#d1a533', 'Grau/Weiß' => '#8a8d91', 'Mint/Weiß' => '#7ec4a5'], 'renner' => 2],
            ['nr' => 'ST-2102', 'name' => 'Bio-Jersey Uni', 'kat' => 'Jersey', 'herst' => 'naturfaser', 'vk' => 19.00, 'ek' => 8.30, 'breite' => 160, 'gsm' => 230,
             'zus' => '95 % Bio-Baumwolle, 5 % Elasthan, GOTS', 'muster' => 'uni', 'farben' => ['Natur' => '#ece3cf', 'Petrol' => '#226c73', 'Ocker' => '#c4862f', 'Bordeaux' => '#6e2234', 'Anthrazit' => '#3a3d41', 'Salbei' => '#95a989'], 'renner' => 1],
            ['nr' => 'ST-2201', 'name' => 'Musselin Double Gauze', 'kat' => 'Musselin', 'herst' => 'stoffhaus', 'vk' => 16.00, 'ek' => 6.60, 'breite' => 135, 'gsm' => 120,
             'zus' => '100 % Baumwolle, doppellagig', 'muster' => 'krepp', 'farben' => array_slice(self::F_PASTELL, 0, 6, true) + ['Senf' => '#d1a533', 'Altrosa' => '#d4a0a4'], 'renner' => 2],
        ];
    }

    /** Kurzwaren: Väter mit Stärken (Nadeln) oder Einzelartikel */
    public static function kurzwaren(): array
    {
        return [
            ['nr' => 'KW-3001', 'name' => 'Rundstricknadel Bambus 80 cm', 'kat' => 'Stricknadeln', 'herst' => 'nadelwerk', 'bild' => 'nadel', 'farbe' => '#c8a26b',
             'staerken' => ['2,5 mm' => 6.90, '3,0 mm' => 6.90, '3,5 mm' => 6.90, '4,0 mm' => 7.20, '4,5 mm' => 7.20, '5,0 mm' => 7.50, '6,0 mm' => 7.90, '8,0 mm' => 8.50], 'ek_faktor' => 0.45, 'renner' => 3],
            ['nr' => 'KW-3002', 'name' => 'Nadelspiel Metall 20 cm', 'kat' => 'Stricknadeln', 'herst' => 'nadelwerk', 'bild' => 'nadel', 'farbe' => '#b9bec4',
             'staerken' => ['2,0 mm' => 8.90, '2,5 mm' => 8.90, '3,0 mm' => 9.50, '3,5 mm' => 9.50], 'ek_faktor' => 0.45, 'renner' => 2],
            ['nr' => 'KW-3101', 'name' => 'Häkelnadel Softgriff', 'kat' => 'Häkelnadeln', 'herst' => 'nadelwerk', 'bild' => 'haekel', 'farbe' => '#6aa6c8',
             'staerken' => ['2,5 mm' => 4.50, '3,0 mm' => 4.50, '3,5 mm' => 4.50, '4,0 mm' => 4.90, '5,0 mm' => 4.90, '6,0 mm' => 5.50], 'ek_faktor' => 0.45, 'renner' => 2],
            ['nr' => 'KW-3201', 'name' => 'Holzknöpfe Natur 18 mm (6 Stk.)', 'kat' => 'Knöpfe', 'herst' => 'nadelwerk', 'bild' => 'knopf', 'farbe' => '#b58552', 'vk' => 3.90, 'ek' => 1.40, 'renner' => 1],
            ['nr' => 'KW-3202', 'name' => 'Kokosknöpfe 15 mm (8 Stk.)', 'kat' => 'Knöpfe', 'herst' => 'nadelwerk', 'bild' => 'knopf', 'farbe' => '#6b4a32', 'vk' => 4.50, 'ek' => 1.70, 'renner' => 1],
            ['nr' => 'KW-3301', 'name' => 'Maschenmarkierer bunt (20 Stk.)', 'kat' => 'Zubehör', 'herst' => 'nadelwerk', 'bild' => 'marker', 'farbe' => '#e0508b', 'vk' => 3.50, 'ek' => 1.10, 'renner' => 2],
            ['nr' => 'KW-3302', 'name' => 'Wollnadeln stumpf (3 Stk.)', 'kat' => 'Zubehör', 'herst' => 'nadelwerk', 'bild' => 'nadel', 'farbe' => '#b9bec4', 'vk' => 2.90, 'ek' => 0.90, 'renner' => 1],
            ['nr' => 'KW-3303', 'name' => 'Maßband 150 cm', 'kat' => 'Zubehör', 'herst' => 'nadelwerk', 'bild' => 'massband', 'farbe' => '#efd84a', 'vk' => 2.50, 'ek' => 0.80, 'renner' => 1],
            ['nr' => 'KW-3304', 'name' => 'Projekttasche Leinen', 'kat' => 'Zubehör', 'herst' => 'stoffhaus', 'bild' => 'tasche', 'farbe' => '#cdbb98', 'vk' => 19.90, 'ek' => 8.50, 'renner' => 1],
        ];
    }

    /** Bücher: 10 % USt */
    public static function buecher(): array
    {
        return [
            ['nr' => 'BU-4001', 'name' => 'Socken stricken – Das Grundlagenbuch', 'farbe' => '#8e2a4f', 'vk' => 19.90, 'ek' => 12.00, 'renner' => 2],
            ['nr' => 'BU-4002', 'name' => 'Nordische Pullover', 'farbe' => '#1f5f8b', 'vk' => 26.00, 'ek' => 15.60, 'renner' => 1],
            ['nr' => 'BU-4003', 'name' => 'Nähen mit Musselin', 'farbe' => '#d4a0a4', 'vk' => 22.00, 'ek' => 13.20, 'renner' => 1],
            ['nr' => 'BU-4004', 'name' => 'Häkeln für Einsteiger', 'farbe' => '#6caa3c', 'vk' => 16.90, 'ek' => 10.10, 'renner' => 1],
        ];
    }
}
