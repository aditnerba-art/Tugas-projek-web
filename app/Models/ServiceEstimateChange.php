<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceEstimateChange extends Model
{
    protected $fillable = [
        'service_id',
        'proposed_by',
        'previous_estimated_completion_at',
        'proposed_estimated_completion_at',
        'additional_days',
        'reason',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_estimated_completion_at' => 'date',
            'proposed_estimated_completion_at' => 'date',
            'additional_days' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
