<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Employees\Models\Demande;
use Modules\Employees\Models\Employee;
use Modules\Leaves\Models\Leave;
use Modules\Settings\Models\WorkLocation;
use Modules\Time\Models\Holiday;
use Modules\Time\Models\Pointeuse;
use Modules\Time\Models\TimeSheet;

/**
 * Rapport hebdomadaire de présence envoyé chaque vendredi à l'admin de
 * l'entreprise (commande rapports:absences-hebdo).
 *
 * Période : du lundi de la semaine au jour de référence (le vendredi de
 * l'envoi), jamais au-delà d'aujourd'hui.
 *
 * Absences justifiées : congés et demandes d'absence accordés, et feuilles de
 * temps approuvées (là où les validations web les inscrivent).
 *
 * Absences non justifiées : jours ouvrés sans pointage ni absence accordée.
 * Calculées seulement si l'entreprise a paramétré son rythme hebdomadaire
 * (working_days_per_week) ou si la succursale de l'employé a le sien : sans ce
 * réglage, on ne sait pas quels jours l'employé était attendu.
 */
class WeeklyAttendanceReport
{
    private const LEAVE_ACCORDE = ['Approuvé', 'Démarré', 'Terminé'];
    private const DEMANDE_ACCORDEE = ['Approved', 'Approuvé'];

