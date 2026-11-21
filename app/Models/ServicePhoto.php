<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePhoto extends Model
{
    protected $fillable = [
        'service_id', 'uploaded_by', 'kind', 'path', 'thumbnail_path', 'mime_type', 'size',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
