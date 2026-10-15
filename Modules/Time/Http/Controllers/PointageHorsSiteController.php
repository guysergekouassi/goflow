<?php

namespace Modules\Time\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PointageHorsSiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Time\Models\PointageHorsSite;

/**
 * Pointages GPS faits hors des sites connus : le supérieur hiérarchique (ou le
 * compte entreprise) les valide ou les refuse. Voir PointageHorsSiteService.
 */
class PointageHorsSiteController extends Controller
{
    private const STATUTS = [
        PointageHorsSite::EN_ATTENTE => 'À valider',
        PointageHorsSite::VALIDE => 'Validés',
        PointageHorsSite::REFUSE => 'Refusés',
    ];

    public function index(Request $request)
    {
        if (!PointageHorsSite::tableExiste()) {
            return redirect('/')
                ->with('error', 'Les pointages hors site ne sont pas encore installés : la migration doit être lancée.');
        }

        $user = Auth::user();
        $statuts = self::STATUTS;
        $status = array_key_exists($request->input('status'), $statuts)
            ? $request->input('status')
            : PointageHorsSite::EN_ATTENTE;

        $pointages = PointageHorsSite::with(['employee.department', 'nearestLocation', 'validator', 'decidedBy'])
            ->visiblePar($user)
            ->where('status', $status)
            ->orderByDesc('pointed_at')
            ->paginate(25)
            ->withQueryString();

        $compteurs = PointageHorsSite::visiblePar($user)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $service = app(PointageHorsSiteService::class);

        return view('time::pointages-hors-site.index', compact('pointages', 'statuts', 'status', 'compteurs', 'service', 'user'));
    }

    public function decide(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:valider,refuser',
            'commentaire' => 'nullable|string|max:500|required_if:decision,refuser',
        ], [
            'commentaire.required_if' => 'Indiquez la raison du refus : elle sera transmise à l\'employé.',
        ]);

        $user = Auth::user();
        $service = app(PointageHorsSiteService::class);
        $pointage = PointageHorsSite::find($id);

        if (!$pointage || !$service->canDecide($user, $pointage)) {
            return redirect()->back()->with('error', 'Ce pointage est introuvable ou n\'est pas confié à votre compte.');
        }

        $valider = $validated['decision'] === 'valider';

        try {
            $service->decide($pointage, $user, $valider, $validated['commentaire'] ?? null);
        } catch (\RuntimeException $e) {
            // Déjà traité, par un autre supérieur ou depuis l'application
            return redirect()->back()->with('error', $e->getMessage());
        }

        $nom = $pointage->employee?->name ?? 'l\'employé';

        return redirect()->back()->with('success', $valider
            ? 'Pointage de ' . $nom . ' validé : il compte désormais dans ses présences.'
            : 'Pointage de ' . $nom . ' refusé. L\'employé en a été informé.');
    }
}
