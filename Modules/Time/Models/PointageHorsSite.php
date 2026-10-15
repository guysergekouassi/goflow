<?php

namespace Modules\Time\Models;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Employees\Models\Employee;
use Modules\Settings\Models\WorkLocation;

/**
 * Pointage GPS fait hors du rayon de tout site connu, en attente de la
 * décision du supérieur hiérarchique (voir App\Services\PointageHorsSiteService).
 */
class PointageHorsSite extends Model
{
    protected $table = 'pointages_hors_site';

    public const EN_ATTENTE = 'en_attente';
    public const VALIDE = 'valide';
    public const REFUSE = 'refuse';

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'pointed_at',
        'latitude',
        'longitude',
        'gps_accuracy',
        'nearest_location_id',
        'nearest_distance',
        'motif',
        'device_name',
        'scan_status',
        'duree_retard',
        'status',
        'validator_user_id',
        'validator_level',
        'decided_by',
        'decided_at',
        'decision_comment',
        'pointeuse_id',
    ];

    protected $casts = [
        'pointed_at' => 'datetime',
        'decided_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_user_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function nearestLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'nearest_location_id');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('status', self::EN_ATTENTE);
    }

    /**
     * Pointages que l'utilisateur peut consulter et trancher : tous ceux de
     * l'entreprise pour le compte entreprise, sinon ceux qui lui sont confiés.
     */
    public function scopeVisiblePar($query, User $user)
    {
        $query->where('company_id', $user->company_id);

        if ($user->type !== 'company') {
            $query->where('validator_user_id', $user->id);
        }

        return $query;
    }

    /** Résultat de Schema::hasTable, mis en cache : le menu le consulte à chaque page. */
    private static ?bool $tableExiste = null;

    public static function tableExiste(): bool
    {
        return static::$tableExiste ??= Schema::hasTable('pointages_hors_site');
    }

    /**
     * Nombre de pointages en attente pour l'utilisateur. Sert au menu : renvoie
     * 0 tant que la migration n'est pas passée, pour ne jamais casser la page.
     */
    public static function pendingCountFor(?User $user): int
    {
        if (!$user || !static::tableExiste()) {
            return 0;
        }

        return static::visiblePar($user)->enAttente()->count();
    }

    /**
     * L'entrée de menu s'affiche pour le compte entreprise et pour tout
     * supérieur susceptible de recevoir un pointage : gestionnaire d'un service
     * ou d'une succursale, ou déjà désigné sur un pointage.
     */
    public static function menuVisibleFor(?User $user): bool
    {
        if (!$user || !static::tableExiste()) {
            return false;
        }

        if ($user->type === 'company') {
            return true;
        }

        return Department::where('manager_id', $user->id)->exists()
            || Branch::where('manager_id', $user->id)->exists()
            || static::where('validator_user_id', $user->id)->exists();
    }

    public function libelleType(): string
    {
        return $this->type === 'sortie' ? 'Sortie' : 'Entrée';
    }

    public function libelleStatut(): string
    {
        return match ($this->status) {
            self::VALIDE => 'Validé',
            self::REFUSE => 'Refusé',
            default => 'En attente',
        };
    }

    public function mapsUrl(): string
    {
        return 'https://www.google.com/maps?q=' . $this->latitude . ',' . $this->longitude;
    }
}
