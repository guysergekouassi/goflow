<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProvisionnementException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LotAcces;
use App\Services\ProvisionnementRhflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ouverture des comptes GPFlow depuis RHFlow (protégé par VerifyHubToken).
 *
 * POST /api/provisionnement/entreprises                 : ouvre ou lie la société
 * POST /api/provisionnement/entreprises/{id}/salaries   : transfère un lot de salariés (200 max)
 */
class ProvisionnementController extends Controller
{
    public function __construct(private ProvisionnementRhflowService $service)
    {
    }

    public function entreprise(Request $request): JsonResponse
    {
        $d = $request->validate([
            'source'            => 'required|string|max:191',
            'rhflow_company_id' => 'required|integer|min:1',
            'gpflow_company_id' => 'nullable|integer|min:1',
            'nom'               => 'required|string|max:255',
            'email'             => 'required|email|max:255',
            'telephone'         => 'nullable|string|max:30',
            'adresse'           => 'nullable|string|max:500',
            'ville'             => 'nullable|string|max:100',
            'pays'              => 'nullable|string|max:100',
            'admin_nom'         => 'nullable|string|max:255',
            'admin_email'       => 'nullable|string|max:255',
            // Mêmes accès que dans RHFlow pour le responsable : identifiant et empreinte bcrypt du mot de passe
            'admin_identifiant'       => 'nullable|string|max:100',
            'admin_mot_de_passe_hash' => 'nullable|string|max:255',
        ]);

        try {
            $r = $this->service->ouvrirEntreprise($d);
        } catch (ProvisionnementException $e) {
            return $this->refus($e);
        } catch (Throwable $e) {
            return $this->erreur($e, 'ouverture de la société');
        }

        return response()->json([
            'success'           => true,
            'gpflow_company_id' => $r['company']->id,
            'nom'               => $r['company']->name,
            'creee'             => $r['creee'],
            'identifiant_admin' => $r['identifiant_admin'],
            'email_admin'       => $r['email_admin'] ?? null,
            // « rhflow » : même mot de passe que dans RHFlow ; « provisoire » : voir le récapitulatif des accès
            'acces_admin'       => $r['acces_admin'] ?? null,
            'lot_acces_id'      => $r['lot']?->id,
            'url_recapitulatif' => route('acces-gpflow.index'),
        ], $r['creee'] ? 201 : 200);
    }

    public function salaries(Request $request, int $companyId): JsonResponse
    {
        $d = $request->validate([
            'source'                          => 'required|string|max:191',
            'rhflow_company_id'               => 'required|integer|min:1',
            'lot_acces_id'                    => 'nullable|integer',
            'salaries'                        => 'required|array|min:1|max:200',
            'salaries.*.rhflow_employee_id'   => 'required|integer|min:1',
            'salaries.*.nom'                  => 'required|string|max:255',
            'salaries.*.email'                => 'nullable|string|max:255',
            'salaries.*.telephone'            => 'nullable|string|max:30',
            'salaries.*.genre'                => 'nullable|string|max:20',
            'salaries.*.date_naissance'       => 'nullable|string|max:30',
            'salaries.*.matricule'            => 'nullable|string|max:100',
            'salaries.*.site'                 => 'nullable|string|max:255',
            'salaries.*.departement'          => 'nullable|string|max:255',
            'salaries.*.poste'                => 'nullable|string|max:255',
            'salaries.*.date_embauche'        => 'nullable|string|max:30',
            'salaries.*.actif'                => 'nullable|boolean',
        ]);

        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['success' => false, 'message' => "La société GPFlow #{$companyId} n'existe pas."], 404);
        }

        // Une entreprise RHFlow ne peut alimenter que la société GPFlow qui lui est liée
        if ($company->rhflow_source !== $d['source'] || (int) $company->rhflow_company_id !== (int) $d['rhflow_company_id']) {
            return response()->json([
                'success' => false,
                'message' => "La société GPFlow #{$companyId} ({$company->name}) n'est pas liée à cette entreprise RHFlow : ouvrez d'abord le compte.",
            ], 409);
        }

        $lot = !empty($d['lot_acces_id'])
            ? LotAcces::where('company_id', $company->id)->find($d['lot_acces_id'])
            : null;

        try {
            $r = $this->service->synchroniserSalaries($company, $d['salaries'], $lot);
        } catch (Throwable $e) {
            return $this->erreur($e, 'synchronisation des salariés');
        }

        return response()->json([
            'success'           => true,
            'crees'             => $r['crees'],
            'mis_a_jour'        => $r['mis_a_jour'],
            'desactives'        => $r['desactives'],
            'ignores'           => $r['ignores'],
            'erreurs'           => $r['erreurs'],
            'lot_acces_id'      => $r['lot']?->id,
            'comptes_dans_lot'  => $r['lot']?->nb_comptes ?? 0,
            'url_recapitulatif' => route('acces-gpflow.index'),
        ]);
    }

    private function refus(ProvisionnementException $e): JsonResponse
    {
        $statut = in_array($e->getCode(), [404, 409, 422, 500], true) ? $e->getCode() : 422;

        return response()->json(['success' => false, 'message' => $e->getMessage()], $statut);
    }

    private function erreur(Throwable $e, string $etape): JsonResponse
    {
        Log::error("Provisionnement RHFlow : échec de la {$etape}", ['erreur' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

        return response()->json(['success' => false, 'message' => "Erreur GPFlow lors de la {$etape} : " . $e->getMessage()], 500);
    }
}
