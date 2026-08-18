<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * L'offre unique présentée sur la landing page.
 *
 * Un seul modèle de souscription : 5 000 FCFA de forfait mensuel, 5 employés
 * inclus, puis 1 000 FCFA par employé supplémentaire.
 *
 *   3 employés  => 5 000 FCFA
 *   5 employés  => 5 000 FCFA
 *   8 employés  => 5 000 + 3 × 1 000 = 8 000 FCFA
 *
 * Le calcul est porté par App\Services\SubscriptionBillingService : ne pas
 * dupliquer la formule ailleurs.
 */
class SubscriptionPlanSeeder extends Seeder
{
    public const PLAN_NAME = 'GP-FLOW';

    public function run(): void
    {
        $plan = Plan::updateOrCreate(
            ['name' => self::PLAN_NAME],
            [
                'price' => 5000,
                'price_yearly' => 60000,
                'price_per_employee' => 1000,
                'included_employees' => 5,
                'popular' => true,
                'enterprise' => false,
                'duration' => 'Mois',
                'max_users' => 0,      // 0 = illimité
                'max_employees' => 0,  // 0 = illimité : l'effectif est facturé, pas plafonné
                'storage_limit' => 20,
                'nbre_trait' => 1000,
                'enable_chatgpt' => true,
                'description' => "Forfait mensuel de 5 000 FCFA incluant 5 employés, "
                    . "puis 1 000 FCFA par employé supplémentaire.",
                'features' => [
                    'Gestion complète des employés et dossiers du personnel',
                    'Gestion des congés, absences et présences',
                    'Pointeuse et suivi du temps de travail',
                    'Utilisateurs illimités',
                    'Assistant IA intégré',
                    'Support client 7j/7',
                    'Mises à jour et hébergement sécurisé inclus',
                ],
                'is_active' => true,
            ]
        );

        // Seul le module Employés est ouvert sur cette offre. Sans ligne dans
        // pack_modules, ModuleService::isModuleActive() renvoie false et le
        // menu correspondant n'apparaît pas.
        // sync() détache les autres modules : ajouter un alias ici suffit à
        // ouvrir un module, le retirer suffit à le fermer.
        $aliases = ['employee'];

        $modules = Module::whereIn('alias', $aliases)->where('is_active', true)->pluck('id');

        $plan->modules()->sync(
            $modules->mapWithKeys(fn($id) => [$id => ['is_active' => true]])->all()
        );
    }
}
