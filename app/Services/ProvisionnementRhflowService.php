<?php

namespace App\Services;

use App\Exceptions\ProvisionnementException;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LotAcces;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Employees\Models\Employee;
use Throwable;

/**
 * Ouverture d'un compte GPFlow pour une entreprise RHFlow et transfert de ses salariés.
 *
 * - Idempotent : relancer met à jour les fiches existantes, crée seulement les
 *   nouveaux salariés et désactive les départs, sans jamais créer de doublon.
 * - Un compte existant garde son identifiant et son mot de passe.
 * - Les identifiants et mots de passe provisoires des comptes créés sont rangés
 *   dans un lot d'accès chiffré, téléchargeable sur GPFlow (voir LotAcces).
 */
class ProvisionnementRhflowService
{
    public const DUREE_LOT_JOURS = 7;

    public function __construct(private IdentifiantService $identifiants)
    {
    }

    /**
     * Retrouve, lie ou crée la société GPFlow d'une entreprise RHFlow.
     *
     * @param  array  $d  source, rhflow_company_id, gpflow_company_id?, nom, email,
     *                    telephone?, adresse?, ville?, pays?, admin_nom?, admin_email?
     * @return array{company: Company, creee: bool, lot: ?LotAcces, identifiant_admin: ?string}
     */
    public function ouvrirEntreprise(array $d): array
    {
        $source   = $d['source'];
        $rhflowId = (int) $d['rhflow_company_id'];

        // 1. Déjà ouverte pour cette entreprise RHFlow
        $company = Company::where('rhflow_source', $source)->where('rhflow_company_id', $rhflowId)->first();
        if ($company) {
            return ['company' => $company, 'creee' => false, 'lot' => null, 'identifiant_admin' => null];
        }

        // 2. Liaison à une société GPFlow existante, indiquée par RHFlow
        if (!empty($d['gpflow_company_id'])) {
            $company = Company::find((int) $d['gpflow_company_id']);
            if (!$company) {
                throw new ProvisionnementException("La société GPFlow #{$d['gpflow_company_id']} n'existe pas.", 404);
            }
            if ($company->rhflow_company_id !== null) {
                throw new ProvisionnementException(
                    "La société GPFlow #{$company->id} ({$company->name}) est déjà liée à une autre entreprise RHFlow.",
                    409
                );
            }
            $company->forceFill(['rhflow_source' => $source, 'rhflow_company_id' => $rhflowId])->save();

            return ['company' => $company, 'creee' => false, 'lot' => null, 'identifiant_admin' => null];
        }

        // 3. Création de la société et de son compte responsable
        $email = Str::lower(trim($d['email']));
        $existante = Company::where('email', $email)->first();
        if ($existante) {
            throw new ProvisionnementException(
                "La société GPFlow #{$existante->id} ({$existante->name}) utilise déjà l'e-mail {$email}. "
                . "Pour la lier, renseignez son ID GPFlow ({$existante->id}) dans la fiche de l'entreprise RHFlow.",
                409
            );
        }

        $plan = $this->planParDefaut();

        return DB::transaction(function () use ($d, $source, $rhflowId, $email, $plan) {
            $identifiant = $this->identifiants->identifiantDepuisNom($d['nom']);
            $motDePasse  = $this->identifiants->motDePasseProvisoire();
            $emailAdmin  = $this->emailDisponible($d['admin_email'] ?? $email);

            $responsable = new User();
            $responsable->forceFill([
                'name'              => $d['admin_nom'] ?? $d['nom'],
                'username'          => $identifiant,
                'email'             => $emailAdmin,
                'password'          => Hash::make($motDePasse),
                'type'              => 'company',
                'plan'              => $plan->id,
                'is_active'         => 1,
                'active_status'     => 1,
                'lang'              => 'fr',
                'currency'          => 'XOF',
                'email_verified_at' => now(),
            ])->save();

            $company = new Company();
            $company->forceFill([
                'user_id'                => $responsable->id,
                'plan_id'                => $plan->id,
                'name'                   => $d['nom'],
                'email'                  => $email,
                'phone'                  => $d['telephone'] ?? null,
                'address'                => $d['adresse'] ?? null,
                'city'                   => $d['ville'] ?? null,
                'country'                => $d['pays'] ?? null,
                'working_hours_per_week' => 40,
                'working_days_per_week'  => 5,
                'is_active'              => true,
                'subscription_status'    => 'active',
                'subscription_start_date'=> now(),
                'max_employees'          => 0, // 0 = illimité : l'effectif vient de RHFlow
                'current_storage_used'   => 0,
                'settings'               => [],
                'rhflow_source'          => $source,
                'rhflow_company_id'      => $rhflowId,
            ])->save();

            $responsable->forceFill(['company_id' => $company->id])->save();

            $lot = $this->ajouterAuLot(null, $company, [[
                'type'         => 'Responsable entreprise',
                'nom'          => $responsable->name,
                'matricule'    => null,
                'identifiant'  => $identifiant,
                'mot_de_passe' => $motDePasse,
                'email'        => $emailAdmin,
            ]]);

            return ['company' => $company, 'creee' => true, 'lot' => $lot, 'identifiant_admin' => $identifiant];
        });
    }

