<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Identifiants de connexion et mots de passe provisoires.
 *
 * Identifiant : les 4 premières lettres du nom en majuscules suivies de 2 chiffres
 * (« Kabé Ange » -> « KABE43 »), comme le formulaire de création des salariés.
 * Il est attribué une seule fois, à la création du compte, et ne change plus ensuite.
 */
class IdentifiantService
{
    public function identifiantDepuisNom(string $nom): string
    {
        $lettres = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii($nom)));
        $prefixe = substr(str_pad($lettres, 4, 'X'), 0, 4);

        for ($essai = 0; $essai < 40; $essai++) {
            $candidat = $prefixe . random_int(10, 99);
            if (!$this->estPris($candidat)) {
                return $candidat;
            }
        }

        // Préfixe très courant : on passe à 3 ou 4 chiffres
        do {
            $candidat = $prefixe . random_int(100, 9999);
        } while ($this->estPris($candidat));

        return $candidat;
    }

    /**
     * Mot de passe provisoire lisible : sans caractères ambigus (0/O, 1/l/I),
     * car il sera recopié à la main depuis le récapitulatif.
     */
    public function motDePasseProvisoire(int $longueur = 10): string
    {
        $majuscules = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $minuscules = 'abcdefghjkmnpqrstuvwxyz';
        $chiffres   = '23456789';
        $tous       = $majuscules . $minuscules . $chiffres;

        $caracteres = [
            $majuscules[random_int(0, strlen($majuscules) - 1)],
            $minuscules[random_int(0, strlen($minuscules) - 1)],
            $chiffres[random_int(0, strlen($chiffres) - 1)],
        ];
        while (count($caracteres) < $longueur) {
            $caracteres[] = $tous[random_int(0, strlen($tous) - 1)];
        }

        // Mélange avec un générateur cryptographique
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }

    private function estPris(string $identifiant): bool
    {
        return User::where('username', $identifiant)->exists();
    }
}
