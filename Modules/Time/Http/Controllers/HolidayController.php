<?php

namespace Modules\Time\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Time\Models\Holiday;

/**
 * Saisie manuelle des jours fériés / chômés.
 *
 * Ces dates sont exclues des jours ouvrés dans les rapports de présence :
 * un employé qui ne pointe pas ces jours-là n'est pas compté absent.
 */
class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $year = (int) $request->input('year', date('Y'));

        $holidays = Holiday::forCompany($user->company_id)
            ->where(function ($query) use ($year) {
                $query->whereYear('date', $year)
                      ->orWhere('is_recurring', true);
            })
            ->orderBy('date')
            ->get();

        // Années proposées dans le filtre : de l'an dernier à l'an prochain
        $years = range(date('Y') - 1, date('Y') + 1);

        return view('time::holidays.index', compact('holidays', 'year', 'years'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'date'         => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:date',
            'is_recurring' => 'nullable|boolean',
            'is_worked'    => 'nullable|boolean',
            'description'  => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Le libellé du jour férié est obligatoire.',
            'date.required' => 'La date est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);

        $user = Auth::user();

        // Un même jour ne doit pas être déclaré deux fois pour l'entreprise
        $exists = Holiday::forCompany($user->company_id)
            ->whereDate('date', $validated['date'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Un jour férié est déjà enregistré au ' . Carbon::parse($validated['date'])->format('d/m/Y') . '.');
        }

        Holiday::create([
            'company_id'   => $user->company_id,
            'name'         => $validated['name'],
            'date'         => $validated['date'],
            'end_date'     => $validated['end_date'] ?? null,
            'is_recurring' => $request->boolean('is_recurring'),
            'is_worked'    => $request->boolean('is_worked'),
            'is_active'    => true,
            'description'  => $validated['description'] ?? null,
            'created_by'   => $user->id,
        ]);

        return redirect()->back()->with('success', 'Jour férié « ' . $validated['name'] . ' » ajouté. Les employés ne seront plus comptés absents ce jour-là.');
    }

    public function update(Request $request, $id)
    {
        $holiday = $this->findForCompany($id);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'date'         => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:date',
            'is_recurring' => 'nullable|boolean',
            'is_worked'    => 'nullable|boolean',
            'is_active'    => 'nullable|boolean',
            'description'  => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Le libellé du jour férié est obligatoire.',
            'date.required' => 'La date est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);

        $user = Auth::user();

        // Même règle qu'à la création : deux fériés ne peuvent pas tomber le
        // même jour. Sans ce contrôle, déplacer une date sur celle d'un autre
        // férié créait un doublon que le formulaire de création refuse.
        $exists = Holiday::forCompany($user->company_id)
            ->whereDate('date', $validated['date'])
            ->where('id', '!=', $holiday->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'Un autre jour férié est déjà enregistré au ' . Carbon::parse($validated['date'])->format('d/m/Y') . '.');
        }

        $holiday->update([
            'name'         => $validated['name'],
            'date'         => $validated['date'],
            'end_date'     => $validated['end_date'] ?? null,
            'is_recurring' => $request->boolean('is_recurring'),
            'is_worked'    => $request->boolean('is_worked'),
            // Une case décochée n'est pas envoyée par le navigateur : avec un
            // défaut à true, boolean() renvoyait true dans ce cas et le férié
            // ne pouvait jamais être désactivé. Absent = décoché = false.
            'is_active'    => $request->boolean('is_active'),
            'description'  => $validated['description'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Jour férié mis à jour.');
    }

    public function destroy($id)
    {
        $holiday = $this->findForCompany($id);
        $name    = $holiday->name;

        $holiday->delete();

        return redirect()->back()->with('success', 'Jour férié « ' . $name . ' » supprimé.');
    }

    /**
     * Charge un jour férié en garantissant qu'il appartient bien à l'entreprise
     * de l'utilisateur connecté.
     */
    private function findForCompany($id): Holiday
    {
        return Holiday::forCompany(Auth::user()->company_id)->findOrFail($id);
    }
}
