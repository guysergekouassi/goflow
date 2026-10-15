<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Settings\Imports\ClientSitesImport;
use Modules\Settings\Models\WorkLocation;

/**
 * Import en masse des sites clients ouverts au pointage GPS à distance :
 * modèle à remplir → aperçu ligne par ligne → confirmation.
 */
class WorkLocationImportController extends Controller
{
    private const SESSION = 'import_sites_clients';

    /**
     * Modèle CSV (point-virgule, UTF-8 avec BOM : s'ouvre directement dans Excel).
     */
    public function template()
    {
        $this->company();

        $lignes = [
            ['Nom du client', 'Adresse', 'Ville', 'Coordonnées GPS', 'Rayon (m)', 'Arrivée début', 'Arrivée fin', 'Départ début', 'Départ fin', 'Succursale', 'Service'],
            ['FORMEN Coiffure', 'Près de la pharmacie Aurore, 2 Plateaux Vallons', 'Abidjan', '5.3615428, -3.9911651', '150', '08:00', '09:30', '17:00', '18:30', '', ''],
            ['Client exemple', 'Boulevard Latrille, Cocody', 'Abidjan', '', '100', '', '', '', '', '', ''],
        ];

        return response()->streamDownload(function () use ($lignes) {
            $fichier = fopen('php://output', 'w');
            fprintf($fichier, chr(0xEF) . chr(0xBB) . chr(0xBF));
            foreach ($lignes as $ligne) {
                fputcsv($fichier, $ligne, ';');
            }
            fclose($fichier);
        }, 'modele_import_sites_clients.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lit le fichier et affiche le résultat de la vérification, sans rien enregistrer.
     */
    public function preview(Request $request)
    {
        $company = $this->company();

        $request->validate([
            'file' => 'required|file|max:5120|mimes:xlsx,xls,csv,txt,ods',
        ], [
            'file.required' => 'Choisissez un fichier à importer.',
            'file.mimes' => 'Le fichier doit être au format Excel (.xlsx, .xls), CSV ou ODS.',
        ]);

        $import = new ClientSitesImport(
            $company->id,
            $company->branches()->pluck('name', 'id'),
            \App\Models\Department::where('company_id', $company->id)->pluck('name', 'id'),
            $company->country ?: 'Côte d\'Ivoire',
        );

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            \Log::error('Import sites clients : lecture impossible', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Fichier illisible : utilisez le modèle proposé.');
        }

        if ($import->lignes === []) {
            return redirect()->back()->with('error', 'Le fichier ne contient aucune ligne à importer.');
        }

        $importables = collect($import->lignes)->whereIn('statut', ['ok', 'avertissement'])->pluck('data')->values()->all();
        $jeton = (string) Str::uuid();

        session([self::SESSION => ['jeton' => $jeton, 'lignes' => $importables]]);

        return redirect()->route('company.settings.work-locations.index')
            ->with('import_apercu', ['jeton' => $jeton, 'lignes' => $import->lignes]);
    }

    /**
     * Crée les sites clients validés à l'aperçu.
     */
    public function confirm(Request $request)
    {
        $company = $this->company();
        $attente = session(self::SESSION);

        if (!$attente || $attente['jeton'] !== $request->input('jeton')) {
            return redirect()->route('company.settings.work-locations.index')
                ->with('error', 'Import expiré : déposez de nouveau le fichier.');
        }

        $existants = WorkLocation::where('company_id', $company->id)->pluck('name')
            ->map(fn ($n) => Str::lower(trim($n)))->all();

        $crees = DB::transaction(function () use ($attente, $company, $existants) {
            $crees = 0;
            foreach ($attente['lignes'] as $data) {
                // Créé entre-temps (double clic, autre onglet) : ne pas le dupliquer
                if (in_array(Str::lower($data['name']), $existants, true)) {
                    continue;
                }

                WorkLocation::create($data + [
                    'type' => 'client_site',
                    'country' => $company->country,
                    'allow_remote_clock' => true,
                    'has_time_clock' => false,
                    'is_active' => true,
                    'company_id' => $company->id,
                ]);
                $crees++;
            }

            return $crees;
        });

        session()->forget(self::SESSION);

        return redirect()->route('company.settings.work-locations.index')
            ->with('success', $crees . ' site(s) client(s) importé(s), ouverts au pointage GPS à distance.');
    }

    private function company()
    {
        if (!Auth::check() || Auth::user()->type !== 'company' || !Auth::user()->company) {
            abort(403, 'Accès non autorisé');
        }

        return Auth::user()->company;
    }
}
