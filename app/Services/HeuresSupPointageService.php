<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Analyse des pointages d'une journée : contrôle de la pause et heures
 * supplémentaires détectées, selon les règles appliquées en Côte d'Ivoire.
 *
 * - Pause déjeuner réglementaire d'1 h : elle est déduite de toute journée de
 *   6 h de présence ou plus, même si elle n'a pas été pointée ou a été écourtée.
 *   Une pause pointée plus longue est déduite en entier et signalée en dépassement.
 * - Jour ouvrable : au-delà de 8 h de travail effectif, 1,2 h à +15 %
 *   (6 h par semaine réparties sur 5 jours), puis +50 %.
 * - Heures supplémentaires effectuées la nuit (21 h - 5 h) : +75 %, à la place
 *   de +15 % / +50 %.
 * - Dimanche ou férié chômé : toutes les heures à +75 %, +100 % pour celles de nuit.
 *
 * Les durées sont calculées à partir des timestamps, et non avec diffInMinutes(),
 * dont le signe dépend de la version de Carbon (signé depuis Carbon 3).
 */
class HeuresSupPointageService
{
    public const PAUSE_REGLEMENTAIRE_MINUTES = 60;
    public const PRESENCE_MIN_POUR_PAUSE_MINUTES = 360;
    public const DUREE_NORMALE_JOUR_MINUTES = 480;
    public const QUOTA_15_JOUR_MINUTES = 72;
    public const DEBUT_NUIT = 21;
    public const FIN_NUIT = 5;

    /**
     * Un scan à moins de 2 minutes du précédent est un doublon de badge, quel que
     * soit son type : les pointeuses enregistrent souvent le même passage en
     * « entrée » puis en « sortie » dans la même minute.
     */
    private const ECART_DOUBLON_SECONDES = 120;

    /**
     * @param  iterable  $scans  pointages d'un même jour (auth_date_time, type entree|sortie)
     * @param  bool  $reposOuFerie  dimanche ou jour férié chômé
     * @return array|null null si la journée n'a pas au moins une entrée et une sortie distinctes
     */
    public function analyserJournee(iterable $scans, bool $reposOuFerie = false): ?array
    {
        $scans = $this->dedoublonner(collect($scans));
        if ($scans->count() < 2) {
            return null;
        }

        $arrivee = Carbon::parse($scans->first()->auth_date_time);
        $depart  = Carbon::parse($scans->last()->auth_date_time);
        $presence = $this->minutesEntre($arrivee, $depart);
        if ($presence <= 0) {
            return null;
        }

        // Contrôle de la pause
        $pausePointee = $this->pausePointee($scans);
        $pauseRetenue = $pausePointee;
        if ($presence >= self::PRESENCE_MIN_POUR_PAUSE_MINUTES) {
            $pauseRetenue = max($pausePointee, self::PAUSE_REGLEMENTAIRE_MINUTES);
        }
        $depassement = max(0, $pausePointee - self::PAUSE_REGLEMENTAIRE_MINUTES);
        $pauseCourte = $pausePointee > 0
            && $pausePointee < self::PAUSE_REGLEMENTAIRE_MINUTES
            && $presence >= self::PRESENCE_MIN_POUR_PAUSE_MINUTES;

        $travail = max(0, $presence - $pauseRetenue);
        $nuit = min($travail, $this->minutesDeNuit($arrivee, $depart));

        // Heures supplémentaires (en minutes)
        $hs = ['h15' => 0, 'h50' => 0, 'h75_nuit' => 0, 'h75_repos' => 0, 'h100' => 0];
        if ($reposOuFerie) {
            $hs['h100'] = $nuit;
            $hs['h75_repos'] = $travail - $nuit;
        } else {
            $total = max(0, $travail - self::DUREE_NORMALE_JOUR_MINUTES);
            $hs['h75_nuit'] = min($total, $nuit);
            $deJour = $total - $hs['h75_nuit'];
            $hs['h15'] = min($deJour, self::QUOTA_15_JOUR_MINUTES);
            $hs['h50'] = $deJour - $hs['h15'];
        }

        return [
            'date'                      => $arrivee->toDateString(),
            'arrivee'                   => $arrivee->format('H:i'),
            'depart'                    => $depart->format('H:i'),
            'presence_minutes'          => $presence,
            'pause_pointee_minutes'     => $pausePointee,
            'pause_retenue_minutes'     => $pauseRetenue,
            'pause_depassement_minutes' => $depassement,
            'pause_courte'              => $pauseCourte,
            'travail_minutes'           => $travail,
            'heures_sup'                => [
                'quar_heure'           => $this->enHeures($hs['h15']),
                'heure_audd'           => $this->enHeures($hs['h50']),
                'heure_nuit_ferie'     => $this->enHeures($hs['h75_nuit']),
                'heure_dim_ferie'      => $this->enHeures($hs['h75_repos']),
                'heure_nuit_dim_ferie' => $this->enHeures($hs['h100']),
            ],
        ];
    }

