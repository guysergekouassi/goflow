<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'report_month',
        'generation_key',
        'period_start',
        'period_end',
        'generated_at',
        'format',
        'generation_status',
        'send_status',
        'recipients_sent',
        'file_path_pdf',
        'file_path_excel',
        'error_message',
        'is_manual',
    ];

    protected $casts = [
        'recipients_sent'   => 'array',
        'generated_at'      => 'datetime',
        'period_start'      => 'date',
        'period_end'        => 'date',
        'is_manual'         => 'boolean',
    ];

    /**
     * Entreprise liée à cet historique de rapport mensuel.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
