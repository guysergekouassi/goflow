<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * VerifyHubToken
 *
 * Protège l'API de synchronisation (RHFlow, FlowHub) : la requête doit porter
 * le jeton partagé dans l'en-tête X-Sync-Token, X-Hub-Token ou Authorization: Bearer.
 *
 * Le jeton vient uniquement de la configuration (HUB_SHARED_TOKEN / SYNC_SHARED_TOKEN,
 * voir config/services.php). Il n'y a pas de valeur par défaut : sans jeton valide
 * configuré, l'API reste fermée.
 */
class VerifyHubToken
{
    /** Ancienne valeur par défaut, connue publiquement : jamais acceptée. */
    private const JETON_INTERDIT = 'super_secret_123';

    /** Même exigence que le Hub de RHFlow. */
    private const LONGUEUR_MINIMALE = 32;

    public function handle(Request $request, Closure $next)
    {
        $jetonsValides = array_values(array_filter(
            [config('services.flowhub.shared_token'), config('services.flowhub.sync_token')],
            fn ($jeton) => is_string($jeton)
                && strlen($jeton) >= self::LONGUEUR_MINIMALE
                && $jeton !== self::JETON_INTERDIT
        ));

        if (!$jetonsValides) {
            Log::error('API de synchronisation fermée : HUB_SHARED_TOKEN absent, trop court (32 caractères minimum) ou égal à l\'ancienne valeur par défaut.');

            return response()->json([
                'status'  => 'error',
                'message' => "API GPFlow non configurée : jeton de synchronisation manquant côté serveur GPFlow.",
            ], 503);
        }

        $fourni = $request->header('X-Sync-Token')
            ?? $request->header('X-Hub-Token')
            ?? $request->bearerToken();

        if (is_string($fourni) && $fourni !== '') {
            foreach ($jetonsValides as $jeton) {
                if (hash_equals($jeton, $fourni)) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Accès non autorisé. Token API invalide.',
        ], 401);
    }
}