    private function dedoublonner(Collection $scans): Collection
    {
        $retenus = collect();
        foreach ($scans->sortBy(fn ($s) => Carbon::parse($s->auth_date_time)->getTimestamp())->values() as $scan) {
            $precedent = $retenus->last();
            if ($precedent
                && Carbon::parse($scan->auth_date_time)->getTimestamp() - Carbon::parse($precedent->auth_date_time)->getTimestamp() < self::ECART_DOUBLON_SECONDES) {
                continue;
            }
            $retenus->push($scan);
        }

        return $retenus;
    }

    /**
     * Temps passé hors poste entre une sortie et l'entrée suivante. Sans type
     * entree/sortie sur les scans, une alternance entrée/sortie est supposée,
     * à partir de 4 scans (avec 3, on ne sait pas lequel est la pause).
     */
    private function pausePointee(Collection $scans): int
    {
        $types = $scans->pluck('type')->all();
        $typesConnus = collect($types)->every(fn ($t) => in_array($t, ['entree', 'sortie'], true));
        if (!$typesConnus && $scans->count() < 4) {
            return 0;
        }

        $pause = 0;
        for ($i = 1; $i < $scans->count(); $i++) {
            $sortiePuisEntree = $typesConnus
                ? ($types[$i - 1] === 'sortie' && $types[$i] === 'entree')
                : ($i % 2 === 0);
            if ($sortiePuisEntree) {
                $pause += $this->minutesEntre(
                    Carbon::parse($scans[$i - 1]->auth_date_time),
                    Carbon::parse($scans[$i]->auth_date_time)
                );
            }
        }

        return $pause;
    }

    /** Minutes de présence comprises dans les plages de nuit (0 h - 5 h et 21 h - 5 h le lendemain). */
    private function minutesDeNuit(Carbon $arrivee, Carbon $depart): int
    {
        $jour = $arrivee->copy()->startOfDay();
        $plages = [
            [$jour->copy()->setTime(0, 0), $jour->copy()->setTime(self::FIN_NUIT, 0)],
            [$jour->copy()->setTime(self::DEBUT_NUIT, 0), $jour->copy()->addDay()->setTime(self::FIN_NUIT, 0)],
        ];

        $minutes = 0;
        foreach ($plages as [$debut, $fin]) {
            $chevauchementDebut = max($arrivee->getTimestamp(), $debut->getTimestamp());
            $chevauchementFin   = min($depart->getTimestamp(), $fin->getTimestamp());
            if ($chevauchementFin > $chevauchementDebut) {
                $minutes += intdiv($chevauchementFin - $chevauchementDebut, 60);
            }
        }

        return $minutes;
    }

    private function minutesEntre(Carbon $debut, Carbon $fin): int
    {
        return max(0, intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60));
    }

    private function enHeures(int $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
