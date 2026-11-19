<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identifiants et mots de passe provisoires des comptes créés lors d'une ouverture
 * ou d'une synchronisation depuis RHFlow.
 *
 * Les mots de passe ne sont stockés qu'en version hachée sur les comptes : ce lot est
 * le seul endroit où ils existent en clair. Il est donc chiffré (APP_KEY), réservé au
 * super-admin et aux responsables de la société, et supprimé à expiration.
 */
class LotAcces extends Model
{
    protected $table = 'lots_acces';

    protected $fillable = ['company_id', 'libelle', 'contenu', 'nb_comptes', 'expire_le', 'telecharge_le', 'telechargements'];

    protected $casts = [
        // { entreprise: {...}, comptes: [{type, nom, matricule, identifiant, mot_de_passe, email}] }
        'contenu'       => 'encrypted:array',
        'expire_le'     => 'datetime',
        'telecharge_le' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function estExpire(): bool
    {
        return $this->expire_le === null || $this->expire_le->isPast();
    }
}
