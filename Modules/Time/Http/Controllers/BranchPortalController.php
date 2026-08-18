<?php

namespace Modules\Time\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\BranchScheduleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Employees\Models\Employee;
use Modules\Time\Models\Pointeuse;
use Carbon\Carbon;

class BranchPortalController extends Controller
{
    public function __construct(private BranchScheduleResolver $scheduleResolver)
    {
    }

    /**
     * Affiche le portail de pointage pour une succursale (QR code unique par branch).
     */
    public function show(Request $request)
    {
        $branchId = $request->query('branch_id');

        if (!$branchId) {
            abort(400, 'Parametre branch_id manquant dans le QR code.');
        }

        $user = Auth::user();

        $branch = Branch::where('id', $branchId)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$branch) {
            abort(404, 'Succursale introuvable.');
        }

        // Un manager de site ne peut ouvrir que le portail de son propre site,
        // même en scannant le QR code d'une autre succursale.
        if (!$user->canPointForBranch((int) $branch->id)) {
            abort(403, 'Vous n etes pas autorise a pointer pour la succursale "' . $branch->name . '".');
        }

        $employees = Employee::with('department')
            ->where('company_id', $user->company_id)
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('time::qrcode-pointage.branch-portal', compact('branch', 'employees'));
    }

    /**
     * Pointage collectif depuis le back-office (layout dashboard).
     *
     * Même logique que le portail QR, mais destinée à la RH qui pointe depuis
     * son poste : la succursale est choisie dans une liste au lieu d'être
     * portée par le QR Code. L'enregistrement passe par le même store().
     */
    public function collective(Request $request)
    {
        $user = Auth::user();

        // Un manager de site ne voit que sa succursale dans le sélecteur ;
        // comme il n'en reste qu'une, elle est présélectionnée plus bas.
        $restrictedBranchId = $user->restrictedBranchId();

        $branches = Branch::where('company_id', $user->company_id)
            ->when($restrictedBranchId, fn ($query) => $query->where('id', $restrictedBranchId))
            ->orderBy('name')
            ->get();

        // Succursale demandée, sinon la seule existante quand il n'y en a qu'une
        $branch = null;

        if ($request->filled('branch_id')) {
            $branch = $branches->firstWhere('id', (int) $request->query('branch_id'));

            if (!$branch) {
                abort(404, 'Succursale introuvable.');
            }
        } elseif ($branches->count() === 1) {
            $branch = $branches->first();
        }

        $employees        = collect();
        $pointagesDuJour  = collect();

        if ($branch) {
            $employees = Employee::with('department')
                ->where('company_id', $user->company_id)
                ->where('branch_id', $branch->id)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get();

            // Pointages deja enregistres aujourd hui : evite de pointer deux fois
            // la meme personne, le risque etant multiplie en selection collective.
            if ($employees->isNotEmpty()) {
                $pointagesDuJour = Pointeuse::whereIn('employee_id', $employees->pluck('id'))
                    ->whereDate('auth_date', Carbon::now()->toDateString())
                    ->orderBy('auth_date_time')
                    ->get()
                    ->groupBy('employee_id');
            }
        }

        return view('time::qrcode-pointage.collective', compact(
            'branches',
            'branch',
            'employees',
            'pointagesDuJour'
        ));
    }

    /**
     * Enregistre un pointage collectif via le portail QR unique par succursale.
     *
     * Le superviseur selectionne un ou plusieurs employes : chacun est traite
     * independamment, avec ses propres controles (contrat actif, appartenance
     * au site) et son propre horaire de departement. Un employe refuse
     * n empeche donc jamais les autres d etre pointes ; le detail par employe
     * est renvoye dans « results » pour affichage du recapitulatif.
     */
    public function store(Request $request)
    {
        // Compatibilite : le portail envoie « employee_ids[] », mais un ancien
        // client (page en cache) peut encore poster « employee_id ».
        $request->merge([
            'employee_ids' => $this->normalizeEmployeeIds($request),
        ]);

        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'required|integer|exists:employees,id',
            'branch_id'      => 'required|exists:branches,id',
            'type'           => 'required|in:entree,sortie',
        ]);

        $supervisor = Auth::user();
        $branchId   = (int) $request->branch_id;
        $type       = $request->type;
        $typeLabel  = $type === 'entree' ? 'Entree' : 'Sortie';

        // Controle d autorisation sur le site : c est ici qu il compte, filtrer
        // l affichage ne protege de rien puisque la requete peut etre forgee.
        if (!$supervisor->canPointForBranch($branchId)) {
            return response()->json([
                'success'   => false,
                'message'   => 'Vous n etes pas autorise a pointer pour cette succursale.',
                'type'      => $type,
                'heure'     => Carbon::now()->format('H:i'),
                'total'     => 0,
                'succeeded' => 0,
                'failed'    => count($request->employee_ids),
                'results'   => [],
            ], 403);
        }

        $employees = Employee::whereIn('id', $request->employee_ids)
            ->where('company_id', $supervisor->company_id)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $branchName = optional(Branch::find($branchId))->name ?: 'cette succursale';

        $now       = Carbon::now();
        $results   = [];
        $succeeded = 0;

        // Pointages du meme type deja enregistres aujourd hui. Charges en une
        // requete avant la boucle : sans ce controle, un rechargement de page
        // ou un double clic duplique tout le lot.
        $dejaPointes = Pointeuse::whereIn('employee_id', $request->employee_ids)
            ->whereDate('auth_date', $now->toDateString())
            ->where('type', $type)
            ->orderBy('auth_date_time')
            ->get()
            ->groupBy('employee_id');

        foreach ($request->employee_ids as $employeeId) {
            $employee = $employees->get((int) $employeeId);

            if (!$employee) {
                $results[] = $this->failure($employeeId, 'Employe #' . $employeeId, 'Employe introuvable.');
                continue;
            }

            // Contrat clos / employe desactive : aucun pointage possible, meme via
            // le portail (le superviseur ne doit pas pouvoir pointer a sa place).
            if ((int) $employee->is_active !== 1) {
                $results[] = $this->failure(
                    $employee->id,
                    $employee->name,
                    'Contrat inactif : pointage refuse.'
                );
                continue;
            }

            // Verifier appartenance a la succursale
            if ((int) $employee->branch_id !== $branchId) {
                $results[] = $this->failure(
                    $employee->id,
                    $employee->name,
                    'N appartient pas a la succursale "' . $branchName . '". Ce QR code est reserve aux employes de ce site.'
                );
                continue;
            }

            // Un seul pointage de chaque type par jour : le second est refuse
            // et rappelle l heure du premier, pour lever le doute cote operateur.
            $precedent = $dejaPointes->get($employee->id)?->first();

            if ($precedent) {
                $results[] = $this->failure(
                    $employee->id,
                    $employee->name,
                    'Deja pointe « ' . $typeLabel . ' » a ' . Carbon::parse($precedent->auth_date_time)->format('H:i') . '.'
                );
                continue;
            }

            // Tranche horaire du departement de l employe, sinon horaire par defaut
            // du site. Logique commune a l app mobile : cf. BranchScheduleResolver.
            $workLocation = $this->scheduleResolver->resolveForEmployee(
                (int) $supervisor->company_id,
                $branchId,
                $employee->department_id
            );

            $evaluation  = $this->scheduleResolver->evaluateScan($workLocation, $type, $now);
            $scanStatus  = $evaluation['status'];
            $dureeRetard = $evaluation['minutes'];

            // Enregistrer le pointage
            Pointeuse::create([
                'employee_id'    => $employee->id,
                'auth_date_time' => $now,
                'auth_date'      => $now->toDateString(),
                'auth_time'      => $now->toTimeString(),
                'type'           => $type,
                'direction'      => $supervisor->name,
                'name_device'    => 'Portail Succursale QR (Par ' . $supervisor->name . ')',
                'no_device'      => $supervisor->id,
                'emp_id'         => $employee->id,
                'card_no'        => 'N/A',
                'name_emp'       => $employee->name,
                'location_id'    => $workLocation ? $workLocation->id : null,
                'status'         => 1,
                'company_id'     => $employee->company_id,
                'scan_status'    => $scanStatus,
                'duree_retard'   => $dureeRetard,
            ]);

            $succeeded++;

            if ($scanStatus === 'retard') {
                $statusMsg = 'En retard de ' . $dureeRetard . ' min';
            } elseif ($scanStatus === 'en_avance') {
                $statusMsg = 'En avance de ' . $dureeRetard . ' min';
            } else {
                $statusMsg = 'A l heure' . ($workLocation === null ? ' (mode libre)' : '');
            }

            $results[] = [
                'employee_id'  => $employee->id,
                'name'         => $employee->name,
                'success'      => true,
                'message'      => $statusMsg,
                'scan_status'  => $scanStatus,
                'duree_retard' => $dureeRetard,
                'mode_libre'   => ($workLocation === null),
                'heure'        => $now->format('H:i'),
            ];
        }

        $total  = count($results);
        $failed = $total - $succeeded;

        if ($succeeded === 0) {
            $message = 'Aucun pointage enregistre : ' . $failed . ' employe(s) refuse(s).';
        } elseif ($failed === 0) {
            $message = $succeeded . ' pointage(s) « ' . $typeLabel . ' » enregistre(s) a ' . $now->format('H:i') . '.';
        } else {
            $message = $succeeded . ' pointage(s) enregistre(s), ' . $failed . ' refuse(s).';
        }

        return response()->json([
            'success'   => $succeeded > 0,
            'message'   => $message,
            'type'      => $type,
            'heure'     => $now->format('H:i'),
            'total'     => $total,
            'succeeded' => $succeeded,
            'failed'    => $failed,
            'results'   => $results,
        ]);
    }

    /**
     * Identifiants a pointer, dedoublonnes, quelle que soit la forme envoyee
     * par le client (« employee_ids[] » ou l ancien « employee_id »).
     */
    private function normalizeEmployeeIds(Request $request): array
    {
        $ids = $request->input('employee_ids', $request->input('employee_id'));

        return collect(is_array($ids) ? $ids : [$ids])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ligne de recapitulatif pour un employe dont le pointage a ete refuse.
     */
    private function failure($employeeId, string $name, string $message): array
    {
        return [
            'employee_id'  => (int) $employeeId,
            'name'         => $name,
            'success'      => false,
            'message'      => $message,
            'scan_status'  => null,
            'duree_retard' => 0,
            'mode_libre'   => false,
            'heure'        => null,
        ];
    }
}