    /**
     * Crée ou met à jour les salariés d'une société à partir des fiches RHFlow.
     *
     * @param  array  $salaries  [{rhflow_employee_id, nom, email?, telephone?, genre?, date_naissance?,
     *                            matricule?, site?, departement?, poste?, date_embauche?, actif}]
     * @return array{crees: int, mis_a_jour: int, desactives: int, ignores: int, erreurs: string[], lot: ?LotAcces}
     */
    public function synchroniserSalaries(Company $company, array $salaries, ?LotAcces $lot = null): array
    {
        $resultat = ['crees' => 0, 'mis_a_jour' => 0, 'desactives' => 0, 'ignores' => 0, 'erreurs' => []];
        $nouveauxComptes = [];

        foreach ($salaries as $s) {
            try {
                DB::transaction(function () use ($company, $s, &$resultat, &$nouveauxComptes) {
                    $actif    = filter_var($s['actif'] ?? true, FILTER_VALIDATE_BOOLEAN);
                    $employee = $this->trouverSalarie($company, $s);

                    if (!$employee && !$actif) {
                        // Un salarié déjà parti n'a pas besoin de compte
                        $resultat['ignores']++;
                        return;
                    }

                    $attributs = array_merge($this->attributsSalarie($s, $actif), $this->organisation($company, $s), [
                        'company_id'         => $company->id,
                        'rhflow_employee_id' => (int) $s['rhflow_employee_id'],
                    ]);

                    if ($employee) {
                        $user = $employee->user_id ? User::find($employee->user_id) : null;
                        if ($user) {
                            // Identifiant et mot de passe d'un compte existant : jamais modifiés
                            $user->forceFill([
                                'name'      => $attributs['name'],
                                'email'     => $this->emailDisponible($attributs['email'], $user->id) ?? $user->email,
                                'is_active' => $actif ? 1 : 0,
                            ])->save();
                        } else {
                            [$user, $compte] = $this->creerCompte($company, $attributs, $actif);
                            $nouveauxComptes[] = $compte;
                            $attributs['user_id'] = $user->id;
                        }

                        // Un champ vide côté RHFlow n'efface pas ce qui est déjà renseigné dans GPFlow
                        $employee->forceFill(array_filter($attributs, fn ($v) => $v !== null))->save();
                        $resultat[$actif ? 'mis_a_jour' : 'desactives']++;
                        return;
                    }

                    [$user, $compte] = $this->creerCompte($company, $attributs, $actif);
                    $nouveauxComptes[] = $compte;

                    $employee = new Employee();
                    $employee->forceFill($attributs + ['user_id' => $user->id, 'created_by' => $company->user_id])->save();
                    $resultat['crees']++;
                });
            } catch (Throwable $e) {
                Log::warning('Provisionnement RHFlow : salarié non synchronisé', [
                    'company_id'         => $company->id,
                    'rhflow_employee_id' => $s['rhflow_employee_id'] ?? null,
                    'erreur'             => $e->getMessage(),
                ]);
                $resultat['erreurs'][] = ($s['nom'] ?? ('#' . ($s['rhflow_employee_id'] ?? '?'))) . ' : ' . Str::limit($e->getMessage(), 160);
            }
        }

        if ($nouveauxComptes) {
            $lot = $this->ajouterAuLot($lot, $company, $nouveauxComptes);
        }

        return $resultat + ['lot' => $lot];
    }

