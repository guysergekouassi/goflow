<?php

namespace Modules\Settings\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Modules\Settings\Models\WorkLocation;

/**
 * Lecture et vérification d'un fichier de sites clients (pointage GPS à
 * distance), avant tout enregistrement.
 *
 * Chaque ligne ressort avec un statut :
 *   - ok / avertissement : importable (avertissement = position déduite de
 *     l'adresse, donc approximative) ;
 *   - doublon : un site du même nom existe déjà, la ligne est ignorée ;
 *   - erreur : non importable, les erreurs sont listées.
 */
class ClientSitesImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    /** Au-delà, saisir les coordonnées : Nominatim limite à une requête par seconde. */
    public const MAX_GEOCODAGES = 25;

    public const MAX_LIGNES = 500;

    private const HORAIRES_DEFAUT = [
        'check_in_start' => '08:00',
        'check_in_end' => '09:30',
        'check_out_start' => '17:00',
        'check_out_end' => '18:30',
    ];

    /** @var array<int, array{ligne: int, statut: string, messages: array, data: array}> */
    public array $lignes = [];

    private int $geocodages = 0;

    public function __construct(
        private int $companyId,
        private Collection $succursales,
        private Collection $services,
        private ?string $pays = null,
    ) {
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'input_encoding' => 'UTF-8'];
    }

    public function collection(Collection $rows): void
    {
        $existants = WorkLocation::where('company_id', $this->companyId)
            ->pluck('name')
            ->map(fn ($n) => Str::lower(trim($n)))
            ->all();
        $vus = [];

        foreach ($rows as $index => $row) {
            $row = $row->map(fn ($v) => is_string($v) ? trim($v) : $v);

            if ($row->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            if (count($this->lignes) >= self::MAX_LIGNES) {
                break;
            }

            $this->lignes[] = $this->analyser($row, $index + 2, $existants, $vus);
        }
    }

    private function analyser(Collection $row, int $numero, array $existants, array &$vus): array
    {
        $erreurs = [];
        $avertissements = [];

        $nom = (string) ($row['nom_du_client'] ?? $row['nom'] ?? $row['client'] ?? '');
        $adresse = (string) ($row['adresse'] ?? '');
        $ville = (string) ($row['ville'] ?? '');

        if ($nom === '') {
            $erreurs[] = 'Nom du client manquant.';
        } elseif (mb_strlen($nom) > 255) {
            $erreurs[] = 'Nom du client trop long (255 caractères maximum).';
        }

        $cle = Str::lower($nom);
        if ($nom !== '' && in_array($cle, $vus, true)) {
            $erreurs[] = 'Ce client apparaît plusieurs fois dans le fichier.';
        }
        $vus[] = $cle;

        // Coordonnées : colonne unique « lat, lng » copiée de Google Maps, ou deux colonnes
        $coords = $this->coordonnees($row);
        $saisies = trim((string) ($row['coordonnees_gps'] ?? $row['coordonnees'] ?? $row['gps'] ?? $row['latitude'] ?? '')) !== '';
        if (!$coords && $saisies) {
            // Saisie erronée : ne pas la remplacer en silence par une position approximative
            $erreurs[] = 'Coordonnées GPS invalides (format attendu : 5.3615428, -3.9911651).';
        } elseif (!$coords && ($adresse !== '' || $ville !== '')) {
            $coords = $this->geocoder(trim($adresse . ', ' . $ville, ', '));
            if ($coords) {
                $avertissements[] = 'Position déduite de l\'adresse, souvent approximative : vérifiez-la, sinon des pointages sur place pourraient être refusés.';
            } elseif ($this->geocodages > self::MAX_GEOCODAGES) {
                $erreurs[] = 'Coordonnées GPS manquantes (recherche automatique limitée à ' . self::MAX_GEOCODAGES . ' adresses par import).';
            } else {
                $erreurs[] = 'Coordonnées GPS manquantes et adresse introuvable.';
            }
        } elseif (!$coords) {
            $erreurs[] = 'Coordonnées GPS manquantes (format attendu : 5.3615428, -3.9911651).';
        }

        $rayon = $row['rayon_m'] ?? $row['rayon'] ?? null;
        if ($rayon === null || $rayon === '') {
            $rayon = 100;
        } elseif (!is_numeric($rayon) || (int) $rayon < 10 || (int) $rayon > 1000) {
            $erreurs[] = 'Rayon invalide : entre 10 et 1000 mètres.';
        }

        $horaires = [];
        foreach ([
            'check_in_start' => ['arrivee_debut', 'Arrivée début'],
            'check_in_end' => ['arrivee_fin', 'Arrivée fin'],
            'check_out_start' => ['depart_debut', 'Départ début'],
            'check_out_end' => ['depart_fin', 'Départ fin'],
        ] as $champ => [$colonne, $libelle]) {
            $valeur = $row[$colonne] ?? null;
            $heure = ($valeur === null || $valeur === '') ? self::HORAIRES_DEFAUT[$champ] : $this->heure($valeur);
            if ($heure === null) {
                $erreurs[] = $libelle . ' invalide (format HH:MM).';
            }
            $horaires[$champ] = $heure;
        }

        $branchId = $this->rattachement($row['succursale'] ?? null, $this->succursales, 'Succursale', $erreurs);
        $departmentId = $this->rattachement($row['service'] ?? $row['departement'] ?? null, $this->services, 'Service', $erreurs);

        $statut = match (true) {
            $erreurs !== [] => 'erreur',
            in_array($cle, $existants, true) => 'doublon',
            $avertissements !== [] => 'avertissement',
            default => 'ok',
        };

        if ($statut === 'doublon') {
            $avertissements = ['Un emplacement de ce nom existe déjà : ligne ignorée.'];
        }

        return [
            'ligne' => $numero,
            'statut' => $statut,
            'messages' => $statut === 'erreur' ? $erreurs : $avertissements,
            'data' => [
                'name' => $nom,
                'address' => $adresse ?: null,
                'city' => $ville ?: null,
                'latitude' => $coords[0] ?? null,
                'longitude' => $coords[1] ?? null,
                'max_distance_meters' => (int) $rayon,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
            ] + $horaires,
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function coordonnees(Collection $row): ?array
    {
        $texte = (string) ($row['coordonnees_gps'] ?? $row['coordonnees'] ?? $row['gps'] ?? '');

        // « 5.3615428, -3.9911651 » (Google Maps) ou « 5,3615428 ; -3,9911651 »
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[,;]\s*(-?\d+(?:\.\d+)?)\s*$/', $texte, $m)
            || preg_match('/^\s*(-?\d+(?:,\d+)?)\s*;\s*(-?\d+(?:,\d+)?)\s*$/', $texte, $m)) {
            [$lat, $lng] = [(float) str_replace(',', '.', $m[1]), (float) str_replace(',', '.', $m[2])];
        } elseif (isset($row['latitude'], $row['longitude']) && $row['latitude'] !== '' && $row['longitude'] !== '') {
            [$lat, $lng] = [(float) str_replace(',', '.', $row['latitude']), (float) str_replace(',', '.', $row['longitude'])];
        } else {
            return null;
        }

        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return null;
        }

        return [round($lat, 8), round($lng, 8)];
    }

    /**
     * Position approximative d'une adresse via Nominatim (comme la recherche
     * d'adresse du formulaire), dans la limite de MAX_GEOCODAGES par import.
     */
    private function geocoder(string $adresse): ?array
    {
        if (++$this->geocodages > self::MAX_GEOCODAGES) {
            return null;
        }

        if ($this->geocodages > 1) {
            usleep(1100000); // une requête par seconde au plus (règles d'usage de Nominatim)
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $this->pays ? $adresse . ', ' . $this->pays : $adresse,
            'format' => 'json',
            'limit' => 1,
            'accept-language' => 'fr',
        ]);

        $reponse = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5, 'user_agent' => 'RH-Flow/1.0 (contact@rhflow.com)'],
        ]));
        $resultat = $reponse ? (json_decode($reponse, true)[0] ?? null) : null;

        return $resultat ? [round((float) $resultat['lat'], 8), round((float) $resultat['lon'], 8)] : null;
    }

    /**
     * Heure « HH:MM », « 8h30 », « 08:00:00 » ou fraction de jour Excel (0,354…).
     */
    private function heure($valeur): ?string
    {
        if (is_numeric($valeur) && (float) $valeur >= 0 && (float) $valeur < 1) {
            $minutes = (int) round((float) $valeur * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        if (preg_match('/^(\d{1,2})\s*[:hH]\s*(\d{2})?(?::\d{2})?$/', (string) $valeur, $m)
            && (int) $m[1] < 24 && (int) ($m[2] ?? 0) < 60) {
            return sprintf('%02d:%02d', $m[1], $m[2] ?? 0);
        }

        return null;
    }

    private function rattachement($nom, Collection $liste, string $libelle, array &$erreurs): ?int
    {
        if ($nom === null || $nom === '') {
            return null;
        }

        $id = $liste->search(fn ($n) => Str::lower(trim($n)) === Str::lower((string) $nom));

        if ($id === false) {
            $erreurs[] = $libelle . ' « ' . $nom . ' » introuvable.';

            return null;
        }

        return (int) $id;
    }
}
