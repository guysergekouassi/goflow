<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\EtatCompte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Première connexion avec un mot de passe provisoire : le compte choisit le sien.
 */
class MotDePasseProvisoireController extends Controller
{
    public function show()
    {
        if (!EtatCompte::motDePasseAChanger(Auth::user())) {
            return LoginController::redirectToDashboard();
        }

        return view('auth.mot-de-passe-provisoire');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'mot_de_passe_actuel' => 'required|string',
            'nouveau_mot_de_passe' => 'required|string|min:8|confirmed|different:mot_de_passe_actuel',
        ], [
            'mot_de_passe_actuel.required' => 'Saisissez le mot de passe provisoire que vous avez reçu.',
            'nouveau_mot_de_passe.required' => 'Choisissez un nouveau mot de passe.',
            'nouveau_mot_de_passe.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau_mot_de_passe.confirmed' => 'Les deux saisies du nouveau mot de passe ne correspondent pas.',
            'nouveau_mot_de_passe.different' => 'Le nouveau mot de passe doit être différent du mot de passe provisoire.',
        ]);

        if (!Hash::check($request->input('mot_de_passe_actuel'), $user->password)) {
            throw ValidationException::withMessages(['mot_de_passe_actuel' => 'Le mot de passe provisoire est incorrect.']);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('nouveau_mot_de_passe')),
            'must_change_password' => false,
        ])->save();

        return LoginController::redirectToDashboard()->with('success', 'Votre mot de passe a été enregistré.');
    }
}
