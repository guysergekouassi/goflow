<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class LoginController extends Controller
{
    /**
     * Afficher le formulaire de connexion
     */
    public function showLoginForm()
    {
        // Si déjà connecté, rediriger vers le dashboard approprié
        if (Auth::check()) {
            return self::redirectToDashboard();
        }

        return view('auth.login');
    }

    /**
     * Traiter la connexion
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        // Déterminer si c'est un email ou un username
        $isEmail = filter_var($request->email, FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'username';

        // Validation supplémentaire pour le username (flexible majuscules/minuscules)
        if (!$isEmail && !preg_match('/^[A-Za-z0-9_-]{3,50}$/', $request->email)) {
            throw ValidationException::withMessages([
                'email' => ['Le nom d\'utilisateur n\'est pas valide.'],
            ]);
        }

        // Vérifier les identifiants
        $user = User::where($field, $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Ces identifiants ne correspondent pas à nos enregistrements.'],
            ]);
        }

        // Vérifier si l'utilisateur est actif
        if (!$user->isActive) {
            throw ValidationException::withMessages([
                'email' => ['Votre compte a été désactivé. Contactez l\'administrateur.'],
            ]);
        }

        // Connecter l'utilisateur
        Auth::login($user, $request->boolean('remember'));

        // Rediriger vers le dashboard approprié selon le type d'utilisateur
        return self::redirectToDashboard();
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Vous avez été déconnecté avec succès.');
    }

    /**
     * Rediriger vers le dashboard approprié selon le type d'utilisateur
     */
    public static function redirectToDashboard()
    {
        $user = Auth::user();
    
        if (!$user) {
            return redirect()->route('login');
        }

        switch ($user->type) {
            case 'super_admin':
                return redirect()->route('super-admin.dashboard');
            case 'company':
                return redirect('/company/dashboard');
            case 'hr':
            case 'paie':
                return redirect('/hr/dashboard');
            case 'employee':
            default:
                return redirect('/employee/dashboard');
        }
    }

    /** 
     * Afficher le formulaire de mot de passe oublié
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Traiter la demande de réinitialisation de mot de passe
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Générer un code de réinitialisation
        $user = User::where('email', $request->email)->first();
        $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'password_code' => $resetCode,
        ]);

        // Ici vous pourriez envoyer un email avec le code
        // Pour l'instant, on affiche le code en développement
        return back()->with('success', "Code de réinitialisation : {$resetCode} (Envoi d'email à implémenter)");
    }

    /**
     * Afficher le formulaire de réinitialisation de mot de passe
     */
    public function showResetPasswordForm($code)
    {
        $user = User::where('password_code', $code)->first();

        if (!$user) {
            abort(404, 'Code de réinitialisation invalide.');
        }

        return view('auth.reset-password', compact('code'));
    }

    /**
     * Traiter la réinitialisation de mot de passe
     */
    public function resetPassword(Request $request, $code)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('password_code', $code)->first();

        if (!$user) {
            abort(404, 'Code de réinitialisation invalide.');
        }

        $user->update([
            'password' => $request->password,
            'password_code' => null,
        ]);

        return redirect()->route('login')->with('success', 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
    }

    public function register(Request $request, $pack) {

    }
}