    public function build(Company $company, ?Carbon $reference = null): array
    {
        $reference = ($reference ?? Carbon::today())->copy()->startOfDay();
        $debut = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $reference->copy()->min(Carbon::today());
        if ($fin->lt($debut)) {
            $fin = $debut->copy();
        }

        $jours = collect(CarbonPeriod::create($debut, $fin))->map(fn ($d) => $d->toDateString())->all();

        $employes = Employee::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
        $ids = $employes->pluck('id')->all();

        $departements = \App\Models\Department::where('company_id', $company->id)->pluck('name', 'id');
        $sites = WorkLocation::where('company_id', $company->id)->pluck('name', 'id');

        $pointages = Pointeuse::whereIn('employee_id', $ids)
            ->whereBetween('auth_date', [$debut->toDateString(), $fin->toDateString()])
            ->orderBy('auth_date')->orderBy('auth_time')
            ->get()
            ->groupBy(fn ($p) => $p->employee_id . '|' . Carbon::parse($p->auth_date)->toDateString());

        $justifiees = $this->absencesJustifiees($ids, $debut, $fin);
        $joursAttendus = $this->joursAttendusParSite($company, $debut, $fin);

        $lignes = [];
        $nonJustifiees = [];
        $stats = [
            'effectif' => $employes->count(),
            'presents' => 0,
            'jours_presence' => 0,
            'jours_attendus' => 0,
            'retards' => 0,
            'minutes_retard' => 0,
            'departs_anticipes' => 0,
            'absences_justifiees' => 0,
            'absences_non_justifiees' => 0,
        ];

        foreach ($employes as $employe) {
            $controle = $joursAttendus[$employe->branch_id ?? 0] ?? $joursAttendus[0];
            $embauche = $employe->company_doj ? Carbon::parse($employe->company_doj)->toDateString() : null;

            $detail = [];
            $totaux = ['presence' => 0, 'retards' => 0, 'minutes_retard' => 0, 'justifiees' => 0, 'non_justifiees' => 0, 'minutes_travail' => 0];

            foreach ($jours as $jour) {
                $ligne = $this->journee($pointages->get($employe->id . '|' . $jour, collect()), $sites);
                $motif = $justifiees[$employe->id][$jour] ?? null;

                if ($ligne) {
                    $totaux['presence']++;
                    $totaux['minutes_travail'] += $ligne['minutes_travail'];
                    if ($ligne['retard'] > 0 || $ligne['code_arrivee'] === 'retard') {
                        $totaux['retards']++;
                        $totaux['minutes_retard'] += $ligne['retard'];
                    }
                    if ($ligne['depart_anticipe']) {
                        $stats['departs_anticipes']++;
                    }
                } elseif ($motif) {
                    $totaux['justifiees']++;
                } elseif ($controle !== null && in_array($jour, $controle, true) && (!$embauche || $jour >= $embauche)) {
                    $totaux['non_justifiees']++;
                    $nonJustifiees[$employe->id][] = $jour;
                }

                if ($controle !== null && in_array($jour, $controle, true) && (!$embauche || $jour >= $embauche)) {
                    $stats['jours_attendus']++;
                }

                $detail[$jour] = $ligne + ['absence' => $ligne ? null : $motif, 'non_justifiee' => !$ligne && !$motif
                    && $controle !== null && in_array($jour, $controle, true) && (!$embauche || $jour >= $embauche)];
            }

            if ($totaux['presence'] > 0) {
                $stats['presents']++;
            }
            $stats['jours_presence'] += $totaux['presence'];
            $stats['retards'] += $totaux['retards'];
            $stats['minutes_retard'] += $totaux['minutes_retard'];
            $stats['absences_justifiees'] += $totaux['justifiees'];
            $stats['absences_non_justifiees'] += $totaux['non_justifiees'];

            $lignes[] = [
                'employe' => $employe,
                'matricule' => $employe->employee_id,
                'departement' => $departements[$employe->department_id] ?? '—',
                'jours' => $detail,
                'totaux' => $totaux,
                'controle_absences' => $controle !== null,
            ];
        }

        $controleActif = collect($lignes)->contains(fn ($l) => $l['controle_absences']);
        $stats['taux_presence'] = $controleActif && $stats['jours_attendus'] > 0
            ? round(min(100, $stats['jours_presence'] / $stats['jours_attendus'] * 100), 1)
            : null;

        $noms = $employes->pluck('name', 'id');

        return [
            'company' => $company,
            'debut' => $debut,
            'fin' => $fin,
            'jours' => $jours,
            'genere_le' => Carbon::now(),
            'controle_absences' => $controleActif,
            'stats' => $stats,
            'lignes' => $lignes,
            'retards' => collect($lignes)->filter(fn ($l) => $l['totaux']['retards'] > 0)
                ->sortByDesc(fn ($l) => $l['totaux']['minutes_retard'])->values()->all(),
            'liste_justifiees' => $this->listeJustifiees($ids, $debut, $fin, $noms),
            'liste_non_justifiees' => collect($nonJustifiees)->map(fn ($dates, $id) => [
                'employe' => $noms[$id] ?? '—',
                'jours' => $dates,
            ])->values()->all(),
            'a_venir' => $this->absencesAVenir($ids, $fin, $noms),
            'derogations' => $this->derogations($ids, $debut, $fin, $noms),
        ];
    }

