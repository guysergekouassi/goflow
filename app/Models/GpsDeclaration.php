<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

class GpsDeclaration extends Model
{
    use HasFactory;

    protected $table = 'gps_declarations';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'employee_id',
        'employee_name',
        'employee_department',
        'company_id',
        'site_id',
        'site_name',
        'type',
        'date',
        'time',
        'original_time',
        'latitude',
        'longitude',
        'accuracy',
        'distance_meters',
        'is_mocked',
        'status',
        'is_late',
        'late_minutes',
        'is_offsite',
        'offsite_location_label',
        'offsite_reason',
        'mission_reference',
        'rejection_reason',
        'correction_justification',
        'validated_by',
        'validated_at',
        'history',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'float',
        'distance_meters' => 'integer',
        'is_mocked' => 'boolean',
        'is_late' => 'boolean',
        'late_minutes' => 'integer',
        'is_offsite' => 'boolean',
        'history' => 'array',
        'validated_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
