<?php

namespace App\Support;

use App\Models\User;
use Modules\Employees\Models\Employee;

/**
 * Un compte peut-il se connecter ? Le compte doit être actif et, pour un salarié,
 * sa fiche aussi : désactiver un salarié (départ, synchronisation RHFlow) coupe
 * l'accès à GPFlow, sur le web comme sur l'application mobile.
 */
class EtatCompte
{
    public const MESSAGE_DESACTIVE = 'Votre compte a été désactivé. Contactez votre responsable.';

    /** Motif du blocage, ou null si le compte peut se connecter. */
    public static function motifBlocage(?User $user): ?string
    {
        if (!$user) {
            return self::MESSAGE_DESACTIVE;
        }
        if ((int) ($user->getAttributes()['is_active'] ?? 1) === 0) {
            return self::MESSAGE_DESACTIVE;
        }
        if ($user->type === 'employee') {
            $ficheActive = Employee::where('user_id', $user->id)->value('is_active');
            if ($ficheActive !== null && (int) $ficheActive === 0) {
                return self::MESSAGE_DESACTIVE;
            }
        }

        return null;
    }

    /** Le compte doit-il choisir son propre mot de passe avant d'aller plus loin ? */
    public static function motDePasseAChanger(?User $user): bool
    {
        return $user !== null && (bool) ($user->getAttributes()['must_change_password'] ?? false);
    }
}