    /**
     * PDF du rapport (A4 paysage), sous forme de contenu binaire.
     */
    public function pdf(array $rapport): string
    {
        return Pdf::loadView('reports.weekly-attendance-pdf', $rapport)
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true) // pièce jointe légère : seuls les glyphes utilisés
            ->output();
    }

    /**
     * Arrivée, départ, durée et statut d'une journée de pointage, ou [] sans pointage.
     */
    private function journee(Collection $pointages, Collection $sites): array
    {
        if ($pointages->isEmpty()) {
            return [];
        }

        $entree = $pointages->firstWhere('type', 'entree');
        $sortie = $pointages->where('type', 'sortie')->last();

        $arrivee = $entree ? Carbon::parse($entree->auth_time) : null;
        $depart = $sortie ? Carbon::parse($sortie->auth_time) : null;
        $minutes = $arrivee && $depart && $depart->gt($arrivee) ? (int) round($arrivee->diffInMinutes($depart)) : 0;

        $statuts = [];
        if ($entree && $entree->scan_status === 'retard') {
            $statuts[] = 'Retard' . ($entree->duree_retard ? ' ' . (int) $entree->duree_retard . ' min' : '');
        }
        if ($sortie && $sortie->scan_status === 'en_avance') {
            $statuts[] = 'Départ anticipé' . ($sortie->duree_retard ? ' ' . (int) $sortie->duree_retard . ' min' : '');
        }

        $reference = $entree ?? $sortie;

        return [
            'arrivee' => $arrivee?->format('H:i'),
            'depart' => $depart?->format('H:i'),
            'minutes_travail' => $minutes,
            'duree' => $minutes ? intdiv($minutes, 60) . 'h' . str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) : '—',
            'code_arrivee' => $entree->scan_status ?? null,
            'retard' => $entree && $entree->scan_status === 'retard' ? (int) $entree->duree_retard : 0,
            'depart_anticipe' => $sortie && $sortie->scan_status === 'en_avance',
            'statut' => $statuts ? implode(', ', $statuts) : 'À l\'heure',
            'lieu' => $reference->location_id ? ($sites[$reference->location_id] ?? '—') : '—',
            'mode' => str_starts_with((string) $reference->name_device, 'Pointage GPS') ? 'GPS' : 'QR',
        ];
    }

    /**
     * Jours où chaque employé était attendu, par succursale (clé 0 : rythme de
     * l'entreprise). null quand le rythme n'est pas paramétré : pas de contrôle.
     *
     * @return array<int, array<int, string>|null>
     */
    private function joursAttendusParSite(Company $company, Carbon $debut, Carbon $fin): array
    {
        $chomes = Holiday::datesBetween($company->id, $debut, $fin, false);
        $travailles = Holiday::datesBetween($company->id, $debut, $fin, true);
        $entrepriseParametree = (int) $company->working_days_per_week > 0;

        $calendrier = function (callable $ouvert) use ($debut, $fin, $chomes, $travailles) {
            $jours = [];
            foreach (CarbonPeriod::create($debut, $fin) as $jour) {
                $cle = $jour->toDateString();
                if (in_array($cle, $chomes, true)) {
                    continue;
                }
                if (in_array($cle, $travailles, true) || $ouvert($jour)) {
                    $jours[] = $cle;
                }
            }

            return $jours;
        };

        $parSite = [0 => $entrepriseParametree ? $calendrier(fn ($j) => $company->worksOn($j)) : null];

        foreach (Branch::where('company_id', $company->id)->get() as $site) {
            $parSite[$site->id] = $site->hasOwnWorkingDays() || $entrepriseParametree
                ? $calendrier(fn ($j) => $site->worksOn($j))
                : null;
        }

        return $parSite;
    }

    /**
     * Motif de l'absence accordée, par employé et par jour.
     *
     * @return array<int, array<string, string>>
     */
    private function absencesJustifiees(array $ids, Carbon $debut, Carbon $fin): array
    {
        $motifs = [];
        $ajouter = function ($employeId, $du, $au, $motif) use (&$motifs, $debut, $fin) {
            $du = Carbon::parse($du)->startOfDay()->max($debut);
            $au = Carbon::parse($au ?: $du)->startOfDay()->min($fin);
            foreach ($au->gte($du) ? CarbonPeriod::create($du, $au) : [] as $jour) {
                $motifs[$employeId][$jour->toDateString()] ??= $motif ?: 'Absence justifiée';
            }
        };

        foreach ($this->congesAccordes($ids, $debut, $fin) as $c) {
            $ajouter($c->employee_id, $c->start_date, $c->end_date, $c->leaveType->title ?? 'Congé');
        }
        foreach ($this->demandesAccordees($ids, $debut, $fin) as $d) {
            $ajouter($d->employee_id, $d->start_date, $d->end_date, 'Absence autorisée');
        }
        TimeSheet::whereIn('employee_id', $ids)
            ->where('statut', 'approved')
            ->whereDate('date', '<=', $fin)
            ->where(fn ($q) => $q->whereDate('arrival_date', '>=', $debut)
                ->orWhere(fn ($q) => $q->whereNull('arrival_date')->whereDate('date', '>=', $debut)))
            ->get()
            ->each(fn ($t) => $ajouter($t->employee_id, $t->date, $t->arrival_date, $t->type_permis));

        return $motifs;
    }

    private function listeJustifiees(array $ids, Carbon $debut, Carbon $fin, Collection $noms): array
    {
        $liste = [];
        foreach ($this->congesAccordes($ids, $debut, $fin) as $c) {
            $liste[] = ['employe' => $noms[$c->employee_id] ?? '—', 'motif' => $c->leaveType->title ?? 'Congé',
                'du' => Carbon::parse($c->start_date), 'au' => Carbon::parse($c->end_date ?: $c->start_date)];
        }
        foreach ($this->demandesAccordees($ids, $debut, $fin) as $d) {
            $liste[] = ['employe' => $noms[$d->employee_id] ?? '—', 'motif' => 'Absence autorisée',
                'du' => Carbon::parse($d->start_date), 'au' => Carbon::parse($d->end_date ?: $d->start_date)];
        }

        return collect($liste)->sortBy('employe')->values()->all();
    }

    /** Congés et absences accordés qui commencent la semaine suivante. */
    private function absencesAVenir(array $ids, Carbon $fin, Collection $noms): array
    {
        $du = $fin->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
        $au = $du->copy()->endOfWeek(Carbon::SUNDAY);

        return Leave::with('leaveType')
            ->whereIn('employee_id', $ids)
            ->whereIn('status', self::LEAVE_ACCORDE)
            ->whereNull('deleted_at')
            ->whereBetween('start_date', [$du->toDateString(), $au->toDateString()])
            ->orderBy('start_date')
            ->get()
            ->map(fn ($c) => ['employe' => $noms[$c->employee_id] ?? '—', 'motif' => $c->leaveType->title ?? 'Congé',
                'du' => Carbon::parse($c->start_date), 'au' => Carbon::parse($c->end_date ?: $c->start_date)])
            ->all();
    }

    /** Absences accordées malgré un effectif insuffisant (cf. DepartmentStaffingGuard). */
    private function derogations(array $ids, Carbon $debut, Carbon $fin, Collection $noms): array
    {
        if (!Schema::hasColumn('leaves', 'effectif_derogation')) {
            return [];
        }

        $periode = [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()];

        return Leave::whereIn('employee_id', $ids)->whereNotNull('effectif_derogation')->whereBetween('updated_at', $periode)->get()
            ->concat(Demande::whereIn('employee_id', $ids)->whereNotNull('effectif_derogation')->whereBetween('updated_at', $periode)->get())
            ->map(fn ($a) => ['employe' => $noms[$a->employee_id] ?? '—', 'motif' => $a->effectif_derogation,
                'du' => Carbon::parse($a->start_date), 'au' => Carbon::parse($a->end_date ?: $a->start_date)])
            ->all();
    }

    private function congesAccordes(array $ids, Carbon $debut, Carbon $fin): Collection
    {
        return Leave::with('leaveType')
            ->whereIn('employee_id', $ids)
            ->whereIn('status', self::LEAVE_ACCORDE)
            ->whereNull('deleted_at')
            ->whereDate('start_date', '<=', $fin)
            ->whereDate('end_date', '>=', $debut)
            ->get();
    }

    private function demandesAccordees(array $ids, Carbon $debut, Carbon $fin): Collection
    {
        return Demande::whereIn('employee_id', $ids)
            ->where('categorie_demandes', 'absence')
            ->whereIn('status', self::DEMANDE_ACCORDEE)
            ->whereNull('deleted_at')
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $fin)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $debut)
                ->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('start_date', '>=', $debut)))
            ->get();
    }
}
