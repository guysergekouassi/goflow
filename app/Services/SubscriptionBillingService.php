<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;

/**
 * Calcul du montant d'un abonnement : forfait + part variable par employé.
 *
 *   montant = (prix_forfait + effectif_facturable × prix_par_employé) × durée
 *
 * L'effectif retenu est celui constaté **au moment de la facturation**. Un
 * recrutement en cours de mois n'est donc pas refacturé immédiatement : il est
 * pris en compte à l'échéance suivante (3 employés ce mois-ci → 5 000 + 3 000 ;
 * 5 employés le mois prochain → 5 000 + 5 000).
 *
 * Le résultat est figé sur la commande (employees_count, base_amount,
 * employees_amount) : une facture émise ne change plus de montant.
 */
class SubscriptionBillingService
{
    /**
     * Effectif facturable : les employés actifs. Un employé désactivé
     * (contrat clos) n'est plus facturé.
     */
    public function billableHeadcount(?Company $company): int
    {
        if (!$company) {
            return 0;
        }

        return (int) $company->employees()->where('is_active', 1)->count();
    }

    /**
     * Détail chiffré d'un abonnement, prêt à afficher ou à enregistrer.
     *
     * @param  int $duration Nombre de mois facturés
     * @return array<string, mixed>
     */
    public function quote(?Company $company, Plan $plan, int $duration = 1): array
    {
        return $this->quoteForHeadcount($this->billableHeadcount($company), $plan, $duration);
    }

    /**
     * Même calcul à partir d'un effectif déclaré plutôt que constaté en base.
     *
     * Utilisé à la souscription depuis la landing page : l'entreprise n'existe
     * pas encore, l'effectif vient du formulaire. Il sera ensuite recalculé sur
     * l'effectif réel aux échéances suivantes.
     *
     * @return array<string, mixed>
     */
    public function quoteForHeadcount(int $headcount, Plan $plan, int $duration = 1): array
    {
        $duration  = max(1, $duration);
        $headcount = max(0, $headcount);

        $included = (int) $plan->included_employees;
        $unit     = (float) $plan->price_per_employee;
        $base     = (float) $plan->price;

        // Les employés compris dans le forfait ne sont pas refacturés
        $billable        = max(0, $headcount - $included);
        $employeesAmount = $billable * $unit;
        $monthly         = $base + $employeesAmount;

        return [
            'headcount'                => $headcount,
            'billable_employees'       => $billable,
            'included_employees'       => $included,
            'base_amount'              => $base,
            'price_per_employee'       => $unit,
            'employees_amount'         => $employeesAmount,
            'monthly_amount'           => $monthly,
            'duration'                 => $duration,
            'total_amount'             => $monthly * $duration,
            'has_per_employee_pricing' => $unit > 0,
            'breakdown'                => $this->breakdown($base, $billable, $unit, $monthly, $duration),
        ];
    }

    /**
     * Libellé du calcul, affiché sur la page de paiement et la facture.
     * Ex. « 5 000 + 3 × 1 000 = 8 000 FCFA / mois »
     */
    private function breakdown(float $base, int $billable, float $unit, float $monthly, int $duration): string
    {
        $format = function ($amount) {
            return number_format($amount, 0, ',', ' ');
        };

        if ($unit <= 0) {
            $label = $format($base) . ' FCFA / mois';
        } else {
            $label = $format($base) . ' + ' . $billable . ' × ' . $format($unit)
                   . ' = ' . $format($monthly) . ' FCFA / mois';
        }

        if ($duration > 1) {
            $label .= ' × ' . $duration . ' mois = ' . $format($monthly * $duration) . ' FCFA';
        }

        return $label;
    }

    /**
     * Champs à enregistrer sur la commande pour figer la facturation.
     *
     * @return array<string, mixed>
     */
    public function orderAttributes(array $quote): array
    {
        return [
            // La durée conditionne l'échéance posée à la confirmation du
            // paiement (Company::activateSubscription).
            'duration'         => $quote['duration'],
            'employees_count'  => $quote['billable_employees'],
            'base_amount'      => $quote['base_amount'],
            'employees_amount' => $quote['employees_amount'],
            'amount'           => $quote['total_amount'],
            'total_amount'     => $quote['total_amount'],
        ];
    }
}
