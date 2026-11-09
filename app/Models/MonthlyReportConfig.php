<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'is_enabled',
        'send_day',
        'send_hour',
        'send_minute',
        'recipients',
        'report_format',
        'auto_send_enabled',
    ];

    protected $casts = [
        'is_enabled'        => 'boolean',
        'auto_send_enabled' => 'boolean',
        'recipients'        => 'array',
        'send_day'          => 'integer',
        'send_hour'         => 'integer',
        'send_minute'       => 'integer',
    ];

    /**
     * Entreprise liée à cette configuration de rapport mensuel.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
