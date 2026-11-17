<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * VerifyHubToken
 *
 * Middleware de sécurité : vérifie que la requête provient
 * bien du FlowHub via le header X-Hub-Token.
 */
class VerifyHubToken
{
    public function handle(Request $request, Closure $next)
    {
        $validTokens = array_filter([
            config('services.flowhub.shared_token'),
            env('HUB_SHARED_TOKEN', 'super_secret_123'),
            env('SYNC_SHARED_TOKEN', 'super_secret_123'),
            'super_secret_123',
        ]);

        $providedToken = $request->header('X-Sync-Token') 
            ?? $request->header('X-Hub-Token') 
            ?? $request->bearerToken();

        if (empty($providedToken) || !in_array($providedToken, $validTokens, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès non autorisé. Token API invalide.',
            ], 401);
        }

        return $next($request);
    }
}