    /**
     * Fiche GPFlow correspondant à un salarié RHFlow : par lien RHFlow, sinon (fiches
     * créées avant la liaison) par matricule, e-mail ou nom exact, si un seul salarié
     * non encore lié correspond.
     */
    private function trouverSalarie(Company $company, array $s): ?Employee
    {
        $base = fn () => Employee::where('company_id', $company->id);

        $lie = $base()->where('rhflow_employee_id', (int) $s['rhflow_employee_id'])->first();
        if ($lie) {
            return $lie;
        }

        $nonLies = fn () => $base()->whereNull('rhflow_employee_id');
        $criteres = [];
        if (!empty($s['matricule'])) {
            $criteres[] = fn () => $nonLies()->where('employee_id', trim($s['matricule']));
        }
        $email = $this->emailValide($s['email'] ?? null);
        if ($email) {
            $criteres[] = fn () => $nonLies()->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
        }
        $criteres[] = fn () => $nonLies()->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(trim($s['nom']))]);

        foreach ($criteres as $critere) {
            $trouves = $critere()->limit(2)->get();
            if ($trouves->count() === 1) {
                return $trouves->first();
            }
        }

        return null;
    }

    private function attributsSalarie(array $s, bool $actif): array
    {
        return [
            'name'        => trim($s['nom']),
            'email'       => $this->emailValide($s['email'] ?? null),
            'phone'       => $s['telephone'] ?? null,
            'gender'      => $this->genre($s['genre'] ?? null),
            'dob'         => $this->date($s['date_naissance'] ?? null),
            'employee_id' => isset($s['matricule']) && $s['matricule'] !== '' ? trim($s['matricule']) : null,
            'company_doj' => $this->date($s['date_embauche'] ?? null),
            'is_active'   => $actif ? 1 : 0,
        ];
    }

    /**
     * @return array{0: User, 1: array} le compte créé et sa ligne pour le lot d'accès
     */
    private function creerCompte(Company $company, array $attributs, bool $actif): array
    {
        $identifiant = $this->identifiants->identifiantDepuisNom($attributs['name']);
        $motDePasse  = $this->identifiants->motDePasseProvisoire();
        $email       = $this->emailDisponible($attributs['email']);

        $user = new User();
        $user->forceFill([
            'name'              => $attributs['name'],
            'username'          => $identifiant,
            'email'             => $email,
            'password'          => Hash::make($motDePasse),
            'type'              => 'employee',
            'lang'              => 'fr',
            'company_id'        => $company->id,
            'is_active'         => $actif ? 1 : 0,
            'email_verified_at' => now(),
            'created_by'        => $company->user_id,
        ])->save();

        return [$user, [
            'type'         => 'Salarié',
            'nom'          => $attributs['name'],
            'matricule'    => $attributs['employee_id'],
            'identifiant'  => $identifiant,
            'mot_de_passe' => $motDePasse,
            'email'        => $email,
        ]];
    }

    /** Site, département et poste repris par leur nom ; créés dans la société s'ils manquent. */
    private function organisation(Company $company, array $s): array
    {
        $branch = $this->trouverOuCreer(Branch::class, $company, $s['site'] ?? null, 'SUCC', []);
        $department = $this->trouverOuCreer(Department::class, $company, $s['departement'] ?? null, 'SRV', ['branch_id' => $branch?->id]);
        $designation = $this->trouverOuCreer(Designation::class, $company, $s['poste'] ?? null, 'POST', ['department_id' => $department?->id]);

        return [
            'branch_id'      => $branch?->id,
            'department_id'  => $department?->id,
            'designation_id' => $designation?->id,
        ];
    }

    private function trouverOuCreer(string $modele, Company $company, ?string $nom, string $prefixe, array $extra)
    {
        $nom = trim((string) $nom);
        if ($nom === '') {
            return null;
        }

        $existant = $modele::where('company_id', $company->id)->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower($nom)])->first();
        if ($existant) {
            return $existant;
        }

        $element = new $modele();
        $element->forceFill(array_merge([
            'name'       => $nom,
            'code'       => $this->codeUnique($modele, $prefixe, $nom),
            'company_id' => $company->id,
            'is_active'  => 1,
        ], array_filter($extra, fn ($v) => $v !== null)))->save();

        return $element;
    }

    /** Même format que les codes saisis dans GPFlow : SUCC-ABID090, SRV-ADMI263, POST-AGEN463. */
    private function codeUnique(string $modele, string $prefixe, string $nom): string
    {
        $lettres = substr(str_pad(strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii($nom))), 4, 'X'), 0, 4);
        do {
            $code = "{$prefixe}-{$lettres}" . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        } while ($modele::where('code', $code)->exists());

        return $code;
    }

    private function ajouterAuLot(?LotAcces $lot, Company $company, array $comptes): LotAcces
    {
        if (!$lot) {
            $lot = new LotAcces([
                'company_id' => $company->id,
                'libelle'    => 'Ouverture des accès ' . $company->name . ' du ' . now()->format('d/m/Y H:i'),
                'contenu'    => ['entreprise' => ['id' => $company->id, 'nom' => $company->name], 'comptes' => []],
                'expire_le'  => now()->addDays(self::DUREE_LOT_JOURS),
            ]);
        }

        $contenu = $lot->contenu;
        $contenu['comptes'] = array_merge($contenu['comptes'] ?? [], $comptes);
        $lot->contenu = $contenu;
        $lot->nb_comptes = count($contenu['comptes']);
        $lot->save();

        return $lot;
    }

    /** Plan des sociétés ouvertes depuis RHFlow : « GP-FLOW » (effectif illimité), à défaut le premier plan illimité. */
    private function planParDefaut(): Plan
    {
        $plan = Plan::where('name', 'GP-FLOW')->first()
            ?? Plan::where('max_employees', 0)->orderBy('id')->first()
            ?? Plan::orderBy('id')->first();

        if (!$plan) {
            throw new ProvisionnementException("Aucun plan d'abonnement n'existe sur GPFlow.", 500);
        }

        return $plan;
    }

    /** E-mail utilisable pour un compte : valide et pas déjà pris par un autre compte (users.email est unique). */
    private function emailDisponible(?string $email, ?int $saufUserId = null): ?string
    {
        $email = $this->emailValide($email);
        if (!$email) {
            return null;
        }

        $pris = User::where('email', $email)->when($saufUserId, fn ($q) => $q->where('id', '!=', $saufUserId))->exists();

        return $pris ? null : $email;
    }

    private function emailValide(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function genre(?string $genre): ?string
    {
        $g = Str::lower(Str::ascii(trim((string) $genre)));

        return match (true) {
            in_array($g, ['male', 'homme', 'm', 'masculin'], true)  => 'Male',
            in_array($g, ['female', 'femme', 'f', 'feminin'], true) => 'Female',
            default                                                 => null,
        };
    }

    private function date(?string $valeur): ?string
    {
        if (!$valeur || str_starts_with($valeur, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($valeur)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
