<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetRecord extends Model
{
    protected $fillable = [
        'dataset_id',
        'record_index',
        'original',
        'semantic_description',
        'embedding',
    ];

    protected $casts = [
        'original' => 'array',
        'embedding' => 'array',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
