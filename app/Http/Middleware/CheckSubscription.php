<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe l'accès aux fonctionnalités quand le compte n'est pas actif :
 * abonnement non finalisé (is_active = false) ou arrivé à échéance.
 *
 * Le compte est activé — is_active = true — soit par la confirmation du
 * paiement (webhook Genius Pay / validation manuelle), soit par le super admin
 * depuis son back-office.
 *
 * Le passage en « inactif » est fait ici, au fil des connexions, plutôt que par
 * une tâche planifiée : une entreprise qui ne se connecte pas n'a pas besoin
 * d'être désactivée, et il n'y a aucun cron à maintenir.
 *
 * Les routes d'abonnement, de paiement et de déconnexion restent toujours
 * accessibles : sans cela, l'entreprise ne pourrait plus payer pour rétablir
 * son accès.
 */
class CheckSubscription
{
    /**
     * Routes joignables même sans abonnement valide.
     */
    private const ALLOWED_ROUTE_PREFIXES = [
        'company.packs.',
        'order.',
        'logout',
        'login',
        'profile.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Seules les entreprises sont concernées : ni super admin, ni employé
        if (!$user || $user->type !== 'company' || !$user->company) {
            return $next($request);
        }

        $company = $user->company;

        // Échéance dépassée : le pack passe en inactif
        $company->deactivateIfExpired();

        // Compte pas encore activé : l'abonnement n'a pas été finalisé, ou le
        // super admin ne l'a pas encore validé. L'accès est refusé au même
        // titre qu'un abonnement échu.
        $compteInactif = !$user->is_active;

        if (!$compteInactif && !$company->isSubscriptionExpired()) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        $message = $compteInactif
            ? 'Votre compte n\'est pas encore actif. Finalisez le paiement de votre '
                . 'abonnement pour accéder à vos modules.'
            : 'Votre abonnement a expiré le '
                . $company->subscription_end_date->format('d/m/Y')
                . '. Renouvelez-le pour retrouver l\'accès à vos modules.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'subscription_expired' => true,
            ], 402);
        }

        return redirect()->route('company.packs.index')->with('error', $message);
    }

    /**
     * La route demandée fait-elle partie des exceptions ?
     */
    private function isAllowedRoute(Request $request): bool
    {
        $name = optional($request->route())->getName();

        if (!$name) {
            return false;
        }

        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
