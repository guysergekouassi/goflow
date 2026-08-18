<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\Auth;

class ModuleService
{
    /**
     * Vérifier si un module est actif pour l'utilisateur connecté (méthode principale)
     */
    public function isModuleActive(string $moduleAlias): bool
    {
        // Si l'utilisateur n'est pas connecté ou n'est pas une entreprise, retourner false
        if (!Auth::check() || Auth::user()->type !== 'company') {
            return false;
        }

        $company = Auth::user();
        $plan = $company->plan ? \App\Models\Plan::find($company->plan) : null;

        if (!$plan) {
            return false;
        }

        $module = Module::where('alias', $moduleAlias)->first();

        if (!$module) {
            return false;
        }

        return $module->isActiveForPlan($plan);
    }

    /**
     * Obtenir tous les modules actifs pour l'utilisateur connecté
     */
    public function getActiveModules(): array
    {
        if (!Auth::check() || Auth::user()->type !== 'company') {
            return [];
        }

        $company = Auth::user();
        $plan = $company->plan ? \App\Models\Plan::find($company->plan) : null;

        if (!$plan) {
            return [];
        }

        return $plan->activeModules()->pluck('alias')->toArray();
    }

    /**
     * Vérifier si un module est actif (compatibilité avec l'ancien système)
     */
    public function isModuleEnabled(string $moduleAlias): bool
    {
        // Fallback vers le système global si pas de plan spécifique
        $globalStatus = $this->getGlobalModuleStatus();
        if (isset($globalStatus[$moduleAlias])) {
            return $globalStatus[$moduleAlias];
        }

        return $this->isModuleActive($moduleAlias);
    }

    /**
     * Obtenir le statut global des modules (pour compatibilité)
     */
    private function getGlobalModuleStatus(): array
    {
        $path = base_path('modules_statuses.json');
        if (file_exists($path)) {
            $content = json_decode(file_get_contents($path), true);
            return $content ?: [];
        }

        return [];
    }
}
