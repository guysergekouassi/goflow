<?php

namespace App\Http\Middleware;

use App\Support\EtatCompte;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Web : un compte qui a encore son mot de passe provisoire (donné par le RH ou le
 * récapitulatif des accès) doit d'abord choisir le sien ; toute autre page renvoie
 * vers le changement de mot de passe.
 */
class ExigerNouveauMotDePasse
{
    private const ROUTES_AUTORISEES = ['mot-de-passe.provisoire', 'mot-de-passe.provisoire.enregistrer', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || !EtatCompte::motDePasseAChanger(Auth::user())) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if (in_array($route, self::ROUTES_AUTORISEES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Choisissez votre mot de passe avant de continuer.'], 403);
        }

        return redirect()->route('mot-de-passe.provisoire');
    }
}
