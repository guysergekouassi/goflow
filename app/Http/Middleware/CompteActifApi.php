<?php

namespace App\Http\Middleware;

use App\Support\EtatCompte;
use Closure;
use Illuminate\Http\Request;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * API mobile : un compte désactivé (départ, salarié désactivé) perd l'accès tout de suite,
 * même avec un jeton de session encore valide. Réponse 401 : l'application tente de
 * renouveler la session, ce que le serveur refuse aussi, puis renvoie à la connexion.
 */
class CompteActifApi
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Throwable) {
            // L'authentification elle-même est vérifiée par jwt.auth
            return $next($request);
        }

        $motif = EtatCompte::motifBlocage($user ?: null);
        if ($motif) {
            return response()->json(['status' => false, 'message' => $motif], 401);
        }

        return $next($request);
    }
}
