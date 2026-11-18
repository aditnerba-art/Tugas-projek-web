<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLog extends Model
{
    protected $fillable = ['service_id', 'status_lama', 'status_baru', 'catatan_teknis'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
