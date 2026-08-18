<?php

namespace Modules\Time\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jour férié / chômé déclaré manuellement par l'entreprise.
 *
 * Ces dates sont retirées des jours ouvrés avant tout calcul d'absence :
 * un employé qui ne pointe pas un 1er mai ne doit pas être marqué absent.
 */
class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'company_id',
        'name',
        'date',
        'end_date',
        'is_recurring',
        'is_worked',
        'is_active',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date'         => 'date',
        'end_date'     => 'date',
        'is_recurring' => 'boolean',
        'is_worked'    => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Toutes les dates fériées d'une entreprise entre deux bornes, au format
     * Y-m-d. Gère les ponts (end_date) et les fériés à date fixe annuelle
     * (is_recurring : seuls le jour et le mois comptent).
     *
     * @param  bool|null  $isWorked  null = tous les fériés ; false = seulement
     *                               les chômés ; true = seulement les travaillés.
     * @return array<int, string>
     */
    public static function datesBetween($companyId, Carbon $start, Carbon $end, ?bool $isWorked = null): array
    {
        $holidays = static::forCompany($companyId)
            ->active()
            ->when($isWorked !== null, fn ($query) => $query->where('is_worked', $isWorked))
            ->get();

        $dates = [];

        foreach ($holidays as $holiday) {
            $from = Carbon::parse($holiday->date);
            $to   = $holiday->end_date ? Carbon::parse($holiday->end_date) : $from->copy();

            if ($holiday->is_recurring) {
                // On rejoue le férié sur chaque année couverte par la période
                for ($year = (int) $start->format('Y'); $year <= (int) $end->format('Y'); $year++) {
                    $shiftedFrom = $from->copy()->setYear($year);
                    $shiftedTo   = $to->copy()->setYear($year + ($to->year - $from->year));

                    $dates = array_merge($dates, static::expand($shiftedFrom, $shiftedTo, $start, $end));
                }

                continue;
            }

            $dates = array_merge($dates, static::expand($from, $to, $start, $end));
        }

        return array_values(array_unique($dates));
    }

    /**
     * Déplie une plage de dates, en ne gardant que ce qui tombe dans la période.
     *
     * @return array<int, string>
     */
    private static function expand(Carbon $from, Carbon $to, Carbon $start, Carbon $end): array
    {
        $dates   = [];
        $current = $from->copy();

        while ($current->lte($to)) {
            if ($current->betweenIncluded($start, $end)) {
                $dates[] = $current->format('Y-m-d');
            }

            $current->addDay();
        }

        return $dates;
    }
}
