<?php

namespace App\Support;

/**
 * Graphiques SVG pour les PDF (dompdf ne fait pas tourner de JavaScript).
 *
 * Chaque méthode renvoie une data URI à placer dans un <img>. Les libellés et
 * pourcentages restent dans le HTML (légendes) : le SVG ne porte que les formes
 * et quelques chiffres.
 */
class SvgCharts
{
    private const POLICE = 'DejaVu Sans';
    private const VIDE = '#e4e7ee';

    /**
     * Pastille ronde colorée portant un symbole blanc (icône des cartes d'indicateurs).
     */
    public static function icone(string $symbole, string $couleur, int $taille = 24): string
    {
        $c = $taille / 2;
        $svg = sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>', $c, $c, $c, $couleur)
            . self::texte($c, $c + $taille * 0.17, $symbole, $taille * 0.5, '#ffffff', 'bold');

        return self::uri($svg, $taille, $taille);
    }

    /**
     * Anneau : parts [['valeur' => int, 'couleur' => '#hex'], …] et texte central.
     */
    public static function anneau(array $parts, string $centre, string $sousTitre, int $taille = 120): string
    {
        $c = $taille / 2;
        $svg = self::secteurs($parts, $c, $c, $c - 4, ($c - 4) * 0.58)
            . self::texte($c, $c + 2, $centre, 20, '#253e87', 'bold')
            . self::texte($c, $c + 16, $sousTitre, 8, '#8592a3');

        return self::uri($svg, $taille, $taille);
    }

    /**
     * Camembert avec la valeur de chaque part écrite dessus.
     */
    public static function camembert(array $parts, int $taille = 120): string
    {
        $c = $taille / 2;
        $r = $c - 4;
        $svg = self::secteurs($parts, $c, $c, $r, 0);

        // Valeur au milieu de chaque part assez grande pour la contenir
        $total = array_sum(array_column($parts, 'valeur'));
        $angle = -M_PI / 2;
        foreach ($parts as $part) {
            if ($total <= 0 || $part['valeur'] <= 0) {
                continue;
            }
            $ouverture = 2 * M_PI * $part['valeur'] / $total;
            if ($ouverture > 0.35) {
                $milieu = $angle + $ouverture / 2;
                $svg .= self::texte($c + $r * 0.6 * cos($milieu), $c + $r * 0.6 * sin($milieu) + 4, (string) $part['valeur'], 11, '#ffffff', 'bold');
            }
            $angle += $ouverture;
        }

        return self::uri($svg, $taille, $taille);
    }

    /**
     * Histogramme : barres [['libelle' => 'Lun. 10/08', 'valeur' => int, 'note' => ?string]].
     * Une barre avec une note (ex. « Férié ») affiche la note au lieu de la valeur.
     */
    public static function barres(array $barres, int $max, int $largeur = 230, int $hauteur = 120): string
    {
        $max = max(1, $max);
        $bas = $hauteur - 22;
        $haut = 14;
        $gauche = 18;
        $pas = ($largeur - $gauche - 4) / max(1, count($barres));
        $epaisseur = min(26, $pas * 0.6);

        // Axe et graduations : 0, moitié, maximum
        $svg = '';
        foreach ([0, (int) ceil($max / 2), $max] as $graduation) {
            $y = $bas - ($bas - $haut) * $graduation / $max;
            $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="%s" stroke-width="0.6"/>', $gauche, $y, $largeur - 4, $y, self::VIDE)
                . self::texte($gauche - 4, $y + 3, (string) $graduation, 7, '#8592a3', 'normal', 'end');
        }

        foreach (array_values($barres) as $i => $barre) {
            $x = $gauche + $pas * $i + ($pas - $epaisseur) / 2;
            $milieu = $x + $epaisseur / 2;

            if (!empty($barre['note'])) {
                $svg .= self::texte($milieu, $bas - 4, $barre['note'], 7, '#b7791f', 'bold');
            } else {
                $h = ($bas - $haut) * $barre['valeur'] / $max;
                $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="#2f6fd6"/>', $x, $bas - $h, $epaisseur, max($h, 0.8))
                    . self::texte($milieu, $bas - $h - 3, (string) $barre['valeur'], 8, '#253e87', 'bold');
            }

            foreach (explode("\n", $barre['libelle']) as $ligne => $morceau) {
                $svg .= self::texte($milieu, $bas + 9 + $ligne * 8, $morceau, 6.5, '#4b5266');
            }
        }

        return self::uri($svg, $largeur, $hauteur);
    }

    /** Parts d'un anneau (rayon intérieur > 0) ou d'un camembert (rayon intérieur 0). */
    private static function secteurs(array $parts, float $cx, float $cy, float $r, float $rInterieur): string
    {
        $parts = array_values(array_filter($parts, fn ($p) => $p['valeur'] > 0));
        $total = array_sum(array_column($parts, 'valeur'));

        if ($total <= 0) {
            return sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>', $cx, $cy, $r, self::VIDE)
                . ($rInterieur > 0 ? sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#ffffff"/>', $cx, $cy, $rInterieur) : '');
        }

        // Une seule part : un arc de 360° ne se dessine pas, on trace des cercles
        if (count($parts) === 1) {
            return sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s"/>', $cx, $cy, $r, $parts[0]['couleur'])
                . ($rInterieur > 0 ? sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#ffffff"/>', $cx, $cy, $rInterieur) : '');
        }

        $svg = '';
        $debut = -M_PI / 2;
        foreach ($parts as $part) {
            $fin = $debut + 2 * M_PI * $part['valeur'] / $total;
            $svg .= self::secteur($cx, $cy, $r, $rInterieur, $debut, $fin, $part['couleur']);
            $debut = $fin;
        }

        return $svg;
    }

    private static function secteur(float $cx, float $cy, float $r, float $ri, float $a0, float $a1, string $couleur): string
    {
        $point = fn (float $a, float $rayon) => [$cx + $rayon * cos($a), $cy + $rayon * sin($a)];
        $grand = ($a1 - $a0) > M_PI ? 1 : 0;
        [$x0, $y0] = $point($a0, $r);
        [$x1, $y1] = $point($a1, $r);

        if ($ri <= 0) {
            $d = sprintf('M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f Z', $cx, $cy, $x0, $y0, $r, $r, $grand, $x1, $y1);
        } else {
            [$x2, $y2] = $point($a1, $ri);
            [$x3, $y3] = $point($a0, $ri);
            $d = sprintf('M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
                $x0, $y0, $r, $r, $grand, $x1, $y1, $x2, $y2, $ri, $ri, $grand, $x3, $y3);
        }

        // Liseré blanc entre les parts
        return sprintf('<path d="%s" fill="%s" stroke="#ffffff" stroke-width="1"/>', $d, $couleur);
    }

    private static function texte(float $x, float $y, string $texte, float $taille, string $couleur, string $graisse = 'normal', string $ancre = 'middle'): string
    {
        return sprintf('<text x="%.1f" y="%.1f" text-anchor="%s" font-family="%s" font-size="%s" font-weight="%s" fill="%s">%s</text>',
            $x, $y, $ancre, self::POLICE, $taille, $graisse, $couleur, htmlspecialchars($texte, ENT_XML1));
    }

    private static function uri(string $contenu, int $largeur, int $hauteur): string
    {
        $svg = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s</svg>',
            $largeur, $hauteur, $largeur, $hauteur, $contenu);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
