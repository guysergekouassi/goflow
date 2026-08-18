<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory;
    
    protected $table = 'branches';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'manager_id',
        'company_id',
        'is_active',
        'working_days',
        'qr_token',
        'qr_generated_at'
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'working_days'    => 'array',
        'qr_generated_at' => 'datetime'
    ];

    /**
     * Jours ouvrés du site, en numérotation ISO (1 = lundi … 7 = dimanche).
     *
     * Un site sans rythme propre suit celui de l'entreprise : c'est le cas de
     * tous les sites existants, dont le comportement reste donc inchangé.
     *
     * @return array<int, int>
     */
    public function workingDays(): array
    {
        $days = $this->working_days;

        if (is_array($days) && $days !== []) {
            return array_values(array_map('intval', $days));
        }

        return $this->company ? $this->company->workingDays() : [1, 2, 3, 4, 5];
    }

    /**
     * Le site est-il ouvert ce jour-là, au sens de son rythme hebdomadaire ?
     *
     * Ne dit rien des jours fériés, qui se déclarent au niveau de l'entreprise
     * et peuvent être chômés ou travaillés (cf. Holiday::is_worked).
     */
    public function worksOn(\DateTimeInterface $date): bool
    {
        return in_array(
            \Carbon\Carbon::instance($date)->dayOfWeekIso,
            $this->workingDays(),
            true
        );
    }

    /**
     * Le site a-t-il son propre rythme, ou suit-il celui de l'entreprise ?
     */
    public function hasOwnWorkingDays(): bool
    {
        return is_array($this->working_days) && $this->working_days !== [];
    }

    /**
     * Un QR Code de site est actif tant qu'un jeton est enregistré.
     */
    public function hasQrCode(): bool
    {
        return !empty($this->qr_token);
    }

    /**
     * Relations
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(\Modules\Employees\Models\Employee::class, 'branch_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
